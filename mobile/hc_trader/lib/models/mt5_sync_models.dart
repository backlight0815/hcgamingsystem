import 'dart:math' as math;
import 'dart:convert';

import 'package:cloud_firestore/cloud_firestore.dart';

import '../core/config/mt5_sync_config.dart';

class Mt5Account {
  const Mt5Account({
    required this.id,
    required this.userId,
    required this.accountKey,
    required this.login,
    required this.brokerServer,
    this.displayName = '',
    this.syncMachineType = 'local',
    this.isActive = true,
    this.isPrimary = false,
    this.syncStatus = 'not_connected',
    this.displayStatus = '',
    this.lastSyncTime,
    this.lastSuccessfulSyncAt,
    this.lastConnectionCheckAt,
    this.lastHeartbeatAt,
    this.lastActivityTime,
    this.lastError = '',
    this.lastErrorCode = '',
    this.balance,
    this.equity,
    this.leverage,
    this.margin,
    this.freeMargin,
    this.currency = '',
    this.serverTimezone = '',
    this.serverTimezoneOffsetMinutes,
    this.latestImportedClosingBalance,
    this.latestImportedBalanceAt,
    this.updatedAt,
  });

  final String id;
  final String userId;
  final String accountKey;
  final int login;
  final String brokerServer;
  final String displayName;
  final String syncMachineType;
  final bool isActive;
  final bool isPrimary;
  final String syncStatus;
  final String displayStatus;
  final DateTime? lastSyncTime;
  final DateTime? lastSuccessfulSyncAt;
  final DateTime? lastConnectionCheckAt;
  final DateTime? lastHeartbeatAt;
  final DateTime? lastActivityTime;
  final String lastError;
  final String lastErrorCode;
  final double? balance;
  final double? equity;
  final int? leverage;
  final double? margin;
  final double? freeMargin;
  final String currency;
  final String serverTimezone;
  final int? serverTimezoneOffsetMinutes;
  final double? latestImportedClosingBalance;
  final DateTime? latestImportedBalanceAt;
  final DateTime? updatedAt;

  bool get isConnectionFresh {
    final activity = _freshnessTime(lastConnectionCheckAt, lastHeartbeatAt, [
      lastSuccessfulSyncAt,
      lastSyncTime,
    ]);
    if (activity == null) return false;
    return _isFreshAt(activity);
  }

  bool get isConnected =>
      (syncStatus == 'connected' || syncStatus == 'success') &&
      isConnectionFresh &&
      lastError.trim().isEmpty;

  String get label {
    final name = displayName.trim();
    return name.isEmpty ? '$brokerServer $login' : name;
  }

  factory Mt5Account.fromFirestore(DocumentSnapshot<Map<String, dynamic>> doc) {
    // The Firestore document id is the canonical account identifier. Never
    // allow a stale legacy field in the document body to override it.
    return Mt5Account.fromJson({
      ...?doc.data(),
      'id': doc.id,
      'account_key': doc.id,
    });
  }

  factory Mt5Account.fromJson(Map<String, dynamic> json) {
    final login = _asInt(json['mt5_login'] ?? json['login']);
    final server = (json['broker_server'] ?? json['brokerServer'] ?? '')
        .toString();
    return Mt5Account(
      id: (json['id'] ?? json['account_key'] ?? json['accountKey'] ?? '')
          .toString(),
      userId: (json['ownerUid'] ?? json['user_id'] ?? json['userId'] ?? '')
          .toString(),
      accountKey:
          (json['account_key'] ??
                  json['accountKey'] ??
                  mt5AccountKey(server, login))
              .toString(),
      login: login,
      brokerServer: server,
      displayName: (json['display_name'] ?? json['displayName'] ?? '')
          .toString(),
      syncMachineType:
          (json['sync_machine_type'] ?? json['syncMachineType'] ?? 'local')
              .toString(),
      isActive: _asNullableBool(json['is_active'] ?? json['isActive']) ?? true,
      isPrimary:
          _asNullableBool(
            json['is_primary'] ?? json['isPrimary'] ?? json['is_main'],
          ) ??
          false,
      syncStatus: (json['sync_status'] ?? json['syncStatus'] ?? 'not_connected')
          .toString(),
      displayStatus: (json['display_status'] ?? json['displayStatus'] ?? '')
          .toString(),
      lastSyncTime: _asDate(
        json['last_synced_time'] ??
            json['last_sync_time'] ??
            json['lastSyncTime'],
      ),
      lastSuccessfulSyncAt: _asDate(
        json['last_successful_sync_at'] ?? json['lastSuccessfulSyncAt'],
      ),
      lastConnectionCheckAt: _asDate(
        json['last_connection_check_at'] ?? json['lastConnectionCheckAt'],
      ),
      lastHeartbeatAt: _asDate(
        json['last_heartbeat_at'] ?? json['lastHeartbeatAt'],
      ),
      lastActivityTime: _asDate(
        json['last_trade_activity_at'] ??
            json['last_activity_time'] ??
            json['lastActivityTime'],
      ),
      lastError:
          (json['sync_error_message'] ??
                  json['last_error'] ??
                  json['lastError'] ??
                  '')
              .toString(),
      lastErrorCode:
          (json['sync_error_code'] ??
                  json['last_error_code'] ??
                  json['lastErrorCode'] ??
                  '')
              .toString(),
      balance: _asNullableDouble(json['balance']),
      equity: _asNullableDouble(json['equity']),
      leverage: _asNullableInt(json['leverage']),
      margin: _asNullableDouble(json['margin']),
      freeMargin: _asNullableDouble(
        json['free_margin'] ?? json['margin_free'] ?? json['freeMargin'],
      ),
      currency: (json['currency'] ?? '').toString(),
      serverTimezone: (json['server_timezone'] ?? json['serverTimezone'] ?? '')
          .toString(),
      serverTimezoneOffsetMinutes: _asNullableInt(
        json['server_timezone_offset_minutes'] ??
            json['serverTimezoneOffsetMinutes'],
      ),
      latestImportedClosingBalance: _asNullableDouble(
        json['latest_imported_closing_balance'] ??
            json['latestImportedClosingBalance'],
      ),
      latestImportedBalanceAt: _asDate(
        json['latest_imported_balance_at'] ?? json['latestImportedBalanceAt'],
      ),
      updatedAt: _asDate(json['updated_at'] ?? json['updatedAt']),
    );
  }

  Map<String, Object?> toJson() => {
    'id': id,
    'ownerUid': userId,
    'userId': userId,
    'user_id': userId,
    'mt5AccountId': accountKey,
    'mt5_account_id': accountKey,
    'account_key': accountKey,
    'mt5_login': login,
    'broker_server': brokerServer,
    'display_name': displayName,
    'sync_machine_type': syncMachineType,
    'is_active': isActive,
    'is_primary': isPrimary,
    'sync_status': syncStatus,
    'display_status': displayStatus,
    'last_synced_time': lastSyncTime?.toIso8601String(),
    'last_successful_sync_at': lastSuccessfulSyncAt?.toIso8601String(),
    'last_connection_check_at': lastConnectionCheckAt?.toIso8601String(),
    'last_heartbeat_at': lastHeartbeatAt?.toIso8601String(),
    'last_trade_activity_at': lastActivityTime?.toIso8601String(),
    'last_error': lastError,
    'sync_error_code': lastErrorCode,
    'balance': balance,
    'equity': equity,
    'leverage': leverage,
    'margin': margin,
    'free_margin': freeMargin,
    'currency': currency,
    'server_timezone': serverTimezone,
    'server_timezone_offset_minutes': serverTimezoneOffsetMinutes,
    'latest_imported_closing_balance': latestImportedClosingBalance,
    'latest_imported_balance_at': latestImportedBalanceAt?.toIso8601String(),
    'updated_at': updatedAt?.toIso8601String(),
  };

