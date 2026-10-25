import '../../models/access_status.dart';
import '../../models/app_user.dart';
import 'app_role_permissions.dart';

abstract final class AppPermission {
  static const toolkitUse = 'public.toolkit.use';
  static const riskCalculatorUse = 'public.riskCalculator.use';
  static const journalReadOwn = 'journal.manual.readOwn';
  static const journalCreateOwn = 'journal.manual.createOwn';
  static const journalUpdateOwn = 'journal.manual.updateOwn';
  static const journalDeleteOwn = 'journal.manual.deleteOwn';
  static const psychologyReadOwn = 'psychology.daily.readOwn';
  static const psychologyCreateOwn = 'psychology.daily.createOwn';
  static const psychologyUpdateOwn = 'psychology.daily.updateOwn';
  static const tradingPlanReadOwn = 'tradingPlan.readOwn';
  static const tradingPlanCreateOwn = 'tradingPlan.createOwn';
  static const tradingPlanUpdateOwn = 'tradingPlan.updateOwn';
  static const tradingPlanDeleteOwn = 'tradingPlan.deleteOwn';
  static const tradingStrategyReadOwn = 'tradingStrategy.readOwn';
  static const tradingStrategyCreateOwn = 'tradingStrategy.createOwn';
  static const tradingStrategyUpdateOwn = 'tradingStrategy.updateOwn';
  static const tradingStrategyDeleteOwn = 'tradingStrategy.deleteOwn';
  static const accessCreateOwn = 'accessApplication.createOwn';
  static const accessReadOwn = 'accessApplication.readOwn';
  static const accessWithdrawOwn = 'accessApplication.withdrawOwn';
  static const mt5ConnectOwn = 'mt5.connectOwn';
  static const mt5SyncOwn = 'mt5.syncOwn';
  static const mt5SelectMain = 'mt5.selectMainAccount';
  static const statisticsReadOwn = 'statistics.readOwn';
  static const performanceRatingReadOwn = 'performanceRating.readOwn';
  static const reportsExportOwn = 'reports.exportOwn';
  static const signalsReadApproved = 'signals.readApproved';
  static const signalsCreateOwn = 'signals.createOwn';
  static const signalsUpdateOwn = 'signals.updateOwn';
  static const signalsPublishOwn = 'signals.publishOwn';
  static const signalsCancelOwn = 'signals.cancelOwn';
  static const signalsManageAll = 'signals.manageAll';
  static const marketAnalysisRead = 'marketAnalysis.read';
  static const marketAnalysisCreateOwn = 'marketAnalysis.createOwn';
  static const marketAnalysisUpdateOwn = 'marketAnalysis.updateOwn';
  static const marketAnalysisPublishOwn = 'marketAnalysis.publishOwn';
  static const marketAnalysisManageAll = 'marketAnalysis.manageAll';
  static const teamReadAssigned = 'team.readAssigned';
  static const teamManageAssigned = 'team.manageAssigned';
  static const clientsReadAssigned = 'clients.readAssigned';
  static const clientsManageAssigned = 'clients.manageAssigned';
  static const evaluationsReadAssigned = 'evaluations.readAssigned';
  static const evaluationsRecommend = 'evaluations.recommend';
  static const referralReadOwn = 'referral.readOwn';
  static const referralManageOwn = 'referral.manageOwn';
  static const referralManageAll = 'referral.manageAll';
  static const referralViewConversions = 'referral.viewConversions';
  static const accessReviewAssigned = 'accessApplication.reviewAssigned';
  static const accessApprove = 'accessApplication.approve';
  static const accessReject = 'accessApplication.reject';
  static const usersReadAssigned = 'users.readAssigned';
  static const usersReadAll = 'users.readAll';
  static const usersUpdateBasic = 'users.updateBasic';
  static const usersSuspend = 'users.suspend';
  static const usersAssignNonPrivilegedRole = 'users.assignNonPrivilegedRole';
  static const usersAssignPrivilegedRole = 'users.assignPrivilegedRole';
  static const rolesRead = 'roles.read';
  static const rolesManage = 'roles.manage';
  static const permissionsManage = 'permissions.manage';
  static const auditReadAssigned = 'auditLog.readAssigned';
  static const auditReadAll = 'auditLog.readAll';
  static const systemSettingsManage = 'system.settings.manage';
  static const systemSecurityManage = 'system.security.manage';
  static const courseworkViewAssigned = 'coursework.viewAssignedSubmissions';
  static const courseworkGrade = 'coursework.grade';
  static const courseworkRequestRevision = 'coursework.requestRevision';
  static const courseworkOverrideAutomaticMarks =
      'coursework.overrideAutomaticMarks';
  static const courseworkFinaliseGrade = 'coursework.finaliseGrade';
  static const courseworkManageThemes = 'coursework.manageThemes';
  static const courseworkPublishThemes = 'coursework.publishThemes';
  static const courseworkAssignThemes = 'coursework.assignThemes';
  static const courseworkViewReports = 'coursework.viewReports';
  static const courseworkManageSettings = 'coursework.manageSettings';
}

