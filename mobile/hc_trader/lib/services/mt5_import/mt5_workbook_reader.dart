import 'dart:convert';
import 'dart:typed_data';

import 'package:archive/archive.dart';
import 'package:crypto/crypto.dart';
import 'package:xml/xml.dart';

class Mt5WorkbookReadException implements Exception {
  const Mt5WorkbookReadException(this.code, this.message);

  final String code;
  final String message;

  @override
  String toString() => message;
}

class Mt5WorkbookCell {
  const Mt5WorkbookCell({
    required this.column,
    required this.value,
    this.formula,
    this.numeric = false,
  });

  final int column;
  final String value;
  final String? formula;
  final bool numeric;

  bool get isBlank => value.trim().isEmpty;
}

class Mt5WorkbookRow {
  const Mt5WorkbookRow({required this.number, required this.cells});

  final int number;
  final Map<int, Mt5WorkbookCell> cells;

  String valueAt(int column) => cells[column]?.value.trim() ?? '';
  List<String> values({int? throughColumn}) {
    final last =
        throughColumn ??
        (cells.isEmpty ? 0 : cells.keys.reduce((a, b) => a > b ? a : b));
    return List<String>.generate(last + 1, valueAt, growable: false);
  }

  Iterable<String> get nonBlankValues => cells.values
      .map((cell) => cell.value.trim())
      .where((value) => value.isNotEmpty);
}

class Mt5WorkbookSheet {
  const Mt5WorkbookSheet({required this.name, required this.rows});

  final String name;
  final List<Mt5WorkbookRow> rows;

  bool get isBlank => rows.every((row) => row.nonBlankValues.isEmpty);
}

class Mt5SafeWorkbook {
  const Mt5SafeWorkbook({
    required this.fileHash,
    required this.sheets,
    required this.warnings,
    required this.date1904,
    required this.totalRows,
  });

  final String fileHash;
  final List<Mt5WorkbookSheet> sheets;
  final List<String> warnings;
  final bool date1904;
  final int totalRows;
}

/// Minimal, non-executing OpenXML reader. It deliberately reads only workbook,
/// relationship, shared-string and worksheet XML parts. Macros, embedded OLE
/// objects, external links and formulas are never executed. Formula cells use
/// their stored cached value (`<v>`) when one exists.
class Mt5SafeWorkbookReader {
  const Mt5SafeWorkbookReader({
    this.maxFileBytes = 20 * 1024 * 1024,
    this.maxExpandedBytes = 100 * 1024 * 1024,
    this.maxRows = 100000,
    this.maxColumns = 256,
    this.maxArchiveEntries = 4096,
  });

  final int maxFileBytes;
  final int maxExpandedBytes;
  final int maxRows;
  final int maxColumns;
  final int maxArchiveEntries;