  Map<String, Object?> toFirestore({bool includeCreatedAt = false}) => {
    'ownerUid': userId,
    'userId': userId,
    'user_id': userId,
    'mt5AccountId': accountKey,
    'mt5_account_id': accountKey,
    'account_key': accountKey,
    'mt5_login': login,
    'broker_server': brokerServer,
    'display_name': displayName,
    'sync_machine_type': syncMachineType,
    'is_active': isActive,
    'is_primary': isPrimary,
    'sync_status': syncStatus,
    'display_status': displayStatus,
    if (lastSyncTime != null)
      'last_synced_time': Timestamp.fromDate(lastSyncTime!),
    if (lastSuccessfulSyncAt != null)
      'last_successful_sync_at': Timestamp.fromDate(lastSuccessfulSyncAt!),
    if (lastConnectionCheckAt != null)
      'last_connection_check_at': Timestamp.fromDate(lastConnectionCheckAt!),
    if (lastHeartbeatAt != null)
      'last_heartbeat_at': Timestamp.fromDate(lastHeartbeatAt!),
    if (lastActivityTime != null)
      'last_trade_activity_at': Timestamp.fromDate(lastActivityTime!),
    'last_error': lastError,
    'sync_error_code': lastErrorCode,
    'balance': balance,
    'equity': equity,
    'leverage': leverage,
    'margin': margin,
    'free_margin': freeMargin,
    'currency': currency,
    'server_timezone': serverTimezone,
    'server_timezone_offset_minutes': serverTimezoneOffsetMinutes,
    'latest_imported_closing_balance': latestImportedClosingBalance,
    if (latestImportedBalanceAt != null)
      'latest_imported_balance_at': Timestamp.fromDate(
        latestImportedBalanceAt!,
      ),
    if (includeCreatedAt) 'created_at': FieldValue.serverTimestamp(),
    'updated_at': FieldValue.serverTimestamp(),
  };
}

class Mt5SyncState {
  const Mt5SyncState({
    required this.id,
    required this.userId,
    required this.accountKey,
    required this.login,
    required this.brokerServer,
    this.syncStatus = 'unknown',
    this.displayStatus = '',
    this.realtimeMode = false,
    this.syncIntervalSeconds = 0,
    this.lastSyncStartedAt,
    this.lastSyncedTime,
    this.lastSuccessfulSyncAt,
    this.lastConnectionCheckAt,
    this.lastHeartbeatAt,
    this.lastError = '',
    this.lastErrorCode = '',
    this.lastErrorMessageInternal = '',
    this.syncErrorMessage = '',
    this.lastErrorAt,
    this.detectedMt5Login = 0,
    this.detectedBrokerServer = '',
    this.serviceVersion = '',
    this.updatedAt,
    this.lastTradeActivityAt,
    this.lastTradeActivityId = '',
    this.lastTradeActivityType = '',
    this.lastTradeChangeVersion = 0,
    this.changedRecordCount = 0,
    this.writtenRecordCount = 0,
    this.removedLiveCount = 0,
    this.freeTierSafeMode = true,
    this.floatingProfitLiveUpdates = true,
    this.floatingUpdateMinIntervalSeconds = 0,
    this.floatingUpdateMinProfitChange = 0,
    this.floatingUpdateMinPriceChangePoints = 0,
    this.syncStateHeartbeatIntervalSeconds = 0,
    this.syncStaleAfterSeconds = Mt5SyncConfig.syncStaleAfterSeconds,
    this.vpsServiceStatus = '',
  });

  final String id;
  final String userId;
  final String accountKey;
  final int login;
  final String brokerServer;
  final String syncStatus;
  final String displayStatus;
  final bool realtimeMode;
  final int syncIntervalSeconds;
  final DateTime? lastSyncStartedAt;
  final DateTime? lastSyncedTime;
  final DateTime? lastSuccessfulSyncAt;
  final DateTime? lastConnectionCheckAt;
  final DateTime? lastHeartbeatAt;
  final String lastError;
  final String lastErrorCode;
  final String lastErrorMessageInternal;
  final String syncErrorMessage;
  final DateTime? lastErrorAt;
  final int detectedMt5Login;
  final String detectedBrokerServer;
  final String serviceVersion;
  final DateTime? updatedAt;
  final DateTime? lastTradeActivityAt;
  final String lastTradeActivityId;
  final String lastTradeActivityType;
  final int lastTradeChangeVersion;
  final int changedRecordCount;
  final int writtenRecordCount;
  final int removedLiveCount;
  final bool freeTierSafeMode;
  final bool floatingProfitLiveUpdates;
  final int floatingUpdateMinIntervalSeconds;
  final double floatingUpdateMinProfitChange;
  final double floatingUpdateMinPriceChangePoints;
  final int syncStateHeartbeatIntervalSeconds;
  final int syncStaleAfterSeconds;
  final String vpsServiceStatus;

  bool get isFresh {
    final activity = _freshnessTime(lastConnectionCheckAt, lastHeartbeatAt, [
      lastSuccessfulSyncAt,
      lastSyncedTime,
    ]);
    if (activity == null) return false;
    final graceSeconds = math.max(
      15,
      syncStaleAfterSeconds > 0
          ? syncStaleAfterSeconds
          : Mt5SyncConfig.syncStaleAfterSeconds,
    );
    return _isFreshAt(activity, graceSeconds: graceSeconds);
  }

  bool get isLive => syncStatus == 'connected' && isFresh;

  String get labelKey {
    final friendlyStatus = displayStatus.trim();
    final status = _normalizedSyncStatus;
    if (hasActiveAccountMismatch) return 'Wrong MT5 account detected';
    if (status == 'login_failed' || status == 'loginfailed') {
      return 'Login Failed';
    }
    if (status == 'temporary_error' || status == 'temporaryerror') {
      return 'Temporary Error';
    }
    if (status == 'terminal_closed' || status == 'mt5_terminal_closed') {
      return 'MT5 Terminal Closed';
    }
    if (status == 'vps_offline' ||
        vpsServiceStatus.toLowerCase() == 'offline') {
      return 'VPS Offline';
    }
    if (status == 'failed') return 'Failed';
    if (status == 'completed') return 'Completed';
    if (status == 'disconnected' || status == 'error') {
      return 'Unable to update right now';
    }
    if (syncStatus == 'account_not_linked') {
      return _detectedDifferentAccount ? 'Disconnected' : 'Account not linked';
    }
    if (!isFresh) {
      if (syncStatus == 'syncing' || syncStatus == 'reconnecting') {
        return 'Unable to update right now';
      }
      return 'Disconnected';
    }
    if (syncStatus == 'syncing' || syncStatus == 'reconnecting') {
      return 'Updating';
    }
    if (syncStatus == 'connected') return 'Connected';
    if (syncStatus == 'logged_out' ||
        syncStatus == 'terminal_closed' ||
        syncStatus == 'offline') {
      return 'Disconnected';
    }
    if (friendlyStatus.isNotEmpty) {
      if (friendlyStatus == 'Connected' && !isFresh) return 'Disconnected';
      return friendlyStatus;
    }
    return 'Not connected';
  }

