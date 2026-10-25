import 'dart:io';
import 'dart:math' as math;

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter/widgets.dart';
import 'package:intl/intl.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';

import '../core/localization/app_localizations.dart';
import '../models/trading_data_source.dart';
import '../models/trading_date_filter.dart';
import '../models/trading_report.dart';
import 'trading_report_pdf_theme.dart';

class TradingReportPdfService {
  final _money = NumberFormat('#,##0.00');
  final _date = DateFormat('d MMM yyyy');
  final _dateTime = DateFormat('yyyy-MM-dd HH:mm');
  final _month = DateFormat('MMM yyyy');

  Future<Uint8List> build({
    required TradingReport report,
    String? traderLabel,
    List<String> accountDetails = const [],
  }) async {
    final fontData = await rootBundle.load('assets/fonts/NotoSansSC-VF.ttf');
    final font = pw.Font.ttf(fontData);
    final theme = pw.ThemeData.withFont(base: font, bold: font, italic: font);
    final document = pw.Document(
      title: 'Advanced Trading Journal Report',
      author: 'HC Gaming Studio',
      subject: 'Account-scoped trading journal analysis',
      creator: 'HC Trader Flutter',
    );

    document.addPage(
      pw.MultiPage(
        pageFormat: PdfPageFormat.a4.landscape,
        margin: const pw.EdgeInsets.fromLTRB(
          TradingReportPdfTheme.pageMargin,
          20,
          TradingReportPdfTheme.pageMargin,
          22,
        ),
        theme: theme,
        header: (context) =>
            context.pageNumber == 1 ? pw.SizedBox() : _runningHeader(report),
        footer: (context) => _footer(context, report),
        build: (context) => [
          ..._executivePage(report, traderLabel ?? report.traderLabel),
          pw.NewPage(),
          ..._levelsAndMetricsPage(report),
          pw.NewPage(),
          ..._behaviourAndScorePage(report),
          pw.NewPage(),
          ..._styleIntelligencePage(report),
          pw.NewPage(),
          ..._checksAndRevengePage(report),
          pw.NewPage(),
          ..._gamblingAndLayeringPage(report),
          pw.NewPage(),
          ..._rulesAndMarketPage(report),
          pw.NewPage(),
          ..._timeAndEquityPage(report),
          pw.NewPage(),
          ..._recentTradesPage(report),
        ],
      ),
    );

    final bytes = await document.save();
    if (kDebugMode) {
      debugPrint(
        '[Trading Report] pdfBytes=${bytes.length} source=${report.filter.source.apiValue} '
        'period=${_periodLabel(report)} stage=${report.filter.stage} '
        'mainMt5AccountId=${report.filter.mainMt5AccountId}',
      );
    }
    return bytes;
  }

  Future<void> share({
    required TradingReport report,
    String? traderLabel,
    List<String> accountDetails = const [],
    Uint8List? bytes,
  }) async {
    final pdf =
        bytes ??
        await build(
          report: report,
          traderLabel: traderLabel,
          accountDetails: accountDetails,
        );
    await Printing.sharePdf(
      bytes: pdf,
      filename: filename(report: report, traderLabel: traderLabel),
    );
  }

  Future<File> writeTemporaryFile({
    required TradingReport report,
    required Uint8List bytes,
    required String currentUid,
  }) async {
    final directory = Directory(
      '${Directory.systemTemp.path}${Platform.pathSeparator}hc-trader-report-cache',
    );
    await directory.create(recursive: true);
    final now = DateTime.now();
    await for (final entity in directory.list()) {
      if (entity is! File || !entity.path.toLowerCase().endsWith('.pdf')) {
        continue;
      }
      try {
        final modified = await entity.lastModified();
        if (now.difference(modified) > const Duration(hours: 24)) {
          await entity.delete();
        }
      } catch (_) {
        // A stale file held by another process is harmless and will be retried.
      }
    }
    final cacheKey = _stableHash(
      '$currentUid|${report.filter.mainMt5AccountId}|${report.filter.source.apiValue}|'
      '${report.filter.stage}|${report.filter.effectiveDateFilter.cacheKey}|'
      '${report.filter.timezone}|${report.filter.languageCode}',
    );
    final path =
        '${directory.path}${Platform.pathSeparator}report-$cacheKey-${now.millisecondsSinceEpoch}.pdf';
    final file = File(path);
    await file.writeAsBytes(bytes, flush: true);
    if (kDebugMode) {
      debugPrint('[Trading Report] generatedPdfPath=${file.path}');
    }
    return file;
  }

  Future<void> deleteTemporaryFile(String path) async {
    if (path.trim().isEmpty) return;
    try {
      final file = File(path);
      if (await file.exists()) await file.delete();
    } catch (_) {
      // Cache cleanup is best effort and must not hide a successful report.
    }
  }

  String filename({required TradingReport report, String? traderLabel}) {
    final account = report.account;
    final subject = report.filter.source == TradingReportSource.manual
        ? 'manual'
        : '${account?.displayLabel ?? 'main-mt5'}-${account?.login ?? ''}';
    final safeSubject = _safeFilenamePart(subject);
    final stage = _safeFilenamePart(report.filter.stage);
    final activeFilter = report.filter.effectiveDateFilter;
    final period = switch (activeFilter.type) {
      TradingDateFilterType.allTime => 'all-time',
      TradingDateFilterType.specificDate => DateFormat(
        'yyyyMMdd',
      ).format(activeFilter.startDate!),
      TradingDateFilterType.dateRange =>
        '${DateFormat('yyyyMMdd').format(activeFilter.startDate!)}-'
            '${DateFormat('yyyyMMdd').format(activeFilter.endDate!)}',
      TradingDateFilterType.month =>
        '${activeFilter.year}-${activeFilter.month.toString().padLeft(2, '0')}',
      TradingDateFilterType.year => '${activeFilter.year}',
    };
    final stamp = DateFormat('yyyyMMdd-HHmmss').format(report.generatedAt);
    return 'trading-journal-report-$safeSubject-$stage-$period-$stamp.pdf';
  }