  Mt5SafeWorkbook read(Uint8List bytes) {
    if (bytes.isEmpty) {
      throw const Mt5WorkbookReadException(
        'empty_file',
        'The selected workbook is empty.',
      );
    }
    if (bytes.length > maxFileBytes) {
      throw Mt5WorkbookReadException(
        'file_too_large',
        'The workbook exceeds the safe ${maxFileBytes ~/ (1024 * 1024)} MB limit.',
      );
    }
    if (bytes.length < 4 || bytes[0] != 0x50 || bytes[1] != 0x4b) {
      throw const Mt5WorkbookReadException(
        'unsupported_file_type',
        'Only modern .xlsx MT5 workbooks are supported by this importer.',
      );
    }

    late final Archive archive;
    try {
      archive = ZipDecoder().decodeBytes(bytes, verify: true);
    } on Object {
      throw const Mt5WorkbookReadException(
        'unreadable_workbook',
        'The workbook is damaged or is not a valid .xlsx file.',
      );
    }
    if (archive.length > maxArchiveEntries) {
      throw const Mt5WorkbookReadException(
        'archive_entry_limit',
        'The workbook contains too many internal files to process safely.',
      );
    }

    var expandedBytes = 0;
    final files = <String, ArchiveFile>{};
    final warnings = <String>[];
    for (final file in archive) {
      final name = _normalizeArchivePath(file.name);
      if (name.isEmpty || name.startsWith('../') || name.contains('/../')) {
        throw const Mt5WorkbookReadException(
          'unsafe_archive_path',
          'The workbook contains an unsafe internal path.',
        );
      }
      expandedBytes += file.size;
      if (expandedBytes > maxExpandedBytes) {
        throw const Mt5WorkbookReadException(
          'expanded_size_limit',
          'The workbook expands beyond the safe processing limit.',
        );
      }
      files[name] = file;
      final lower = name.toLowerCase();
      if (lower.endsWith('vbaproject.bin') ||
          lower.startsWith('xl/externallinks/') ||
          lower.startsWith('xl/embeddings/')) {
        if (!warnings.contains('unsafe_parts_ignored')) {
          warnings.add('unsafe_parts_ignored');
        }
      }
    }

    final workbook = _xml(files, 'xl/workbook.xml', required: true)!;
    final relationships = _xml(
      files,
      'xl/_rels/workbook.xml.rels',
      required: true,
    )!;
    final sharedStrings = _readSharedStrings(files);
    final relationshipTargets = <String, String>{};
    for (final relation in relationships.descendants.whereType<XmlElement>()) {
      if (relation.name.local != 'Relationship') continue;
      final id = relation.getAttribute('Id') ?? '';
      final target = relation.getAttribute('Target') ?? '';
      if (id.isNotEmpty && target.isNotEmpty) {
        relationshipTargets[id] = _resolveWorkbookTarget(target);
      }
    }

    final workbookProperties = workbook.descendants
        .whereType<XmlElement>()
        .where((element) => element.name.local == 'workbookPr');
    final date1904 =
        workbookProperties.isNotEmpty &&
        const {'1', 'true'}.contains(
          (workbookProperties.first.getAttribute('date1904') ?? '')
              .toLowerCase(),
        );

    final sheets = <Mt5WorkbookSheet>[];
    var totalRows = 0;
    for (final sheetElement in workbook.descendants.whereType<XmlElement>()) {
      if (sheetElement.name.local != 'sheet') continue;
      final name = sheetElement.getAttribute('name')?.trim() ?? '';
      final relationshipId = sheetElement.attributes
          .where((attribute) => attribute.name.local == 'id')
          .map((attribute) => attribute.value)
          .firstOrNull;
      final target = relationshipTargets[relationshipId];
      if (name.isEmpty || target == null) continue;
      final document = _xml(files, target, required: false);
      if (document == null) continue;
      final rows = <Mt5WorkbookRow>[];
      for (final rowElement in document.descendants.whereType<XmlElement>()) {
        if (rowElement.name.local != 'row') continue;
        totalRows++;
        if (totalRows > maxRows) {
          throw Mt5WorkbookReadException(
            'row_limit',
            'The workbook exceeds the safe $maxRows row limit.',
          );
        }
        final rowNumber =
            int.tryParse(rowElement.getAttribute('r') ?? '') ?? totalRows;
        final cells = <int, Mt5WorkbookCell>{};
        var sequentialColumn = 0;
        for (final cellElement in rowElement.children.whereType<XmlElement>()) {
          if (cellElement.name.local != 'c') continue;
          final reference = cellElement.getAttribute('r') ?? '';
          final column = _columnFromReference(reference) ?? sequentialColumn;
          sequentialColumn = column + 1;
          if (column >= maxColumns) continue;
          final type = cellElement.getAttribute('t') ?? '';
          final formula = _firstChildText(cellElement, 'f');
          final rawValue = _firstChildText(cellElement, 'v');
          final inlineValue = cellElement.descendants
              .whereType<XmlElement>()
              .where((element) => element.name.local == 't')
              .map((element) => element.innerText)
              .join();
          String value;
          var numeric = false;
          switch (type) {
            case 's':
              final index = int.tryParse(rawValue ?? '');
              value =
                  index != null && index >= 0 && index < sharedStrings.length
                  ? sharedStrings[index]
                  : '';
              break;
            case 'inlineStr':
              value = inlineValue;
              break;
            case 'b':
              value = rawValue == '1' ? 'true' : 'false';
              break;
            case 'str':
            case 'e':
              value = rawValue ?? '';
              break;
            default:
              value = rawValue ?? inlineValue;
              numeric = value.isNotEmpty && num.tryParse(value) != null;
          }
          if (formula != null && rawValue == null && value.isEmpty) {
            warnings.add('formula_without_cached_value');
          }
          cells[column] = Mt5WorkbookCell(
            column: column,
            value: value,
            formula: formula,
            numeric: numeric,
          );
        }
        rows.add(Mt5WorkbookRow(number: rowNumber, cells: cells));
      }
      sheets.add(Mt5WorkbookSheet(name: name, rows: rows));
    }
    if (sheets.where((sheet) => !sheet.isBlank).isEmpty) {
      throw const Mt5WorkbookReadException(
        'empty_workbook',
        'The workbook does not contain any readable worksheet data.',
      );
    }
    return Mt5SafeWorkbook(
      fileHash: sha256.convert(bytes).toString(),
      sheets: sheets,
      warnings: warnings.toSet().toList(growable: false),
      date1904: date1904,
      totalRows: totalRows,
    );
  }

