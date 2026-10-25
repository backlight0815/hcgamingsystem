import '../../models/app_user.dart';
import 'permission_service.dart';

/// Single application-side policy for publisher and administrator content.
/// Firestore and Storage rules mirror these permissions as the final authority.
class PublishingAuthorization {
  const PublishingAuthorization(this.user);

  final AppUser user;

  PermissionService get _permissions => PermissionService(user);

  bool get canPublishTradingSignal =>
      _permissions.can(AppPermission.signalsPublishOwn) ||
      _permissions.can(AppPermission.signalsManageAll);

  bool get canManageTradingSignals =>
      _permissions.can(AppPermission.signalsManageAll) ||
      _permissions.can(AppPermission.signalsUpdateOwn);

  bool get canManageAllTradingSignals =>
      _permissions.can(AppPermission.signalsManageAll);

  bool get canUpdateOwnTradingSignals =>
      _permissions.can(AppPermission.signalsUpdateOwn);

  bool get canPublishMarketAnalysis =>
      _permissions.can(AppPermission.marketAnalysisPublishOwn) ||
      _permissions.can(AppPermission.marketAnalysisManageAll);

  bool get canManageMarketAnalysis =>
      _permissions.can(AppPermission.marketAnalysisManageAll) ||
      _permissions.can(AppPermission.marketAnalysisUpdateOwn);

  String get resolvedRoleIds {
    final roles = user.effectiveRoleIds.toList()..sort();
    return roles.isEmpty ? 'missing' : roles.join(',');
  }
}
