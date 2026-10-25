import 'package:flutter/material.dart';

import '../../models/app_user.dart';
import 'permission_service.dart';

enum AppFeatureArea {
  access,
  announcements,
  tradingBlog,
  support,
  appointments,
  tradingTools,
  tradingPlan,
  tradingStrategy,
  psychology,
  profile,
  settings,
  dashboard,
  journal,
  accounts,
  statistics,
  propFirm,
  tradingHubPrivate,
  signals,
  marketResources,
  knowledge,
  recordings,
  marketAnalysis,
  courses,
  exams,
  admin,
}

class ClientAccessPolicy {
  const ClientAccessPolicy._();

  static const publicAllowed = <AppFeatureArea>{
    AppFeatureArea.access,
    AppFeatureArea.announcements,
    AppFeatureArea.tradingBlog,
    AppFeatureArea.support,
    AppFeatureArea.tradingTools,
    AppFeatureArea.tradingPlan,
    AppFeatureArea.tradingStrategy,
    AppFeatureArea.psychology,
    AppFeatureArea.profile,
    AppFeatureArea.settings,
    AppFeatureArea.dashboard,
    AppFeatureArea.journal,
    AppFeatureArea.statistics,
  };

  static bool hasFullAccess(AppUser user) =>
      PermissionService(user).hasApprovedClientAccess;

  static bool canAccess(AppUser user, AppFeatureArea area) {
    final permissions = PermissionService(user);
    if (!permissions.isAccountUsable) {
      return const {
        AppFeatureArea.access,
        AppFeatureArea.profile,
        AppFeatureArea.settings,
      }.contains(area);
    }
    final required = _permissionFor(area);
    if (required != null) return permissions.can(required);
    if (hasFullAccess(user)) return true;
    return publicAllowed.contains(area);
  }

  static bool isRestricted(AppUser user, AppFeatureArea area) {
    return !canAccess(user, area);
  }

  static String? _permissionFor(AppFeatureArea area) => switch (area) {
    AppFeatureArea.tradingTools => AppPermission.toolkitUse,
    AppFeatureArea.tradingPlan => AppPermission.tradingPlanReadOwn,
    AppFeatureArea.tradingStrategy => AppPermission.tradingStrategyReadOwn,
    AppFeatureArea.psychology => AppPermission.psychologyReadOwn,
    AppFeatureArea.journal => AppPermission.journalReadOwn,
    AppFeatureArea.statistics ||
    AppFeatureArea.dashboard => AppPermission.statisticsReadOwn,
    AppFeatureArea.accounts => AppPermission.mt5ConnectOwn,
    AppFeatureArea.signals => AppPermission.signalsReadApproved,
    AppFeatureArea.marketAnalysis => AppPermission.marketAnalysisRead,
    AppFeatureArea.admin => AppPermission.usersReadAll,
    _ => null,
  };
}

class GuardedFeature {
  const GuardedFeature({required this.area, required this.builder});

  final AppFeatureArea area;
  final WidgetBuilder builder;
}