class ResourceContext {
  const ResourceContext({
    this.ownerUserId,
    this.teamId,
    this.clientUserId,
    this.evaluationId,
    this.organisationId,
  });

  final String? ownerUserId;
  final String? teamId;
  final String? clientUserId;
  final String? evaluationId;
  final String? organisationId;
}

class PermissionService {
  const PermissionService(this.user);

  final AppUser user;

  static const publicOwnPermissions = <String>{
    AppPermission.toolkitUse,
    AppPermission.riskCalculatorUse,
    AppPermission.journalReadOwn,
    AppPermission.journalCreateOwn,
    AppPermission.journalUpdateOwn,
    AppPermission.journalDeleteOwn,
    AppPermission.psychologyReadOwn,
    AppPermission.psychologyCreateOwn,
    AppPermission.psychologyUpdateOwn,
    AppPermission.tradingPlanReadOwn,
    AppPermission.tradingPlanCreateOwn,
    AppPermission.tradingPlanUpdateOwn,
    AppPermission.tradingPlanDeleteOwn,
    AppPermission.tradingStrategyReadOwn,
    AppPermission.tradingStrategyCreateOwn,
    AppPermission.tradingStrategyUpdateOwn,
    AppPermission.tradingStrategyDeleteOwn,
    AppPermission.accessCreateOwn,
    AppPermission.accessReadOwn,
    AppPermission.accessWithdrawOwn,
    AppPermission.statisticsReadOwn,
  };

  static const approvedClientPermissions = <String>{
    AppPermission.mt5ConnectOwn,
    AppPermission.mt5SyncOwn,
    AppPermission.mt5SelectMain,
    AppPermission.performanceRatingReadOwn,
    AppPermission.reportsExportOwn,
    AppPermission.signalsReadApproved,
    AppPermission.marketAnalysisRead,
  };

  static const rolePermissions = <int, Set<String>>{
    RolePermissionMatrix.potentialClient: publicOwnPermissions,
    RolePermissionMatrix.trader: {
      ...publicOwnPermissions,
      ...approvedClientPermissions,
    },
    RolePermissionMatrix.signalProvider: {
      AppPermission.signalsReadApproved,
      AppPermission.signalsCreateOwn,
      AppPermission.signalsUpdateOwn,
      AppPermission.signalsPublishOwn,
      AppPermission.signalsCancelOwn,
    },
    RolePermissionMatrix.seniorSignalProvider: {
      AppPermission.signalsReadApproved,
      AppPermission.signalsCreateOwn,
      AppPermission.signalsUpdateOwn,
      AppPermission.signalsPublishOwn,
      AppPermission.signalsCancelOwn,
    },
    RolePermissionMatrix.signalProviderManagement: {
      AppPermission.signalsReadApproved,
      AppPermission.signalsCreateOwn,
      AppPermission.signalsUpdateOwn,
      AppPermission.signalsPublishOwn,
      AppPermission.signalsCancelOwn,
      AppPermission.signalsManageAll,
    },
    RolePermissionMatrix.marketAnalyst: {
      AppPermission.marketAnalysisRead,
      AppPermission.marketAnalysisCreateOwn,
      AppPermission.marketAnalysisUpdateOwn,
      AppPermission.marketAnalysisPublishOwn,
      AppPermission.courseworkViewAssigned,
      AppPermission.courseworkGrade,
      AppPermission.courseworkRequestRevision,
      AppPermission.courseworkFinaliseGrade,
    },
    RolePermissionMatrix.leader: {
      AppPermission.teamReadAssigned,
      AppPermission.teamManageAssigned,
      AppPermission.accessReviewAssigned,
      AppPermission.referralViewConversions,
      AppPermission.usersReadAssigned,
    },
    RolePermissionMatrix.recruiter: {
      AppPermission.referralReadOwn,
      AppPermission.referralManageOwn,
      AppPermission.referralViewConversions,
      AppPermission.usersReadAssigned,
    },
    RolePermissionMatrix.financialConsultant: {
      AppPermission.clientsReadAssigned,
      AppPermission.clientsManageAssigned,
      AppPermission.usersReadAssigned,
    },
    RolePermissionMatrix.evaluator: {
      AppPermission.evaluationsReadAssigned,
      AppPermission.evaluationsRecommend,
      AppPermission.accessReviewAssigned,
      AppPermission.usersReadAssigned,
    },
    RolePermissionMatrix.adminAssistant: {
      AppPermission.rolesRead,
      AppPermission.usersReadAll,
      AppPermission.usersUpdateBasic,
      AppPermission.accessReviewAssigned,
      AppPermission.auditReadAssigned,
    },
    RolePermissionMatrix.subAdmin: {
      AppPermission.rolesRead,
      AppPermission.usersReadAll,
      AppPermission.usersUpdateBasic,
      AppPermission.usersSuspend,
      AppPermission.usersAssignNonPrivilegedRole,
      AppPermission.accessApprove,
      AppPermission.accessReject,
      AppPermission.referralManageAll,
      AppPermission.auditReadAssigned,
      AppPermission.courseworkViewAssigned,
      AppPermission.courseworkGrade,
      AppPermission.courseworkRequestRevision,
      AppPermission.courseworkFinaliseGrade,
      AppPermission.courseworkAssignThemes,
      AppPermission.courseworkViewReports,
    },
    RolePermissionMatrix.founder: {
      AppPermission.rolesRead,
      AppPermission.usersReadAll,
      AppPermission.usersUpdateBasic,
      AppPermission.usersAssignNonPrivilegedRole,
      AppPermission.accessApprove,
      AppPermission.accessReject,
      AppPermission.referralManageAll,
      AppPermission.auditReadAll,
      AppPermission.systemSettingsManage,
      AppPermission.signalsManageAll,
      AppPermission.marketAnalysisManageAll,
      AppPermission.courseworkViewAssigned,
      AppPermission.courseworkGrade,
      AppPermission.courseworkRequestRevision,
      AppPermission.courseworkFinaliseGrade,
      AppPermission.courseworkManageThemes,
      AppPermission.courseworkPublishThemes,
      AppPermission.courseworkAssignThemes,
      AppPermission.courseworkViewReports,
      AppPermission.courseworkManageSettings,
    },
  };

