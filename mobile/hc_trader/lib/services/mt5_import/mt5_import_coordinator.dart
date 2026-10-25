import 'dart:convert';
import 'dart:isolate';
import 'package:flutter/foundation.dart';

import '../../models/mt5_import_models.dart';
import '../../models/mt5_sync_models.dart';
import '../firebase_callable_service.dart';
import 'mt5_import_parser.dart';

class Mt5AccountMatchResult {
  const Mt5AccountMatchResult({
    required this.matches,
    required this.accountNumberMatches,
    required this.serverMatches,
    required this.currencyMatches,
  });

  final bool matches;
  final bool accountNumberMatches;
  final bool serverMatches;
  final bool currencyMatches;
}

class Mt5ImportCommitResult {
  const Mt5ImportCommitResult({
    required this.batchId,
    required this.status,
    required this.imported,
    required this.reused,
    required this.updated,
    required this.conflicts,
    required this.downstreamRecalculationStatus,
    this.previousBatchId = '',
  });

  final String batchId;
  final String status;
  final int imported;
  final int reused;
  final int updated;
  final int conflicts;
  final String downstreamRecalculationStatus;
  final String previousBatchId;

  factory Mt5ImportCommitResult.fromJson(Map<String, dynamic> json) =>
      Mt5ImportCommitResult(
        batchId: '${json['batch_id'] ?? ''}',
        status: '${json['status'] ?? 'completed'}',
        imported: _int(json['imported']),
        reused: _int(json['reused']),
        updated: _int(json['updated']),
        conflicts: _int(json['conflicts']),
        downstreamRecalculationStatus:
            '${json['downstream_recalculation_status'] ?? 'not_required'}',
        previousBatchId: '${json['previous_batch_id'] ?? ''}',
      );
}

class Mt5ImportCoordinator {
  Mt5ImportCoordinator({FirebaseCallableService? callableService})
    : _callable = callableService ?? FirebaseCallableService();

  static const int _chunkSize = 100;
  final FirebaseCallableService _callable;

  Future<Mt5ImportPreview> parseWorkbook({
    required Uint8List bytes,
    required String filename,
  }) async {
    if (kIsWeb) {
      return Mt5ImportParser().parse(bytes: bytes, filename: filename);
    }
    return Isolate.run(
      () => Mt5ImportParser().parse(bytes: bytes, filename: filename),
    );
  }

  Mt5AccountMatchResult matchAccount(
    Mt5ImportPreview preview,
    Mt5Account account,
  ) {
    final metadata = preview.metadata;
    final loginMatches = metadata.accountNumber == account.login.toString();
    final serverMatches =
        _normalizedServer(metadata.brokerServer) ==
        _normalizedServer(account.brokerServer);
    final sourceCurrency = metadata.currency.trim().toUpperCase();
    final selectedCurrency = account.currency.trim().toUpperCase();
    final currencyMatches =
        sourceCurrency.isEmpty ||
        selectedCurrency.isEmpty ||
        sourceCurrency == selectedCurrency;
    return Mt5AccountMatchResult(
      matches: loginMatches && serverMatches && currencyMatches,
      accountNumberMatches: loginMatches,
      serverMatches: serverMatches,
      currencyMatches: currencyMatches,
    );
  }

  Mt5ImportPreview applySelectedAccount(
    Mt5ImportPreview preview,
    Mt5Account account,
  ) {
    final match = matchAccount(preview, account);
    final issues = preview.issues
        .where((issue) => issue.code != 'account_mismatch')
        .where(
          (issue) =>
              issue.code != 'missing_source_timezone' ||
              account.serverTimezoneOffsetMinutes == null,
        )
        .toList();
    if (!match.matches) {
      issues.add(
        Mt5ValidationIssue(
          code: 'account_mismatch',
          messageKey:
              'The detected MT5 report account does not match the selected connection.',
          severity: Mt5ValidationSeverity.blocking,
          details: {
            'detected_account': preview.metadata.accountNumber,
            'detected_server': preview.metadata.brokerServer,
            'detected_currency': preview.metadata.currency,
            'selected_account': account.login.toString(),
            'selected_server': account.brokerServer,
            'selected_currency': account.currency,
          },
        ),
      );
    }
    final metadata = account.serverTimezoneOffsetMinutes == null
        ? preview.metadata
        : preview.metadata.copyWith(
            sourceTimezone: account.serverTimezone.trim().isEmpty
                ? 'MT5 server UTC${_offsetLabel(account.serverTimezoneOffsetMinutes!)}'
                : account.serverTimezone,
            sourceTimezoneOffsetMinutes: account.serverTimezoneOffsetMinutes,
          );
    return preview.copyWith(issues: issues, metadata: metadata);
  }

