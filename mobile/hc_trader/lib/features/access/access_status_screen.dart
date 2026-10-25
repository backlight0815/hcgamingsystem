import 'package:flutter/material.dart';

import '../../core/localization/app_localizations.dart';
import '../../models/access_status.dart';
import '../../models/app_user.dart';
import '../../repositories/auth_repository.dart';
import '../../shared/app_feedback.dart';

class AccessStatusScreen extends StatefulWidget {
  const AccessStatusScreen({
    super.key,
    required this.user,
    required this.authRepository,
  });

  final AppUser user;
  final AuthRepository authRepository;

  @override
  State<AccessStatusScreen> createState() => _AccessStatusScreenState();
}

class _AccessStatusScreenState extends State<AccessStatusScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _email;
  late final TextEditingController _phone;
  var _submitting = false;
  var _withdrawing = false;

  @override
  void initState() {
    super.initState();
    _name = TextEditingController(text: widget.user.displayName ?? '');
    _email = TextEditingController(text: widget.user.email);
    _phone = TextEditingController(text: widget.user.phoneNumber);
  }

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _phone.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final status = widget.user.accessStatus;
    return Scaffold(
      appBar: AppBar(title: Text(context.l10n.text('Client access'))),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(_icon(status), size: 42),
                  const SizedBox(height: 12),
                  Text(
                    context.l10n.text(_title(status)),
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(context.l10n.text(_message(status))),
                ],
              ),
            ),
          ),
          if (status != AccessStatus.notSubmitted) ...[
            const SizedBox(height: 14),
            _AccessTimeline(status: status),
          ],
          if (status.canWithdraw) ...[
            const SizedBox(height: 14),
            OutlinedButton.icon(
              key: const ValueKey('withdraw-access-application'),
              onPressed: _withdrawing ? null : _confirmWithdraw,
              icon: _withdrawing
                  ? const SizedBox.square(
                      dimension: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.undo_outlined),
              label: Text(context.l10n.text('Withdraw application')),
            ),
          ],
          if (status.canReapply) ...[
            const SizedBox(height: 14),
            _AccessRequestForm(
              formKey: _formKey,
              name: _name,
              email: _email,
              phone: _phone,
              submitting: _submitting,
              onSubmit: _submit,
            ),
          ],
        ],
      ),
    );
  }

  IconData _icon(AccessStatus status) {
    return switch (status) {
      AccessStatus.approved => Icons.verified_user_outlined,
      AccessStatus.pendingReview => Icons.hourglass_top_outlined,
      AccessStatus.rejected => Icons.block_outlined,
      AccessStatus.moreInfoRequired => Icons.contact_support_outlined,
      AccessStatus.withdrawn => Icons.undo_outlined,
      AccessStatus.cancelled => Icons.cancel_outlined,
      AccessStatus.public ||
      AccessStatus.unapproved ||
      AccessStatus.notSubmitted => Icons.assignment_ind_outlined,
      AccessStatus.suspended => Icons.pause_circle_outline,
      AccessStatus.revoked || AccessStatus.deleted => Icons.gpp_bad_outlined,
    };
  }

  String _title(AccessStatus status) {
    return switch (status) {
      AccessStatus.approved => 'Access approved',
      AccessStatus.pendingReview => 'Access request pending',
      AccessStatus.rejected => 'Access request not approved',
      AccessStatus.moreInfoRequired => 'More information required',
      AccessStatus.withdrawn => 'Access application withdrawn',
      AccessStatus.cancelled => 'Access application cancelled',
      AccessStatus.public ||
      AccessStatus.unapproved ||
      AccessStatus.notSubmitted => 'Apply for client access',
      AccessStatus.suspended => 'Access suspended',
      AccessStatus.revoked => 'Access revoked',
      AccessStatus.deleted => 'Account deleted',
    };
  }

  String _message(AccessStatus status) {
    return switch (status) {
      AccessStatus.approved =>
        'Your client access is approved. Full app features are available based on your role.',
      AccessStatus.pendingReview =>
        'Your access request is pending admin review.',
      AccessStatus.rejected =>
        'Your access request was not approved. You may submit your contact details again for a new review.',
      AccessStatus.moreInfoRequired =>
        'An administrator requested more information. Review the note and submit an updated application.',
      AccessStatus.withdrawn =>
        'Your access application was withdrawn. You may submit a new application when you are ready.',
      AccessStatus.cancelled =>
        'Your access application was cancelled. You may submit a new application when eligible.',
      AccessStatus.public ||
      AccessStatus.unapproved ||
      AccessStatus.notSubmitted =>
        'Public personal tools are available now. Apply for client access to unlock approved-client features.',
      AccessStatus.suspended =>
        'Your restricted features are suspended. Contact support or view your application status.',
      AccessStatus.revoked =>
        'Your client access was revoked. Public account actions may be limited.',
      AccessStatus.deleted => 'This account is no longer active.',
    };
  }

  Future<void> _confirmWithdraw() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(context.l10n.text('Withdraw access application?')),
        content: Text(
          context.l10n.text(
            'Your pending application will remain in your history and can no longer be approved.',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(context.l10n.text('Keep application')),
          ),
          FilledButton.tonal(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(context.l10n.text('Withdraw application')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() => _withdrawing = true);
    try {
      await widget.authRepository.withdrawAccessApplication();
      if (mounted) {
        AppFeedback.success(
          context,
          'Your access application was withdrawn successfully.',
        );
      }
    } catch (error) {
      if (mounted) AppFeedback.error(context, error);
    } finally {
      if (mounted) setState(() => _withdrawing = false);
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _submitting = true);
    try {
      await widget.authRepository.submitAccessRequest(
        name: _name.text,
        email: _email.text,
        phoneNumber: _phone.text,
      );
      if (mounted) {
        AppFeedback.success(context, 'Access request submitted.');
      }
    } catch (error) {
      if (mounted) AppFeedback.error(context, error.toString());
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }
}

class _AccessTimeline extends StatelessWidget {
  const _AccessTimeline({required this.status});

  final AccessStatus status;

  @override
  Widget build(BuildContext context) {
    final stages = <(String, bool)>[
      ('Application submitted', true),
      ('Admin review', status != AccessStatus.pendingReview),
      if (status != AccessStatus.pendingReview)
        (_accessTimelineResult(status), true),
    ];
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              context.l10n.text('Application timeline'),
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 12),
            for (final stage in stages.indexed)
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Column(
                    children: [
                      Icon(
                        stage.$2.$2
                            ? Icons.check_circle
                            : Icons.radio_button_unchecked,
                        size: 20,
                      ),
                      if (stage.$1 < stages.length - 1)
                        Container(
                          width: 2,
                          height: 24,
                          color: Theme.of(context).colorScheme.outlineVariant,
                        ),
                    ],
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: Text(context.l10n.text(stage.$2.$1)),
                    ),
                  ),
                ],
              ),
          ],
        ),
      ),
    );
  }
}