  bool get hasActiveAccountMismatch {
    final code = _normalizedErrorCode;
    final status = _normalizedSyncStatus;
    return code == 'MT5_ACTIVE_ACCOUNT_MISMATCH' ||
        code == 'ACCOUNT_MISMATCH' ||
        status == 'account_mismatch' ||
        status == 'active_account_mismatch' ||
        status == 'mt5_active_account_mismatch' ||
        _detectedDifferentAccount;
  }

  String get publicErrorMessage {
    final explicit = syncErrorMessage.trim().isNotEmpty
        ? syncErrorMessage.trim()
        : lastError.trim();
    if (explicit.isNotEmpty) return explicit;
    if (hasActiveAccountMismatch) {
      final actualLogin = detectedMt5Login > 0 ? '$detectedMt5Login' : '-';
      final actualServer = detectedBrokerServer.trim().isEmpty
          ? '-'
          : detectedBrokerServer.trim();
      return 'Expected $login / $brokerServer, but MT5 is logged in to $actualLogin / $actualServer.';
    }
    return lastErrorMessageInternal.trim();
  }

  String get _normalizedSyncStatus =>
      syncStatus.trim().toLowerCase().replaceAll(RegExp(r'[\s-]+'), '_');

  String get _normalizedErrorCode =>
      lastErrorCode.trim().toUpperCase().replaceAll(RegExp(r'[\s-]+'), '_');

  bool get _detectedDifferentAccount {
    final detectedServer = detectedBrokerServer.trim();
    if (detectedMt5Login > 0 && detectedMt5Login != login) return true;
    if (detectedServer.isEmpty) return false;
    return _serverFingerprint(detectedServer) !=
        _serverFingerprint(brokerServer);
  }

  factory Mt5SyncState.fromFirestore(
    DocumentSnapshot<Map<String, dynamic>> doc,
  ) {
    return Mt5SyncState.fromJson({'id': doc.id, ...?doc.data()});
  }

  factory Mt5SyncState.fromJson(Map<String, dynamic> json) {
    final login = _asInt(json['mt5_login'] ?? json['login']);
    final server = (json['broker_server'] ?? json['brokerServer'] ?? '')
        .toString();
    return Mt5SyncState(
      id: (json['id'] ?? 'default').toString(),
      userId: (json['user_id'] ?? json['userId'] ?? '').toString(),
      accountKey:
          (json['account_key'] ??
                  json['accountKey'] ??
                  mt5AccountKey(server, login))
              .toString(),
      login: login,
      brokerServer: server,
      syncStatus: (json['sync_status'] ?? json['syncStatus'] ?? 'unknown')
          .toString(),
      displayStatus: (json['display_status'] ?? json['displayStatus'] ?? '')
          .toString(),
      realtimeMode:
          _asNullableBool(json['realtime_mode'] ?? json['realtimeMode']) ??
          false,
      syncIntervalSeconds: _asInt(
        json['sync_interval_seconds'] ?? json['syncIntervalSeconds'],
      ),
      lastSyncStartedAt: _asDate(
        json['last_sync_started_at'] ?? json['lastSyncStartedAt'],
      ),
      lastSyncedTime: _asDate(
        json['last_synced_time'] ?? json['lastSyncedTime'],
      ),
      lastSuccessfulSyncAt: _asDate(
        json['last_successful_sync_at'] ?? json['lastSuccessfulSyncAt'],
      ),
      lastConnectionCheckAt: _asDate(
        json['last_connection_check_at'] ?? json['lastConnectionCheckAt'],
      ),
      lastHeartbeatAt: _asDate(
        json['last_heartbeat_at'] ?? json['lastHeartbeatAt'],
      ),
      lastError:
          (json['sync_error_message'] ??
                  json['last_error'] ??
                  json['lastError'] ??
                  '')
              .toString(),
      lastErrorCode:
          (json['sync_error_code'] ??
                  json['last_error_code'] ??
                  json['lastErrorCode'] ??
                  '')
              .toString(),
      lastErrorMessageInternal:
          (json['last_error_message_internal'] ??
                  json['lastErrorMessageInternal'] ??
                  '')
              .toString(),
      syncErrorMessage:
          (json['sync_error_message'] ??
                  json['last_error_message'] ??
                  json['lastErrorMessage'] ??
                  '')
              .toString(),
      lastErrorAt: _asDate(json['last_error_at'] ?? json['lastErrorAt']),
      detectedMt5Login: _asInt(
        json['detected_mt5_login'] ?? json['detectedMt5Login'],
      ),
      detectedBrokerServer:
          (json['detected_broker_server'] ?? json['detectedBrokerServer'] ?? '')
              .toString(),
      serviceVersion:
          (json['service_version'] ??
                  json['sync_service_version'] ??
                  json['serviceVersion'] ??
                  '')
              .toString(),
      updatedAt: _asDate(json['updated_at'] ?? json['updatedAt']),
      lastTradeActivityAt: _asDate(
        json['last_trade_activity_at'] ?? json['lastTradeActivityAt'],
      ),
      lastTradeActivityId:
          (json['last_trade_activity_id'] ?? json['lastTradeActivityId'] ?? '')
              .toString(),
      lastTradeActivityType:
          (json['last_trade_activity_type'] ??
                  json['lastTradeActivityType'] ??
                  '')
              .toString(),
      lastTradeChangeVersion: _asInt(
        json['last_trade_change_version'] ?? json['lastTradeChangeVersion'],
      ),
      changedRecordCount: _asInt(
        json['changed_record_count'] ?? json['changedRecordCount'],
      ),
      writtenRecordCount: _asInt(
        json['written_record_count'] ?? json['writtenRecordCount'],
      ),
      removedLiveCount: _asInt(
        json['removed_live_count'] ?? json['removedLiveCount'],
      ),
      freeTierSafeMode:
          _asNullableBool(
            json['free_tier_safe_mode'] ?? json['freeTierSafeMode'],
          ) ??
          true,
      floatingProfitLiveUpdates:
          _asNullableBool(
            json['sync_floating_profit_updates'] ??
                json['floatingProfitLiveUpdates'],
          ) ??
          true,
      floatingUpdateMinIntervalSeconds: _asInt(
        json['floating_update_min_interval_seconds'] ??
            json['floatingUpdateMinIntervalSeconds'],
      ),
      floatingUpdateMinProfitChange: _asDouble(
        json['floating_update_min_profit_change'] ??
            json['floatingUpdateMinProfitChange'],
      ),
      floatingUpdateMinPriceChangePoints: _asDouble(
        json['floating_update_min_price_change_points'] ??
            json['floatingUpdateMinPriceChangePoints'],
      ),
      syncStateHeartbeatIntervalSeconds: _asInt(
        json['sync_state_heartbeat_interval_seconds'] ??
            json['syncStateHeartbeatIntervalSeconds'],
      ),
      syncStaleAfterSeconds: _asInt(
        json['sync_stale_after_seconds'] ?? json['syncStaleAfterSeconds'],
      ),
      vpsServiceStatus:
          (json['vps_service_status'] ?? json['vpsServiceStatus'] ?? '')
              .toString(),
    );
  }