  Future<Mt5ImportPreview> loadDuplicatePreview({
    required Mt5ImportPreview preview,
    required Mt5Account account,
  }) async {
    final scoped = applySelectedAccount(preview, account);
    if (scoped.hasBlockingErrors) return scoped;
    final response = await _callable.call(
      'previewMt5DesktopImport',
      Map<String, dynamic>.from(
        scoped.manifestJson(selectedAccountId: account.id),
      ),
    );
    final duplicate = Mt5DuplicateSummary.fromJson(
      Map<String, dynamic>.from(
        response['duplicate_summary'] as Map? ?? const {},
      ),
    );
    final issues = List<Mt5ValidationIssue>.from(scoped.issues);
    if (duplicate.conflicts > 0) {
      issues.add(
        Mt5ValidationIssue(
          code: 'conflicting_source_records',
          messageKey:
              'Existing source tickets contain incompatible financial values.',
          severity: Mt5ValidationSeverity.blocking,
          details: {'conflicts': duplicate.conflicts},
        ),
      );
    }
    return scoped.copyWith(duplicateSummary: duplicate, issues: issues);
  }

  Future<Mt5ImportCommitResult> commit({
    required Mt5ImportPreview preview,
    required Mt5Account account,
  }) async {
    final scoped = applySelectedAccount(preview, account);
    if (scoped.hasBlockingErrors) {
      throw const FirebaseCallableFailure(
        'failed-precondition',
        'Resolve all blocking import validation errors before committing.',
      );
    }
    final begin = await _callable.call(
      'beginMt5DesktopImport',
      Map<String, dynamic>.from(
        scoped.auditJson(selectedAccountId: account.id),
      ),
    );
    if (begin['same_file_already_imported'] == true) {
      return Mt5ImportCommitResult.fromJson(begin);
    }
    final batchId = '${begin['batch_id'] ?? ''}'.trim();
    if (batchId.isEmpty) {
      throw const FirebaseCallableFailure(
        'internal',
        'The import service did not return a batch identifier.',
      );
    }
    final records = scoped.recordPayloads();
    var chunkIndex = 0;
    for (var offset = 0; offset < records.length; offset += _chunkSize) {
      final end = (offset + _chunkSize).clamp(0, records.length);
      await _callable.call('uploadMt5DesktopImportChunk', {
        'batch_id': batchId,
        'selected_mt5_connection_id': account.id,
        'chunk_index': chunkIndex,
        'records': records.sublist(offset, end),
      });
      chunkIndex++;
    }
    final finalized = await _callable.call('finalizeMt5DesktopImport', {
      'batch_id': batchId,
      'selected_mt5_connection_id': account.id,
      'expected_chunks': chunkIndex,
      'file_hash': scoped.fileHash,
    });
    return Mt5ImportCommitResult.fromJson(finalized);
  }

  String rejectedRowsCsv(Mt5ImportPreview preview) {
    const headers = [
      'sheet',
      'row',
      'section',
      'reason_code',
      'explanation',
      'suggested_correction',
      'raw_values_json',
      'normalized_values_json',
      'parser_version',
    ];
    final rows = <List<String>>[
      headers,
      for (final rejected in preview.rejectedRows)
        [
          rejected.reference.sheet,
          '${rejected.reference.row}',
          rejected.reference.section.name,
          rejected.reasonCode,
          rejected.messageKey,
          rejected.suggestedCorrection,
          jsonEncode(rejected.rawValues),
          jsonEncode(rejected.normalizedValues),
          preview.parserVersion,
        ],
    ];
    return rows.map((row) => row.map(_csvCell).join(',')).join('\r\n');
  }

  static String _csvCell(String value) => '"${value.replaceAll('"', '""')}"';

  static String _normalizedServer(String value) =>
      value.trim().toLowerCase().replaceAll(RegExp(r'\s+'), '');

  static String _offsetLabel(int minutes) {
    final sign = minutes < 0 ? '-' : '+';
    final absolute = minutes.abs();
    final hours = (absolute ~/ 60).toString().padLeft(2, '0');
    final mins = (absolute % 60).toString().padLeft(2, '0');
    return '$sign$hours:$mins';
  }
}

int _int(Object? value) =>
    value is num ? value.toInt() : int.tryParse('${value ?? ''}') ?? 0;