String _accessTimelineResult(AccessStatus status) => switch (status) {
  AccessStatus.approved => 'Approved',
  AccessStatus.rejected => 'Rejected',
  AccessStatus.moreInfoRequired => 'More information required',
  AccessStatus.withdrawn => 'Withdrawn',
  AccessStatus.cancelled => 'Cancelled',
  _ => 'Pending Review',
};

class _AccessRequestForm extends StatelessWidget {
  const _AccessRequestForm({
    required this.formKey,
    required this.name,
    required this.email,
    required this.phone,
    required this.submitting,
    required this.onSubmit,
  });

  final GlobalKey<FormState> formKey;
  final TextEditingController name;
  final TextEditingController email;
  final TextEditingController phone;
  final bool submitting;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                context.l10n.text('Access application'),
                style: Theme.of(
                  context,
                ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: name,
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
                controller: email,
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
                controller: phone,
                keyboardType: TextInputType.phone,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Phone number'),
                  prefixIcon: const Icon(Icons.phone_outlined),
                ),
                validator: (value) => (value ?? '').trim().isEmpty
                    ? context.l10n.text('Phone number is required')
                    : null,
              ),
              const SizedBox(height: 16),
              FilledButton.icon(
                onPressed: submitting ? null : onSubmit,
                icon: submitting
                    ? const SizedBox.square(
                        dimension: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.send_outlined),
                label: Text(context.l10n.text('Submit access request')),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
