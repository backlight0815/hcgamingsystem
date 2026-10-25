class Mt5SyncConfig {
  const Mt5SyncConfig._();

  static const mt5SyncEnabled = bool.fromEnvironment(
    'MT5_SYNC_ENABLED',
    defaultValue: true,
  );

  static const defaultEnabled = bool.fromEnvironment(
    'MT5_SYNC_DEFAULT_ENABLED',
  );

  static const mockDataEnabled = bool.fromEnvironment('MT5_MOCK_DATA');

  static const mt5RealtimeListenerEnabled = bool.fromEnvironment(
    'MT5_REALTIME_LISTENER_ENABLED',
    defaultValue: true,
  );

  static const mt5FreeTierSafeMode = bool.fromEnvironment(
    'MT5_FREE_TIER_SAFE_MODE',
    defaultValue: true,
  );

  static const mt5FloatingProfitLiveUpdates = bool.fromEnvironment(
    'MT5_FLOATING_PROFIT_LIVE_UPDATES',
    defaultValue: true,
  );

  static const syncStaleAfterSeconds = int.fromEnvironment(
    'MT5_SYNC_STALE_AFTER_SECONDS',
    defaultValue: 600,
  );

  static const activityCheckIntervalMinutes = int.fromEnvironment(
    'MT5_ACTIVITY_CHECK_INTERVAL_MINUTES',
    defaultValue: 5,
  );

  static const propFirmMt5SyncEnabled = bool.fromEnvironment(
    'PROP_FIRM_MT5_SYNC_ENABLED',
    defaultValue: true,
  );
}
