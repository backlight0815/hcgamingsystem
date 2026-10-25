import 'dart:typed_data';

import '../../models/mt5_import_models.dart';
import 'mt5_value_parser.dart';
import 'mt5_workbook_reader.dart';

const mt5ImportParserVersion = 'mt5-desktop-xlsx/1.0.0';

class Mt5ImportParser {
  Mt5ImportParser({
    Mt5SafeWorkbookReader? workbookReader,
    Mt5ValueParser? valueParser,
  }) : _workbookReader = workbookReader ?? const Mt5SafeWorkbookReader(),
       _values = valueParser ?? const Mt5ValueParser() {
    _aliases = Mt5AliasRegistry(_values);
  }

  final Mt5SafeWorkbookReader _workbookReader;
  final Mt5ValueParser _values;
  late final Mt5AliasRegistry _aliases;

  Mt5ImportPreview parse({required Uint8List bytes, required String filename}) {
    final workbook = _workbookReader.read(bytes);
    final issues = <Mt5ValidationIssue>[];
    for (final warning in workbook.warnings) {
      issues.add(
        Mt5ValidationIssue(
          code: warning,
          messageKey: warning == 'formula_without_cached_value'
              ? 'A formula cell had no stored result and was treated as blank.'
              : 'Unsafe or external workbook content was ignored.',
          severity: Mt5ValidationSeverity.warning,
        ),
      );
    }

    final sections = _detectSections(workbook);
    final hasSectionProfile = sections.any((item) => item.markerRow != null);
    final profile = hasSectionProfile
        ? 'mt5_trade_history_sections_v1'
        : 'mt5_flat_deal_history_v1';
    final recognized = sections.where(
      (item) => const {
        Mt5ImportSection.positions,
        Mt5ImportSection.orders,
        Mt5ImportSection.deals,
      }.contains(item.section),
    );
    if (recognized.isEmpty) {
      issues.add(
        const Mt5ValidationIssue(
          code: 'unsupported_format',
          messageKey:
              'No recognizable MT5 Positions, Orders, or Deals table was found.',
          severity: Mt5ValidationSeverity.blocking,
          suggestedCorrection:
              'Export Trade History from MT5 Desktop as an .xlsx workbook.',
        ),
      );
    }

    final metadata = _parseMetadata(workbook, sections);
    if (metadata.accountNumber.isEmpty) {
      issues.add(
        const Mt5ValidationIssue(
          code: 'missing_account_metadata',
          messageKey: 'The source account number could not be detected.',
          severity: Mt5ValidationSeverity.blocking,
        ),
      );
    }
    if (metadata.sourceTimezoneOffsetMinutes == null) {
      issues.add(
        const Mt5ValidationIssue(
          code: 'missing_source_timezone',
          messageKey:
              'The report has no source timezone. Times will remain in MT5 server time until the connection timezone is configured.',
          severity: Mt5ValidationSeverity.warning,
        ),
      );
    }

    final positions = <Mt5ImportedPosition>[];
    final orders = <Mt5ImportedOrder>[];
    final deals = <Mt5ImportedDeal>[];
    final rejected = <Mt5RejectedRow>[];
    final mappings = <String, Map<String, int>>{};
    var parsedRows = 0;

    for (final detected in sections) {
      mappings['${detected.sheet.name}:${detected.section.name}'] = {
        for (final entry in detected.columns.entries)
          if (entry.value.isNotEmpty) entry.key: entry.value.first,
      };
      switch (detected.section) {
        case Mt5ImportSection.positions:
          _parsePositions(
            detected,
            workbook: workbook,
            target: positions,
            rejected: rejected,
            issues: issues,
          );
          break;
        case Mt5ImportSection.orders:
          _parseOrders(
            detected,
            workbook: workbook,
            target: orders,
            rejected: rejected,
            issues: issues,
          );
          break;
        case Mt5ImportSection.deals:
          _parseDeals(
            detected,
            workbook: workbook,
            target: deals,
            rejected: rejected,
            issues: issues,
          );
          break;
        default:
          break;
      }
    }
    parsedRows = positions.length + orders.length + deals.length;

    final reconstructed = _reconstructPositions(
      positions: positions,
      orders: orders,
      deals: deals,
      issues: issues,
    );
    final linkedDeals = reconstructed.deals;
    final balanceTransactions = linkedDeals
        .where(
          (deal) =>
              !deal.isTradingDeal &&
              _isLedgerBalanceClassification(deal.classification),
        )
        .map(
          (deal) => Mt5BalanceTransaction(
            dealTicket: deal.ticket,
            time: deal.time,
            amount: deal.profit + deal.commission + deal.fee + deal.swap,
            resultingBalance: deal.resultingBalance,
            classification: deal.classification,
            comment: deal.comment,
            reference: deal.reference,
          ),
        )
        .toList(growable: false);

    final sourceResults = _parseResults(workbook, sections);
    final sourceClosingBalance = _sourceClosingBalance(
      workbook,
      sections,
      linkedDeals,
    );
    final financial = _financialSummary(
      positions: reconstructed.positions,
      deals: linkedDeals,
      balanceTransactions: balanceTransactions,
      sourceClosingBalance: sourceClosingBalance,
    );
    _validateLedger(linkedDeals, issues);
    if (!financial.reconciled) {
      issues.add(
        Mt5ValidationIssue(
          code: 'financial_reconciliation_mismatch',
          messageKey:
              'The calculated closing balance does not reconcile with the source report.',
          severity: Mt5ValidationSeverity.blocking,
          details: {
            'source': financial.sourceClosingBalance.toString(),
            'calculated': financial.calculatedClosingBalance.toString(),
            'difference': financial.reconciliationDifference.toString(),
          },
        ),
      );
    }
    final comparisons = _compareResults(
      sourceResults,
      financial,
      reconstructed.positions,
      issues,
    );

    final recognizedSections = sections.map((item) => item.section).toSet();
    final confidence = _formatConfidence(
      recognizedSections,
      metadata,
      sections,
    );
    if (confidence < 0.70) {
      issues.add(
        Mt5ValidationIssue(
          code: 'low_format_confidence',
          messageKey:
              'The MT5 report mapping confidence is below the safe import threshold.',
          severity: Mt5ValidationSeverity.blocking,
          details: {'confidence': confidence},
        ),
      );
    }

    final sourceRows = workbook.sheets
        .where((sheet) => !sheet.isBlank)
        .fold<int>(0, (sum, sheet) => sum + sheet.rows.length);
    return Mt5ImportPreview(
      filename: filename,
      fileSize: bytes.length,
      fileHash: workbook.fileHash,
      parserProfile: profile,
      parserVersion: mt5ImportParserVersion,
      sheetNames: workbook.sheets
          .where((sheet) => !sheet.isBlank)
          .map((sheet) => sheet.name)
          .toList(),
      detectedSections: recognizedSections,
      columnMappings: mappings,
      formatConfidence: confidence,
      metadata: metadata,
      positions: reconstructed.positions,
      orders: orders,
      deals: linkedDeals,
      balanceTransactions: balanceTransactions,
      financialSummary: financial,
      resultComparisons: comparisons,
      issues: issues,
      rejectedRows: rejected,
      totalSourceRows: sourceRows,
      parsedRows: parsedRows,
      ignoredRows: (sourceRows - parsedRows - rejected.length).clamp(
        0,
        sourceRows,
      ),
    );
  }