  static XmlDocument? _xml(
    Map<String, ArchiveFile> files,
    String path, {
    required bool required,
  }) {
    final file = files[_normalizeArchivePath(path)];
    if (file == null) {
      if (!required) return null;
      throw Mt5WorkbookReadException(
        'missing_openxml_part',
        'The workbook is missing the required $path part.',
      );
    }
    try {
      return XmlDocument.parse(_decodeXml(_contentBytes(file)));
    } on Object {
      throw Mt5WorkbookReadException(
        'invalid_openxml_part',
        'The workbook contains invalid XML in $path.',
      );
    }
  }

  static List<String> _readSharedStrings(Map<String, ArchiveFile> files) {
    final document = _xml(files, 'xl/sharedStrings.xml', required: false);
    if (document == null) return const [];
    return document.descendants
        .whereType<XmlElement>()
        .where((element) => element.name.local == 'si')
        .map(
          (element) => element.descendants
              .whereType<XmlElement>()
              .where((part) => part.name.local == 't')
              .map((part) => part.innerText)
              .join(),
        )
        .toList(growable: false);
  }

  static Uint8List _contentBytes(ArchiveFile file) {
    final content = file.content;
    if (content is Uint8List) return content;
    if (content is List<int>) return Uint8List.fromList(content);
    throw const Mt5WorkbookReadException(
      'unreadable_archive_entry',
      'An internal workbook file could not be read.',
    );
  }

  static String _decodeXml(Uint8List bytes) {
    if (bytes.length >= 2 && bytes[0] == 0xff && bytes[1] == 0xfe) {
      final units = <int>[];
      for (var index = 2; index + 1 < bytes.length; index += 2) {
        units.add(bytes[index] | (bytes[index + 1] << 8));
      }
      return String.fromCharCodes(units);
    }
    if (bytes.length >= 2 && bytes[0] == 0xfe && bytes[1] == 0xff) {
      final units = <int>[];
      for (var index = 2; index + 1 < bytes.length; index += 2) {
        units.add((bytes[index] << 8) | bytes[index + 1]);
      }
      return String.fromCharCodes(units);
    }
    final start =
        bytes.length >= 3 &&
            bytes[0] == 0xef &&
            bytes[1] == 0xbb &&
            bytes[2] == 0xbf
        ? 3
        : 0;
    return utf8.decode(bytes.sublist(start), allowMalformed: false);
  }

  static String? _firstChildText(XmlElement parent, String localName) {
    for (final child in parent.children.whereType<XmlElement>()) {
      if (child.name.local == localName) return child.innerText;
    }
    return null;
  }

  static int? _columnFromReference(String reference) {
    final match = RegExp(r'^([A-Za-z]+)').firstMatch(reference);
    if (match == null) return null;
    var value = 0;
    for (final code in match.group(1)!.toUpperCase().codeUnits) {
      value = value * 26 + code - 64;
    }
    return value - 1;
  }

  static String _resolveWorkbookTarget(String target) {
    final normalized = target.replaceAll('\\', '/');
    if (normalized.startsWith('/')) return _normalizeArchivePath(normalized);
    return _normalizeArchivePath('xl/$normalized');
  }

  static String _normalizeArchivePath(String value) {
    final parts = <String>[];
    for (final part in value.replaceAll('\\', '/').split('/')) {
      if (part.isEmpty || part == '.') continue;
      if (part == '..') {
        if (parts.isEmpty) return '../';
        parts.removeLast();
      } else {
        parts.add(part);
      }
    }
    return parts.join('/');
  }
}
