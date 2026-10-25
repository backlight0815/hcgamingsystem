import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/localization/app_locale_controller.dart';
import '../../core/localization/app_localizations.dart';
import '../../core/security/access_policy.dart';
import '../../models/app_user.dart';
import '../../repositories/auth_repository.dart';
import '../../repositories/trading_repository.dart';
import '../../core/storage/local_app_storage.dart';
import '../../services/push_notification_service.dart';
import '../../services/account_deletion_service.dart';
import '../../shared/app_feedback.dart';
import '../../shared/trading_data_builder.dart';
import '../../shared/trading_data_source_selector.dart';

const _privacyPolicyUrl = 'https://www.hcgaming.my/privacy-policy';

class SettingsScreen extends StatefulWidget {
  const SettingsScreen({
    super.key,
    required this.user,
    required this.authRepository,
    required this.repository,
    required this.firebaseEnabled,
    required this.storage,
    this.notificationServiceEnabled = true,
  });

  final AppUser user;
  final AuthRepository authRepository;
  final TradingRepository repository;
  final bool firebaseEnabled;
  final LocalAppStorage storage;
  final bool notificationServiceEnabled;

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  late NotificationPreferences _notifications;
  var _permission = 'checking';
  var _deletingAccount = false;
  String _deletionProgress = '';
  late AccountDeletionService _accountDeletionService;

  @override
  void initState() {
    super.initState();
    _accountDeletionService = AccountDeletionService(
      authRepository: widget.authRepository,
      storage: widget.storage,
      apiClient: widget.repository.apiClient,
      unregisterNotifications: widget.notificationServiceEnabled
          ? PushNotificationService.instance.unregisterUser
          : null,
    );
    if (widget.notificationServiceEnabled) {
      _notifications = PushNotificationService.instance.preferencesFor(
        widget.user.uid,
      );
      _loadPermission();
    } else {
      _notifications = const NotificationPreferences();
      _permission = 'unsupported';
    }
  }