  List<pw.Widget> _executivePage(TradingReport report, String traderLabel) {
    final score = report.scoreProfile;
    final summary = report.summary;
    final level = report.levelProfile;
    return [
      _heroHeader(report, traderLabel),
      pw.SizedBox(height: 10),
      pw.Row(
        crossAxisAlignment: pw.CrossAxisAlignment.start,
        children: [
          _executiveCard(
            report,
            'Trading Score',
            score.score.toStringAsFixed(2),
            '${score.grade} / 100 MAX',
            'Behaviour penalty -${score.behaviourPenaltyPoints.toStringAsFixed(2)} pts',
            TradingReportPdfTheme.teal,
          ),
          _executiveCard(
            report,
            'Performance Status',
            report.performanceStatus,
            _scoreMeaning(report),
            'L${level.levelNumber} ${level.label}',
            _statusColor(report.performanceStatus),
          ),
          _executiveCard(
            report,
            'Trader Style',
            report.traderStyle,
            'Behaviour ${report.behaviourProfile.finalScore.toStringAsFixed(2)}/100',
            'Confidence ${score.confidencePercent.toStringAsFixed(2)}/100',
            TradingReportPdfTheme.navy,
          ),
          _executiveCard(
            report,
            'Net P/L',
            _moneyText(summary.netProfitLoss),
            'Growth ${_percent(summary.growthPercent)}',
            '${summary.totalTrades} closed trade(s)',
            _valueColor(summary.netProfitLoss),
          ),
        ],
      ),
      pw.SizedBox(height: 9),
      _insightBox(report, 'Executive Insight', _executiveInsight(report)),
      pw.SizedBox(height: 7),
      _insightBox(report, 'Trader Resume Summary', _resumeSummary(report)),
      pw.SizedBox(height: 9),
      pw.Row(
        crossAxisAlignment: pw.CrossAxisAlignment.start,
        children: [
          _miniProfile(
            report,
            'Trader Level',
            'L${level.levelNumber}',
            level.label,
          ),
          _miniProfile(
            report,
            'Technical Skill',
            report.technicalSkill,
            'Score ${score.score.toStringAsFixed(2)}/100',
          ),
          _miniProfile(
            report,
            'Risk Profile',
            report.riskProfile,
            'Behaviour ${report.behaviourProfile.finalScore.toStringAsFixed(2)}/100',
          ),
          _miniProfile(
            report,
            'Administration Action',
            report.administrationAction,
            _t(
              report,
              'Generated from score, behaviour, risk, consistency, timing, and profitability.',
            ),
          ),
        ],
      ),
      pw.SizedBox(height: 9),
      _sectionTitle(report, 'Trader Level Profile'),
      _profileGrid(report),
    ];
  }

  List<pw.Widget> _levelsAndMetricsPage(TradingReport report) => [
    _pageTitle(report, 'Levels, Strengths and Core Metrics'),
    if (report.levelProfile.capActive)
      _insightBox(report, 'Active Cap', report.levelProfile.capReason),
    pw.SizedBox(height: 8),
    _levelTable(report),
    pw.SizedBox(height: 9),
    pw.Row(
      crossAxisAlignment: pw.CrossAxisAlignment.start,
      children: [
        _listCard(
          report,
          'Strengths',
          report.strengths,
          TradingReportPdfTheme.paleGreen,
        ),
        _listCard(report, 'Risks', report.risks, TradingReportPdfTheme.paleRed),
        _listCard(
          report,
          'Coaching Focus',
          report.coachingFocus,
          TradingReportPdfTheme.paleAmber,
        ),
      ],
    ),
    pw.SizedBox(height: 9),
    _sectionTitle(report, 'Core Metrics'),
    _coreMetrics(report),
  ];

  List<pw.Widget> _behaviourAndScorePage(TradingReport report) {
    final b = report.behaviourProfile;
    return [
      _pageTitle(report, 'Behaviour Summary and Score Matrix'),
      pw.Wrap(
        spacing: 7,
        runSpacing: 7,
        children: [
          _metricCard(
            report,
            'Revenge Trading',
            b.revenge.status,
            '${b.revenge.tier} tier | ${b.revenge.score.toStringAsFixed(2)}/100',
            width: 181,
          ),
          _metricCard(
            report,
            'Layering',
            b.layering.status,
            '${b.layering.score.toStringAsFixed(2)}/100',
            width: 181,
          ),
          _metricCard(
            report,
            'Gambling Behaviour',
            b.gambling.status,
            '${b.gambling.tier} tier',
            width: 181,
          ),
          _metricCard(
            report,
            'Behaviour Penalty',
            '-${report.scoreProfile.behaviourPenaltyPoints.toStringAsFixed(2)} pts',
            '${report.scoreProfile.behaviourPenaltyPercent.toStringAsFixed(0)}%',
            width: 181,
          ),
          _metricCard(
            report,
            'Layered Trades',
            _percent(b.layeredTradePercent),
            '${b.layeredTradeCount} affected',
            width: 181,
          ),
          _metricCard(
            report,
            'Maximum Layers',
            '${b.maximumLayers}',
            'Same pair/direction',
            width: 181,
          ),
          _metricCard(
            report,
            'Weekly Revenge',
            b.revenge.score.toStringAsFixed(2),
            'Selected period',
            width: 181,
          ),
          _metricCard(
            report,
            'Weekly Gambling',
            b.gambling.score.toStringAsFixed(2),
            'Selected period',
            width: 181,
          ),
        ],
      ),
      pw.SizedBox(height: 9),
      _insightBox(
        report,
        '8-Trade Behaviour Recovery',
        b.recoveryBlocks > 0
            ? _t(
                report,
                'Behaviour recovery applied across {blocks} completed 8-trade block(s). Raw behaviour score {raw}/100 was reduced by {percent}% to {final}/100.',
                {
                  'blocks': b.recoveryBlocks,
                  'raw': b.rawScore.toStringAsFixed(2),
                  'percent': b.recoveryPercent.toStringAsFixed(2),
                  'final': b.finalScore.toStringAsFixed(2),
                },
              )
            : _t(
                report,
                'No completed improvement block qualified for behaviour recovery. Raw and final score remain {score}/100.',
                {'score': b.finalScore.toStringAsFixed(2)},
              ),
      ),
      pw.SizedBox(height: 9),
      _sectionTitle(report, 'Score Matrix'),
      _scoreTable(report),
    ];
  }

  List<pw.Widget> _styleIntelligencePage(TradingReport report) => [
    _pageTitle(report, 'Trader Style Intelligence'),
    pw.Row(
      crossAxisAlignment: pw.CrossAxisAlignment.start,
      children: [
        _behaviourDetailCard(
          report,
          'Revenge Trading Detection',
          report.behaviourProfile.revenge,
        ),
        pw.SizedBox(width: 9),
        _behaviourDetailCard(
          report,
          'Gambling-style Risk Detection',
          report.behaviourProfile.gambling,
        ),
      ],
    ),
    pw.SizedBox(height: 9),
    _behaviourDetailCard(
      report,
      'Layering Detection',
      report.behaviourProfile.layering,
      fullWidth: true,
    ),
    pw.SizedBox(height: 9),
    _sectionTitle(report, 'Behaviour Check'),
    _behaviourChecksTable(
      report,
      report.behaviourProfile.checks.take(8).toList(),
    ),
  ];

