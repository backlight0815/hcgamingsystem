import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/localization/app_localizations.dart';
import '../../core/security/access_policy.dart';
import '../../core/security/app_role_permissions.dart';
import '../../core/storage/local_app_storage.dart';
import '../../models/access_status.dart';
import '../../models/app_notification.dart';
import '../../models/app_user.dart';
import '../../repositories/auth_repository.dart';
import '../../repositories/trading_repository.dart';
import '../../repositories/leaderboard_repository.dart';
import '../../repositories/notification_repository.dart';
import '../../repositories/stock_repository.dart';
import '../../services/push_notification_service.dart';
import '../../shared/formatters.dart';
import '../access/access_status_screen.dart';
import '../announcements/announcement_screens.dart';
import '../appointments/trading_appointments_screen.dart';
import '../examination/final_exam_screen.dart';
import '../leaderboard/leaderboard_screen.dart';
import '../notifications/notification_center_screen.dart';
import '../profile/profile_screen.dart';
import '../profile/current_roles_card.dart';
import '../readiness/readiness_checklist_screen.dart';
import '../recruitment/recruitment_screen.dart';
import '../settings/settings_screen.dart';
import '../support/support_tickets_screen.dart';
import '../stocks/stock_information_screen.dart';
import '../tools/toolkit_models.dart';
import '../tools/tools_screen.dart';
import 'profile_workspace_models.dart';
import 'profile_workspace_widgets.dart';

class MoreScreen extends StatefulWidget {
  const MoreScreen({
    super.key,
    required this.user,
    required this.authRepository,
    required this.tradingRepository,
    required this.storage,
    required this.firebaseEnabled,
    this.settingsDestinationBuilder,
    this.notificationUnregister,
    this.stockRepository,
    this.notificationRepository,
    this.leaderboardRepository,
    this.notificationOpen,
  });

  final AppUser user;
  final AuthRepository authRepository;
  final TradingRepository tradingRepository;
  final LocalAppStorage storage;
  final bool firebaseEnabled;
  final WidgetBuilder? settingsDestinationBuilder;
  final Future<void> Function(String userId)? notificationUnregister;
  final StockRepository? stockRepository;
  final NotificationRepository? notificationRepository;
  final LeaderboardRepository? leaderboardRepository;
  final ValueChanged<AppNotificationRecord>? notificationOpen;

  @override
  State<MoreScreen> createState() => _MoreScreenState();
}

class _MoreScreenState extends State<MoreScreen> {
  late Future<Map<String, bool>> _featuresFuture;
  var _openingRoute = false;
  var _signingOut = false;
  var _recentTools = const <ToolkitRecentEntry>[];

  @override
  void initState() {
    super.initState();
    _reloadLocalState();
    _featuresFuture = widget.tradingRepository.featureConfiguration();
  }