  List<_DetectedSection> _detectSections(Mt5SafeWorkbook workbook) {
    final detected = <_DetectedSection>[];
    for (final sheet in workbook.sheets.where((item) => !item.isBlank)) {
      final markers = <({int index, Mt5ImportSection section})>[];
      for (var index = 0; index < sheet.rows.length; index++) {
        final row = sheet.rows[index];
        final nonBlank = row.nonBlankValues.toList(growable: false);
        if (nonBlank.isEmpty || nonBlank.length > 3) continue;
        final section = _aliases.sectionFor(nonBlank.first);
        if (section != null) markers.add((index: index, section: section));
      }
      for (var markerIndex = 0; markerIndex < markers.length; markerIndex++) {
        final marker = markers[markerIndex];
        final headerIndex = marker.section == Mt5ImportSection.results
            ? marker.index
            : _findHeaderRow(
                sheet,
                marker.index + 1,
                end: markerIndex + 1 < markers.length
                    ? markers[markerIndex + 1].index
                    : sheet.rows.length,
              );
        if (headerIndex == null) continue;
        final columns = _mapHeader(sheet.rows[headerIndex], marker.section);
        detected.add(
          _DetectedSection(
            sheet: sheet,
            section: marker.section,
            markerRow: sheet.rows[marker.index].number,
            headerIndex: headerIndex,
            endIndex: markerIndex + 1 < markers.length
                ? markers[markerIndex + 1].index
                : sheet.rows.length,
            columns: columns,
          ),
        );
      }
      if (markers.isEmpty) {
        for (var index = 0; index < sheet.rows.length; index++) {
          final columns = _mapHeader(sheet.rows[index], Mt5ImportSection.deals);
          if (_hasColumns(columns, const [
                'time',
                'deal_ticket',
                'type',
                'profit',
              ]) &&
              (columns.containsKey('direction') ||
                  columns.containsKey('symbol'))) {
            detected.add(
              _DetectedSection(
                sheet: sheet,
                section: Mt5ImportSection.deals,
                headerIndex: index,
                endIndex: sheet.rows.length,
                columns: columns,
              ),
            );
            break;
          }
        }
      }
    }
    return detected;
  }

  int? _findHeaderRow(Mt5WorkbookSheet sheet, int start, {required int end}) {
    for (var index = start; index < end && index < start + 8; index++) {
      final known = sheet.rows[index].nonBlankValues
          .where((value) => _aliases.headerMeaning(value) != null)
          .length;
      if (known >= 3) return index;
    }
    return null;
  }

  Map<String, List<int>> _mapHeader(
    Mt5WorkbookRow row,
    Mt5ImportSection section,
  ) {
    final mapping = <String, List<int>>{};
    for (final entry in row.cells.entries) {
      final meaning = _aliases.headerMeaning(entry.value.value);
      if (meaning == null) continue;
      mapping.putIfAbsent(meaning, () => []).add(entry.key);
    }
    if (section == Mt5ImportSection.positions) {
      _promoteOccurrences(mapping, 'time', 'open_time', 'close_time');
      _promoteOccurrences(mapping, 'price', 'open_price', 'close_price');
    } else if (section == Mt5ImportSection.orders) {
      _promoteOccurrences(mapping, 'time', 'created_time', 'updated_time');
    }
    return mapping;
  }

  void _promoteOccurrences(
    Map<String, List<int>> mapping,
    String source,
    String first,
    String second,
  ) {
    final values = mapping[source];
    if (values == null || values.isEmpty) return;
    mapping[first] = [values.first];
    if (values.length > 1) mapping[second] = [values[1]];
  }

