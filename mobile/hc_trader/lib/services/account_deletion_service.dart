import '../core/network/api_client.dart';
import '../core/storage/local_app_storage.dart';
import '../repositories/auth_repository.dart';

enum AccountDeletionStage {
  reauthenticating,
  removingNotifications,
  deletingRemoteData,
  clearingLocalData,
  completed,
}

typedef AccountDeletionProgress = void Function(AccountDeletionStage stage);

class AccountDeletionService {
  AccountDeletionService({
    required AuthRepository authRepository,
    required LocalAppStorage storage,
    ApiClient? apiClient,
    Future<void> Function(String userId)? unregisterNotifications,
  }) : _authRepository = authRepository,
       _storage = storage,
       _apiClient = apiClient,
       _unregisterNotifications = unregisterNotifications;

  final AuthRepository _authRepository;
  final LocalAppStorage _storage;
  final ApiClient? _apiClient;
  final Future<void> Function(String userId)? _unregisterNotifications;
  bool _running = false;

  bool get isRunning => _running;

  Future<void> deleteCurrentAccount({
    required String currentPassword,
    String reason = '',
    AccountDeletionProgress? onProgress,
  }) async {
    if (_running) {
      throw const AuthFailure('Account deletion is already in progress.');
    }
    final userId = _authRepository.currentUser?.uid;
    if (userId == null || userId.isEmpty) {
      throw const AuthFailure('Please sign in again.');
    }
    _running = true;
    try {
      onProgress?.call(AccountDeletionStage.reauthenticating);
      await _authRepository.prepareCurrentAccountDeletion(
        currentPassword: currentPassword,
      );

      onProgress?.call(AccountDeletionStage.removingNotifications);
      try {
        await _unregisterNotifications?.call(userId);
      } on Object {
        // Server-side recursive deletion also removes token documents. Local
        // unsubscription is best effort and must not strand the account.
      }

      onProgress?.call(AccountDeletionStage.deletingRemoteData);
      await _authRepository.deletePreparedCurrentAccount(reason: reason);

      onProgress?.call(AccountDeletionStage.clearingLocalData);
      await _storage.clearUser(userId);
      await _apiClient?.clearToken();
      onProgress?.call(AccountDeletionStage.completed);
    } finally {
      _running = false;
    }
  }
}
