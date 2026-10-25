enum TradingRecordSource {
  manual('manual'),
  excelImport('excel_import'),
  mt5Sync('mt5_sync');

  const TradingRecordSource(this.storageValue);

  final String storageValue;

  bool get isManualDisplaySource =>
      this == TradingRecordSource.manual ||
      this == TradingRecordSource.excelImport;

  static TradingRecordSource fromStorage(
    Object? value, {
    TradingRecordSource fallback = TradingRecordSource.manual,
  }) {
    final normalized = value?.toString().trim().toLowerCase();
    return switch (normalized) {
      'manual' || 'manual_journal' => TradingRecordSource.manual,
      'excel_import' ||
      'excelimport' ||
      'import' ||
      'spreadsheet_import' => TradingRecordSource.excelImport,
      'mt5_sync' ||
      'mt5' ||
      'mobile_mt5' ||
      'manual_mt5' => TradingRecordSource.mt5Sync,
      _ => fallback,
    };
  }
}

enum TradingDataDisplayMode {
  manualOnly('manualOnly'),
  mt5Only('mt5Only');

  const TradingDataDisplayMode(this.storageValue);

  final String storageValue;

  static TradingDataDisplayMode fromStorage(Object? value) {
    final normalized = value?.toString().trim().toLowerCase();
    return switch (normalized) {
      'mt5only' ||
      'mt5_only' ||
      'mt5' ||
      'mt5_sync' => TradingDataDisplayMode.mt5Only,
      _ => TradingDataDisplayMode.manualOnly,
    };
  }
}
