import 'package:flutter/material.dart';

import '../../core/localization/app_localizations.dart';
import '../../core/localization/app_locale_controller.dart';
import '../../core/security/app_roles.dart';
import '../../repositories/auth_repository.dart';
import '../../shared/app_feedback.dart';
import 'forgot_password_dialog.dart';

class AuthScreen extends StatefulWidget {
  const AuthScreen({
    super.key,
    required this.repository,
    required this.firebaseEnabled,
    this.initialReferralCode = '',
    this.campaignId = '',
    this.registrationSource = '',
    this.onOpenPublicPlanner,
    this.onOpenGuestAnalytics,
  });

  final AuthRepository repository;
  final bool firebaseEnabled;
  final String initialReferralCode;
  final String campaignId;
  final String registrationSource;
  final VoidCallback? onOpenPublicPlanner;
  final VoidCallback? onOpenGuestAnalytics;

  @override
  State<AuthScreen> createState() => _AuthScreenState();
}

class _AuthScreenState extends State<AuthScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _usernameController = TextEditingController();
  final _loginController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();
  final _referralController = TextEditingController();
  late final Future<List<RegistrationRole>> _roles;
  RegistrationRole? _selectedRole;
  var _register = false;
  var _loading = false;
  var _obscurePassword = true;
  var _obscureConfirmation = true;

  @override
  void initState() {
    super.initState();
    _roles = widget.repository.registrationRoles();
    final inboundReferral = widget.initialReferralCode.trim();
    if (inboundReferral.isNotEmpty && widget.repository.currentUser == null) {
      _referralController.text = inboundReferral;
      _register = true;
    }
  }

  @override
  void dispose() {
    _nameController.dispose();
    _usernameController.dispose();
    _loginController.dispose();
    _passwordController.dispose();
    _confirmPasswordController.dispose();
    _referralController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(22),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 460),
              child: Card(
                child: Padding(
                  padding: const EdgeInsets.all(22),
                  child: Form(
                    key: _formKey,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Row(
                          children: [
                            CircleAvatar(
                              radius: 24,
                              child: Icon(
                                Icons.candlestick_chart,
                                color: Theme.of(context).colorScheme.primary,
                              ),
                            ),
                            const SizedBox(width: 14),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    'HC Trader',
                                    style: Theme.of(context)
                                        .textTheme
                                        .headlineSmall
                                        ?.copyWith(fontWeight: FontWeight.w800),
                                  ),
                                  Text(
                                    context.l10n.text(
                                      'HC Gaming secure account',
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            PopupMenuButton<String>(
                              tooltip: context.l10n.text('Language'),
                              icon: const Icon(Icons.language),
                              initialValue: context.l10n.locale.languageCode,
                              onSelected: (value) => AppLocaleController.of(
                                context,
                              ).setLanguage(value),
                              itemBuilder: (context) => [
                                PopupMenuItem(
                                  value: 'en',
                                  child: Text(context.l10n.text('English')),
                                ),
                                PopupMenuItem(
                                  value: 'zh',
                                  child: Text(
                                    context.l10n.text('Simplified Chinese'),
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                        const SizedBox(height: 28),
                        SegmentedButton<bool>(
                          segments: [
                            ButtonSegment(
                              value: false,
                              icon: const Icon(Icons.login),
                              label: Text(context.l10n.text('Sign in')),
                            ),
                            ButtonSegment(
                              value: true,
                              icon: const Icon(Icons.person_add_alt_1),
                              label: Text(context.l10n.text('Register')),
                            ),
                          ],
                          selected: {_register},
                          onSelectionChanged: (selection) =>
                              setState(() => _register = selection.first),
                        ),
                        const SizedBox(height: 18),
                        if (_register) ...[
                          TextFormField(
                            controller: _nameController,
                            textInputAction: TextInputAction.next,
                            decoration: InputDecoration(
                              labelText: context.l10n.text('Name'),
                              prefixIcon: const Icon(Icons.badge_outlined),
                            ),
                            validator: (value) => (value ?? '').trim().isEmpty
                                ? context.l10n.text('Name is required')
                                : null,
                          ),
                          const SizedBox(height: 14),
                          TextFormField(
                            controller: _usernameController,
                            textInputAction: TextInputAction.next,
                            autocorrect: false,
                            decoration: InputDecoration(
                              labelText: context.l10n.text('Username'),
                              helperText: context.l10n.text(
                                'Lowercase letters, numbers, and underscores only',
                              ),
                              prefixIcon: const Icon(Icons.alternate_email),
                            ),
                            validator: (value) =>
                                RegExp(
                                  r'^[a-z0-9_]+$',
                                ).hasMatch((value ?? '').trim())
                                ? null
                                : context.l10n.text(
                                    'Enter a valid lowercase username',
                                  ),
                          ),
                          const SizedBox(height: 14),
                        ],
                        TextFormField(
                          controller: _loginController,
                          keyboardType: _register
                              ? TextInputType.emailAddress
                              : TextInputType.text,
                          textInputAction: TextInputAction.next,
                          decoration: InputDecoration(
                            labelText: _register
                                ? context.l10n.text('Email')
                                : context.l10n.text('Username or email'),
                            prefixIcon: const Icon(Icons.person_outline),
                          ),
                          validator: (value) {
                            final text = (value ?? '').trim();
                            if (text.isEmpty) {
                              return context.l10n.text(
                                'This field is required',
                              );
                            }
                            if (_register && !text.contains('@')) {
                              return context.l10n.text('Enter a valid email');
                            }
                            return null;
                          },
                        ),
                        const SizedBox(height: 14),
                        TextFormField(
                          controller: _passwordController,
                          obscureText: _obscurePassword,
                          textInputAction: _register
                              ? TextInputAction.next
                              : TextInputAction.done,
                          decoration: InputDecoration(
                            labelText: context.l10n.text('Password'),
                            helperText: _register
                                ? context.l10n.text(
                                    '8+ characters with uppercase, lowercase, and a number',
                                  )
                                : null,
                            prefixIcon: const Icon(Icons.lock_outline),
                            suffixIcon: IconButton(
                              tooltip: _obscurePassword
                                  ? context.l10n.text('Show password')
                                  : context.l10n.text('Hide password'),
                              onPressed: () => setState(
                                () => _obscurePassword = !_obscurePassword,
                              ),
                              icon: Icon(
                                _obscurePassword
                                    ? Icons.visibility_outlined
                                    : Icons.visibility_off_outlined,
                              ),
                            ),
                          ),
                          validator: (value) {
                            final text = value ?? '';
                            if (!_register) {
                              return text.isEmpty
                                  ? context.l10n.text('Password is required')
                                  : null;
                            }
                            return RegExp(
                                  r'^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$',
                                ).hasMatch(text)
                                ? null
                                : context.l10n.text(
                                    'Use 8+ characters with upper, lower, and number',
                                  );
                          },
                          onFieldSubmitted: (_) => _register ? null : _submit(),
                        ),
                        if (_register) ...[
                          const SizedBox(height: 14),
                          TextFormField(
                            controller: _confirmPasswordController,
                            obscureText: _obscureConfirmation,
                            textInputAction: TextInputAction.next,
                            decoration: InputDecoration(
                              labelText: context.l10n.text('Confirm password'),
                              prefixIcon: const Icon(Icons.lock_reset_outlined),
                              suffixIcon: IconButton(
                                tooltip: _obscureConfirmation
                                    ? context.l10n.text('Show confirmation')
                                    : context.l10n.text('Hide confirmation'),
                                onPressed: () => setState(
                                  () => _obscureConfirmation =
                                      !_obscureConfirmation,
                                ),
                                icon: Icon(
                                  _obscureConfirmation
                                      ? Icons.visibility_outlined
                                      : Icons.visibility_off_outlined,
                                ),
                              ),
                            ),
                            validator: (value) =>
                                value == _passwordController.text
                                ? null
                                : context.l10n.text('Passwords do not match'),
                          ),
                          const SizedBox(height: 14),
                          FutureBuilder<List<RegistrationRole>>(
                            future: _roles,
                            builder: (context, snapshot) {
                              if (snapshot.hasError) {
                                return Text(
                                  '${context.l10n.text('Unable to load roles')}: '
                                  '${snapshot.error}',
                                );
                              }
                              if (!snapshot.hasData) {
                                return const LinearProgressIndicator();
                              }
                              final roles = snapshot.data!;
                              _selectedRole ??= roles.firstOrNull;
                              return DropdownButtonFormField<RegistrationRole>(
                                initialValue: _selectedRole,
                                decoration: InputDecoration(
                                  labelText: context.l10n.text('Account type'),
                                  prefixIcon: const Icon(Icons.groups_outlined),
                                ),
                                items: roles
                                    .map(
                                      (role) => DropdownMenuItem(
                                        value: role,
                                        child: Text(
                                          AppRoles.localizedName(
                                            role.id,
                                            context.l10n.locale.languageCode,
                                          ),
                                        ),
                                      ),
                                    )
                                    .toList(),
                                onChanged: (role) => _selectedRole = role,
                                validator: (role) => role == null
                                    ? context.l10n.text(
                                        'Choose an account type',
                                      )
                                    : null,
                              );
                            },
                          ),
                          const SizedBox(height: 14),
                          if (widget.repository.currentUser == null) ...[
                            TextFormField(
                              controller: _referralController,
                              decoration: InputDecoration(
                                labelText: context.l10n.text(
                                  'Referral code (optional)',
                                ),
                                prefixIcon: const Icon(Icons.link),
                                helperText: widget.campaignId.trim().isEmpty
                                    ? null
                                    : context.l10n.text(
                                        'Campaign: {campaign}',
                                        {'campaign': widget.campaignId.trim()},
                                      ),
                              ),
                            ),
                          ],
                        ],
                        const SizedBox(height: 20),
                        FilledButton.icon(
                          onPressed: _loading ? null : _submit,
                          icon: _loading
                              ? const SizedBox.square(
                                  dimension: 18,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                  ),
                                )
                              : Icon(
                                  _register
                                      ? Icons.person_add_alt_1
                                      : Icons.login,
                                ),
                          label: Text(
                            context.l10n.text(
                              _register ? 'Create Account' : 'Sign In',
                            ),
                          ),
                        ),
                        if (!_register)
                          TextButton(
                            onPressed: _loading ? null : _forgotPassword,
                            child: Text(context.l10n.text('Forgot password?')),
                          ),
                        if (widget.onOpenPublicPlanner != null) ...[
                          const SizedBox(height: 10),
                          const Divider(),
                          const SizedBox(height: 10),
                          OutlinedButton.icon(
                            onPressed: _loading
                                ? null
                                : widget.onOpenPublicPlanner,
                            icon: const Icon(Icons.account_tree_outlined),
                            label: Text(
                              context.l10n.text(
                                'Use funded account planner as guest',
                              ),
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            context.l10n.text(
                              'Core planning works locally without sign-in. Sign in is required only for private app features.',
                            ),
                            textAlign: TextAlign.center,
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                        ],
                        if (widget.onOpenGuestAnalytics != null) ...[
                          const SizedBox(height: 10),
                          const Divider(),
                          const SizedBox(height: 10),
                          OutlinedButton.icon(
                            onPressed: _loading
                                ? null
                                : widget.onOpenGuestAnalytics,
                            icon: const Icon(Icons.analytics_outlined),
                            label: Text(
                              context.l10n.text(
                                'Explore account analytics as guest',
                              ),
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            context.l10n.text(
                              'Explore sample Manual, Excel, and MT5 analytics without sign-in. No private trading records are loaded.',
                            ),
                            textAlign: TextAlign.center,
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _loading = true);
    try {
      if (_register) {
        final selectedRole = _selectedRole;
        if (selectedRole == null) {
          throw AuthFailure(
            context.l10n.text('Choose an account type before registering.'),
          );
        }
        await widget.repository.register(
          name: _nameController.text,
          username: _usernameController.text,
          email: _loginController.text,
          password: _passwordController.text,
          roleId: selectedRole.id,
          referralCode: _referralController.text,
          campaignId: widget.campaignId,
          registrationSource: widget.registrationSource,
        );
      } else {
        await widget.repository.signIn(
          email: _loginController.text,
          password: _passwordController.text,
        );
      }
    } catch (error) {
      if (mounted) {
        AppFeedback.error(context, error);
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _forgotPassword() async {
    await showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (context) => ForgotPasswordDialog(
        initialEmail: _loginController.text.contains('@')
            ? _loginController.text
            : '',
        sendPasswordReset: widget.repository.sendPasswordReset,
      ),
    );
  }
}