  @override
  Widget build(BuildContext context) {
    final hasTradingAccess = ClientAccessPolicy.hasFullAccess(widget.user);
    return Scaffold(
      appBar: AppBar(title: Text(context.l10n.text('Settings'))),
      body: TradingDataBuilder(
        user: widget.user,
        repository: widget.repository,
        includePrivateTradingData: hasTradingAccess,
        builder: (context, journals, capitals, pairs, mode) {
          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 24),
            children: [
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    children: [
                      ListTile(
                        contentPadding: EdgeInsets.zero,
                        leading: CircleAvatar(
                          foregroundImage: widget.user.avatarUrl.trim().isEmpty
                              ? null
                              : NetworkImage(widget.user.avatarUrl),
                          child: widget.user.avatarUrl.trim().isEmpty
                              ? Text(
                                  widget.user.label.characters.first
                                      .toUpperCase(),
                                )
                              : null,
                        ),
                        title: Text(widget.user.label),
                        subtitle: Text(
                          '${widget.user.email}\n'
                          '${context.l10n.roleName(widget.user.roleId)}',
                        ),
                        isThreeLine: true,
                        trailing: const Chip(
                          avatar: Icon(Icons.cloud_done_outlined, size: 18),
                          label: Text('Firebase'),
                        ),
                      ),
                      if (hasTradingAccess) ...[
                        const Divider(height: 24),
                        Row(
                          children: [
                            Expanded(
                              child: _CountFigure(
                                label: context.l10n.text('Trades'),
                                value: '${journals.length}',
                              ),
                            ),
                            Expanded(
                              child: _CountFigure(
                                label: context.l10n.text('Capital records'),
                                value: '${capitals.length}',
                              ),
                            ),
                          ],
                        ),
                      ],
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 14),
              if (hasTradingAccess)
                TradingDataSourceSelector(
                  user: widget.user,
                  repository: widget.repository,
                  mode: mode,
                )
              else
                Card(
                  child: ListTile(
                    leading: const Icon(Icons.lock_outline),
                    title: Text(
                      context.l10n.text('Trading settings are locked'),
                    ),
                    subtitle: Text(
                      context.l10n.text(
                        'Trading data preferences become available after client access is approved. General settings remain available.',
                      ),
                    ),
                  ),
                ),
              const SizedBox(height: 14),
              Card(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        context.l10n.text('Language'),
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.w900),
                      ),
                      const SizedBox(height: 8),
                      SegmentedButton<String>(
                        segments: [
                          ButtonSegment(
                            value: 'en',
                            label: Text(context.l10n.text('English')),
                          ),
                          ButtonSegment(
                            value: 'zh',
                            label: Text(
                              context.l10n.text('Simplified Chinese'),
                            ),
                          ),
                        ],
                        selected: {context.l10n.locale.languageCode},
                        onSelectionChanged: (selection) {
                          AppLocaleController.of(
                            context,
                          ).setLanguage(selection.first);
                        },
                      ),
                      const SizedBox(height: 8),
                      Text(
                        context.l10n.text(
                          'Language changes are applied immediately and remembered on this device.',
                        ),
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 14),
              Card(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(8, 12, 8, 8),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 8),
                        child: Row(
                          children: [
                            const Icon(Icons.notifications_active_outlined),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    context.l10n.text('Phone notifications'),
                                    style: Theme.of(context)
                                        .textTheme
                                        .titleMedium
                                        ?.copyWith(fontWeight: FontWeight.w900),
                                  ),
                                  Text(_permissionLabel(context)),
                                ],
                              ),
                            ),
                            TextButton(
                              onPressed: _requestPermission,
                              child: Text(context.l10n.text('Allow')),
                            ),
                          ],
                        ),
                      ),
                      const Divider(),
                      _notificationSwitch(
                        title: context.l10n.text('Trading signals'),
                        subtitle: context.l10n.text('New live signal alerts'),
                        value: _notifications.signals,
                        onChanged: (value) => _saveNotifications(
                          _notifications.copyWith(signals: value),
                        ),
                      ),
                      _notificationSwitch(
                        title: context.l10n.text('Market analysis'),
                        subtitle: context.l10n.text('New analyst outlooks'),
                        value: _notifications.marketAnalysis,
                        onChanged: (value) => _saveNotifications(
                          _notifications.copyWith(marketAnalysis: value),
                        ),
                      ),
                      _notificationSwitch(
                        title: context.l10n.text('Recording classes'),
                        subtitle: context.l10n.text('New class recordings'),
                        value: _notifications.recordings,
                        onChanged: (value) => _saveNotifications(
                          _notifications.copyWith(recordings: value),
                        ),
                      ),
                      _notificationSwitch(
                        title: context.l10n.text('Knowledge centre'),
                        subtitle: context.l10n.text('New learning resources'),
                        value: _notifications.knowledgeCentre,
                        onChanged: (value) => _saveNotifications(
                          _notifications.copyWith(knowledgeCentre: value),
                        ),
                      ),
                      _notificationSwitch(
                        title: context.l10n.text('Appointments'),
                        subtitle: context.l10n.text(
                          'Bookings, changes, and reminders',
                        ),
                        value: _notifications.appointments,
                        onChanged: (value) => _saveNotifications(
                          _notifications.copyWith(appointments: value),
                        ),
                      ),
                      _notificationSwitch(
                        title: context.l10n.text(
                          'Receive Announcement Notifications',
                        ),
                        subtitle: context.l10n.text(
                          'Company updates and notices',
                        ),
                        value: _notifications.announcements,
                        onChanged: (value) => _saveNotifications(
                          _notifications.copyWith(announcements: value),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 14),
              if (hasTradingAccess) ...[
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          context.l10n.text('Trading Pairs'),
                          style: Theme.of(context).textTheme.titleMedium
                              ?.copyWith(fontWeight: FontWeight.w900),
                        ),
                        const SizedBox(height: 10),
                        Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: [
                            for (final pair in pairs)
                              Chip(label: Text(pair.symbol)),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 14),
              ],
              Card(
                child: ListTile(
                  leading: const Icon(Icons.privacy_tip_outlined),
                  title: Text(context.l10n.text('Privacy Policy')),
                  subtitle: Text(
                    context.l10n.text('How HC Gaming handles app data'),
                  ),
                  trailing: const Icon(Icons.open_in_new),
                  onTap: () => _openPrivacyPolicy(context),
                ),
              ),
              const SizedBox(height: 14),
              Card(
                child: ListTile(
                  leading: Icon(
                    Icons.delete_forever_outlined,
                    color: Theme.of(context).colorScheme.error,
                  ),
                  title: Text(context.l10n.text('Delete account')),
                  subtitle: Text(
                    _deletingAccount
                        ? context.l10n.text(_deletionProgress)
                        : context.l10n.text(
                            'Permanently remove your login and active app data. An anonymized audit record is kept for administrators.',
                          ),
                  ),
                  trailing: _deletingAccount
                      ? const SizedBox.square(
                          dimension: 22,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : null,
                  onTap: _deletingAccount ? null : _confirmDeleteAccount,
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _notificationSwitch({
    required String title,
    required String subtitle,
    required bool value,
    required ValueChanged<bool> onChanged,
  }) {
    return SwitchListTile.adaptive(
      title: Text(title),
      subtitle: Text(subtitle),
      value: value,
      onChanged: onChanged,
    );
  }

  Future<void> _loadPermission() async {
    final settings = await PushNotificationService.instance.currentSettings();
    if (!mounted) return;
    setState(() {
      _permission = settings == null
          ? 'unsupported'
          : settings.authorizationStatus.name;
    });
  }

  String _permissionLabel(BuildContext context) {
    if (_permission == 'checking') {
      return context.l10n.text('Checking phone permission…');
    }
    if (_permission == 'unsupported') {
      return context.l10n.text('Not supported on this device');
    }
    return context.l10n.text('Phone permission: {status}', {
      'status': context.l10n.text(_permission),
    });
  }

  Future<void> _requestPermission() async {
    await PushNotificationService.instance.requestPermission();
    await _loadPermission();
  }

  Future<void> _confirmDeleteAccount() async {
    final password = await showDialog<String>(
      context: context,
      barrierDismissible: true,
      builder: (_) => DeleteAccountConfirmationDialog(
        passwordRequired: widget.authRepository.currentUserUsesPasswordProvider,
      ),
    );
    if (password == null || !mounted) return;
    setState(() => _deletingAccount = true);
    try {
      await _accountDeletionService.deleteCurrentAccount(
        currentPassword: password,
        reason: 'Deleted from mobile Settings',
        onProgress: (stage) {
          if (!mounted) return;
          setState(
            () => _deletionProgress = switch (stage) {
              AccountDeletionStage.reauthenticating =>
                'Verifying your identity...',
              AccountDeletionStage.removingNotifications =>
                'Removing notification access...',
              AccountDeletionStage.deletingRemoteData =>
                'Deleting your account data...',
              AccountDeletionStage.clearingLocalData =>
                'Clearing data from this device...',
              AccountDeletionStage.completed => 'Account deletion completed',
            },
          );
        },
      );
    } catch (error) {
      if (!mounted) return;
      setState(() => _deletingAccount = false);
      AppFeedback.error(context, error);
    }
  }

  Future<void> _saveNotifications(NotificationPreferences preferences) async {
    final l10n = context.l10n;
    setState(() => _notifications = preferences);
    try {
      await PushNotificationService.instance.updatePreferences(
        widget.user,
        preferences,
      );
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              l10n.text('Unable to save: {error}', {'error': error}),
            ),
          ),
        );
      }
    }
  }

  Future<void> _openPrivacyPolicy(BuildContext context) async {
    final l10n = context.l10n;
    final opened = await launchUrl(
      Uri.parse(_privacyPolicyUrl),
      mode: LaunchMode.externalApplication,
    );
    if (!opened && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(l10n.text('Unable to open privacy policy.'))),
      );
    }
  }
}