  Mt5ReportMetadata _parseMetadata(
    Mt5SafeWorkbook workbook,
    List<_DetectedSection> sections,
  ) {
    var reportName = '';
    var ownerName = '';
    var accountNumber = '';
    var currency = '';
    var server = '';
    var company = '';
    var environment = '';
    var accountMode = '';
    DateTime? generatedAt;
    var generatedAtSource = '';
    for (final sheet in workbook.sheets.where((item) => !item.isBlank)) {
      final firstSectionRow = sections
          .where((item) => identical(item.sheet, sheet))
          .map(
            (item) =>
                item.markerRow ?? item.sheet.rows[item.headerIndex].number,
          )
          .fold<int?>(
            null,
            (lowest, row) => lowest == null || row < lowest ? row : lowest,
          );
      for (final row in sheet.rows) {
        if (firstSectionRow != null && row.number >= firstSectionRow) break;
        final values = row.nonBlankValues.toList(growable: false);
        if (values.isEmpty) continue;
        if (reportName.isEmpty &&
            values.length == 1 &&
            _values
                .normalizeLabel(values.first)
                .contains('tradehistoryreport')) {
          reportName = values.first;
          continue;
        }
        if (values.length < 2) continue;
        final key = _values.normalizeLabel(values.first);
        final value = values
            .skip(1)
            .firstWhere((item) => item.trim().isNotEmpty, orElse: () => '');
        if (const {'name', 'owner', '姓名', '名称', '名稱'}.contains(key)) {
          ownerName = value;
        } else if (const {
          'account',
          'accountnumber',
          '账户',
          '帳戶',
        }.contains(key)) {
          final match = RegExp(
            r'^\s*([^\s(]+)\s*(?:\((.*)\))?',
          ).firstMatch(value);
          if (match != null) {
            accountNumber = _values.ticket(match.group(1));
            final details = (match.group(2) ?? '')
                .split(',')
                .map((item) => item.trim())
                .toList();
            if (details.isNotEmpty) currency = details[0];
            if (details.length > 1) server = details[1];
            if (details.length > 2) environment = details[2].toLowerCase();
            if (details.length > 3) accountMode = details[3];
          }
        } else if (const {'company', 'broker', '公司'}.contains(key)) {
          company = value;
        } else if (const {'date', 'reportdate', '日期'}.contains(key)) {
          generatedAtSource = value;
          try {
            generatedAt = _values.dateTime(value, date1904: workbook.date1904);
          } on FormatException {
            generatedAt = null;
          }
        }
      }
      if (reportName.isEmpty) {
        reportName = sheet.rows
            .expand((row) => row.nonBlankValues)
            .firstWhere(
              (value) =>
                  _values.normalizeLabel(value).contains('tradehistoryreport'),
              orElse: () => '',
            );
      }
    }
    return Mt5ReportMetadata(
      reportName: reportName,
      ownerName: ownerName,
      accountNumber: accountNumber,
      currency: currency,
      brokerServer: server,
      company: company,
      environment: environment,
      accountMode: accountMode,
      reportGeneratedAt: generatedAt,
      reportGeneratedAtSource: generatedAtSource,
    );
  }

  void _parsePositions(
    _DetectedSection section, {
    required Mt5SafeWorkbook workbook,
    required List<Mt5ImportedPosition> target,
    required List<Mt5RejectedRow> rejected,
    required List<Mt5ValidationIssue> issues,
  }) {
    const requiredColumns = [
      'open_time',
      'position_ticket',
      'symbol',
      'type',
      'volume',
      'open_price',
      'close_time',
      'close_price',
      'profit',
    ];
    if (!_requireColumns(section, requiredColumns, issues)) return;
    for (final row in section.dataRows) {
      final ticket = _cell(row, section, 'position_ticket');
      if (ticket.isEmpty) continue;
      try {
        final openTimeSource = _cell(row, section, 'open_time');
        final closeTimeSource = _cell(row, section, 'close_time');
        final symbol = _cell(row, section, 'symbol');
        final side = _values.orderType(_cell(row, section, 'type'));
        if (symbol.isEmpty || !const {'buy', 'sell'}.contains(side)) {
          throw const FormatException('Position symbol or side is invalid.');
        }
        target.add(
          Mt5ImportedPosition(
            ticket: _values.ticket(ticket),
            symbol: symbol,
            side: side,
            volume: _values.decimal(
              _cell(row, section, 'volume'),
              required: true,
            ),
            openPrice: _values.decimal(
              _cell(row, section, 'open_price'),
              required: true,
            ),
            stopLoss: _values.nullableDecimal(_cell(row, section, 'stop_loss')),
            takeProfit: _values.nullableDecimal(
              _cell(row, section, 'take_profit'),
            ),
            openTime: _values.dateTime(
              openTimeSource,
              date1904: workbook.date1904,
            ),
            openTimeSource: openTimeSource,
            closeTime: closeTimeSource.isEmpty
                ? null
                : _values.dateTime(
                    closeTimeSource,
                    date1904: workbook.date1904,
                  ),
            closeTimeSource: closeTimeSource,
            closePrice: _values.nullableDecimal(
              _cell(row, section, 'close_price'),
            ),
            commission: _values.decimal(_cell(row, section, 'commission')),
            swap: _values.decimal(_cell(row, section, 'swap')),
            grossPriceProfit: _values.decimal(
              _cell(row, section, 'profit'),
              required: true,
            ),
            reference: _reference(section, row),
          ),
        );
      } on FormatException catch (error) {
        _reject(
          section,
          row,
          'invalid_position_row',
          '$error',
          rejected,
          issues,
        );
      }
    }
  }