  List<pw.Widget> _checksAndRevengePage(TradingReport report) => [
    _pageTitle(report, 'Behaviour Checks and Revenge Evidence'),
    _behaviourChecksTable(
      report,
      report.behaviourProfile.checks.skip(8).toList(),
    ),
    pw.SizedBox(height: 10),
    _sectionTitle(report, 'Revenge Tier Evidence'),
    _evidenceTable(
      report,
      report.behaviourProfile.revenge.examples,
      kind: 'revenge',
    ),
  ];

  List<pw.Widget> _gamblingAndLayeringPage(TradingReport report) => [
    _pageTitle(report, 'Gambling and Layering Evidence'),
    _sectionTitle(report, 'Gambling Tier Evidence'),
    _evidenceTable(
      report,
      report.behaviourProfile.gambling.examples,
      kind: 'gambling',
    ),
    pw.SizedBox(height: 10),
    _sectionTitle(report, 'Layering Evidence'),
    _evidenceTable(
      report,
      report.behaviourProfile.layering.examples,
      kind: 'layering',
    ),
  ];

  List<pw.Widget> _rulesAndMarketPage(TradingReport report) => [
    _pageTitle(report, 'Rule Monitor and Market Profile'),
    _sectionTitle(report, 'Rule Monitor'),
    _ruleMonitor(report),
    pw.SizedBox(height: 9),
    _sectionTitle(report, 'Position Consistency'),
    _positionGrid(report),
    pw.SizedBox(height: 9),
    pw.Row(
      crossAxisAlignment: pw.CrossAxisAlignment.start,
      children: [
        pw.Expanded(
          child: pw.Column(
            crossAxisAlignment: pw.CrossAxisAlignment.start,
            children: [
              _sectionTitle(report, 'Top Pairs'),
              _breakdownTable(report, report.pairStats.take(8).toList()),
            ],
          ),
        ),
        pw.SizedBox(width: 10),
        pw.Expanded(
          child: pw.Column(
            crossAxisAlignment: pw.CrossAxisAlignment.start,
            children: [
              _sectionTitle(report, 'Sessions and Bias'),
              _breakdownTable(report, report.sessionStats),
              pw.SizedBox(height: 6),
              _breakdownTable(report, report.directionStats),
            ],
          ),
        ),
      ],
    ),
    pw.SizedBox(height: 5),
    pw.Text(
      _t(report, 'Session times use {timezone}.', {
        'timezone': report.filter.timezone,
      }),
      style: pw.TextStyle(fontSize: 7, color: TradingReportPdfTheme.muted),
    ),
  ];

  List<pw.Widget> _timeAndEquityPage(TradingReport report) => [
    _pageTitle(report, 'Time Profile and Equity Evidence'),
    pw.Row(
      crossAxisAlignment: pw.CrossAxisAlignment.start,
      children: [
        pw.Expanded(
          child: pw.Column(
            crossAxisAlignment: pw.CrossAxisAlignment.start,
            children: [
              _sectionTitle(report, 'Weekday Profile'),
              _breakdownTable(report, report.weekdayStats),
            ],
          ),
        ),
        pw.SizedBox(width: 10),
        pw.Expanded(
          child: pw.Column(
            crossAxisAlignment: pw.CrossAxisAlignment.start,
            children: [
              _sectionTitle(report, 'Monthly P/L'),
              _breakdownTable(report, report.monthlyStats),
            ],
          ),
        ),
      ],
    ),
    pw.SizedBox(height: 9),
    _sectionTitle(report, 'Equity Curve Evidence'),
    _equityEvidence(report),
    pw.SizedBox(height: 9),
    _sectionTitle(report, 'Hedging Evidence'),
    _insightBox(
      report,
      report.hedgingProfile.detected ? 'Detected' : 'Clear',
      '${report.hedgingProfile.description} ${report.hedgingProfile.eventCount} event(s), ${report.hedgingProfile.affectedTrades} affected trade(s).',
    ),
    pw.SizedBox(height: 9),
    _sectionTitle(report, 'Recent Trades Included'),
    pw.Text(
      _t(
        report,
        'Summary metrics use every filtered closed trade. The following table contains the most recent records only.',
      ),
      style: pw.TextStyle(fontSize: 8, color: TradingReportPdfTheme.muted),
    ),
  ];

  List<pw.Widget> _recentTradesPage(TradingReport report) => [
    _pageTitle(report, 'Recent Trades'),
    _recentTradesTable(report),
  ];

  pw.Widget _heroHeader(TradingReport report, String traderLabel) {
    final account = report.account;
    return pw.Container(
      width: double.infinity,
      padding: const pw.EdgeInsets.all(13),
      decoration: pw.BoxDecoration(
        color: TradingReportPdfTheme.navyDark,
        borderRadius: pw.BorderRadius.circular(6),
      ),
      child: pw.Column(
        crossAxisAlignment: pw.CrossAxisAlignment.start,
        children: [
          pw.Text(
            'HC GAMING STUDIO',
            style: pw.TextStyle(
              color: TradingReportPdfTheme.teal,
              fontSize: 8,
              fontWeight: pw.FontWeight.bold,
              letterSpacing: 1.2,
            ),
          ),
          pw.SizedBox(height: 4),
          pw.Text(
            _t(report, 'Advanced Trading Journal Report'),
            style: pw.TextStyle(
              color: PdfColors.white,
              fontSize: 19,
              fontWeight: pw.FontWeight.bold,
            ),
          ),
          pw.SizedBox(height: 5),
          pw.Text(
            '${_t(report, 'Trader')}: $traderLabel  |  ${_t(report, 'Period')}: ${_periodLabel(report)}  |  ${_t(report, 'Stage')}: ${_stageLabel(report)}  |  ${_t(report, 'Source')}: ${_sourceLabel(report)}  |  ${_t(report, 'Timezone')}: ${report.filter.timezone}  |  ${_t(report, 'Generated')}: ${_dateTime.format(report.generatedAt)}',
            style: const pw.TextStyle(color: PdfColors.white, fontSize: 8),
          ),
          if (account != null) ...[
            pw.SizedBox(height: 4),
            pw.Text(
              '${_t(report, 'Main MT5 Account')}: ${account.displayLabel}  |  ${_t(report, 'Login')}: ${account.login}  |  ${_t(report, 'Broker')}: ${account.broker.isEmpty ? '-' : account.broker}  |  ${_t(report, 'Server')}: ${account.server}  |  ${_t(report, 'Currency')}: ${account.currency.isEmpty ? '-' : account.currency}',
              style: pw.TextStyle(
                color: TradingReportPdfTheme.tableHeader,
                fontSize: 8,
              ),
            ),
          ],
        ],
      ),
    );
  }