  Map<String, Object?> toJson() => {
    'id': id,
    'user_id': userId,
    'account_key': accountKey,
    'mt5_login': login,
    'broker_server': brokerServer,
    'sync_status': syncStatus,
    'display_status': displayStatus,
    'realtime_mode': realtimeMode,
    'sync_interval_seconds': syncIntervalSeconds,
    'last_sync_started_at': lastSyncStartedAt?.toIso8601String(),
    'last_synced_time': lastSyncedTime?.toIso8601String(),
    'last_successful_sync_at': lastSuccessfulSyncAt?.toIso8601String(),
    'last_connection_check_at': lastConnectionCheckAt?.toIso8601String(),
    'last_heartbeat_at': lastHeartbeatAt?.toIso8601String(),
    'last_error': lastError,
    'last_error_code': lastErrorCode,
    'sync_error_code': lastErrorCode,
    'sync_error_message': syncErrorMessage,
    'last_error_message_internal': lastErrorMessageInternal,
    'last_error_at': lastErrorAt?.toIso8601String(),
    'detected_mt5_login': detectedMt5Login,
    'detected_broker_server': detectedBrokerServer,
    'service_version': serviceVersion,
    'updated_at': updatedAt?.toIso8601String(),
    'last_trade_activity_at': lastTradeActivityAt?.toIso8601String(),
    'last_trade_activity_id': lastTradeActivityId,
    'last_trade_activity_type': lastTradeActivityType,
    'last_trade_change_version': lastTradeChangeVersion,
    'changed_record_count': changedRecordCount,
    'written_record_count': writtenRecordCount,
    'removed_live_count': removedLiveCount,
    'free_tier_safe_mode': freeTierSafeMode,
    'sync_floating_profit_updates': floatingProfitLiveUpdates,
    'floating_update_min_interval_seconds': floatingUpdateMinIntervalSeconds,
    'floating_update_min_profit_change': floatingUpdateMinProfitChange,
    'floating_update_min_price_change_points':
        floatingUpdateMinPriceChangePoints,
    'sync_state_heartbeat_interval_seconds': syncStateHeartbeatIntervalSeconds,
    'sync_stale_after_seconds': syncStaleAfterSeconds,
    'vps_service_status': vpsServiceStatus,
  };
}

class Mt5SyncJob {
  const Mt5SyncJob({
    required this.id,
    required this.userId,
    required this.accountKey,
    required this.login,
    required this.brokerServer,
    required this.refreshType,
    required this.status,
    this.requestedDateFrom,
    this.requestedDateTo,
    this.createdAt,
    this.startedAt,
    this.completedAt,
    this.failedAt,
    this.errorCode = '',
    this.errorMessage = '',
    this.retryCount = 0,
  });

  final String id;
  final String userId;
  final String accountKey;
  final int login;
  final String brokerServer;
  final String refreshType;
  final String status;
  final DateTime? requestedDateFrom;
  final DateTime? requestedDateTo;
  final DateTime? createdAt;
  final DateTime? startedAt;
  final DateTime? completedAt;
  final DateTime? failedAt;
  final String errorCode;
  final String errorMessage;
  final int retryCount;

  bool get isTerminal =>
      status == 'completed' || status == 'failed' || status == 'cancelled';

  bool get isCompleted => status == 'completed';

  bool get isFailed => status == 'failed' || status == 'cancelled';

  factory Mt5SyncJob.fromFirestore(DocumentSnapshot<Map<String, dynamic>> doc) {
    return Mt5SyncJob.fromJson({'id': doc.id, ...?doc.data()});
  }

  factory Mt5SyncJob.fromJson(Map<String, dynamic> json) {
    final login = _asInt(
      json['account_number'] ??
          json['accountNumber'] ??
          json['mt5_login'] ??
          json['login'],
    );
    final server = (json['broker_server'] ?? json['brokerServer'] ?? '')
        .toString();
    return Mt5SyncJob(
      id: (json['id'] ?? json['job_id'] ?? json['jobId'] ?? '').toString(),
      userId: (json['user_id'] ?? json['userId'] ?? '').toString(),
      accountKey:
          (json['account_key'] ??
                  json['accountKey'] ??
                  json['mt5_account_id'] ??
                  json['mt5AccountId'] ??
                  mt5AccountKey(server, login))
              .toString(),
      login: login,
      brokerServer: server,
      refreshType:
          (json['refresh_type'] ?? json['refreshType'] ?? 'manual_refresh')
              .toString(),
      status: (json['status'] ?? 'pending').toString(),
      requestedDateFrom: _asDate(
        json['requested_date_from'] ?? json['requestedDateFrom'],
      ),
      requestedDateTo: _asDate(
        json['requested_date_to'] ?? json['requestedDateTo'],
      ),
      createdAt: _asDate(json['created_at'] ?? json['createdAt']),
      startedAt: _asDate(json['started_at'] ?? json['startedAt']),
      completedAt: _asDate(json['completed_at'] ?? json['completedAt']),
      failedAt: _asDate(json['failed_at'] ?? json['failedAt']),
      errorCode:
          (json['error_code'] ??
                  json['errorCode'] ??
                  json['sync_error_code'] ??
                  json['syncErrorCode'] ??
                  '')
              .toString(),
      errorMessage:
          (json['error_message'] ??
                  json['errorMessage'] ??
                  json['sync_error_message'] ??
                  json['syncErrorMessage'] ??
                  '')
              .toString(),
      retryCount: _asInt(json['retry_count'] ?? json['retryCount']),
    );
  }
}

class Mt5TradeRecord {
  const Mt5TradeRecord({
    required this.id,
    required this.userId,
    required this.accountKey,
    required this.mt5Login,
    required this.brokerServer,
    required this.symbol,
    required this.tradeType,
    required this.recordType,
    required this.status,
    required this.uniqueMt5Id,
    this.tradeKey = '',
    this.lifecycleKey = '',
    this.recordHash = '',
    this.importBatchId = '',
    this.orderTicket,
    this.dealTicket,
    this.positionId,
    this.orderTicketString = '',
    this.dealTicketString = '',
    this.positionIdString = '',
    this.lotSize = 0,
    this.entryPrice = 0,
    this.closePrice = 0,
    this.currentPrice = 0,
    this.sl = 0,
    this.tp = 0,
    this.openTime,
    this.closeTime,
    this.profit = 0,
    this.commission = 0,
    this.fee = 0,
    this.swap = 0,
    this.magicNumber,
    this.comment = '',
    this.source = 'manual_mt5',
    this.syncedAt,
    this.lastSeenAt,
    this.rawMt5Type,
    this.rawMt5Reason,
    this.rawOrderState,
    this.rawDealEntry,
    this.requestedVolume = 0,
    this.executedVolume = 0,
    this.remainingVolume = 0,
    this.hasEntryDeal,
    this.hasExitDeal,
    this.wasTriggered,
    this.mood,
    this.emotion,
    this.psychologyNotes = '',
    this.confidence,
    this.discipline,
    this.followedTradingPlan,
    this.mistakeReason,
    this.lessonLearned = '',
    this.entryReason = '',
    this.exitReview = '',
    this.setup = '',
    this.notes = '',
    this.screenshotUrl,
    this.attachmentUrls = const [],
    this.customTags = const [],
    this.strategyId,
    this.tradingPlanId,
    this.tradingPlanName,
  });