  void _parseOrders(
    _DetectedSection section, {
    required Mt5SafeWorkbook workbook,
    required List<Mt5ImportedOrder> target,
    required List<Mt5RejectedRow> rejected,
    required List<Mt5ValidationIssue> issues,
  }) {
    const requiredColumns = [
      'created_time',
      'order_ticket',
      'symbol',
      'type',
      'volume',
      'price',
      'state',
    ];
    if (!_requireColumns(section, requiredColumns, issues)) return;
    for (final row in section.dataRows) {
      final ticket = _cell(row, section, 'order_ticket');
      if (ticket.isEmpty) continue;
      try {
        final createdSource = _cell(row, section, 'created_time');
        final updatedSource = _cell(row, section, 'updated_time');
        final rawPrice = _cell(row, section, 'price');
        final market =
            _values.normalizeLabel(rawPrice) == 'market' ||
            rawPrice == '市价' ||
            rawPrice == '市價';
        final volume = _values.volumePair(_cell(row, section, 'volume'));
        final orderType = _values.orderType(_cell(row, section, 'type'));
        final state = _values.orderState(_cell(row, section, 'state'));
        if (!_supportedOrderType(orderType) || !_supportedOrderState(state)) {
          throw const FormatException('Unsupported order type or state.');
        }
        target.add(
          Mt5ImportedOrder(
            ticket: _values.ticket(ticket),
            symbol: _cell(row, section, 'symbol'),
            orderType: orderType,
            requestedVolume: volume.requested,
            executedVolume: volume.executed,
            requestedPrice: market ? null : _values.nullableDecimal(rawPrice),
            marketPrice: market,
            stopLoss: _values.nullableDecimal(_cell(row, section, 'stop_loss')),
            takeProfit: _values.nullableDecimal(
              _cell(row, section, 'take_profit'),
            ),
            createdAt: _values.dateTime(
              createdSource,
              date1904: workbook.date1904,
            ),
            createdAtSource: createdSource,
            updatedAt: updatedSource.isEmpty
                ? null
                : _values.dateTime(updatedSource, date1904: workbook.date1904),
            updatedAtSource: updatedSource,
            state: state,
            comment: _cell(row, section, 'comment'),
            relatedPositionTicket: _nullableTicket(
              _cell(row, section, 'position_ticket'),
            ),
            reference: _reference(section, row),
          ),
        );
      } on FormatException catch (error) {
        _reject(section, row, 'invalid_order_row', '$error', rejected, issues);
      }
    }
  }

  void _parseDeals(
    _DetectedSection section, {
    required Mt5SafeWorkbook workbook,
    required List<Mt5ImportedDeal> target,
    required List<Mt5RejectedRow> rejected,
    required List<Mt5ValidationIssue> issues,
  }) {
    const requiredColumns = ['time', 'deal_ticket', 'type', 'profit'];
    if (!_requireColumns(section, requiredColumns, issues)) return;
    for (final row in section.dataRows) {
      final ticket = _cell(row, section, 'deal_ticket');
      if (ticket.isEmpty) continue;
      try {
        final timeSource = _cell(row, section, 'time');
        final dealType = _values.orderType(_cell(row, section, 'type'));
        final direction = _values.dealDirection(
          _cell(row, section, 'direction'),
        );
        final profit = _values.decimal(_cell(row, section, 'profit'));
        final comment = _cell(row, section, 'comment');
        final classification = _classifyDeal(
          dealType: dealType,
          direction: direction,
          profit: profit,
          comment: comment,
        );
        if (classification == Mt5DealClassification.unknown) {
          issues.add(
            Mt5ValidationIssue(
              code: 'unknown_deal_classification',
              messageKey:
                  'A non-trading deal could not be classified precisely and was stored as an adjustment.',
              severity: Mt5ValidationSeverity.warning,
              reference: _reference(section, row),
            ),
          );
        }
        if (const {'buy', 'sell'}.contains(dealType) &&
            !const {'in', 'out', 'in_out', 'out_by'}.contains(direction)) {
          throw const FormatException(
            'A trading deal has an unsupported entry direction.',
          );
        }
        target.add(
          Mt5ImportedDeal(
            ticket: _values.ticket(ticket),
            symbol: _cell(row, section, 'symbol'),
            dealType: dealType,
            direction: direction,
            volume: _values.decimal(_cell(row, section, 'volume')),
            price: _values.nullableDecimal(_cell(row, section, 'price')),
            time: _values.dateTime(timeSource, date1904: workbook.date1904),
            timeSource: timeSource,
            orderTicket: _nullableTicket(_cell(row, section, 'order_ticket')),
            positionTicket: _nullableTicket(
              _cell(row, section, 'position_ticket'),
            ),
            commission: _values.decimal(_cell(row, section, 'commission')),
            fee: _values.decimal(_cell(row, section, 'fee')),
            swap: _values.decimal(_cell(row, section, 'swap')),
            profit: profit,
            resultingBalance: _values.nullableDecimal(
              _cell(row, section, 'balance'),
            ),
            comment: comment,
            classification: classification,
            reference: _reference(section, row),
          ),
        );
      } on FormatException catch (error) {
        _reject(section, row, 'invalid_deal_row', '$error', rejected, issues);
      }
    }
  }

