import 'dart:async';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import '../../core/localization/app_localizations.dart';
import '../../core/network/api_client.dart';
import '../../models/app_user.dart';
import '../../models/access_status.dart';
import '../../models/mt5_sync_models.dart';
import '../../models/referral_relationship.dart';
import '../../models/trader_account_profile.dart';
import '../../repositories/auth_repository.dart';
import '../../repositories/trading_repository.dart';
import '../../shared/app_feedback.dart';
import '../../services/referral_relationship_service.dart';
import 'current_roles_card.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({
    super.key,
    required this.user,
    required this.repository,
    required this.uploadRepository,
    this.referralService,
  });

  final AppUser user;
  final AuthRepository repository;
  final TradingRepository uploadRepository;
  final ReferralRelationshipService? referralService;

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen>
    with WidgetsBindingObserver {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _email;
  late final TextEditingController _phone;
  StreamSubscription<AppUser?>? _userSubscription;
  late AppUser _displayedUser;
  PlatformFile? _avatar;
  var _saving = false;
  var _dirty = false;
  var _syncingUser = false;
  var _refreshing = false;
  Object? _profileRefreshError;
  String? _pendingVerificationEmail;
  late String _initialName;
  late String _initialEmail;
  late String _initialPhone;
  late ReferralRelationshipService _referralService;
  late Future<UplineRelationship> _upline;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _displayedUser = widget.user;
    _initialName = (_displayedUser.displayName ?? '').trim();
    _initialEmail = _displayedUser.email.trim().toLowerCase();
    _initialPhone = _displayedUser.phoneNumber.trim();
    _name = TextEditingController(text: _initialName);
    _email = TextEditingController(text: _initialEmail);
    _phone = TextEditingController(text: _initialPhone);
    _name.addListener(_updateDirtyState);
    _email.addListener(_updateDirtyState);
    _phone.addListener(_updateDirtyState);
    _referralService =
        widget.referralService ??
        ReferralRelationshipService(
          loader: widget.repository.resolveCurrentUpline,
        );
    _upline = _loadUpline();
    _subscribeToUser();
  }

  @override
  void didUpdateWidget(covariant ProfileScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.repository != widget.repository) {
      unawaited(_userSubscription?.cancel());
      _subscribeToUser();
    }
    if (oldWidget.repository != widget.repository ||
        oldWidget.referralService != widget.referralService) {
      _referralService =
          widget.referralService ??
          ReferralRelationshipService(
            loader: widget.repository.resolveCurrentUpline,
          );
      _upline = _loadUpline();
    }
    if (oldWidget.user != widget.user &&
        widget.user.uid == _displayedUser.uid) {
      _applyUser(widget.user);
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    unawaited(_userSubscription?.cancel());
    _name.dispose();
    _email.dispose();
    _phone.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      unawaited(_refreshProfile());
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.l10n.text('Profile'))),
      body: RefreshIndicator(
        onRefresh: () => _refreshProfile(showError: true),
        child: Form(
          key: _formKey,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
            children: [
              if (_refreshing) ...[
                const LinearProgressIndicator(minHeight: 2),
                const SizedBox(height: 12),
              ],
              if (_profileRefreshError != null) ...[
                Card(
                  color: Theme.of(context).colorScheme.errorContainer,
                  child: ListTile(
                    leading: const Icon(Icons.cloud_off_outlined),
                    title: Text(context.l10n.text('Unable to refresh profile')),
                    trailing: IconButton(
                      tooltip: context.l10n.text('Retry'),
                      onPressed: _refreshing
                          ? null
                          : () => _refreshProfile(showError: true),
                      icon: const Icon(Icons.refresh),
                    ),
                  ),
                ),
                const SizedBox(height: 12),
              ],
              Center(
                child: Column(
                  children: [
                    ClipOval(
                      child: SizedBox.square(
                        dimension: 96,
                        child: _avatar?.bytes != null
                            ? Image.memory(_avatar!.bytes!, fit: BoxFit.cover)
                            : _displayedUser.avatarUrl.trim().isNotEmpty
                            ? Image.network(
                                _displayedUser.avatarUrl,
                                fit: BoxFit.cover,
                                errorBuilder: (_, _, _) =>
                                    const Icon(Icons.person, size: 48),
                              )
                            : const ColoredBox(
                                color: Color(0xFFE0F2F1),
                                child: Icon(Icons.person, size: 48),
                              ),
                      ),
                    ),
                    TextButton.icon(
                      onPressed: _saving ? null : _pickAvatar,
                      icon: const Icon(Icons.add_a_photo_outlined),
                      label: Text(context.l10n.text('Change profile photo')),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 8),
              TextFormField(
                controller: _name,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Name'),
                  prefixIcon: const Icon(Icons.person_outline),
                ),
                validator: (value) => (value ?? '').trim().isEmpty
                    ? context.l10n.text('Name is required')
                    : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _email,
                keyboardType: TextInputType.emailAddress,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Email'),
                  prefixIcon: const Icon(Icons.email_outlined),
                ),
                validator: (value) => (value ?? '').contains('@')
                    ? null
                    : context.l10n.text('Enter a valid email'),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _phone,
                keyboardType: TextInputType.phone,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Phone number'),
                  prefixIcon: const Icon(Icons.phone_outlined),
                ),
              ),
              const SizedBox(height: 12),
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.badge_outlined),
                title: Text(
                  _displayedUser.username ?? context.l10n.text('No username'),
                ),
              ),
              CurrentRolesCard(roles: _displayedUser.effectiveRoles),
              const SizedBox(height: 12),
              _uplineTile(),
              _ProfileStatusPanel(
                user: _displayedUser,
                tradingRepository: widget.uploadRepository,
              ),
              const SizedBox(height: 12),
              FilledButton.icon(
                key: const ValueKey('profile-save'),
                onPressed: _saving || !_dirty ? null : _save,
                icon: _saving
                    ? const SizedBox.square(
                        dimension: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.save_outlined),
                label: Text(context.l10n.text('Save profile')),
              ),
              const SizedBox(height: 16),
              OutlinedButton.icon(
                onPressed: _changePassword,
                icon: const Icon(Icons.lock_reset),
                label: Text(context.l10n.text('Change password')),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _uplineTile() => FutureBuilder<UplineRelationship>(
    future: _upline,
    builder: (context, snapshot) {
      final value = snapshot.data;
      final isLoading = snapshot.connectionState == ConnectionState.waiting;
      return Semantics(
        label: context.l10n.text('Upline'),
        readOnly: true,
        child: ListTile(
          key: const ValueKey('profile-upline'),
          contentPadding: EdgeInsets.zero,
          leading: const Icon(Icons.account_tree_outlined),
          title: Text(context.l10n.text('Upline')),
          subtitle: Text(
            isLoading
                ? context.l10n.text('Loading upline...')
                : snapshot.hasError
                ? context.l10n.text('Unable to load upline')
                : value?.isAvailable == true
                ? value!.displayName
                : context.l10n.text(_uplineFallback(value?.status)),
          ),
          trailing: isLoading
              ? const SizedBox.square(
                  dimension: 20,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : snapshot.hasError
              ? IconButton(
                  tooltip: context.l10n.text('Retry'),
                  onPressed: _refreshUpline,
                  icon: const Icon(Icons.refresh),
                )
              : null,
        ),
      );
    },
  );

  String _uplineFallback(UplineRelationshipStatus? status) => switch (status) {
    UplineRelationshipStatus.unavailable => 'Upline unavailable',
    UplineRelationshipStatus.missingRecord => 'Referral record not found',
    UplineRelationshipStatus.conflict => 'Upline unavailable',
    _ => 'No upline',
  };

  void _refreshUpline() {
    setState(() => _upline = _loadUpline(refresh: true));
  }

  Future<UplineRelationship> _loadUpline({bool refresh = false}) =>
      Future<UplineRelationship>.sync(
        () => _referralService.load(refresh: refresh),
      );

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);
    try {
      final outcome = await saveProfileWithOptionalPhoto(
        selectedAvatar: _avatar,
        upload: (file) => widget.uploadRepository.uploadManagedFile(
          area: 'profile_images',
          file: file,
        ),
        save: (photoUrl) => widget.repository.updateProfile(
          name: _name.text,
          email: _email.text,
          phoneNumber: _phone.text,
          photoUrl: photoUrl,
        ),
      );
      final result = outcome.profileResult;
      final avatarUploadFailed = outcome.avatarUploadFailed;
      _pendingVerificationEmail = result.emailVerificationSent
          ? _email.text.trim().toLowerCase()
          : null;
      final latestUser = widget.repository.currentUser;
      if (latestUser != null && latestUser.uid == _displayedUser.uid) {
        _displayedUser = latestUser;
      }
      _initialName = _name.text.trim();
      _initialEmail = _email.text.trim().toLowerCase();
      _initialPhone = _phone.text.trim();
      if (!avatarUploadFailed) _avatar = null;
      if (mounted) {
        setState(() => _dirty = avatarUploadFailed);
        if (avatarUploadFailed) {
          AppFeedback.warning(
            context,
            'Profile updated, but the selected photo could not be uploaded. Try changing the photo again later.',
          );
        } else {
          AppFeedback.success(
            context,
            result.emailVerificationSent
                ? 'Profile updated. Verify the link sent to your new email address to finish changing your email.'
                : 'Profile updated successfully.',
          );
        }
      }
    } catch (error) {
      if (mounted) {
        AppFeedback.error(context, error);
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _pickAvatar() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.image,
      withData: true,
    );
    if (result != null && mounted) {
      setState(() {
        _avatar = result.files.single;
        _dirty = true;
      });
    }
  }

  void _updateDirtyState() {
    if (_syncingUser) return;
    final changed =
        _name.text.trim() != _initialName ||
        _email.text.trim().toLowerCase() != _initialEmail ||
        _phone.text.trim() != _initialPhone ||
        _avatar != null;
    if (changed != _dirty && mounted) setState(() => _dirty = changed);
  }

  void _subscribeToUser() {
    _userSubscription = widget.repository.authStateChanges().listen(
      (user) {
        if (user == null || user.uid != _displayedUser.uid || !mounted) return;
        _profileRefreshError = null;
        _applyUser(user);
      },
      onError: (Object error, StackTrace stackTrace) {
        if (mounted) setState(() => _profileRefreshError = error);
      },
    );
  }

  Future<void> _refreshProfile({bool showError = false}) async {
    if (_refreshing) return;
    if (mounted) {
      setState(() {
        _refreshing = true;
        _profileRefreshError = null;
      });
    }
    try {
      final user = await widget.repository.refreshCurrentUser();
      if (user == null) {
        throw StateError('The current user profile is unavailable.');
      }
      if (mounted && user.uid == _displayedUser.uid) {
        _applyUser(user);
        setState(() => _upline = _loadUpline(refresh: true));
      }
    } catch (error) {
      if (mounted) {
        setState(() => _profileRefreshError = error);
        if (showError) AppFeedback.error(context, error);
      }
    } finally {
      if (mounted) setState(() => _refreshing = false);
    }
  }

  void _applyUser(AppUser user) {
    if (!mounted) return;
    final verifiedEmail = user.email.trim().toLowerCase();
    if (_pendingVerificationEmail == verifiedEmail) {
      _pendingVerificationEmail = null;
    }
    if (!_dirty && !_saving) {
      _syncingUser = true;
      _initialName = (user.displayName ?? '').trim();
      _initialEmail = _pendingVerificationEmail ?? verifiedEmail;
      _initialPhone = user.phoneNumber.trim();
      _name.text = _initialName;
      _email.text = _initialEmail;
      _phone.text = _initialPhone;
      _syncingUser = false;
    }
    setState(() => _displayedUser = user);
  }

  Future<void> _changePassword() async {
    final l10n = context.l10n;
    final values = await showDialog<List<String>>(
      context: context,
      builder: (_) => const _PasswordDialog(),
    );
    if (values == null) return;
    try {
      await widget.repository.updatePassword(
        currentPassword: values[0],
        password: values[1],
      );
    } catch (error) {
      _message(l10n.text(error.toString()));
    }
  }

  void _message(String text) {
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
    }
  }
}