class DeleteAccountConfirmationDialog extends StatefulWidget {
  const DeleteAccountConfirmationDialog({
    super.key,
    this.passwordRequired = true,
  });

  final bool passwordRequired;

  @override
  State<DeleteAccountConfirmationDialog> createState() =>
      _DeleteAccountConfirmationDialogState();
}

class _DeleteAccountConfirmationDialogState
    extends State<DeleteAccountConfirmationDialog> {
  final _controller = TextEditingController();
  final _passwordController = TextEditingController();
  var _obscurePassword = true;

  @override
  void dispose() {
    _controller.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final enabled =
        _controller.text.trim().toUpperCase() == 'DELETE' &&
        (!widget.passwordRequired || _passwordController.text.isNotEmpty);
    return AlertDialog(
      title: Text(context.l10n.text('Delete account?')),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              context.l10n.text(
                'This permanently removes your login and active app data. This cannot be undone.',
              ),
            ),
            const SizedBox(height: 8),
            Text(
              context.l10n.text(
                'Your trading data, personality answers, files, sessions, and active account access will be removed. A limited anonymized audit record may be retained for security and compliance.',
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _controller,
              textCapitalization: TextCapitalization.characters,
              decoration: InputDecoration(
                labelText: context.l10n.text('Type DELETE to confirm'),
              ),
              onChanged: (_) => setState(() {}),
            ),
            const SizedBox(height: 12),
            if (widget.passwordRequired)
              TextField(
                controller: _passwordController,
                obscureText: _obscurePassword,
                autofillHints: const [AutofillHints.password],
                decoration: InputDecoration(
                  labelText: context.l10n.text('Current password'),
                  suffixIcon: IconButton(
                    tooltip: context.l10n.text(
                      _obscurePassword ? 'Show password' : 'Hide password',
                    ),
                    onPressed: () =>
                        setState(() => _obscurePassword = !_obscurePassword),
                    icon: Icon(
                      _obscurePassword
                          ? Icons.visibility
                          : Icons.visibility_off,
                    ),
                  ),
                ),
                onChanged: (_) => setState(() {}),
              )
            else
              Text(
                context.l10n.text(
                  'For your social sign-in account, sign in again first if the service asks for recent authentication.',
                ),
              ),
          ],
        ),
      ),
      actions: [
        TextButton(
          key: const ValueKey('delete-account-cancel'),
          autofocus: true,
          onPressed: () => Navigator.pop(context),
          child: Text(context.l10n.text('Cancel')),
        ),
        FilledButton(
          key: const ValueKey('delete-account-confirm'),
          onPressed: enabled
              ? () => Navigator.pop(context, _passwordController.text)
              : null,
          style: FilledButton.styleFrom(
            backgroundColor: Theme.of(context).colorScheme.error,
          ),
          child: Text(context.l10n.text('Delete account')),
        ),
      ],
    );
  }
}

class _CountFigure extends StatelessWidget {
  const _CountFigure({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: Theme.of(context).textTheme.labelLarge),
        const SizedBox(height: 4),
        Text(
          value,
          style: Theme.of(
            context,
          ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900),
        ),
      ],
    );
  }
}