  _ReconstructionResult _reconstructPositions({
    required List<Mt5ImportedPosition> positions,
    required List<Mt5ImportedOrder> orders,
    required List<Mt5ImportedDeal> deals,
    required List<Mt5ValidationIssue> issues,
  }) {
    final mutableDeals = List<Mt5ImportedDeal>.from(deals);
    var canonicalPositions = List<Mt5ImportedPosition>.from(positions);
    if (canonicalPositions.isEmpty) {
      canonicalPositions = _positionsFromDeals(mutableDeals, issues);
    }
    final claimedExitDeals = <String>{};
    for (var index = 0; index < canonicalPositions.length; index++) {
      final position = canonicalPositions[index];
      final relatedDeals = <Mt5ImportedDeal>[];
      for (var dealIndex = 0; dealIndex < mutableDeals.length; dealIndex++) {
        var deal = mutableDeals[dealIndex];
        final explicit = deal.positionTicket == position.ticket;
        final entryOrderMatch =
            deal.isTradeEntry && deal.orderTicket == position.ticket;
        if (explicit || entryOrderMatch) {
          if (deal.positionTicket == null) {
            deal = deal.copyWith(positionTicket: position.ticket);
            mutableDeals[dealIndex] = deal;
          }
          relatedDeals.add(deal);
          if (deal.isTradeExit) claimedExitDeals.add(deal.ticket);
        }
      }
      if (position.isClosed && !relatedDeals.any((deal) => deal.isTradeExit)) {
        final candidateIndex = mutableDeals.indexWhere((deal) {
          if (!deal.isTradeExit || claimedExitDeals.contains(deal.ticket)) {
            return false;
          }
          if (deal.symbol != position.symbol ||
              deal.time.isBefore(position.openTime)) {
            return false;
          }
          if (position.closeTime != null && deal.time != position.closeTime) {
            return false;
          }
          final closesLong = position.side == 'buy' && deal.dealType == 'sell';
          final closesShort = position.side == 'sell' && deal.dealType == 'buy';
          return closesLong || closesShort;
        });
        if (candidateIndex >= 0) {
          final linked = mutableDeals[candidateIndex].copyWith(
            positionTicket: position.ticket,
          );
          mutableDeals[candidateIndex] = linked;
          relatedDeals.add(linked);
          claimedExitDeals.add(linked.ticket);
        }
      }
      final relatedOrders = <String>{
        for (final order in orders)
          if (order.ticket == position.ticket ||
              order.relatedPositionTicket == position.ticket ||
              relatedDeals.any((deal) => deal.orderTicket == order.ticket))
            order.ticket,
      };
      final dealCommission = _sum(relatedDeals.map((deal) => deal.commission));
      final dealSwap = _sum(relatedDeals.map((deal) => deal.swap));
      canonicalPositions[index] = position.copyWith(
        relatedOrderTickets: relatedOrders.toList(growable: false),
        relatedDealTickets: relatedDeals
            .map((deal) => deal.ticket)
            .toList(growable: false),
        commission: position.commission.isZero
            ? dealCommission
            : position.commission,
        fee: _sum(relatedDeals.map((deal) => deal.fee)),
        swap: position.swap.isZero ? dealSwap : position.swap,
      );
    }
    for (final deal in mutableDeals.where(
      (item) => item.isTradeExit && item.positionTicket == null,
    )) {
      issues.add(
        Mt5ValidationIssue(
          code: 'unlinked_exit_deal',
          messageKey:
              'An exit deal could not be linked to a position with high confidence.',
          severity: Mt5ValidationSeverity.warning,
          reference: deal.reference,
        ),
      );
    }
    return _ReconstructionResult(
      positions: canonicalPositions,
      deals: mutableDeals,
    );
  }

  List<Mt5ImportedPosition> _positionsFromDeals(
    List<Mt5ImportedDeal> deals,
    List<Mt5ValidationIssue> issues,
  ) {
    final entries = deals.where((deal) => deal.isTradeEntry).toList()
      ..sort(_compareDeals);
    final exits = deals.where((deal) => deal.isTradeExit).toList()
      ..sort(_compareDeals);
    final positions = <Mt5ImportedPosition>[];
    final usedExits = <String>{};
    for (final entry in entries) {
      if (entry.symbol.isEmpty || entry.price == null || entry.volume.isZero) {
        continue;
      }
      final side = entry.dealType;
      final explicitTicket = entry.positionTicket ?? entry.orderTicket;
      final ticket = explicitTicket ?? 'fallback_${_fingerprint(entry)}';
      final candidates = exits
          .where((exit) {
            if (usedExits.contains(exit.ticket) ||
                exit.symbol != entry.symbol) {
              return false;
            }
            if (exit.time.isBefore(entry.time)) return false;
            if (explicitTicket != null && exit.positionTicket != null) {
              return exit.positionTicket == explicitTicket;
            }
            return (side == 'buy' && exit.dealType == 'sell') ||
                (side == 'sell' && exit.dealType == 'buy');
          })
          .toList(growable: false);
      final exit = candidates.isEmpty ? null : candidates.first;
      if (exit != null) usedExits.add(exit.ticket);
      final related = <Mt5ImportedDeal>[entry];
      if (exit != null) related.add(exit);
      positions.add(
        Mt5ImportedPosition(
          ticket: ticket,
          symbol: entry.symbol,
          side: side,
          volume: entry.volume,
          openPrice: entry.price!,
          openTime: entry.time,
          openTimeSource: entry.timeSource,
          closeTime: exit?.time,
          closeTimeSource: exit?.timeSource ?? '',
          closePrice: exit?.price,
          grossPriceProfit: exit?.profit ?? Mt5Decimal.zero,
          commission: _sum(related.map((deal) => deal.commission)),
          fee: _sum(related.map((deal) => deal.fee)),
          swap: _sum(related.map((deal) => deal.swap)),
          relatedOrderTickets: related
              .map((deal) => deal.orderTicket)
              .whereType<String>()
              .toSet()
              .toList(growable: false),
          relatedDealTickets: related
              .map((deal) => deal.ticket)
              .toList(growable: false),
          reference: entry.reference,
          identityConfidence: explicitTicket == null
              ? Mt5IdentityConfidence.deterministicFallback
              : Mt5IdentityConfidence.explicitLink,
        ),
      );
    }
    if (positions.isEmpty && entries.isNotEmpty) {
      issues.add(
        const Mt5ValidationIssue(
          code: 'trade_reconstruction_failed',
          messageKey:
              'Trading deals were found but positions could not be reconstructed.',
          severity: Mt5ValidationSeverity.blocking,
        ),
      );
    }
    return positions;
  }