  final String id;
  final String userId;
  final String accountKey;
  final int mt5Login;
  final String brokerServer;
  final String symbol;
  final int? orderTicket;
  final int? dealTicket;
  final int? positionId;
  final String orderTicketString;
  final String dealTicketString;
  final String positionIdString;
  final String uniqueMt5Id;
  final String tradeKey;
  final String lifecycleKey;
  final String recordHash;
  final String importBatchId;
  final String tradeType;
  final String recordType;
  final String status;
  final double lotSize;
  final double entryPrice;
  final double closePrice;
  final double currentPrice;
  final double sl;
  final double tp;
  final DateTime? openTime;
  final DateTime? closeTime;
  final double profit;
  final double commission;
  final double fee;
  final double swap;
  final int? magicNumber;
  final String comment;
  final String source;
  final DateTime? syncedAt;
  final DateTime? lastSeenAt;
  final int? rawMt5Type;
  final int? rawMt5Reason;
  final int? rawOrderState;
  final int? rawDealEntry;
  final double requestedVolume;
  final double executedVolume;
  final double remainingVolume;
  final bool? hasEntryDeal;
  final bool? hasExitDeal;
  final bool? wasTriggered;
  final String? mood;
  final String? emotion;
  final String psychologyNotes;
  final int? confidence;
  final int? discipline;
  final bool? followedTradingPlan;
  final String? mistakeReason;
  final String lessonLearned;
  final String entryReason;
  final String exitReview;
  final String setup;
  final String notes;
  final String? screenshotUrl;
  final List<String> attachmentUrls;
  final List<String> customTags;
  final String? strategyId;
  final String? tradingPlanId;
  final String? tradingPlanName;

  bool get isOpen => status == 'open';
  bool get isClosed =>
      status == 'closed' || (recordType != 'order' && status == 'triggered');
  bool get isPending => status == 'pending';
  double get netProfit => profit + commission + fee + swap;
  String? get canonicalPositionTicket =>
      _ticketString(positionIdString, positionId);
  String? get canonicalOrderTicket =>
      _ticketString(orderTicketString, orderTicket);
  String? get canonicalDealTicket =>
      _ticketString(dealTicketString, dealTicket);
  String get normalizedOrderType {
    if (rawMt5Type != null) {
      return switch (rawMt5Type) {
        0 => 'buy',
        1 => 'sell',
        2 => 'buy_limit',
        3 => 'sell_limit',
        4 => 'buy_stop',
        5 => 'sell_stop',
        6 => 'buy_stop_limit',
        7 => 'sell_stop_limit',
        _ => 'unknown',
      };
    }
    return _normalizeMt5Value(tradeType);
  }

  String get normalizedOrderState {
    if (rawOrderState != null) {
      return switch (rawOrderState) {
        1 => 'pending',
        2 || 9 => 'cancelled',
        3 => 'partially_filled',
        4 => 'triggered',
        5 => 'rejected',
        6 => 'expired',
        _ => _normalizeMt5Value(status),
      };
    }
    final normalized = _normalizeMt5Value(status);
    return switch (normalized) {
      'canceled' ||
      'deleted' ||
      'removed' ||
      'unfilled' ||
      'not_filled' => 'cancelled',
      'order_state_canceled' => 'cancelled',
      'order_state_expired' => 'expired',
      'order_state_rejected' => 'rejected',
      'invalid' => 'rejected',
      _ => normalized,
    };
  }

  bool get isPendingOrderType => const {
    'buy_limit',
    'sell_limit',
    'buy_stop',
    'sell_stop',
    'buy_stop_limit',
    'sell_stop_limit',
  }.contains(normalizedOrderType);

  bool get hasValidPosition => canonicalPositionTicket != null;
  bool get hasExecutionEvidence {
    final type = _normalizeMt5Value(recordType);
    final direction = _normalizeMt5Value(tradeType);
    final validDirection = direction == 'buy' || direction == 'sell';
    if (!validDirection) return false;
    if (executedVolume > 0 || hasEntryDeal == true) return true;
    if (type == 'position') {
      return hasValidPosition && lotSize > 0;
    }
    if (type == 'deal') {
      return canonicalDealTicket != null && lotSize > 0;
    }
    return false;
  }

  bool get isBalanceOperation {
    final type = _normalizeMt5Value(recordType);
    final direction = _normalizeMt5Value(tradeType);
    if (const {
      'balance',
      'credit',
      'bonus',
      'deposit',
      'withdrawal',
      'transfer',
      'correction',
      'commission',
      'fee',
      'swap',
    }.contains(direction)) {
      return true;
    }
    return type == 'deal' &&
        rawMt5Type != null &&
        rawMt5Type != 0 &&
        rawMt5Type != 1;
  }

  bool get isTradingActivity =>
      const {'buy', 'sell'}.contains(tradeType.trim().toLowerCase()) &&
      symbol.trim().isNotEmpty &&
      (canonicalPositionTicket != null ||
          canonicalOrderTicket != null ||
          canonicalDealTicket != null);

  String get canonicalLifecycleKey {
    final position = canonicalPositionTicket;
    if (position != null) {
      return 'position_$mt5Login*$brokerServer*$position';
    }
    final order = canonicalOrderTicket;
    if (order != null) {
      return 'order_$mt5Login*$brokerServer*$order';
    }
    final explicit = lifecycleKey.trim();
    if (explicit.isNotEmpty) return explicit;
    final deal = canonicalDealTicket;
    if (deal != null) {
      return 'deal_$mt5Login*$brokerServer*$deal';
    }
    final account = accountKey.trim().isNotEmpty
        ? accountKey.trim()
        : mt5AccountKey(brokerServer, mt5Login);
    final fallback = tradeKey.trim().isNotEmpty
        ? tradeKey.trim()
        : uniqueMt5Id.trim().isNotEmpty
        ? uniqueMt5Id.trim()
        : id;
    return '$account|$mt5Login|$brokerServer|$fallback';
  }

  factory Mt5TradeRecord.fromFirestore(
    DocumentSnapshot<Map<String, dynamic>> doc,
  ) {
    final accountRef = doc.reference.parent.parent;
    final ownerRef = accountRef?.parent.parent;
    // The nested Firestore path is authoritative. Old sync records may lack
    // identity aliases in their body, and stale aliases must never redirect a
    // report to another account.
    return Mt5TradeRecord.fromJson({
      ...?doc.data(),
      'id': doc.id,
      if (ownerRef != null) 'user_id': ownerRef.id,
      if (ownerRef != null) 'ownerUid': ownerRef.id,
      if (accountRef != null) 'account_key': accountRef.id,
      if (accountRef != null) 'mt5AccountId': accountRef.id,
    });
  }