  pw.Widget _runningHeader(TradingReport report) => pw.Container(
    margin: const pw.EdgeInsets.only(bottom: 8),
    padding: const pw.EdgeInsets.symmetric(horizontal: 8, vertical: 5),
    decoration: pw.BoxDecoration(
      color: TradingReportPdfTheme.navyDark,
      borderRadius: pw.BorderRadius.circular(3),
    ),
    child: pw.Row(
      children: [
        pw.Expanded(
          child: pw.Text(
            _t(report, 'Advanced Trading Journal Report'),
            style: pw.TextStyle(
              color: PdfColors.white,
              fontSize: 8,
              fontWeight: pw.FontWeight.bold,
            ),
          ),
        ),
        pw.Text(
          '${_sourceLabel(report)} | ${_periodLabel(report)}',
          style: const pw.TextStyle(color: PdfColors.white, fontSize: 7),
        ),
      ],
    ),
  );

  pw.Widget _footer(pw.Context context, TradingReport report) => pw.Container(
    padding: const pw.EdgeInsets.only(top: 5),
    decoration: pw.BoxDecoration(
      border: pw.Border(
        top: pw.BorderSide(color: TradingReportPdfTheme.border, width: .5),
      ),
    ),
    child: pw.Row(
      children: [
        pw.Expanded(
          child: pw.Text(
            '${_t(report, 'Generated by HC Trader Flutter')} | ${_t(report, 'Score WR30 RR30 RF15 BD10 PC5 EX10 - penalties')} | ${_sourceLabel(report)}',
            style: pw.TextStyle(
              fontSize: 6.5,
              color: TradingReportPdfTheme.muted,
            ),
          ),
        ),
        pw.Text(
          '${_t(report, 'Page')} ${context.pageNumber} ${_t(report, 'of')} ${context.pagesCount} | ${_dateTime.format(report.generatedAt)}',
          style: pw.TextStyle(
            fontSize: 6.5,
            color: TradingReportPdfTheme.muted,
          ),
        ),
      ],
    ),
  );

  pw.Widget _pageTitle(TradingReport report, String title) => pw.Padding(
    padding: const pw.EdgeInsets.only(bottom: 8),
    child: pw.Text(
      _t(report, title),
      style: pw.TextStyle(
        fontSize: 16,
        fontWeight: pw.FontWeight.bold,
        color: TradingReportPdfTheme.navy,
      ),
    ),
  );

  pw.Widget _sectionTitle(TradingReport report, String title) => pw.Padding(
    padding: const pw.EdgeInsets.only(bottom: 5),
    child: pw.Text(
      _t(report, title),
      style: pw.TextStyle(
        fontSize: 10.5,
        fontWeight: pw.FontWeight.bold,
        color: TradingReportPdfTheme.navy,
      ),
    ),
  );

  pw.Widget _executiveCard(
    TradingReport report,
    String title,
    String value,
    String detail,
    String footnote,
    PdfColor tone,
  ) => pw.Expanded(
    child: pw.Container(
      height: 83,
      margin: const pw.EdgeInsets.symmetric(horizontal: 3),
      padding: const pw.EdgeInsets.all(8),
      decoration: TradingReportPdfTheme.card(),
      child: pw.Column(
        crossAxisAlignment: pw.CrossAxisAlignment.start,
        children: [
          pw.Text(
            _t(report, title).toUpperCase(),
            style: pw.TextStyle(
              fontSize: 6.8,
              color: TradingReportPdfTheme.muted,
              fontWeight: pw.FontWeight.bold,
            ),
          ),
          pw.SizedBox(height: 5),
          pw.Text(
            _t(report, value),
            maxLines: 2,
            style: pw.TextStyle(
              fontSize: value.length > 20 ? 10 : 15,
              color: tone,
              fontWeight: pw.FontWeight.bold,
            ),
          ),
          pw.Spacer(),
          pw.Text(
            _t(report, detail),
            maxLines: 2,
            style: const pw.TextStyle(fontSize: 6.8),
          ),
          pw.Text(
            _t(report, footnote),
            maxLines: 2,
            style: pw.TextStyle(
              fontSize: 6.5,
              color: TradingReportPdfTheme.muted,
            ),
          ),
        ],
      ),
    ),
  );

  pw.Widget _miniProfile(
    TradingReport report,
    String title,
    String value,
    String detail,
  ) => pw.Expanded(
    child: pw.Container(
      margin: const pw.EdgeInsets.symmetric(horizontal: 3),
      padding: const pw.EdgeInsets.all(8),
      decoration: TradingReportPdfTheme.card(
        color: TradingReportPdfTheme.surface,
      ),
      child: pw.Column(
        crossAxisAlignment: pw.CrossAxisAlignment.start,
        children: [
          pw.Text(
            _t(report, title).toUpperCase(),
            style: pw.TextStyle(
              fontSize: 6.8,
              color: TradingReportPdfTheme.muted,
              fontWeight: pw.FontWeight.bold,
            ),
          ),
          pw.SizedBox(height: 4),
          pw.Text(
            _t(report, value),
            style: pw.TextStyle(
              fontSize: 10,
              color: TradingReportPdfTheme.navy,
              fontWeight: pw.FontWeight.bold,
            ),
          ),
          pw.SizedBox(height: 2),
          pw.Text(
            _t(report, detail),
            maxLines: 3,
            style: const pw.TextStyle(fontSize: 6.8),
          ),
        ],
      ),
    ),
  );

  pw.Widget _insightBox(TradingReport report, String title, String text) =>
      pw.Container(
        width: double.infinity,
        padding: const pw.EdgeInsets.all(8),
        decoration: TradingReportPdfTheme.card(
          color: TradingReportPdfTheme.surface,
        ),
        child: pw.RichText(
          text: pw.TextSpan(
            children: [
              pw.TextSpan(
                text: '${_t(report, title)}: ',
                style: pw.TextStyle(
                  fontSize: 8,
                  fontWeight: pw.FontWeight.bold,
                  color: TradingReportPdfTheme.navy,
                ),
              ),
              pw.TextSpan(
                text: _t(report, text),
                style: const pw.TextStyle(fontSize: 7.5),
              ),
            ],
          ),
        ),
      );

  pw.Widget _profileGrid(TradingReport report) {
    final level = report.levelProfile;
    final values = [
      (
        'Sample Mode',
        level.sampleMode,
        '${report.scoreProfile.confidencePercent.toStringAsFixed(0)}% confidence',
      ),
      (
        'Classification Score',
        level.classificationScore.toStringAsFixed(2),
        'Raw ${report.scoreProfile.rawScore.toStringAsFixed(2)}',
      ),
      (
        'Base / Cap Level',
        'L${level.baseLevel} / L${level.capLevel}',
        level.capActive ? 'Cap active' : 'No active cap',
      ),
      (
        'Next Level',
        level.nextLevel,
        level.capReason.isEmpty
            ? 'Continue building sample quality.'
            : level.capReason,
      ),
      (
        'Reward Estimate',
        _moneyText(level.rewardEstimate),
        'Highest ${_moneyText(level.highestRewardEstimate)}',
      ),
      ('Profile Note', level.label, level.profileNote),
    ];
    return pw.Row(
      children: [
        for (final item in values)
          _miniProfile(report, item.$1, item.$2, item.$3),
      ],
    );
  }