  Mt5FinancialSummary _financialSummary({
    required List<Mt5ImportedPosition> positions,
    required List<Mt5ImportedDeal> deals,
    required List<Mt5BalanceTransaction> balanceTransactions,
    required Mt5Decimal sourceClosingBalance,
  }) {
    final closed = positions.where((position) => position.isClosed).toList();
    final grossProfit = _sum(
      closed
          .where((item) => item.grossPriceProfit.isPositive)
          .map((item) => item.grossPriceProfit),
    );
    final grossLoss = _sum(
      closed
          .where((item) => item.grossPriceProfit.isNegative)
          .map((item) => item.grossPriceProfit),
    );
    var commission = _sum(closed.map((item) => item.commission));
    var fee = _sum(closed.map((item) => item.fee));
    var swap = _sum(closed.map((item) => item.swap));
    commission += _sum(
      deals
          .where(
            (item) =>
                item.classification ==
                Mt5DealClassification.commissionAdjustment,
          )
          .map((item) => item.profit),
    );
    fee += _sum(
      deals
          .where((item) => item.classification == Mt5DealClassification.fee)
          .map((item) => item.profit),
    );
    swap += _sum(
      deals
          .where(
            (item) =>
                item.classification == Mt5DealClassification.swapAdjustment,
          )
          .map((item) => item.profit),
    );
    final netPnl = grossProfit + grossLoss + commission + fee + swap;
    final deposits = _sum(
      balanceTransactions
          .where((item) => item.classification == Mt5DealClassification.deposit)
          .map((item) => item.amount),
    );
    final withdrawals = _sum(
      balanceTransactions
          .where(
            (item) => item.classification == Mt5DealClassification.withdrawal,
          )
          .map((item) => item.amount),
    );
    final credits = _sum(
      balanceTransactions
          .where(
            (item) => const {
              Mt5DealClassification.credit,
              Mt5DealClassification.creditRemoval,
              Mt5DealClassification.bonus,
            }.contains(item.classification),
          )
          .map((item) => item.amount),
    );
    final other = _sum(
      balanceTransactions
          .where(
            (item) => !const {
              Mt5DealClassification.deposit,
              Mt5DealClassification.withdrawal,
              Mt5DealClassification.credit,
              Mt5DealClassification.creditRemoval,
              Mt5DealClassification.bonus,
            }.contains(item.classification),
          )
          .map((item) => item.amount),
    );
    final capital = deposits + withdrawals + credits;

    Mt5Decimal? opening;
    final chronological = List<Mt5ImportedDeal>.from(deals)
      ..sort(_compareDeals);
    for (final deal in chronological) {
      if (deal.resultingBalance != null) {
        opening =
            deal.resultingBalance! -
            (deal.profit + deal.commission + deal.fee + deal.swap);
        break;
      }
    }
    opening ??= sourceClosingBalance - capital - other - netPnl;
    final calculated = opening + capital + other + netPnl;
    final difference = calculated - sourceClosingBalance;
    final tolerance = Mt5Decimal.parse('0.02');
    return Mt5FinancialSummary(
      grossProfit: grossProfit,
      grossLoss: grossLoss,
      commission: commission,
      fee: fee,
      swap: swap,
      netTradingPnl: netPnl,
      deposits: deposits,
      withdrawals: withdrawals,
      credits: credits,
      otherAdjustments: other,
      netCapitalFlow: capital,
      openingBalance: opening,
      calculatedClosingBalance: calculated,
      sourceClosingBalance: sourceClosingBalance,
      reconciliationDifference: difference,
      reconciled: difference.abs.compareTo(tolerance) <= 0,
    );
  }

  void _validateLedger(
    List<Mt5ImportedDeal> deals,
    List<Mt5ValidationIssue> issues,
  ) {
    final ordered = List<Mt5ImportedDeal>.from(deals)..sort(_compareDeals);
    Mt5Decimal? previous;
    final tolerance = Mt5Decimal.parse('0.02');
    for (final deal in ordered) {
      final reported = deal.resultingBalance;
      if (reported == null) continue;
      if (previous != null) {
        final expected =
            previous + deal.profit + deal.commission + deal.fee + deal.swap;
        final difference = expected - reported;
        if (difference.abs.compareTo(tolerance) > 0) {
          issues.add(
            Mt5ValidationIssue(
              code: 'ledger_balance_mismatch',
              messageKey:
                  'A reported deal balance does not reconcile with the preceding ledger balance.',
              severity: Mt5ValidationSeverity.blocking,
              reference: deal.reference,
              details: {
                'expected': expected.toString(),
                'reported': reported.toString(),
                'difference': difference.toString(),
              },
            ),
          );
        }
      }
      previous = reported;
    }
  }

  Map<String, Mt5Decimal> _parseResults(
    Mt5SafeWorkbook workbook,
    List<_DetectedSection> sections,
  ) {
    final result = <String, Mt5Decimal>{};
    final resultSections = sections.where(
      (item) => item.section == Mt5ImportSection.results,
    );
    for (final section in resultSections) {
      for (final row in section.sheet.rows.skip(section.headerIndex)) {
        final cells = row.cells.entries.toList()
          ..sort((a, b) => a.key.compareTo(b.key));
        for (var index = 0; index < cells.length; index++) {
          final key = _resultKey(cells[index].value.value);
          if (key == null) continue;
          for (var next = index + 1; next < cells.length; next++) {
            try {
              result[key] = Mt5Decimal.parse(cells[next].value.value);
              break;
            } on FormatException {
              continue;
            }
          }
        }
      }
    }
    return result;
  }