  factory Mt5TradeRecord.fromJson(Map<String, dynamic> json) {
    final login = _asInt(json['mt5_login'] ?? json['login']);
    final server = (json['broker_server'] ?? json['brokerServer'] ?? '')
        .toString();
    return Mt5TradeRecord(
      id: (json['id'] ?? json['unique_mt5_id'] ?? '').toString(),
      userId: (json['ownerUid'] ?? json['user_id'] ?? json['userId'] ?? '')
          .toString(),
      accountKey:
          (json['account_key'] ??
                  json['accountKey'] ??
                  json['mt5AccountId'] ??
                  json['mt5_account_id'] ??
                  mt5AccountKey(server, login))
              .toString(),
      mt5Login: login,
      brokerServer: server,
      symbol: (json['symbol'] ?? '').toString(),
      orderTicket: _asNullableInt(json['order_ticket'] ?? json['orderTicket']),
      dealTicket: _asNullableInt(json['deal_ticket'] ?? json['dealTicket']),
      positionId: _asNullableInt(json['position_id'] ?? json['positionId']),
      orderTicketString:
          (json['source_order_ticket'] ??
                  json['order_ticket_string'] ??
                  json['orderTicketString'] ??
                  '')
              .toString(),
      dealTicketString:
          (json['source_deal_ticket'] ??
                  json['deal_ticket_string'] ??
                  json['dealTicketString'] ??
                  '')
              .toString(),
      positionIdString:
          (json['source_position_ticket'] ??
                  json['position_id_string'] ??
                  json['positionIdString'] ??
                  '')
              .toString(),
      uniqueMt5Id: (json['unique_mt5_id'] ?? json['uniqueMt5Id'] ?? '')
          .toString(),
      tradeKey: (json['trade_key'] ?? json['tradeKey'] ?? '').toString(),
      lifecycleKey: (json['lifecycle_key'] ?? json['lifecycleKey'] ?? '')
          .toString(),
      recordHash: (json['record_hash'] ?? json['recordHash'] ?? '').toString(),
      importBatchId: (json['import_batch_id'] ?? json['importBatchId'] ?? '')
          .toString(),
      tradeType: (json['trade_type'] ?? json['tradeType'] ?? 'unknown')
          .toString(),
      recordType: (json['record_type'] ?? json['recordType'] ?? 'deal')
          .toString(),
      status: (json['status'] ?? 'unknown').toString(),
      lotSize: _asDouble(json['lot_size'] ?? json['lotSize']),
      entryPrice: _asDouble(json['entry_price'] ?? json['entryPrice']),
      closePrice: _asDouble(json['close_price'] ?? json['closePrice']),
      currentPrice: _asDouble(json['current_price'] ?? json['currentPrice']),
      sl: _asDouble(json['sl']),
      tp: _asDouble(json['tp']),
      openTime: _asDate(json['open_time'] ?? json['openTime']),
      closeTime: _asDate(json['close_time'] ?? json['closeTime']),
      profit: _asDouble(json['profit']),
      commission: _asDouble(json['commission']),
      fee: _asDouble(json['fee']),
      swap: _asDouble(json['swap']),
      magicNumber: _asNullableInt(json['magic_number'] ?? json['magicNumber']),
      comment: (json['comment'] ?? '').toString(),
      source: (json['source'] ?? 'manual_mt5').toString(),
      syncedAt: _asDate(json['synced_at'] ?? json['syncedAt']),
      lastSeenAt: _asDate(json['last_seen_at'] ?? json['lastSeenAt']),
      rawMt5Type: _asNullableInt(json['raw_mt5_type'] ?? json['rawMt5Type']),
      rawMt5Reason: _asNullableInt(
        json['raw_mt5_reason'] ?? json['rawMt5Reason'],
      ),
      rawOrderState: _asNullableInt(
        json['raw_order_state'] ?? json['rawOrderState'],
      ),
      rawDealEntry: _asNullableInt(
        json['raw_deal_entry'] ?? json['rawDealEntry'],
      ),
      requestedVolume: _asDouble(
        json['requested_volume'] ?? json['requestedVolume'],
      ),
      executedVolume: _asDouble(
        json['executed_volume'] ?? json['executedVolume'],
      ),
      remainingVolume: _asDouble(
        json['remaining_volume'] ?? json['remainingVolume'],
      ),
      hasEntryDeal: _asNullableBool(
        json['has_entry_deal'] ?? json['hasEntryDeal'],
      ),
      hasExitDeal: _asNullableBool(
        json['has_exit_deal'] ?? json['hasExitDeal'],
      ),
      wasTriggered: _asNullableBool(
        json['was_triggered'] ?? json['wasTriggered'],
      ),
      mood: _asNullableString(json['mood']),
      emotion: _asNullableString(json['emotion']),
      psychologyNotes: (json['psychology_notes'] ?? '').toString(),
      confidence: _asNullableInt(json['confidence']),
      discipline: _asNullableInt(json['discipline']),
      followedTradingPlan: _asNullableBool(
        json['followed_trading_plan'] ?? json['followedTradingPlan'],
      ),
      mistakeReason: _asNullableString(
        json['mistake_reason'] ?? json['mistakeReason'],
      ),
      lessonLearned: (json['lesson_learned'] ?? '').toString(),
      entryReason: (json['entry_reason'] ?? '').toString(),
      exitReview: (json['exit_review'] ?? '').toString(),
      setup: (json['setup'] ?? '').toString(),
      notes: (json['notes'] ?? '').toString(),
      screenshotUrl: _asNullableString(
        json['screenshot_url'] ?? json['screenshotUrl'],
      ),
      attachmentUrls: _asStrings(
        json['attachment_urls'] ?? json['attachmentUrls'],
      ),
      customTags: _asStrings(json['custom_tags'] ?? json['customTags']),
      strategyId: _asNullableString(json['strategy_id'] ?? json['strategyId']),
      tradingPlanId: _asNullableString(
        json['trading_plan_id'] ?? json['tradingPlanId'],
      ),
      tradingPlanName: _asNullableString(
        json['trading_plan_name'] ?? json['tradingPlanName'],
      ),
    );
  }