  pw.Widget _levelTable(TradingReport report) {
    const standard = [
      'No Data / Failed Profile',
      'Novice Trader',
      'Gambling Trader',
      'High-Risk Reactive Trader',
      'Developing Trader',
      'Intermediate Trader',
      'Consistent Trader',
      'Skilled Trader',
      'Professional Trader',
      'Elite Professional Trader',
    ];
    const sample = [
      'No Small-Sample Profile',
      'Small Sample Novice Trader',
      'Small Sample Gambling Trader',
      'Small Sample Reactive Trader',
      'Small Sample Developing Trader',
      'Controlled Small Sample Trader',
      'Promising Small Sample Trader',
      'Skilled Small Sample Trader',
      'Provisional Professional Trader',
      'Small Sample Professional Candidate',
    ];
    return _table(
      report,
      headers: const [
        'Level',
        'Score Band',
        'Small Sample Label',
        'Standard Sample Label',
      ],
      rows: [
        for (var index = 0; index < 10; index++)
          [
            'L${index + 1}',
            index == 9
                ? '90 - 100'
                : '${index * 10} - ${(index + 1) * 10 - .01}',
            sample[index],
            standard[index],
          ],
      ],
      columnWidths: const {
        0: pw.FlexColumnWidth(.5),
        1: pw.FlexColumnWidth(.8),
        2: pw.FlexColumnWidth(2.3),
        3: pw.FlexColumnWidth(2.3),
      },
    );
  }

