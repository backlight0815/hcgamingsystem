import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/localization/app_localizations.dart';
import '../../repositories/auth_repository.dart';

class ForgotPasswordDialog extends StatefulWidget {
  const ForgotPasswordDialog({
    super.key,
    required this.sendPasswordReset,
    this.initialEmail = '',
    this.cooldown = const Duration(seconds: 45),
  });

  final Future<void> Function(String email) sendPasswordReset;
  final String initialEmail;
  final Duration cooldown;

  @override
  State<ForgotPasswordDialog> createState() => _ForgotPasswordDialogState();
}

class _ForgotPasswordDialogState extends State<ForgotPasswordDialog> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _emailController;
  Timer? _cooldownTimer;
  bool _sending = false;
  bool _sent = false;
  int _cooldownSeconds = 0;
  PasswordResetFailureKind? _failureKind;

  @override
  void initState() {
    super.initState();
    _emailController = TextEditingController(text: widget.initialEmail.trim());
  }

  @override
  void dispose() {
    _cooldownTimer?.cancel();
    _emailController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 500),
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: _sent ? _buildSuccess(context) : _buildForm(context),
        ),
      ),
    );
  }

  Widget _buildForm(BuildContext context) {
    return Form(
      key: _formKey,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Icon(Icons.lock_reset, size: 48),
          const SizedBox(height: 16),
          Text(
            context.l10n.text('Reset password'),
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          const SizedBox(height: 8),
          Text(
            context.l10n.text(
              'Enter your account email and we will request a secure reset link from Firebase.',
            ),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 20),
          TextFormField(
            key: const Key('password-reset-email'),
            controller: _emailController,
            enabled: !_sending,
            autofocus: true,
            keyboardType: TextInputType.emailAddress,
            textInputAction: TextInputAction.done,
            autofillHints: const [AutofillHints.email],
            autocorrect: false,
            maxLength: 254,
            decoration: InputDecoration(
              labelText: context.l10n.text('Email'),
              prefixIcon: const Icon(Icons.email_outlined),
              counterText: '',
            ),
            onFieldSubmitted: (_) => _submit(),
            onChanged: (_) {
              if (_failureKind != null) {
                setState(() => _failureKind = null);
              }
            },
            validator: (value) {
              return switch (validatePasswordResetEmail(value ?? '')) {
                PasswordResetEmailValidation.empty => context.l10n.text(
                  'Enter your email address.',
                ),
                PasswordResetEmailValidation.invalid => context.l10n.text(
                  'Enter a valid email address.',
                ),
                null => null,
              };
            },
          ),
          if (_failureKind case final failure?) ...[
            const SizedBox(height: 12),
            _ErrorMessage(message: _messageForFailure(context, failure)),
          ],
          const SizedBox(height: 20),
          Row(
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              TextButton(
                onPressed: _sending ? null : () => Navigator.pop(context),
                child: Text(context.l10n.text('Cancel')),
              ),
              const SizedBox(width: 8),
              FilledButton.icon(
                key: const Key('password-reset-submit'),
                onPressed: _sending ? null : _submit,
                icon: _sending
                    ? const SizedBox.square(
                        dimension: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.send_outlined),
                label: Text(
                  context.l10n.text(_sending ? 'Sending…' : 'Send reset link'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildSuccess(BuildContext context) {
    final canResend = !_sending && _cooldownSeconds == 0;
    final resendLabel = _sending
        ? 'Sending…'
        : _cooldownSeconds > 0
        ? context.l10n.text('Resend in {seconds}s', {
            'seconds': _cooldownSeconds,
          })
        : context.l10n.text('Resend email');
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Icon(Icons.mark_email_read_outlined, size: 52),
        const SizedBox(height: 16),
        Text(
          context.l10n.text('Check your inbox'),
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.headlineSmall,
        ),
        const SizedBox(height: 12),
        Text(
          context.l10n.text(
            'If an account exists for this email, a password reset link will be sent shortly.',
          ),
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 8),
        Text(
          context.l10n.text(
            'Check your spam or junk folder and allow a few minutes for delivery.',
          ),
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.bodySmall,
        ),
        if (_failureKind case final failure?) ...[
          const SizedBox(height: 12),
          _ErrorMessage(message: _messageForFailure(context, failure)),
        ],
        const SizedBox(height: 24),
        OutlinedButton.icon(
          key: const Key('password-reset-resend'),
          onPressed: canResend ? _submit : null,
          icon: const Icon(Icons.refresh),
          label: Text(resendLabel),
        ),
        const SizedBox(height: 8),
        FilledButton(
          onPressed: _sending ? null : () => Navigator.pop(context),
          child: Text(context.l10n.text('Back to sign in')),
        ),
      ],
    );
  }

  Future<void> _submit() async {
    if (_sending || (_sent && _cooldownSeconds > 0)) return;
    if (!_sent && !(_formKey.currentState?.validate() ?? false)) return;

    final email = normalizePasswordResetEmail(_emailController.text);
    setState(() {
      _sending = true;
      _failureKind = null;
    });
    try {
      await widget.sendPasswordReset(email);
      if (!mounted) return;
      setState(() {
        _sent = true;
        _sending = false;
      });
      _startCooldown();
    } on PasswordResetFailure catch (error) {
      if (!mounted) return;
      setState(() {
        _sending = false;
        _failureKind = error.kind;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _sending = false;
        _failureKind = PasswordResetFailureKind.unknown;
      });
    }
  }

  void _startCooldown() {
    _cooldownTimer?.cancel();
    final seconds = widget.cooldown.inSeconds;
    if (seconds <= 0) {
      if (mounted) setState(() => _cooldownSeconds = 0);
      return;
    }
    setState(() => _cooldownSeconds = seconds);
    _cooldownTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) {
        timer.cancel();
        return;
      }
      if (_cooldownSeconds <= 1) {
        timer.cancel();
        setState(() => _cooldownSeconds = 0);
      } else {
        setState(() => _cooldownSeconds--);
      }
    });
  }

  String _messageForFailure(
    BuildContext context,
    PasswordResetFailureKind kind,
  ) {
    final english = switch (kind) {
      PasswordResetFailureKind.invalidEmail => 'Enter a valid email address.',
      PasswordResetFailureKind.rateLimited =>
        'Too many reset requests. Wait a few minutes and try again.',
      PasswordResetFailureKind.network =>
        'Unable to reach Firebase. Check your connection and try again.',
      PasswordResetFailureKind.configuration =>
        'Password reset is not configured correctly. Please contact support.',
      PasswordResetFailureKind.unknown =>
        'Unable to request a reset link. Please try again.',
    };
    return context.l10n.text(english);
  }
}

class _ErrorMessage extends StatelessWidget {
  const _ErrorMessage({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      liveRegion: true,
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.errorContainer,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Text(
          message,
          style: TextStyle(
            color: Theme.of(context).colorScheme.onErrorContainer,
          ),
        ),
      ),
    );
  }
}
