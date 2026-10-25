import 'package:file_picker/file_picker.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:printing/printing.dart';

import '../../core/localization/app_localizations.dart';
import '../../models/trading_report.dart';
import '../../services/trading_report_pdf_service.dart';
import '../../shared/analytics/analytics_widgets.dart';

class TradingReportPreviewScreen extends StatefulWidget {
  const TradingReportPreviewScreen({
    super.key,
    required this.report,
    required this.bytes,
    required this.temporaryPath,
    required this.pdfService,
  });

  final TradingReport report;
  final Uint8List bytes;
  final String temporaryPath;
  final TradingReportPdfService pdfService;

  @override
  State<TradingReportPreviewScreen> createState() =>
      _TradingReportPreviewScreenState();
}

class _TradingReportPreviewScreenState
    extends State<TradingReportPreviewScreen> {
  var _saving = false;
  var _sharing = false;

  @override
  void dispose() {
    widget.pdfService.deleteTemporaryFile(widget.temporaryPath);
    super.dispose();
  }

  Future<void> _save() async {
    if (_saving) return;
    setState(() => _saving = true);
    try {
      final path = await FilePicker.platform.saveFile(
        dialogTitle: context.l10n.text('Save PDF report'),
        fileName: widget.pdfService.filename(report: widget.report),
        type: FileType.custom,
        allowedExtensions: const ['pdf'],
        bytes: widget.bytes,
        lockParentWindow: true,
      );
      if (!mounted || path == null) return;
      _show(context.l10n.text('The PDF was saved successfully.'));
    } catch (error) {
      if (mounted) {
        _show(context.l10n.text('Unable to save the PDF report.'));
      }
      if (kDebugMode) {
        debugPrint('[Trading Report] saveError=$error');
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _share() async {
    if (_sharing) return;
    setState(() => _sharing = true);
    try {
      await widget.pdfService.share(report: widget.report, bytes: widget.bytes);
    } catch (error) {
      if (mounted) {
        _show(context.l10n.text('Unable to share the PDF report.'));
      }
      if (kDebugMode) {
        debugPrint('[Trading Report] shareError=$error');
      }
    } finally {
      if (mounted) setState(() => _sharing = false);
    }
  }

  void _show(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(context.l10n.text('PDF Report Preview')),
        actions: [
          IconButton(
            tooltip: context.l10n.text('Save'),
            onPressed: _saving ? null : _save,
            icon: _saving
                ? const SizedBox.square(
                    dimension: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.download_outlined),
          ),
          IconButton(
            tooltip: context.l10n.text('Share'),
            onPressed: _sharing ? null : _share,
            icon: _sharing
                ? const SizedBox.square(
                    dimension: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.share_outlined),
          ),
        ],
      ),
      body: PdfPreview(
        build: (_) async => widget.bytes,
        pdfFileName: widget.pdfService.filename(report: widget.report),
        canChangePageFormat: false,
        canChangeOrientation: false,
        allowPrinting: false,
        allowSharing: false,
        useActions: false,
        loadingWidget: const AnalyticsSkeleton(),
      ),
    );
  }
}