  Map<String, Object?> toJson() => {
    'id': id,
    'user_id': userId,
    'account_key': accountKey,
    'mt5_login': mt5Login,
    'broker_server': brokerServer,
    'symbol': symbol,
    'order_ticket': orderTicket,
    'deal_ticket': dealTicket,
    'position_id': positionId,
    'order_ticket_string': orderTicketString,
    'deal_ticket_string': dealTicketString,
    'position_id_string': positionIdString,
    'unique_mt5_id': uniqueMt5Id,
    'trade_key': tradeKey,
    'lifecycle_key': lifecycleKey,
    'record_hash': recordHash,
    'import_batch_id': importBatchId,
    'trade_type': tradeType,
    'record_type': recordType,
    'status': status,
    'lot_size': lotSize,
    'entry_price': entryPrice,
    'close_price': closePrice,
    'current_price': currentPrice,
    'sl': sl,
    'tp': tp,
    'open_time': openTime?.toIso8601String(),
    'close_time': closeTime?.toIso8601String(),
    'profit': profit,
    'commission': commission,
    'fee': fee,
    'swap': swap,
    'magic_number': magicNumber,
    'comment': comment,
    'source': source,
    'synced_at': syncedAt?.toIso8601String(),
    'last_seen_at': lastSeenAt?.toIso8601String(),
    'raw_mt5_type': rawMt5Type,
    'raw_mt5_reason': rawMt5Reason,
    'raw_order_state': rawOrderState,
    'raw_deal_entry': rawDealEntry,
    'requested_volume': requestedVolume,
    'executed_volume': executedVolume,
    'remaining_volume': remainingVolume,
    'has_entry_deal': hasEntryDeal,
    'has_exit_deal': hasExitDeal,
    'was_triggered': wasTriggered,
    'mood': mood,
    'emotion': emotion,
    'psychology_notes': psychologyNotes,
    'confidence': confidence,
    'discipline': discipline,
    'followed_trading_plan': followedTradingPlan,
    'mistake_reason': mistakeReason,
    'lesson_learned': lessonLearned,
    'entry_reason': entryReason,
    'exit_review': exitReview,
    'setup': setup,
    'notes': notes,
    'screenshot_url': screenshotUrl,
    'attachment_urls': attachmentUrls,
    'custom_tags': customTags,
    'strategy_id': strategyId,
    'trading_plan_id': tradingPlanId,
    'trading_plan_name': tradingPlanName,
  };

  Mt5TradeRecord withAnnotation(Mt5JournalAnnotation? annotation) {
    if (annotation == null) return this;
    return Mt5TradeRecord.fromJson({...toJson(), ...annotation.toJson()});
  }
}

class Mt5JournalAnnotation {
  const Mt5JournalAnnotation({
    required this.id,
    required this.userId,
    required this.accountId,
    required this.lifecycleKey,
    this.tradeId = '',
    this.mood,
    this.emotion,
    this.psychologyNotes = '',
    this.confidence,
    this.discipline,
    this.followedTradingPlan,
    this.mistakeReason,
    this.lessonLearned = '',
    this.entryReason = '',
    this.exitReview = '',
    this.strategyId,
    this.tradingPlanId,
    this.tradingPlanName,
    this.setup = '',
    this.notes = '',
    this.screenshotUrl,
    this.attachmentUrls = const [],
    this.customTags = const [],
  });

  final String id;
  final String userId;
  final String accountId;
  final String lifecycleKey;
  final String tradeId;
  final String? mood;
  final String? emotion;
  final String psychologyNotes;
  final int? confidence;
  final int? discipline;
  final bool? followedTradingPlan;
  final String? mistakeReason;
  final String lessonLearned;
  final String entryReason;
  final String exitReview;
  final String? strategyId;
  final String? tradingPlanId;
  final String? tradingPlanName;
  final String setup;
  final String notes;
  final String? screenshotUrl;
  final List<String> attachmentUrls;
  final List<String> customTags;

  static String idForLifecycle(String lifecycleKey) =>
      base64Url.encode(utf8.encode(lifecycleKey.trim())).replaceAll('=', '');

  factory Mt5JournalAnnotation.fromFirestore(
    DocumentSnapshot<Map<String, dynamic>> document,
  ) => Mt5JournalAnnotation.fromJson({'id': document.id, ...?document.data()});

  factory Mt5JournalAnnotation.fromJson(Map<String, dynamic> json) =>
      Mt5JournalAnnotation(
        id: (json['id'] ?? '').toString(),
        userId: (json['ownerUid'] ?? json['user_id'] ?? '').toString(),
        accountId: (json['mt5_account_id'] ?? json['account_key'] ?? '')
            .toString(),
        lifecycleKey: (json['lifecycle_key'] ?? '').toString(),
        tradeId: (json['trade_id'] ?? '').toString(),
        mood: _asNullableString(json['mood']),
        emotion: _asNullableString(json['emotion']),
        psychologyNotes: (json['psychology_notes'] ?? '').toString(),
        confidence: _asNullableInt(json['confidence']),
        discipline: _asNullableInt(json['discipline']),
        followedTradingPlan: _asNullableBool(json['followed_trading_plan']),
        mistakeReason: _asNullableString(json['mistake_reason']),
        lessonLearned: (json['lesson_learned'] ?? '').toString(),
        entryReason: (json['entry_reason'] ?? '').toString(),
        exitReview: (json['exit_review'] ?? '').toString(),
        strategyId: _asNullableString(json['strategy_id']),
        tradingPlanId: _asNullableString(json['trading_plan_id']),
        tradingPlanName: _asNullableString(json['trading_plan_name']),
        setup: (json['setup'] ?? '').toString(),
        notes: (json['notes'] ?? '').toString(),
        screenshotUrl: _asNullableString(json['screenshot_url']),
        attachmentUrls: _asStrings(json['attachment_urls']),
        customTags: _asStrings(json['custom_tags']),
      );

  Map<String, Object?> toJson() => {
    'ownerUid': userId,
    'user_id': userId,
    'mt5_account_id': accountId,
    'account_key': accountId,
    'lifecycle_key': lifecycleKey,
    'trade_id': tradeId,
    'mood': mood,
    'emotion': emotion,
    'psychology_notes': psychologyNotes,
    'confidence': confidence,
    'discipline': discipline,
    'followed_trading_plan': followedTradingPlan,
    'mistake_reason': mistakeReason,
    'lesson_learned': lessonLearned,
    'entry_reason': entryReason,
    'exit_review': exitReview,
    'strategy_id': strategyId,
    'trading_plan_id': tradingPlanId,
    'trading_plan_name': tradingPlanName,
    'setup': setup,
    'notes': notes,
    'screenshot_url': screenshotUrl,
    'attachment_urls': attachmentUrls,
    'custom_tags': customTags,
  };
}

List<Mt5TradeRecord> dedupeMt5LifecycleRecords(
  Iterable<Mt5TradeRecord> records,
) {
  final byKey = <String, List<Mt5TradeRecord>>{};
  for (final record in records) {
    byKey.putIfAbsent(record.canonicalLifecycleKey, () => []).add(record);
  }

  final deduped = <Mt5TradeRecord>[];
  for (final group in byKey.values) {
    if (_isDealOnlyLifecycle(group)) {
      deduped.add(_aggregateDealLifecycle(group));
      continue;
    }
    deduped.add(group.reduce(_preferredMt5LifecycleRecord));
  }
  return deduped..sort((left, right) {
    final leftTime = mt5LifecycleSortTime(left);
    final rightTime = mt5LifecycleSortTime(right);
    return rightTime.compareTo(leftTime);
  });
}

DateTime mt5LifecycleSortTime(Mt5TradeRecord record) {
  return record.closeTime ??
      record.openTime ??
      record.lastSeenAt ??
      record.syncedAt ??
      DateTime.fromMillisecondsSinceEpoch(0);
}

