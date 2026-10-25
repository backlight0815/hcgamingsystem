import 'package:flutter/material.dart';

import '../../../core/localization/app_localizations.dart';
import '../../../repositories/trading_repository.dart';
import '../../../shared/app_feedback.dart';
import 'admin_performance_models.dart';

Future<bool> showAdminPerformanceReviewDialog(
  BuildContext context, {
  required TradingRepository repository,
  required AdminTraderPerformance record,
}) async =>
    await showDialog<bool>(
      context: context,
      builder: (context) =>
          _AdminPerformanceReviewDialog(repository: repository, record: record),
    ) ??
    false;

class _AdminPerformanceReviewDialog extends StatefulWidget {
  const _AdminPerformanceReviewDialog({
    required this.repository,
    required this.record,
  });

  final TradingRepository repository;
  final AdminTraderPerformance record;

  @override
  State<_AdminPerformanceReviewDialog> createState() =>
      _AdminPerformanceReviewDialogState();
}

class _AdminPerformanceReviewDialogState
    extends State<_AdminPerformanceReviewDialog> {
  final _note = TextEditingController();
  String _decision = 'approve_phase2';
  bool _saving = false;

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text(
      context.l10n.text('Review {trader}', {'trader': widget.record.name}),
    ),
    content: ConstrainedBox(
      constraints: const BoxConstraints(maxWidth: 520),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              context.l10n.text(
                'This action updates the trader Prop Firm review state and creates an audit record.',
              ),
            ),
            const SizedBox(height: 16),
            DropdownButtonFormField<String>(
              initialValue: _decision,
              decoration: InputDecoration(
                labelText: context.l10n.text('Decision'),
              ),
              items: [
                for (final option in const [
                  ('approve_phase2', 'Approve Phase 2'),
                  ('approve_funded', 'Approve funded account'),
                  ('allow_risk', 'Allow after risk review'),
                  ('suspend', 'Suspend'),
                  ('correction_required', 'Request correction'),
                  ('reject', 'Reject'),
                ])
                  DropdownMenuItem(
                    value: option.$1,
                    child: Text(context.l10n.text(option.$2)),
                  ),
              ],
              onChanged: _saving
                  ? null
                  : (value) => setState(() => _decision = value ?? _decision),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _note,
              enabled: !_saving,
              maxLines: 4,
              decoration: InputDecoration(
                labelText: context.l10n.text('Internal review note'),
                helperText: context.l10n.text(
                  'A note is required for rejection or correction.',
                ),
              ),
            ),
          ],
        ),
      ),
    ),
    actions: [
      TextButton(
        onPressed: _saving ? null : () => Navigator.pop(context, false),
        child: Text(context.l10n.text('Cancel')),
      ),
      FilledButton.icon(
        onPressed: _saving ? null : _save,
        icon: _saving
            ? const SizedBox.square(
                dimension: 18,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            : const Icon(Icons.fact_check_outlined),
        label: Text(context.l10n.text(_saving ? 'Saving...' : 'Confirm')),
      ),
    ],
  );

  Future<void> _save() async {
    if (const {'reject', 'correction_required'}.contains(_decision) &&
        _note.text.trim().isEmpty) {
      AppFeedback.error(
        context,
        'Enter a note explaining the rejection or required correction.',
      );
      return;
    }
    setState(() => _saving = true);
    try {
      await widget.repository.adminReviewPropFirm(
        widget.record.id,
        decision: _decision,
        note: _note.text.trim(),
      );
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (!mounted) return;
      setState(() => _saving = false);
      AppFeedback.error(context, '$error');
    }
  }
}
