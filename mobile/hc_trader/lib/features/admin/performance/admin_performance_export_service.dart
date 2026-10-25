import 'dart:typed_data';

import 'package:excel/excel.dart';
import 'package:file_picker/file_picker.dart';
import 'package:intl/intl.dart';

import 'admin_performance_models.dart';

class AdminPerformanceExportService {
  const AdminPerformanceExportService();

  Future<bool> exportExcel({
    required List<AdminTraderPerformance> records,
    required Map<String, String> labels,
  }) async {
    final bytes = buildExcelBytes(records: records, labels: labels);
    if (bytes == null) return false;
    final date = DateFormat('yyyyMMdd-HHmm').format(DateTime.now());
    final path = await FilePicker.platform.saveFile(
      dialogTitle: labels['save'] ?? 'Export performance report',
      fileName: 'trader-performances-$date.xlsx',
      type: FileType.custom,
      allowedExtensions: const ['xlsx'],
      bytes: bytes,
    );
    return path != null;
  }

  Uint8List? buildExcelBytes({
    required List<AdminTraderPerformance> records,
    required Map<String, String> labels,
  }) {
    if (records.isEmpty) return null;
    final workbook = Excel.createExcel();
    final sheet = workbook[labels['sheet'] ?? 'Performances'];
    if (workbook.sheets.containsKey('Sheet1') && sheet.sheetName != 'Sheet1') {
      workbook.delete('Sheet1');
    }
    final headers = [
      'rank',
      'trader',
      'email',
      'status',
      'sources',
      'netProfit',
      'return',
      'winRate',
      'trades',
      'profitFactor',
      'drawdown',
      'score',
      'review',
      'lastTrade',
    ];
    sheet.appendRow([
      for (final key in headers) TextCellValue(labels[key] ?? key),
    ]);
    for (final record in records) {
      sheet.appendRow([
        IntCellValue(record.rank),
        TextCellValue(record.name),
        TextCellValue(record.email),
        TextCellValue(record.accountStatus),
        TextCellValue(record.dataSources.join(', ')),
        DoubleCellValue(record.netProfit),
        DoubleCellValue(record.returnPercent / 100),
        DoubleCellValue(record.winRate / 100),
        IntCellValue(record.totalTrades),
        record.profitFactor == null
            ? TextCellValue(record.profitFactorLabel)
            : DoubleCellValue(record.profitFactor!),
        DoubleCellValue(record.maxDrawdownPercent / 100),
        DoubleCellValue(record.overallScore),
        TextCellValue(record.reviewStatus),
        TextCellValue(
          record.lastTradeAt == null
              ? ''
              : DateFormat('yyyy-MM-dd HH:mm').format(record.lastTradeAt!),
        ),
      ]);
    }
    for (var column = 0; column < headers.length; column++) {
      sheet.setColumnWidth(column, column == 1 || column == 2 ? 24 : 16);
    }
    final bytes = workbook.encode();
    return bytes == null ? null : Uint8List.fromList(bytes);
  }
}