  static const privilegedRoleIds = <int>{
    RolePermissionMatrix.superAdmin,
    RolePermissionMatrix.founder,
    RolePermissionMatrix.subAdmin,
    RolePermissionMatrix.adminAssistant,
  };

  bool get isAccountUsable {
    final status = user.accountStatus.trim().toLowerCase();
    return !user.isSuspended &&
        !const {
          'suspended',
          'deleted',
          'deletioninprogress',
          'revoked',
          'disabled',
          'inactive',
        }.contains(status.replaceAll(RegExp(r'[-_\s]'), '')) &&
        !const {
          AccessStatus.suspended,
          AccessStatus.revoked,
          AccessStatus.deleted,
        }.contains(user.accessStatus);
  }

  bool get hasApprovedClientAccess =>
      isAccountUsable &&
      (user.isAdmin ||
          user.effectiveRoleIds.contains(RolePermissionMatrix.superAdmin) ||
          user.accessStatus == AccessStatus.approved);

  bool can(String permission, {ResourceContext? resource}) {
    if (!isAccountUsable) return false;
    final override = user.permissionOverrides[permission];
    if (override == false) return false;
    if (!_scopeAllows(permission, resource)) return false;
    if (user.effectiveRoleIds.contains(RolePermissionMatrix.superAdmin)) {
      return true;
    }
    if (override == true) return true;
    if (publicOwnPermissions.contains(permission)) return true;
    if (approvedClientPermissions.contains(permission)) {
      return hasApprovedClientAccess;
    }

    final granted = <String>{
      for (final roleId in user.effectiveRoleIds) ...?rolePermissions[roleId],
    };
    if (!granted.contains(permission)) return false;
    return true;
  }

  bool canAny(Set<String> permissions, {ResourceContext? resource}) =>
      permissions.any((permission) => can(permission, resource: resource));

  bool canAll(Set<String> permissions, {ResourceContext? resource}) =>
      permissions.every((permission) => can(permission, resource: resource));

  bool canAssignRole(int targetRoleId) {
    final permission = privilegedRoleIds.contains(targetRoleId)
        ? AppPermission.usersAssignPrivilegedRole
        : AppPermission.usersAssignNonPrivilegedRole;
    return can(permission) &&
        user.effectiveRoleIds.any(
          (roleId) => RolePermissionMatrix.canInviteRole(roleId, targetRoleId),
        );
  }

  bool _scopeAllows(String permission, ResourceContext? resource) {
    if (resource == null) return true;
    if (permission.endsWith('Own') &&
        resource.ownerUserId != null &&
        resource.ownerUserId != user.uid) {
      return false;
    }
    if (permission.contains('Assigned')) {
      if (resource.clientUserId != null &&
          !user.assignedClientIds.contains(resource.clientUserId)) {
        return false;
      }
      if (resource.teamId != null && !user.teamIds.contains(resource.teamId)) {
        return false;
      }
      if (resource.evaluationId != null &&
          !user.assignedEvaluationIds.contains(resource.evaluationId)) {
        return false;
      }
    }
    return resource.organisationId == null ||
        user.organisationId.isEmpty ||
        resource.organisationId == user.organisationId;
  }
}