  @override
  void didUpdateWidget(covariant MoreScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.user.uid != widget.user.uid ||
        oldWidget.storage != widget.storage) {
      _reloadLocalState();
    }
    if (oldWidget.tradingRepository != widget.tradingRepository) {
      _featuresFuture = widget.tradingRepository.featureConfiguration();
    }
  }

  void _reloadLocalState() {
    _recentTools = ToolkitRecentStore(widget.storage).load(widget.user.uid);
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, bool>>(
      future: _featuresFuture,
      builder: (context, snapshot) {
        final features = snapshot.data ?? const <String, bool>{};
        return _workspace(
          context,
          features: features,
          loadingFeatures: snapshot.connectionState == ConnectionState.waiting,
          featureError: snapshot.hasError ? snapshot.error : null,
        );
      },
    );
  }

  Widget _workspace(
    BuildContext context, {
    required Map<String, bool> features,
    required bool loadingFeatures,
    required Object? featureError,
  }) {
    final l10n = context.l10n;
    final completion = ProfileCompletion.fromUser(widget.user);
    final services = _serviceItems(context, features);
    final quickActions = _quickActions(context, services);
    final statuses = _statusItems(context, completion);

    return LayoutBuilder(
      builder: (context, viewport) {
        final width = viewport.maxWidth
            .clamp(0, ProfileWorkspaceLayout.maxContentWidth)
            .toDouble();
        final desktop = ProfileWorkspaceLayout.useDesktopRail(width);
        final identity = ProfileIdentityHeader(
          key: const ValueKey('profile-identity'),
          name: _displayName(l10n),
          email: widget.user.email,
          // The dedicated Current Roles card below is the authoritative role
          // display and avoids reducing a multi-role account to one badge.
          role: '',
          approvalStatus: l10n.text(_accessStatusKey),
          completionLabel: completion.isComplete
              ? l10n.text('Profile details complete')
              : l10n.text('{completed} of {total} profile fields', {
                  'completed': completion.completedFields,
                  'total': completion.totalFields,
                }),
          completionProgress: completion.progress,
          completionNextStep: completion.isComplete
              ? null
              : l10n.text(completion.nextStepKey),
          completionActionLabel: completion.isComplete
              ? null
              : l10n.text('Continue setup'),
          initials: _initials(_displayName(l10n)),
          editTooltip: l10n.text('Edit profile'),
          avatarUrl: widget.user.avatarUrl,
          onEdit: _openingRoute ? null : () => _open(_profileBuilder),
          onContinueSetup: _openingRoute ? null : () => _open(_profileBuilder),
        );
        final statusStrip = ProfileStatusStrip(items: statuses);
        final mainContent = _mainContent(
          context,
          width: desktop
              ? width - ProfileWorkspaceLayout.desktopRailWidth - 22
              : width,
          services: services,
          quickActions: quickActions,
          loadingFeatures: loadingFeatures,
          featureError: featureError,
          includeSettings: true,
          includeAccountActions: !desktop,
        );

        return SingleChildScrollView(
          padding: ProfileWorkspaceLayout.pagePadding(width),
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(
                maxWidth: ProfileWorkspaceLayout.maxContentWidth,
              ),
              child: desktop
                  ? Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        SizedBox(
                          width: ProfileWorkspaceLayout.desktopRailWidth,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              ProfileAnimatedSection(child: identity),
                              const SizedBox(height: 14),
                              CurrentRolesCard(
                                roles: widget.user.effectiveRoles,
                              ),
                              const SizedBox(height: 14),
                              statusStrip,
                              const SizedBox(height: 18),
                              _accountActions(context),
                            ],
                          ),
                        ),
                        const SizedBox(width: 22),
                        Expanded(child: mainContent),
                      ],
                    )
                  : Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        ProfileAnimatedSection(child: identity),
                        const SizedBox(height: 12),
                        CurrentRolesCard(roles: widget.user.effectiveRoles),
                        const SizedBox(height: 12),
                        statusStrip,
                        const SizedBox(height: 20),
                        mainContent,
                      ],
                    ),
            ),
          ),
        );
      },
    );
  }

  Widget _mainContent(
    BuildContext context, {
    required double width,
    required List<ProfileServiceItem> services,
    required List<_QuickActionDefinition> quickActions,
    required bool loadingFeatures,
    required Object? featureError,
    required bool includeSettings,
    required bool includeAccountActions,
  }) {
    final l10n = context.l10n;
    final recentActivity = _recentActivity(l10n);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (loadingFeatures) ...[
          const LinearProgressIndicator(minHeight: 2),
          const SizedBox(height: 10),
        ],
        if (featureError != null) ...[
          ProfileInlineNotice(
            message: l10n.text(
              'Some service availability could not be loaded. Core account actions remain available.',
            ),
            icon: Icons.cloud_off_outlined,
          ),
          const SizedBox(height: 14),
        ],
        ProfileSectionHeader(
          title: l10n.text('Quick Actions'),
          subtitle: l10n.text('Your most useful account commands.'),
        ),
        const SizedBox(height: 10),
        _quickActionGrid(width, quickActions, l10n),
        const SizedBox(height: 22),
        ProfileSectionHeader(
          title: l10n.text('My Services'),
          subtitle: l10n.text('Services available for your account and role.'),
        ),
        const SizedBox(height: 10),
        _serviceWorkspace(width, services, l10n),
        if (recentActivity != null) ...[
          const SizedBox(height: 22),
          ProfileSectionHeader(title: l10n.text('Recent Activity')),
          const SizedBox(height: 9),
          recentActivity,
        ],
        if (includeSettings) ...[
          const SizedBox(height: 22),
          ProfileSectionHeader(
            title: l10n.text('Account and Preferences'),
            subtitle: l10n.text(
              'Manage personal details, trading data, language and privacy.',
            ),
          ),
          const SizedBox(height: 10),
          _settingsGroups(context),
        ],
        if (includeAccountActions) ...[
          const SizedBox(height: 22),
          _accountActions(context),
        ],
      ],
    );
  }

  List<ProfileServiceItem> _serviceItems(
    BuildContext context,
    Map<String, bool> features,
  ) {
    bool enabled(String key) => features[key] ?? true;
    final user = widget.user;
    final fullAccess = ClientAccessPolicy.hasFullAccess(user);
    final tradingAccess = user.effectiveRoleIds.any(
      const {1, 2, 750, 760, 770}.contains,
    );
    final supportAccess = user.effectiveRoleIds.any(
      const {1, 2, 201, 202, 501, 502, 750, 760, 770}.contains,
    );
    final recruitmentAccess = user.effectiveRoleIds.any(
      (roleId) => RolePermissionMatrix.assignableRoleIds(
        roleId,
      ).any(RolePermissionMatrix.isPublicRegistrationRole),
    );

    return <ProfileServiceItem>[
      if (!fullAccess)
        ProfileServiceItem(
          id: 'client_access',
          titleKey: 'Client access',
          descriptionKey: 'Application status and request form',
          icon: Icons.verified_user_outlined,
          size: ProfileModuleSize.highlighted,
          statusKey: _accessStatusKey,
          destinationBuilder: (_) => AccessStatusScreen(
            user: user,
            authRepository: widget.authRepository,
          ),
        ),
      if (fullAccess && enabled('trading_exams'))
        ProfileServiceItem(
          id: 'final_exam',
          titleKey: 'Final exam',
          descriptionKey: 'Official assessment and results',
          icon: Icons.school_outlined,
          statusKey: user.finalExamPassed
              ? 'Passed'
              : user.requiresFinalExam
              ? 'Action required'
              : 'Available',
          destinationBuilder: (_) =>
              FinalExamScreen(repository: widget.tradingRepository),
        ),
      if (ClientAccessPolicy.canAccess(user, AppFeatureArea.tradingTools))
        ProfileServiceItem(
          id: 'trading_tools',
          titleKey: 'Trading Toolkit',
          descriptionKey: 'Prepare, analyze and improve your trading',
          icon: Icons.calculate_outlined,
          size: ProfileModuleSize.highlighted,
          statusKey: _recentToolkitStatusKey,
          statusValues: _recentToolkitStatusValues,
          destinationBuilder: (_) => ToolsScreen(
            user: user,
            repository: widget.tradingRepository,
            storage: widget.storage,
          ),
        ),
      ProfileServiceItem(
        id: 'announcements',
        titleKey: 'Announcements',
        descriptionKey: 'Company updates and notices',
        icon: Icons.campaign_outlined,
        size: ProfileModuleSize.compact,
        statusKey: 'Check for updates',
        destinationBuilder: (_) => AnnouncementListScreen(
          user: user,
          repository: widget.tradingRepository,
        ),
      ),
      if (widget.notificationRepository != null)
        ProfileServiceItem(
          id: 'notifications',
          titleKey: 'Notification Centre',
          descriptionKey: 'Signals, announcements and stock-report updates',
          icon: Icons.notifications_outlined,
          size: ProfileModuleSize.highlighted,
          statusKey: 'Manage inbox and preferences',
          destinationBuilder: (_) => NotificationCenterScreen(
            user: user,
            repository: widget.notificationRepository!,
            onOpen: widget.notificationOpen,
          ),
        ),
      if (widget.stockRepository != null && enabled('stock_information'))
        ProfileServiceItem(
          id: 'stock_information',
          titleKey: 'Stock Information',
          descriptionKey: 'Market data, watchlists and financial daily reports',
          icon: Icons.query_stats_outlined,
          size: ProfileModuleSize.highlighted,
          statusKey: 'Search supported stocks',
          destinationBuilder: (_) => StockInformationScreen(
            user: user,
            repository: widget.stockRepository!,
          ),
        ),
      if (widget.leaderboardRepository != null && enabled('leaderboard'))
        ProfileServiceItem(
          id: 'leaderboard',
          titleKey: 'Leaderboard',
          descriptionKey: 'Privacy-aware risk-adjusted trader rankings',
          icon: Icons.emoji_events_outlined,
          size: ProfileModuleSize.compact,
          statusKey: 'Verified rankings',
          destinationBuilder: (_) => LeaderboardScreen(
            user: user,
            repository: widget.leaderboardRepository!,
          ),
        ),
      if (fullAccess && tradingAccess && enabled('trader_centre'))
        ProfileServiceItem(
          id: 'readiness',
          titleKey: 'Readiness checklist',
          descriptionKey: 'Prepare safely before live trading',
          icon: Icons.fact_check_outlined,
          destinationBuilder: (_) => ReadinessChecklistScreen(
            user: user,
            repository: widget.tradingRepository,
          ),
        ),
      if (tradingAccess &&
          ClientAccessPolicy.canAccess(user, AppFeatureArea.appointments) &&
          enabled('appointments'))
        ProfileServiceItem(
          id: 'appointments',
          titleKey: 'Appointments',
          descriptionKey: 'Book time with trading mentors',
          icon: Icons.calendar_month_outlined,
          size: ProfileModuleSize.compact,
          statusKey: 'Book or review',
          destinationBuilder: (_) => TradingAppointmentsScreen(
            user: user,
            repository: widget.tradingRepository,
          ),
        ),
      if (supportAccess &&
          ClientAccessPolicy.canAccess(user, AppFeatureArea.support) &&
          enabled('support_tickets'))
        ProfileServiceItem(
          id: 'support',
          titleKey: 'Support',
          descriptionKey: 'Open and follow support tickets',
          icon: Icons.support_agent_outlined,
          size: ProfileModuleSize.compact,
          statusKey: 'Create or view tickets',
          destinationBuilder: (_) => SupportTicketsScreen(
            user: user,
            repository: widget.tradingRepository,
          ),
        ),
      if (fullAccess && recruitmentAccess)
        ProfileServiceItem(
          id: 'recruitment',
          titleKey: 'Recruitment',
          descriptionKey: 'Invite traders and lead clients',
          icon: Icons.group_add_outlined,
          statusKey: 'Invite and manage',
          destinationBuilder: (_) => RecruitmentScreen(
            user: user,
            repository: widget.tradingRepository,
          ),
        ),
    ];
  }

  List<_QuickActionDefinition> _quickActions(
    BuildContext context,
    List<ProfileServiceItem> services,
  ) {
    ProfileServiceItem? service(String id) =>
        services.where((item) => item.id == id).firstOrNull;
    final preferred = <_QuickActionDefinition>[
      _QuickActionDefinition(
        id: 'edit_profile',
        labelKey: 'Edit profile',
        icon: Icons.person_outline,
        destinationBuilder: _profileBuilder,
      ),
      if (service('trading_tools') case final item?)
        _QuickActionDefinition.fromService(item),
      if (service('final_exam') case final item?)
        _QuickActionDefinition.fromService(item),
      _QuickActionDefinition.fromService(service('announcements')!),
      if (service('support') case final item?)
        _QuickActionDefinition.fromService(item),
      _QuickActionDefinition(
        id: 'settings',
        labelKey: 'Settings',
        icon: Icons.tune_outlined,
        destinationBuilder: _settingsBuilder,
      ),
    ];
    final seen = <String>{};
    return preferred
        .where((item) => seen.add(item.id))
        .take(4)
        .toList(growable: false);
  }

  List<ProfileStatusData> _statusItems(
    BuildContext context,
    ProfileCompletion completion,
  ) {
    final l10n = context.l10n;
    final setupComplete =
        !widget.user.requiresOnboarding && !widget.user.requiresFinalExam;
    return [
      ProfileStatusData(
        label: l10n.text('Account access'),
        value: l10n.text(_accessStatusKey),
        icon: Icons.verified_user_outlined,
        onTap: widget.user.accessStatus == AccessStatus.approved
            ? null
            : () => _open(
                (_) => AccessStatusScreen(
                  user: widget.user,
                  authRepository: widget.authRepository,
                ),
              ),
      ),
      ProfileStatusData(
        label: l10n.text('Account setup'),
        value: l10n.text(setupComplete ? 'Setup complete' : 'Action required'),
        icon: setupComplete ? Icons.task_alt : Icons.pending_actions_outlined,
        onTap: setupComplete ? null : _continueSetup,
      ),
      ProfileStatusData(
        label: l10n.text('Profile details'),
        value: l10n.text(
          completion.isComplete ? 'Completed' : 'Action required',
        ),
        icon: completion.isComplete
            ? Icons.person_pin_circle_outlined
            : Icons.person_add_alt_outlined,
        onTap: completion.isComplete ? null : () => _open(_profileBuilder),
      ),
      if (ClientAccessPolicy.hasFullAccess(widget.user))
        ProfileStatusData(
          label: l10n.text('Main MT5 account'),
          value: l10n.text(
            widget.user.mainMt5AccountKey.trim().isEmpty
                ? 'No main account selected'
                : 'Main account selected',
          ),
          icon: Icons.sync_alt_outlined,
          onTap: () => _open(_settingsBuilder),
        ),
    ];
  }

  Widget _quickActionGrid(
    double width,
    List<_QuickActionDefinition> actions,
    AppLocalizations l10n,
  ) {
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: actions.length,
      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: ProfileWorkspaceLayout.quickActionColumns(width),
        crossAxisSpacing: 9,
        mainAxisSpacing: 9,
        mainAxisExtent: ProfileWorkspaceLayout.quickActionExtent(
          MediaQuery.textScalerOf(context),
        ),
      ),
      itemBuilder: (context, index) {
        final action = actions[index];
        return ProfileQuickAction(
          key: ValueKey('quick-${action.id}'),
          label: l10n.text(action.labelKey),
          tooltip: l10n.text('Open {item}', {
            'item': l10n.text(action.labelKey),
          }),
          icon: action.icon,
          onTap: _openingRoute ? null : () => _open(action.destinationBuilder),
        );
      },
    );
  }

  Widget _serviceWorkspace(
    double width,
    List<ProfileServiceItem> services,
    AppLocalizations l10n,
  ) {
    final highlighted = services
        .where((item) => item.size == ProfileModuleSize.highlighted)
        .toList(growable: false);
    final regular = services
        .where((item) => item.size != ProfileModuleSize.highlighted)
        .toList(growable: false);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (var index = 0; index < highlighted.length; index++) ...[
          if (index > 0) const SizedBox(height: 10),
          SizedBox(
            height: ProfileWorkspaceLayout.serviceExtent(
              MediaQuery.textScalerOf(context),
              compact: false,
            ),
            child: _serviceModule(highlighted[index], l10n),
          ),
        ],
        if (highlighted.isNotEmpty && regular.isNotEmpty)
          const SizedBox(height: 10),
        if (regular.isNotEmpty)
          GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: regular.length,
            gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: ProfileWorkspaceLayout.serviceColumns(width),
              crossAxisSpacing: 10,
              mainAxisSpacing: 10,
              mainAxisExtent: ProfileWorkspaceLayout.serviceExtent(
                MediaQuery.textScalerOf(context),
                compact: true,
              ),
            ),
            itemBuilder: (context, index) =>
                _serviceModule(regular[index], l10n),
          ),
      ],
    );
  }

  Widget _serviceModule(ProfileServiceItem item, AppLocalizations l10n) {
    return ProfileBentoModule(
      key: ValueKey('service-${item.id}'),
      title: l10n.text(item.titleKey),
      description: l10n.text(item.descriptionKey),
      actionLabel: l10n.text('Open'),
      status: item.statusKey == null
          ? null
          : l10n.text(item.statusKey!, item.statusValues),
      icon: item.icon,
      highlighted: item.size == ProfileModuleSize.highlighted,
      onTap: _openingRoute ? null : () => _open(item.destinationBuilder),
    );
  }

  Widget _settingsGroups(BuildContext context) {
    final l10n = context.l10n;
    return Column(
      children: [
        ProfileSettingsGroup(
          title: l10n.text('Account'),
          children: [
            ProfileSettingsRow(
              key: const ValueKey('setting-profile'),
              title: l10n.text('Personal information'),
              subtitle: l10n.text('Name, email, phone and profile photo'),
              icon: Icons.person_outline,
              onTap: _openingRoute ? null : () => _open(_profileBuilder),
            ),
            ProfileSettingsRow(
              key: const ValueKey('setting-password'),
              title: l10n.text('Password and security'),
              subtitle: l10n.text('Change your current account password'),
              icon: Icons.lock_outline,
              onTap: _openingRoute ? null : () => _open(_profileBuilder),
            ),
          ],
        ),
        const SizedBox(height: 10),
        ProfileSettingsGroup(
          title: l10n.text('App Preferences'),
          children: [
            ProfileSettingsRow(
              key: const ValueKey('setting-app'),
              title: l10n.text('Language and notifications'),
              subtitle: l10n.text(
                'Locale, alerts and announcement preferences',
              ),
              icon: Icons.tune_outlined,
              onTap: _openingRoute ? null : () => _open(_settingsBuilder),
            ),
            if (ClientAccessPolicy.hasFullAccess(widget.user))
              ProfileSettingsRow(
                key: const ValueKey('setting-trading-data'),
                title: l10n.text('Trading data preferences'),
                subtitle: l10n.text(
                  'Manual journal or selected main MT5 account',
                ),
                icon: Icons.swap_horiz_outlined,
                onTap: _openingRoute ? null : () => _open(_settingsBuilder),
              ),
            ProfileSettingsRow(
              key: const ValueKey('setting-privacy'),
              title: l10n.text('Privacy and account data'),
              subtitle: l10n.text('Privacy policy and account management'),
              icon: Icons.privacy_tip_outlined,
              onTap: _openingRoute ? null : () => _open(_settingsBuilder),
            ),
          ],
        ),
      ],
    );
  }

  Widget _accountActions(BuildContext context) {
    final l10n = context.l10n;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        ProfileSettingsGroup(
          title: l10n.text('Account Actions'),
          children: [
            ProfileSettingsRow(
              key: const ValueKey('account-sign-out'),
              title: l10n.text(_signingOut ? 'Signing out…' : 'Sign out'),
              subtitle: l10n.text('End the current session on this device'),
              icon: Icons.logout,
              onTap: _signingOut ? null : _confirmAndSignOut,
            ),
          ],
        ),
        const SizedBox(height: 10),
        ProfileSettingsGroup(
          title: l10n.text('Danger Zone'),
          children: [
            ProfileSettingsRow(
              key: const ValueKey('account-delete'),
              title: l10n.text('Manage account deletion'),
              subtitle: l10n.text(
                'Review deletion information and secure confirmation requirements',
              ),
              icon: Icons.delete_forever_outlined,
              danger: true,
              onTap: _openingRoute ? null : () => _open(_settingsBuilder),
            ),
          ],
        ),
      ],
    );
  }

  ProfileActivityTimeline? _recentActivity(AppLocalizations l10n) {
    final recent = _recentTools.firstOrNull;
    if (recent == null) return null;
    final tool = toolkitCatalog
        .where((item) => item.id == recent.toolId)
        .firstOrNull;
    if (tool == null) return null;
    return ProfileActivityTimeline(
      title: l10n.text('Trading Toolkit opened'),
      detail: l10n.text(tool.titleKey),
      time: formatDateTime(recent.openedAt),
      icon: tool.icon,
    );
  }

  WidgetBuilder get _profileBuilder =>
      (_) => ProfileScreen(
        user: widget.user,
        repository: widget.authRepository,
        uploadRepository: widget.tradingRepository,
      );

  WidgetBuilder get _settingsBuilder =>
      widget.settingsDestinationBuilder ??
      (_) => SettingsScreen(
        user: widget.user,
        authRepository: widget.authRepository,
        repository: widget.tradingRepository,
        firebaseEnabled: widget.firebaseEnabled,
        storage: widget.storage,
      );

  Future<void> _open(WidgetBuilder builder) async {
    if (_openingRoute) return;
    setState(() => _openingRoute = true);
    await Navigator.of(
      context,
    ).push<void>(MaterialPageRoute<void>(builder: builder));
    if (!mounted) return;
    setState(() {
      _openingRoute = false;
      _reloadLocalState();
    });
  }

  void _continueSetup() {
    if (widget.user.requiresOnboarding) {
      unawaited(
        _open(
          (_) => AccessStatusScreen(
            user: widget.user,
            authRepository: widget.authRepository,
          ),
        ),
      );
      return;
    }
    if (widget.user.requiresFinalExam) {
      unawaited(
        _open((_) => FinalExamScreen(repository: widget.tradingRepository)),
      );
    }
  }

  Future<void> _confirmAndSignOut() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(dialogContext.l10n.text('Sign out?')),
        content: Text(
          dialogContext.l10n.text(
            'You will need to sign in again to access your account.',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(dialogContext.l10n.text('Cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(dialogContext.l10n.text('Sign out')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    final navigator = Navigator.of(context);
    setState(() => _signingOut = true);
    unawaited(_unregisterNotifications());
    try {
      await widget.authRepository.signOut();
      if (!mounted) return;
      setState(() => _signingOut = false);
      navigator.popUntil((route) => route.isFirst);
    } catch (error, stackTrace) {
      debugPrint('Profile sign-out failed: $error\n$stackTrace');
      if (!mounted) return;
      setState(() => _signingOut = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            context.l10n.text('Unable to sign out. Please try again.'),
          ),
        ),
      );
    }
  }

  Future<void> _unregisterNotifications() async {
    try {
      final unregister = widget.notificationUnregister;
      if (unregister != null) {
        await unregister(widget.user.uid);
      } else {
        await PushNotificationService.instance.unregisterUser(widget.user.uid);
      }
    } catch (error, stackTrace) {
      // Notification cleanup must never prevent an explicit account sign-out.
      debugPrint('Push notification cleanup failed: $error\n$stackTrace');
    }
  }

  String _displayName(AppLocalizations l10n) {
    final value = widget.user.displayName?.trim() ?? '';
    return value.isEmpty ? l10n.text('HC Trader member') : value;
  }

  String get _accessStatusKey {
    if (widget.user.isAdmin ||
        widget.user.accessStatus == AccessStatus.approved) {
      return 'Access approved';
    }
    return switch (widget.user.accessStatus) {
      AccessStatus.pendingReview => 'Access pending review',
      AccessStatus.rejected => 'Access rejected',
      AccessStatus.moreInfoRequired => 'More information required',
      AccessStatus.withdrawn => 'Access withdrawn',
      AccessStatus.cancelled => 'Access cancelled',
      _ => 'Access not submitted',
    };
  }

  String get _recentToolkitStatusKey =>
      _recentTools.isEmpty ? '{count} tools available' : 'Recent: {tool}';

  Map<String, Object?> get _recentToolkitStatusValues {
    final fullAccess = ClientAccessPolicy.hasFullAccess(widget.user);
    final availableCount = toolkitCatalog
        .where((tool) => !tool.requiresFullAccess || fullAccess)
        .length;
    if (_recentTools.isEmpty) return {'count': availableCount};
    final recentTool = toolkitCatalog
        .where((tool) => tool.id == _recentTools.first.toolId)
        .firstOrNull;
    return {
      'tool': recentTool == null
          ? context.l10n.text('Trading Toolkit')
          : context.l10n.text(recentTool.titleKey),
    };
  }

  static String _initials(String value) {
    final parts = value.trim().split(RegExp(r'\s+'));
    if (parts.isEmpty || parts.first.isEmpty) return 'HC';
    return parts.take(2).map((part) => part[0].toUpperCase()).join();
  }
}

class _QuickActionDefinition {
  const _QuickActionDefinition({
    required this.id,
    required this.labelKey,
    required this.icon,
    required this.destinationBuilder,
  });

  factory _QuickActionDefinition.fromService(ProfileServiceItem item) {
    return _QuickActionDefinition(
      id: item.id,
      labelKey: item.titleKey,
      icon: item.icon,
      destinationBuilder: item.destinationBuilder,
    );
  }

  final String id;
  final String labelKey;
  final IconData icon;
  final WidgetBuilder destinationBuilder;
}