  List<Mt5ResultComparison> _compareResults(
    Map<String, Mt5Decimal> source,
    Mt5FinancialSummary financial,
    List<Mt5ImportedPosition> positions,
    List<Mt5ValidationIssue> issues,
  ) {
    final calculated = <String, Mt5Decimal>{
      'total_net_profit': financial.netTradingPnl,
      'gross_profit': financial.grossProfit,
      'gross_loss': financial.grossLoss,
      'total_trades': Mt5Decimal.fromInt(
        positions.where((item) => item.isClosed).length,
      ),
      'long_trades': Mt5Decimal.fromInt(
        positions.where((item) => item.isClosed && item.side == 'buy').length,
      ),
      'short_trades': Mt5Decimal.fromInt(
        positions.where((item) => item.isClosed && item.side == 'sell').length,
      ),
    };
    final tolerance = Mt5Decimal.parse('0.02');
    final comparisons = <Mt5ResultComparison>[];
    for (final entry in source.entries) {
      final expected = calculated[entry.key];
      if (expected == null) continue;
      final difference = expected - entry.value;
      final matches = difference.abs.compareTo(tolerance) <= 0;
      comparisons.add(
        Mt5ResultComparison(
          key: entry.key,
          sourceValue: entry.value,
          calculatedValue: expected,
          difference: difference,
          matches: matches,
        ),
      );
      if (!matches) {
        issues.add(
          Mt5ValidationIssue(
            code: 'results_mismatch_${entry.key}',
            messageKey:
                'An application-calculated result does not match the MT5 Results section.',
            severity: Mt5ValidationSeverity.blocking,
            details: {
              'result': entry.key,
              'source': entry.value.toString(),
              'calculated': expected.toString(),
            },
          ),
        );
      }
    }
    return comparisons;
  }

  Mt5Decimal _sourceClosingBalance(
    Mt5SafeWorkbook workbook,
    List<_DetectedSection> sections,
    List<Mt5ImportedDeal> deals,
  ) {
    final balances = deals
        .map((deal) => deal.resultingBalance)
        .whereType<Mt5Decimal>()
        .toList(growable: false);
    if (balances.isNotEmpty) return balances.last;
    for (final sheet in workbook.sheets) {
      for (final row in sheet.rows.reversed) {
        final cells = row.cells.entries.toList()
          ..sort((a, b) => a.key.compareTo(b.key));
        for (var index = 0; index < cells.length; index++) {
          if (_values.normalizeLabel(cells[index].value.value) != 'balance') {
            continue;
          }
          for (var next = index + 1; next < cells.length; next++) {
            try {
              return Mt5Decimal.parse(cells[next].value.value);
            } on FormatException {
              continue;
            }
          }
        }
      }
    }
    return Mt5Decimal.zero;
  }

  Mt5DealClassification _classifyDeal({
    required String dealType,
    required String direction,
    required Mt5Decimal profit,
    required String comment,
  }) {
    if (const {'buy', 'sell'}.contains(dealType)) {
      return switch (direction) {
        'in' => Mt5DealClassification.tradeEntry,
        'out' => Mt5DealClassification.tradeExit,
        'in_out' => Mt5DealClassification.tradeInOut,
        'out_by' => Mt5DealClassification.tradeOutBy,
        _ => Mt5DealClassification.unknown,
      };
    }
    final type = _values.normalizeLabel(dealType);
    final note = _values.normalizeLabel(comment);
    if (type.contains('deposit') ||
        note.contains('deposit') ||
        note.contains('入金') ||
        note.contains('存款')) {
      return Mt5DealClassification.deposit;
    }
    if (type.contains('withdraw') ||
        note.contains('withdraw') ||
        note.contains('出金') ||
        note.contains('提款')) {
      return Mt5DealClassification.withdrawal;
    }
    if (type.contains('credit')) {
      return profit.isNegative
          ? Mt5DealClassification.creditRemoval
          : Mt5DealClassification.credit;
    }
    if (type.contains('bonus')) return Mt5DealClassification.bonus;
    if (type.contains('commission')) {
      return Mt5DealClassification.commissionAdjustment;
    }
    if (type == 'fee' || type.contains('charge')) {
      return Mt5DealClassification.fee;
    }
    if (type.contains('swap')) return Mt5DealClassification.swapAdjustment;
    if (type.contains('dividend')) return Mt5DealClassification.dividend;
    if (type.contains('tax')) return Mt5DealClassification.tax;
    if (type.contains('interest')) return Mt5DealClassification.interest;
    if (type.contains('correction')) return Mt5DealClassification.correction;
    if (type.contains('balance')) {
      if (profit.isPositive &&
          (note.contains('demo') || note.contains('fund'))) {
        return Mt5DealClassification.deposit;
      }
      return Mt5DealClassification.otherBalanceAdjustment;
    }
    return Mt5DealClassification.unknown;
  }

  bool _isLedgerBalanceClassification(Mt5DealClassification classification) =>
      const {
        Mt5DealClassification.deposit,
        Mt5DealClassification.withdrawal,
        Mt5DealClassification.credit,
        Mt5DealClassification.creditRemoval,
        Mt5DealClassification.bonus,
        Mt5DealClassification.charge,
        Mt5DealClassification.correction,
        Mt5DealClassification.dividend,
        Mt5DealClassification.tax,
        Mt5DealClassification.interest,
        Mt5DealClassification.otherBalanceAdjustment,
        Mt5DealClassification.unknown,
      }.contains(classification);

  bool _requireColumns(
    _DetectedSection section,
    List<String> required,
    List<Mt5ValidationIssue> issues,
  ) {
    final missing = required
        .where((key) => !(section.columns[key]?.isNotEmpty ?? false))
        .toList();
    if (missing.isEmpty) return true;
    issues.add(
      Mt5ValidationIssue(
        code: 'missing_required_columns',
        messageKey:
            'A detected MT5 section is missing critical required columns.',
        severity: Mt5ValidationSeverity.blocking,
        reference: Mt5SourceReference(
          sheet: section.sheet.name,
          row: section.sheet.rows[section.headerIndex].number,
          section: section.section,
        ),
        details: {'missing': missing},
      ),
    );
    return false;
  }