class ProfileSaveOutcome {
  const ProfileSaveOutcome({
    required this.profileResult,
    required this.avatarUploadFailed,
  });

  final ProfileUpdateResult profileResult;
  final bool avatarUploadFailed;
}

Future<ProfileSaveOutcome> saveProfileWithOptionalPhoto({
  required PlatformFile? selectedAvatar,
  required Future<Map<String, dynamic>> Function(ApiUploadFile file) upload,
  required Future<ProfileUpdateResult> Function(String? photoUrl) save,
}) async {
  String? photoUrl;
  var avatarUploadFailed = false;
  if (selectedAvatar != null) {
    try {
      final result = await upload(
        ApiUploadFile(
          field: 'image',
          filename: selectedAvatar.name,
          path: selectedAvatar.path,
          bytes: selectedAvatar.bytes,
        ),
      );
      photoUrl = result['url']?.toString().trim();
      if (photoUrl == null || photoUrl.isEmpty) {
        photoUrl = null;
        avatarUploadFailed = true;
      }
    } catch (error) {
      avatarUploadFailed = true;
      if (kDebugMode) {
        debugPrint(
          '[HC Trader Profile][avatarUpload] failed '
          'errorType=${error.runtimeType}',
        );
      }
    }
  }
  final profileResult = await save(photoUrl);
  return ProfileSaveOutcome(
    profileResult: profileResult,
    avatarUploadFailed: avatarUploadFailed,
  );
}