Mt5TradeRecord _preferredMt5LifecycleRecord(
  Mt5TradeRecord existing,
  Mt5TradeRecord candidate,
) {
  final existingRank = _lifecycleDisplayRank(existing);
  final candidateRank = _lifecycleDisplayRank(candidate);
  if (candidateRank != existingRank) {
    return candidateRank > existingRank ? candidate : existing;
  }

  final existingTypeRank = _recordTypeRank(existing);
  final candidateTypeRank = _recordTypeRank(candidate);
  if (candidateTypeRank != existingTypeRank) {
    return candidateTypeRank > existingTypeRank ? candidate : existing;
  }

  final existingTime = mt5LifecycleSortTime(existing);
  final candidateTime = mt5LifecycleSortTime(candidate);
  return candidateTime.isAfter(existingTime) ? candidate : existing;
}

int _lifecycleDisplayRank(Mt5TradeRecord record) {
  if (record.isClosed) return 3;
  if (record.isOpen) return 2;
  if (record.isPending) return 1;
  return 0;
}

int _recordTypeRank(Mt5TradeRecord record) {
  if (record.recordType == 'position') return 2;
  if (record.recordType == 'deal') return 1;
  return 0;
}

bool _isDealOnlyLifecycle(List<Mt5TradeRecord> records) {
  return records.length > 1 &&
      records.every((record) => record.recordType == 'deal');
}

Mt5TradeRecord _aggregateDealLifecycle(List<Mt5TradeRecord> records) {
  final ordered = [...records]
    ..sort(
      (left, right) =>
          mt5LifecycleSortTime(left).compareTo(mt5LifecycleSortTime(right)),
    );
  final entries = ordered
      .where(
        (record) =>
            record.hasEntryDeal == true ||
            record.rawDealEntry == 0 ||
            record.rawDealEntry == 2,
      )
      .toList(growable: false);
  final exits = ordered
      .where(
        (record) =>
            record.hasExitDeal == true ||
            record.rawDealEntry == 1 ||
            record.rawDealEntry == 2 ||
            record.rawDealEntry == 3,
      )
      .toList(growable: false);
  final opening = entries.isNotEmpty ? entries.first : ordered.first;
  final closing = exits.isNotEmpty ? exits.last : ordered.last;
  final hasExit = exits.isNotEmpty || ordered.any((record) => record.isClosed);
  final entryVolume = entries.fold<double>(
    0,
    (total, record) =>
        total +
        (record.executedVolume > 0 ? record.executedVolume : record.lotSize),
  );
  final fallbackVolume = ordered.fold<double>(
    0,
    (largest, record) => record.lotSize > largest ? record.lotSize : largest,
  );
  final executedVolume = entryVolume > 0 ? entryVolume : fallbackVolume;

  return Mt5TradeRecord.fromJson({
    ...closing.toJson(),
    'id':
        'position_${opening.canonicalPositionTicket ?? opening.canonicalLifecycleKey}',
    'unique_mt5_id':
        'position_${opening.canonicalPositionTicket ?? opening.canonicalLifecycleKey}',
    'record_type': 'position',
    'status': hasExit ? 'closed' : 'open',
    'trade_type': opening.tradeType,
    'deal_ticket': closing.dealTicket,
    'lot_size': executedVolume,
    'executed_volume': executedVolume,
    'remaining_volume': 0,
    'entry_price': opening.entryPrice > 0
        ? opening.entryPrice
        : opening.currentPrice,
    'close_price': hasExit
        ? (closing.closePrice > 0 ? closing.closePrice : closing.currentPrice)
        : 0,
    'current_price': closing.currentPrice,
    'open_time': opening.openTime ?? opening.closeTime,
    'close_time': hasExit ? closing.closeTime ?? closing.openTime : null,
    'profit': ordered.fold<double>(0, (total, record) => total + record.profit),
    'commission': ordered.fold<double>(
      0,
      (total, record) => total + record.commission,
    ),
    'fee': ordered.fold<double>(0, (total, record) => total + record.fee),
    'swap': ordered.fold<double>(0, (total, record) => total + record.swap),
    'has_entry_deal': entries.isNotEmpty || opening.hasExecutionEvidence,
    'has_exit_deal': hasExit,
    'was_triggered': true,
  });
}

String mt5AccountKey(String brokerServer, int login) {
  final safeServer = brokerServer
      .trim()
      .replaceAll(RegExp(r'\s+'), '-')
      .replaceAll(RegExp(r'[/#?\[\]]'), '_');
  return '${safeServer}_$login';
}

String? _ticketString(String value, int? legacy) {
  final canonical = value.trim();
  if (canonical.isNotEmpty && canonical != '0') return canonical;
  return legacy != null && legacy > 0 ? legacy.toString() : null;
}

String _normalizeMt5Value(String value) =>
    value.trim().toLowerCase().replaceAll(RegExp(r'[\s-]+'), '_');

DateTime? _freshnessTime(
  DateTime? connectionCheck,
  DateTime? heartbeat,
  List<DateTime?> fallbacks,
) {
  if (connectionCheck != null && heartbeat != null) {
    return connectionCheck.isBefore(heartbeat) ? connectionCheck : heartbeat;
  }
  if (connectionCheck != null) return connectionCheck;
  if (heartbeat != null) return heartbeat;
  for (final fallback in fallbacks) {
    if (fallback != null) return fallback;
  }
  return null;
}

bool _isFreshAt(
  DateTime activity, {
  int graceSeconds = Mt5SyncConfig.syncStaleAfterSeconds,
}) {
  return DateTime.now().difference(activity).inSeconds <= graceSeconds;
}

String _serverFingerprint(String value) {
  return value.trim().toLowerCase().replaceAll(RegExp(r'\s+'), '-');
}

DateTime? _asDate(Object? value) {
  if (value == null) return null;
  if (value is Timestamp) return value.toDate();
  if (value is DateTime) return value;
  if (value is int) {
    final milliseconds = value > 100000000000 ? value : value * 1000;
    return DateTime.fromMillisecondsSinceEpoch(
      milliseconds,
      isUtc: true,
    ).toLocal();
  }
  return DateTime.tryParse(value.toString());
}

double _asDouble(Object? value) {
  if (value is num) return value.toDouble();
  return double.tryParse(value?.toString() ?? '') ?? 0;
}

double? _asNullableDouble(Object? value) {
  if (value == null || value.toString().isEmpty) return null;
  return _asDouble(value);
}

int _asInt(Object? value) {
  if (value is num) return value.toInt();
  return int.tryParse(value?.toString() ?? '') ?? 0;
}

int? _asNullableInt(Object? value) {
  if (value == null || value.toString().isEmpty) return null;
  return _asInt(value);
}

bool? _asNullableBool(Object? value) {
  if (value == null || value.toString().isEmpty) return null;
  if (value is bool) return value;
  if (value is num) return value != 0;
  final text = value.toString().trim().toLowerCase();
  if (text == 'true' || text == '1' || text == 'yes') return true;
  if (text == 'false' || text == '0' || text == 'no') return false;
  return null;
}

String? _asNullableString(Object? value) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? null : text;
}

List<String> _asStrings(Object? value) {
  if (value is List) return value.map((item) => item.toString()).toList();
  if (value is String && value.trim().isNotEmpty) {
    return value
        .split(',')
        .map((item) => item.trim())
        .where((item) => item.isNotEmpty)
        .toList();
  }
  return const [];
}