  pw.Widget _listCard(
    TradingReport report,
    String title,
    List<String> values,
    PdfColor color,
  ) => pw.Expanded(
    child: pw.Container(
      margin: const pw.EdgeInsets.symmetric(horizontal: 3),
      padding: const pw.EdgeInsets.all(8),
      decoration: TradingReportPdfTheme.card(color: color),
      child: pw.Column(
        crossAxisAlignment: pw.CrossAxisAlignment.start,
        children: [
          _sectionTitle(report, title),
          for (final value in values)
            pw.Padding(
              padding: const pw.EdgeInsets.only(bottom: 3),
              child: pw.Row(
                crossAxisAlignment: pw.CrossAxisAlignment.start,
                children: [
                  pw.Text('- ', style: const pw.TextStyle(fontSize: 7)),
                  pw.Expanded(
                    child: pw.Text(
                      _t(report, value),
                      style: const pw.TextStyle(fontSize: 7),
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    ),
  );

  pw.Widget _coreMetrics(TradingReport report) {
    final s = report.summary;
    final hasPips = report.trades.any((trade) => trade.pips != 0);
    final items = [
      (
        'Trades',
        '${s.totalTrades}',
        '${s.winningTrades} wins / ${s.losingTrades} losses',
      ),
      ('Win Rate', _percent(s.winRate), 'Daily ${_percent(s.dailyWinRate)}'),
      ('Risk Reward', s.profitFactorLabel, 'Payoff ${s.payoffRatioLabel}'),
      (
        'Expectancy',
        _moneyText(s.expectancy),
        'Avg win ${_moneyText(s.averageWin)}',
      ),
      (
        'Pips',
        hasPips ? s.totalPips.toStringAsFixed(2) : 'No Data',
        hasPips
            ? 'Avg ${s.averagePips.toStringAsFixed(2)}'
            : 'Unavailable from source',
      ),
      ('Holding', s.averageHoldingLabel, 'Median ${s.medianHoldingLabel}'),
      (
        'Current Balance',
        s.currentBalance == 0 && report.capital.transactions == 0
            ? 'No Data'
            : _moneyText(s.currentBalance),
        report.capital.transactions > 0
            ? 'Capital ${_moneyText(report.capital.netCapital)}'
            : 'Capital unavailable',
      ),
      (
        'Best Day',
        _moneyText(s.bestDay),
        s.bestDayRule.bestWinningDayDate == null
            ? 'No Data'
            : _date.format(s.bestDayRule.bestWinningDayDate!),
      ),
      (
        'Worst Day',
        _moneyText(s.worstDay),
        'Daily loss ${_percent(s.maxDailyLossPercent)}',
      ),
      (
        'Best Day Rule',
        _percent(s.bestDayRule.scorePercent),
        s.bestDayRule.status,
      ),
      (
        'Position Score',
        report.positionProfile.score.toStringAsFixed(2),
        report.positionProfile.status,
      ),
      (
        'Behaviour Risk',
        '${report.behaviourProfile.finalScore.toStringAsFixed(2)}/100',
        'Raw ${report.behaviourProfile.rawScore.toStringAsFixed(2)}',
      ),
    ];
    return pw.Wrap(
      spacing: 6,
      runSpacing: 6,
      children: [
        for (final item in items)
          _metricCard(report, item.$1, item.$2, item.$3, width: 120),
      ],
    );
  }

  pw.Widget _metricCard(
    TradingReport report,
    String title,
    String value,
    String detail, {
    double width = 140,
  }) => pw.Container(
    width: width,
    padding: const pw.EdgeInsets.all(7),
    decoration: TradingReportPdfTheme.card(),
    child: pw.Column(
      crossAxisAlignment: pw.CrossAxisAlignment.start,
      children: [
        pw.Text(
          _t(report, title).toUpperCase(),
          style: pw.TextStyle(
            fontSize: 6.5,
            color: TradingReportPdfTheme.muted,
            fontWeight: pw.FontWeight.bold,
          ),
        ),
        pw.SizedBox(height: 3),
        pw.Text(
          _t(report, value),
          maxLines: 2,
          style: pw.TextStyle(
            fontSize: 9,
            fontWeight: pw.FontWeight.bold,
            color: TradingReportPdfTheme.navy,
          ),
        ),
        pw.Text(
          _t(report, detail),
          maxLines: 2,
          style: pw.TextStyle(
            fontSize: 6.5,
            color: TradingReportPdfTheme.muted,
          ),
        ),
      ],
    ),
  );

  pw.Widget _scoreTable(TradingReport report) => _table(
    report,
    headers: const [
      'Metric',
      'Value',
      'Grade',
      'Points',
      'Maximum',
      'Status',
      'Calculation',
    ],
    rows: [
      for (final item in report.scoreProfile.components)
        [
          item.metric,
          item.value,
          item.grade,
          '${item.points >= 0 ? '+' : ''}${item.points.toStringAsFixed(2)}',
          item.maximumPoints == 0
              ? 'Penalty'
              : item.maximumPoints.toStringAsFixed(0),
          item.status,
          '${item.formula} ${item.calculation}',
        ],
    ],
    columnWidths: const {
      0: pw.FlexColumnWidth(1.2),
      1: pw.FlexColumnWidth(.7),
      2: pw.FlexColumnWidth(.5),
      3: pw.FlexColumnWidth(.55),
      4: pw.FlexColumnWidth(.55),
      5: pw.FlexColumnWidth(.85),
      6: pw.FlexColumnWidth(3.1),
    },
    fontSize: 6.4,
  );

  pw.Widget _behaviourDetailCard(
    TradingReport report,
    String title,
    TradingBehaviourComponent item, {
    bool fullWidth = false,
  }) => pw.Container(
    width: fullWidth ? double.infinity : 366,
    padding: const pw.EdgeInsets.all(9),
    decoration: TradingReportPdfTheme.card(
      color: TradingReportPdfTheme.surface,
    ),
    child: pw.Column(
      crossAxisAlignment: pw.CrossAxisAlignment.start,
      children: [
        _sectionTitle(report, title),
        pw.Wrap(
          spacing: 7,
          runSpacing: 5,
          children: [
            _metricCard(
              report,
              'Status',
              item.status,
              item.method,
              width: fullWidth ? 245 : 170,
            ),
            _metricCard(
              report,
              'Score',
              '${item.score.toStringAsFixed(2)}/100',
              '${item.tier} tier',
              width: fullWidth ? 140 : 170,
            ),
            _metricCard(
              report,
              'Events',
              '${item.eventCount}',
              'Average delay ${item.averageDelayLabel}',
              width: fullWidth ? 140 : 170,
            ),
            _metricCard(
              report,
              'Fast Entries',
              '${item.quickCount}',
              'Lot increase ${item.lotIncreaseCount}',
              width: fullWidth ? 140 : 170,
            ),
          ],
        ),
      ],
    ),
  );

  pw.Widget _behaviourChecksTable(
    TradingReport report,
    List<TradingBehaviourCheck> checks,
  ) => _table(
    report,
    headers: const [
      'Behaviour Check',
      'Value',
      'Status',
      'Points',
      'Description',
    ],
    rows: [
      for (final item in checks)
        [
          item.name,
          item.value,
          item.status,
          item.points?.toStringAsFixed(2) ?? '-',
          item.description,
        ],
    ],
    emptyText: 'No behaviour checks',
    columnWidths: const {
      0: pw.FlexColumnWidth(1.3),
      1: pw.FlexColumnWidth(1.2),
      2: pw.FlexColumnWidth(.7),
      3: pw.FlexColumnWidth(.45),
      4: pw.FlexColumnWidth(3.2),
    },
    fontSize: 6.8,
  );

  pw.Widget _evidenceTable(
    TradingReport report,
    List<TradingBehaviourEvidence> evidence, {
    required String kind,
  }) {
    if (kind == 'gambling') {
      return _table(
        report,
        headers: const [
          'Tier',
          'Trade',
          'Pair',
          'Side',
          'Lot',
          'P/L',
          'Why Flagged',
        ],
        rows: [
          for (final item in evidence)
            [
              item.tier,
              item.primaryTrade,
              item.pair,
              item.side,
              item.lot?.toStringAsFixed(2) ?? '-',
              item.profitLoss == null ? '-' : _moneyText(item.profitLoss!),
              item.signals,
            ],
        ],
        emptyText: 'No gambling evidence',
        columnWidths: const {
          0: pw.FlexColumnWidth(.5),
          1: pw.FlexColumnWidth(1.2),
          2: pw.FlexColumnWidth(.65),
          3: pw.FlexColumnWidth(.55),
          4: pw.FlexColumnWidth(.45),
          5: pw.FlexColumnWidth(.65),
          6: pw.FlexColumnWidth(3.3),
        },
        fontSize: 6.8,
      );
    }
    return _table(
      report,
      headers: kind == 'revenge'
          ? const [
              'Tier',
              'Losing Trade',
              'Next Trade',
              'Pair',
              'Delay',
              'Lot Change',
              'Signals',
            ]
          : const [
              'Tier',
              'Base Trade',
              'Layer Trade',
              'Pair',
              'Delay',
              'Lot Change',
              'Signals',
            ],
      rows: [
        for (final item in evidence)
          [
            item.tier,
            item.primaryTrade,
            item.secondaryTrade,
            item.pair,
            item.delayLabel,
            item.lotChange,
            item.signals,
          ],
      ],
      emptyText: kind == 'revenge'
          ? 'No revenge evidence'
          : 'No layering evidence',
      columnWidths: const {
        0: pw.FlexColumnWidth(.5),
        1: pw.FlexColumnWidth(1.1),
        2: pw.FlexColumnWidth(1.1),
        3: pw.FlexColumnWidth(.65),
        4: pw.FlexColumnWidth(.55),
        5: pw.FlexColumnWidth(.9),
        6: pw.FlexColumnWidth(3.2),
      },
      fontSize: 6.8,
    );
  }

  pw.Widget _ruleMonitor(TradingReport report) => _table(
    report,
    headers: const ['Rule', 'Value', 'Progress', 'Status', 'Explanation'],
    rows: [
      for (final rule in report.ruleMonitor)
        [
          rule.title,
          rule.value,
          '${rule.progress.toStringAsFixed(0)}%',
          rule.status,
          rule.caption,
        ],
    ],
    columnWidths: const {
      0: pw.FlexColumnWidth(1.2),
      1: pw.FlexColumnWidth(1.2),
      2: pw.FlexColumnWidth(.7),
      3: pw.FlexColumnWidth(.8),
      4: pw.FlexColumnWidth(2.5),
    },
  );

  pw.Widget _positionGrid(TradingReport report) {
    final p = report.positionProfile;
    return pw.Wrap(
      spacing: 6,
      runSpacing: 6,
      children: [
        _metricCard(report, 'Grade', p.grade, p.status, width: 120),
        _metricCard(
          report,
          'Main Lot',
          p.mainLot.toStringAsFixed(4),
          '${_percent(p.mainLotShare)} exact share',
          width: 120,
        ),
        _metricCard(
          report,
          'Near Main Lot',
          _percent(p.nearMainLotShare),
          'Within Laravel tolerance',
          width: 120,
        ),
        _metricCard(
          report,
          'Average / Median',
          '${p.averageLot.toStringAsFixed(4)} / ${p.medianLot.toStringAsFixed(4)}',
          'Lot size',
          width: 140,
        ),
        _metricCard(
          report,
          'Range Ratio',
          '${p.rangeRatio.toStringAsFixed(2)}x',
          '${p.minimumLot.toStringAsFixed(4)} - ${p.maximumLot.toStringAsFixed(4)}',
          width: 120,
        ),
        _metricCard(
          report,
          'Position Score',
          '${p.score.toStringAsFixed(2)}/100',
          p.description,
          width: 186,
        ),
      ],
    );
  }

  pw.Widget _breakdownTable(
    TradingReport report,
    List<TradingBreakdownStat> stats,
  ) => _table(
    report,
    headers: const ['Name', 'Trades', 'W/L', 'Win %', 'P/L', 'Pips'],
    rows: [
      for (final stat in stats)
        [
          stat.shortLabel ?? stat.label,
          '${stat.trades}',
          '${stat.wins}/${stat.losses}',
          _percent(stat.winRate),
          _moneyText(stat.profitLoss),
          stat.pips == 0 ? '-' : stat.pips.toStringAsFixed(1),
        ],
    ],
    emptyText: 'No data',
    fontSize: 6.7,
  );

  pw.Widget _equityEvidence(TradingReport report) {
    if (report.dailyStats.isEmpty) return _emptyBox(report, 'No equity data');
    final stats = report.dailyStats.length > 8
        ? report.dailyStats.sublist(report.dailyStats.length - 8)
        : report.dailyStats;
    final maxAbs = stats
        .map((item) => item.cumulativeProfitLoss.abs())
        .fold<double>(0, math.max);
    return pw.Column(
      children: [
        _table(
          report,
          headers: const [
            'Date',
            'Trades',
            'Daily P/L',
            'Cumulative P/L',
            'Curve',
          ],
          rows: [
            for (final stat in stats)
              [
                _date.format(stat.date),
                '${stat.trades}',
                _moneyText(stat.profitLoss),
                _moneyText(stat.cumulativeProfitLoss),
                '',
              ],
          ],
          columnWidths: const {
            0: pw.FlexColumnWidth(1),
            1: pw.FlexColumnWidth(.5),
            2: pw.FlexColumnWidth(.8),
            3: pw.FlexColumnWidth(.9),
            4: pw.FlexColumnWidth(2.6),
          },
          fontSize: 6.8,
        ),
        pw.SizedBox(height: 5),
        for (final stat in stats)
          pw.Padding(
            padding: const pw.EdgeInsets.only(bottom: 2),
            child: pw.Row(
              children: [
                pw.SizedBox(
                  width: 62,
                  child: pw.Text(
                    _date.format(stat.date),
                    style: const pw.TextStyle(fontSize: 6),
                  ),
                ),
                pw.Expanded(
                  child: pw.Stack(
                    children: [
                      pw.Container(
                        height: 5,
                        decoration: pw.BoxDecoration(
                          color: TradingReportPdfTheme.tableHeader,
                          borderRadius: pw.BorderRadius.circular(2),
                        ),
                      ),
                      pw.Container(
                        height: 5,
                        width: maxAbs <= 0
                            ? 0
                            : 620 * (stat.cumulativeProfitLoss.abs() / maxAbs),
                        decoration: pw.BoxDecoration(
                          color: _valueColor(stat.cumulativeProfitLoss),
                          borderRadius: pw.BorderRadius.circular(2),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }

  pw.Widget _recentTradesTable(TradingReport report) {
    final trades = report.trades.reversed.take(25).toList();
    return _table(
      report,
      headers: const [
        '#',
        'Close Time',
        'Source',
        'Stage',
        'Pair',
        'Side',
        'Lot',
        'Pips',
        'P/L',
        'Signals / Notes',
      ],
      rows: [
        for (var index = 0; index < trades.length; index++)
          [
            '${index + 1}',
            _dateTime.format(trades[index].closeDate),
            trades[index].source == TradingRecordSource.mt5Sync
                ? 'MT5'
                : trades[index].source == TradingRecordSource.excelImport
                ? 'Excel'
                : 'Manual',
            report.filter.stage,
            trades[index].pair,
            trades[index].directionLabel,
            trades[index].lotSize.toStringAsFixed(2),
            trades[index].pips == 0
                ? '-'
                : trades[index].pips.toStringAsFixed(1),
            _moneyText(trades[index].profitLoss),
            [
              trades[index].mistakeTags.join(', '),
              trades[index].notes,
            ].where((value) => value.trim().isNotEmpty).join(' | '),
          ],
      ],
      emptyText: 'No trades',
      columnWidths: const {
        0: pw.FlexColumnWidth(.3),
        1: pw.FlexColumnWidth(1.15),
        2: pw.FlexColumnWidth(.55),
        3: pw.FlexColumnWidth(.55),
        4: pw.FlexColumnWidth(.6),
        5: pw.FlexColumnWidth(.45),
        6: pw.FlexColumnWidth(.4),
        7: pw.FlexColumnWidth(.45),
        8: pw.FlexColumnWidth(.65),
        9: pw.FlexColumnWidth(3.2),
      },
      fontSize: 6.4,
    );
  }

  pw.Widget _table(
    TradingReport report, {
    required List<String> headers,
    required List<List<String>> rows,
    String emptyText = 'No records',
    Map<int, pw.TableColumnWidth>? columnWidths,
    double fontSize = 7,
  }) {
    if (rows.isEmpty) return _emptyBox(report, emptyText);
    return pw.TableHelper.fromTextArray(
      border: pw.TableBorder.all(
        color: TradingReportPdfTheme.border,
        width: .45,
      ),
      headerDecoration: pw.BoxDecoration(
        color: TradingReportPdfTheme.tableHeader,
      ),
      headerStyle: pw.TextStyle(
        fontSize: fontSize,
        fontWeight: pw.FontWeight.bold,
        color: TradingReportPdfTheme.navy,
      ),
      cellStyle: pw.TextStyle(fontSize: fontSize, lineSpacing: 1.1),
      cellPadding: const pw.EdgeInsets.symmetric(horizontal: 4, vertical: 3.3),
      headers: headers.map((value) => _t(report, value)).toList(),
      data: rows
          .map((row) => row.map((value) => _t(report, value)).toList())
          .toList(),
      columnWidths: columnWidths,
      cellAlignment: pw.Alignment.centerLeft,
      headerAlignment: pw.Alignment.centerLeft,
    );
  }

  pw.Widget _emptyBox(TradingReport report, String text) => pw.Container(
    width: double.infinity,
    padding: const pw.EdgeInsets.all(10),
    decoration: TradingReportPdfTheme.card(
      color: TradingReportPdfTheme.surface,
    ),
    child: pw.Text(
      _t(report, text),
      style: pw.TextStyle(fontSize: 8, color: TradingReportPdfTheme.muted),
    ),
  );

  String _sourceLabel(TradingReport report) => switch (report.filter.source) {
    TradingReportSource.manual => _t(report, 'Manual Journal'),
    TradingReportSource.mt5 =>
      report.account == null
          ? _t(report, 'MT5 Main Account')
          : '${_t(report, 'MT5 Main Account')} - ${report.account!.displayLabel} / ${report.account!.login}',
    TradingReportSource.all =>
      report.account == null
          ? _t(report, 'Manual + MT5 Main Account')
          : '${_t(report, 'Manual + MT5 Main Account')} - ${report.account!.displayLabel} / ${report.account!.login}',
  };

  String _periodLabel(TradingReport report) {
    final active = report.filter.effectiveDateFilter;
    if (active.isActive) {
      return switch (active.type) {
        TradingDateFilterType.specificDate => _date.format(active.startDate!),
        TradingDateFilterType.dateRange =>
          '${_date.format(active.startDate!)} - ${_date.format(active.endDate!)}',
        TradingDateFilterType.month => _month.format(
          DateTime(active.year!, active.month!),
        ),
        TradingDateFilterType.year => '${active.year}',
        TradingDateFilterType.allTime => _t(report, 'All time'),
      };
    }
    final year = report.filter.year;
    final month = report.filter.month;
    if (year == null && month == null) {
      return _t(report, 'All Months All Years');
    }
    if (year != null && month != null) {
      return _month.format(DateTime(year, month));
    }
    if (year != null) return year.toString();
    return '${_t(report, 'Month')} ${month.toString().padLeft(2, '0')}';
  }

  String _stageLabel(TradingReport report) => report.filter.stage == 'overall'
      ? _t(report, 'Overall')
      : report.filter.stage.replaceAll('_', ' ');

  String _scoreMeaning(TradingReport report) {
    if (report.filter.languageCode != 'zh') return report.scoreProfile.meaning;
    if (report.scoreProfile.isProvisional) {
      return '\u7531\u4e8e\u5df2\u5e73\u4ed3\u4ea4\u6613\u5c11\u4e8e 10 \u7b14\uff0c\u6b64\u8bc4\u5206\u4e3a\u6682\u5b9a\u8bc4\u5206\u3002';
    }
    final score = report.scoreProfile.score;
    if (score >= 85) {
      return '\u4f18\u5f02\uff1a\u6838\u5fc3\u8bc4\u5206\u6307\u6807\u663e\u793a\u51fa\u8272\u7684\u4ea4\u6613\u8868\u73b0\u3002';
    }
    if (score >= 70) {
      return '\u826f\u597d\uff1a\u4ea4\u6613\u57fa\u7840\u7a33\u56fa\uff0c\u4ec5\u6709\u5c11\u91cf\u6539\u8fdb\u9879\u3002';
    }
    if (score >= 50) {
      return '\u4e2d\u7ea7\uff1a\u8868\u73b0\u8fbe\u6807\uff0c\u4f46\u4ecd\u6709\u660e\u786e\u7684\u63d0\u5347\u7a7a\u95f4\u3002';
    }
    return '\u9700\u8981\u5ba1\u67e5\uff1a\u5e94\u52a0\u5f3a\u6267\u884c\u4e0e\u98ce\u9669\u63a7\u5236\u3002';
  }

  String _executiveInsight(TradingReport report) {
    if (report.filter.languageCode != 'zh') return report.executiveInsight;
    final behaviourNote = report.behaviourProfile.finalScore < 40
        ? '\u5f53\u524d\u884c\u4e3a\u53d7\u63a7\uff0c\u8bf7\u7ee7\u7eed\u4fdd\u62a4\u6bcf\u65e5\u548c\u603b\u56de\u64a4\u9650\u5236\u3002'
        : '\u5f53\u524d\u884c\u4e3a\u98ce\u9669\u4ecd\u7136\u6d3b\u8dc3\uff0c\u5728\u6269\u5927\u4ed3\u4f4d\u524d\u5e94\u5148\u964d\u4f4e\u98ce\u9669\u3002';
    return '${_scoreMeaning(report)} $behaviourNote';
  }

  String _resumeSummary(TradingReport report) {
    if (report.filter.languageCode != 'zh') return report.resumeSummary;
    final summary = report.summary;
    return '${_t(report, report.levelProfile.label)}\uff1a\u8d26\u6237\u8bc4\u5206\u4e3a '
        '${report.scoreProfile.score.toStringAsFixed(2)}/100\uff08${report.scoreProfile.grade}\uff09\uff0c'
        '\u80dc\u7387 ${summary.winRate.toStringAsFixed(2)}%\uff0c\u76c8\u5229\u56e0\u5b50 ${summary.profitFactorLabel}\uff0c'
        '\u6bcf\u7b14\u671f\u671b\u503c ${summary.expectancy.toStringAsFixed(2)}u\uff0c\u6700\u5927\u56de\u64a4 '
        '${summary.maxDrawdownPercent.toStringAsFixed(2)}%\u3002\u4ea4\u6613\u98ce\u683c\u4e3a '
        '${_t(report, report.traderStyle)}\uff0c\u4ed3\u4f4d\u4e00\u81f4\u6027\u8bc4\u5206\u4e3a '
        '${report.positionProfile.score.toStringAsFixed(2)}/100\u3002';
  }

  String _t(
    TradingReport report,
    String english, [
    Map<String, Object?> values = const {},
  ]) => AppLocalizations(
    Locale(report.filter.languageCode),
  ).text(english, values);

  String _moneyText(double value) => '${_money.format(value)}u';
  String _percent(double value) => '${value.toStringAsFixed(2)}%';
  PdfColor _valueColor(double value) => value > 0
      ? TradingReportPdfTheme.positive
      : value < 0
      ? TradingReportPdfTheme.negative
      : TradingReportPdfTheme.muted;
  PdfColor _statusColor(String value) =>
      value.toLowerCase().contains('breach') ||
          value.toLowerCase().contains('high')
      ? TradingReportPdfTheme.negative
      : value.toLowerCase().contains('ready') ||
            value.toLowerCase().contains('pass')
      ? TradingReportPdfTheme.positive
      : TradingReportPdfTheme.warning;
  String _safeFilenamePart(String value) => value
      .trim()
      .toLowerCase()
      .replaceAll(RegExp(r'[^a-z0-9_-]+'), '-')
      .replaceAll(RegExp(r'^-+|-+$'), '')
      .replaceAll(RegExp(r'-{2,}'), '-');
  String _stableHash(String value) {
    var hash = 0x811C9DC5;
    for (final code in value.codeUnits) {
      hash ^= code;
      hash = (hash * 0x01000193) & 0xFFFFFFFF;
    }
    return hash.toRadixString(16).padLeft(8, '0');
  }
}