class _ProfileStatusPanel extends StatelessWidget {
  const _ProfileStatusPanel({
    required this.user,
    required this.tradingRepository,
  });

  final AppUser user;
  final TradingRepository tradingRepository;

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<TraderAccountProfile>(
      stream: tradingRepository.accountProfileFor(user.uid),
      builder: (context, profileSnapshot) {
        final verification =
            profileSnapshot.data ?? TraderAccountProfile.empty(user.uid);
        return StreamBuilder<Mt5Account?>(
          stream: tradingRepository.mainMt5AccountFor(user.uid),
          builder: (context, accountSnapshot) {
            final account = accountSnapshot.data;
            return Card(
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 6),
                child: Column(
                  children: [
                    _ProfileStatusRow(
                      label: context.l10n.text('Access Status'),
                      value: context.l10n.text(
                        _accessStatusLabel(user.accessStatus),
                      ),
                      icon: Icons.verified_user_outlined,
                    ),
                    _ProfileStatusRow(
                      label: context.l10n.text('Broker Verification Status'),
                      value: context.l10n.text(verification.statusLabel),
                      icon: Icons.policy_outlined,
                    ),
                    _ProfileStatusRow(
                      label: context.l10n.text('Main MT5 Account Status'),
                      value: context.l10n.text(
                        account == null
                            ? 'No main account selected'
                            : 'Main account selected',
                      ),
                      icon: Icons.account_balance_wallet_outlined,
                    ),
                    _ProfileStatusRow(
                      label: context.l10n.text('MT5 Connection Status'),
                      value: context.l10n.text(
                        account == null
                            ? 'Not connected'
                            : account.isConnected
                            ? 'Connected'
                            : 'Selected but not connected',
                      ),
                      icon: Icons.cable_outlined,
                    ),
                    _ProfileStatusRow(
                      label: context.l10n.text('Last Synchronization Status'),
                      value: context.l10n.text(
                        account == null || account.syncStatus.trim().isEmpty
                            ? 'Not synced yet'
                            : account.syncStatus,
                      ),
                      icon: Icons.sync_outlined,
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }
}

class _ProfileStatusRow extends StatelessWidget {
  const _ProfileStatusRow({
    required this.label,
    required this.value,
    required this.icon,
  });

  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) => ListTile(
    dense: true,
    leading: Icon(icon),
    title: Text(label),
    subtitle: Text(value),
  );
}

String _accessStatusLabel(AccessStatus status) => switch (status) {
  AccessStatus.public => 'Public',
  AccessStatus.unapproved => 'Unapproved',
  AccessStatus.approved => 'Approved',
  AccessStatus.pendingReview => 'Pending Review',
  AccessStatus.rejected => 'Rejected',
  AccessStatus.moreInfoRequired => 'More Information Required',
  AccessStatus.withdrawn => 'Withdrawn',
  AccessStatus.cancelled => 'Cancelled',
  AccessStatus.notSubmitted => 'Not Submitted',
  AccessStatus.suspended => 'Suspended',
  AccessStatus.revoked => 'Revoked',
  AccessStatus.deleted => 'Deleted',
};

class _PasswordDialog extends StatefulWidget {
  const _PasswordDialog();
  @override
  State<_PasswordDialog> createState() => _PasswordDialogState();
}

class _PasswordDialogState extends State<_PasswordDialog> {
  final _key = GlobalKey<FormState>();
  final _current = TextEditingController();
  final _password = TextEditingController();
  final _confirmation = TextEditingController();

  @override
  void dispose() {
    _current.dispose();
    _password.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text(context.l10n.text('Change password')),
    content: Form(
      key: _key,
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextFormField(
              controller: _current,
              obscureText: true,
              decoration: InputDecoration(
                labelText: context.l10n.text('Current password'),
              ),
              validator: (value) => _required(context, value),
            ),
            const SizedBox(height: 10),
            TextFormField(
              controller: _password,
              obscureText: true,
              decoration: InputDecoration(
                labelText: context.l10n.text('New password'),
              ),
              validator: (value) {
                final text = value ?? '';
                final strong =
                    text.length >= 8 &&
                    RegExp('[a-z]').hasMatch(text) &&
                    RegExp('[A-Z]').hasMatch(text) &&
                    RegExp(r'\d').hasMatch(text);
                return strong
                    ? null
                    : context.l10n.text(
                        'Use 8+ characters with upper, lower and number',
                      );
              },
            ),
            const SizedBox(height: 10),
            TextFormField(
              controller: _confirmation,
              obscureText: true,
              decoration: InputDecoration(
                labelText: context.l10n.text('Confirm password'),
              ),
              validator: (value) => value == _password.text
                  ? null
                  : context.l10n.text('Passwords do not match'),
            ),
          ],
        ),
      ),
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: Text(context.l10n.text('Cancel')),
      ),
      FilledButton(
        onPressed: () {
          if (_key.currentState!.validate()) {
            Navigator.pop(context, [_current.text, _password.text]);
          }
        },
        child: Text(context.l10n.text('Update')),
      ),
    ],
  );

  String? _required(BuildContext context, String? value) =>
      (value ?? '').isEmpty ? context.l10n.text('Required') : null;
}