  void _reject(
    _DetectedSection section,
    Mt5WorkbookRow row,
    String code,
    String explanation,
    List<Mt5RejectedRow> rejected,
    List<Mt5ValidationIssue> issues,
  ) {
    final reference = _reference(section, row);
    rejected.add(
      Mt5RejectedRow(
        reference: reference,
        rawValues: {
          for (final entry in row.cells.entries)
            'column_${entry.key + 1}': entry.value.value,
        },
        reasonCode: code,
        messageKey: explanation,
        suggestedCorrection:
            'Correct the source row in MT5/Excel and import a new export.',
      ),
    );
    issues.add(
      Mt5ValidationIssue(
        code: code,
        messageKey: explanation,
        severity: Mt5ValidationSeverity.blocking,
        reference: reference,
      ),
    );
  }

  String _cell(Mt5WorkbookRow row, _DetectedSection section, String key) {
    final columns = section.columns[key];
    if (columns == null || columns.isEmpty) return '';
    return row.valueAt(columns.first);
  }

  Mt5SourceReference _reference(_DetectedSection section, Mt5WorkbookRow row) =>
      Mt5SourceReference(
        sheet: section.sheet.name,
        row: row.number,
        section: section.section,
      );

  bool _hasColumns(Map<String, List<int>> columns, List<String> required) =>
      required.every((key) => columns[key]?.isNotEmpty ?? false);

  bool _supportedOrderType(String value) => const {
    'buy',
    'sell',
    'buy_limit',
    'sell_limit',
    'buy_stop',
    'sell_stop',
    'buy_stop_limit',
    'sell_stop_limit',
  }.contains(value);

  bool _supportedOrderState(String value) => const {
    'filled',
    'partially_filled',
    'placed',
    'pending',
    'cancelled',
    'expired',
    'rejected',
  }.contains(value);

  String? _nullableTicket(String value) {
    final ticket = _values.ticket(value);
    return ticket.isEmpty ? null : ticket;
  }

  String? _resultKey(String value) {
    final normalized = _values.normalizeLabel(value);
    return switch (normalized) {
      'totalnetprofit' || 'netprofit' || '总净盈利' || '總淨盈利' => 'total_net_profit',
      'grossprofit' || '毛利' => 'gross_profit',
      'grossloss' || '毛损' || '毛損' => 'gross_loss',
      'totaltrades' || 'trades' || '总交易' || '總交易' => 'total_trades',
      'longtradeswonpercent' ||
      'longtrades' ||
      '多头交易' ||
      '多頭交易' => 'long_trades',
      'shorttradeswonpercent' ||
      'shorttrades' ||
      '空头交易' ||
      '空頭交易' => 'short_trades',
      _ => null,
    };
  }

  double _formatConfidence(
    Set<Mt5ImportSection> sections,
    Mt5ReportMetadata metadata,
    List<_DetectedSection> detected,
  ) {
    var score = 0.0;
    if (sections.contains(Mt5ImportSection.deals)) score += 0.30;
    if (sections.contains(Mt5ImportSection.positions)) score += 0.20;
    if (sections.contains(Mt5ImportSection.orders)) score += 0.15;
    if (sections.contains(Mt5ImportSection.results)) score += 0.10;
    if (metadata.accountNumber.isNotEmpty) score += 0.10;
    if (metadata.brokerServer.isNotEmpty) score += 0.05;
    if (metadata.currency.isNotEmpty) score += 0.05;
    if (detected.any((item) => item.columns.length >= 8)) score += 0.05;
    if (detected.any(
      (item) =>
          item.section == Mt5ImportSection.deals &&
          item.markerRow == null &&
          _hasColumns(item.columns, const [
            'time',
            'deal_ticket',
            'symbol',
            'type',
            'direction',
            'volume',
            'price',
            'profit',
          ]),
    )) {
      score += 0.25;
    }
    return score.clamp(0, 1);
  }

  Mt5Decimal _sum(Iterable<Mt5Decimal> values) =>
      values.fold(Mt5Decimal.zero, (sum, value) => sum + value);

  int _compareDeals(Mt5ImportedDeal left, Mt5ImportedDeal right) {
    final date = left.time.compareTo(right.time);
    if (date != 0) return date;
    final row = left.reference.row.compareTo(right.reference.row);
    return row != 0 ? row : left.ticket.compareTo(right.ticket);
  }

  String _fingerprint(Mt5ImportedDeal deal) {
    final input =
        '${deal.symbol}|${deal.dealType}|${deal.timeSource}|${deal.volume}|${deal.price}|${deal.reference.row}';
    var hash = 0x811c9dc5;
    for (final byte in input.codeUnits) {
      hash ^= byte;
      hash = (hash * 0x01000193) & 0xffffffff;
    }
    return hash.toRadixString(16).padLeft(8, '0');
  }
}

class _DetectedSection {
  const _DetectedSection({
    required this.sheet,
    required this.section,
    required this.headerIndex,
    required this.endIndex,
    required this.columns,
    this.markerRow,
  });

  final Mt5WorkbookSheet sheet;
  final Mt5ImportSection section;
  final int? markerRow;
  final int headerIndex;
  final int endIndex;
  final Map<String, List<int>> columns;

  Iterable<Mt5WorkbookRow> get dataRows =>
      sheet.rows.skip(headerIndex + 1).take(endIndex - headerIndex - 1);
}

class _ReconstructionResult {
  const _ReconstructionResult({required this.positions, required this.deals});

  final List<Mt5ImportedPosition> positions;
  final List<Mt5ImportedDeal> deals;
}
