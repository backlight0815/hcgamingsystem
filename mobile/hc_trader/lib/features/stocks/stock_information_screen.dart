import 'dart:async';

import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/localization/app_localizations.dart';
import '../../models/app_user.dart';
import '../../models/stock_market.dart';
import '../../repositories/stock_repository.dart';

class StockInformationScreen extends StatelessWidget {
  const StockInformationScreen({
    super.key,
    required this.user,
    required this.repository,
  });

  final AppUser user;
  final StockRepository repository;

  @override
  Widget build(BuildContext context) => DefaultTabController(
    length: 3,
    child: Scaffold(
      appBar: AppBar(
        title: Text(context.l10n.text('Stock Information')),
        bottom: TabBar(
          tabs: [
            Tab(
              icon: const Icon(Icons.search),
              text: context.l10n.text('Discover'),
            ),
            Tab(
              icon: const Icon(Icons.bookmark_outline),
              text: context.l10n.text('Watchlist'),
            ),
            Tab(
              icon: const Icon(Icons.article_outlined),
              text: context.l10n.text('Daily Reports'),
            ),
          ],
        ),
      ),
      body: TabBarView(
        children: [
          _StockDiscovery(user: user, repository: repository),
          _Watchlist(user: user, repository: repository),
          _LatestReports(repository: repository),
        ],
      ),
    ),
  );
}

class _StockDiscovery extends StatefulWidget {
  const _StockDiscovery({required this.user, required this.repository});

  final AppUser user;
  final StockRepository repository;

  @override
  State<_StockDiscovery> createState() => _StockDiscoveryState();
}

class _StockDiscoveryState extends State<_StockDiscovery> {
  final _search = TextEditingController();
  Timer? _debounce;
  late Future<List<StockSearchResult>> _future = widget.repository.search('');

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  void _queueSearch(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), () {
      if (!mounted) return;
      setState(() => _future = widget.repository.search(value));
    });
  }

  Future<void> _refresh() async {
    final next = widget.repository.search(_search.text);
    setState(() => _future = next);
    await next;
  }

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) => RefreshIndicator(
      onRefresh: _refresh,
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 18, 16, 10),
            sliver: SliverToBoxAdapter(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 1040),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      context.l10n.text('Market research workspace'),
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      context.l10n.text(
                        'Search supported stocks, review sourced market data, and follow daily reports.',
                      ),
                    ),
                    const SizedBox(height: 16),
                    SearchBar(
                      controller: _search,
                      leading: const Icon(Icons.search),
                      hintText: context.l10n.text(
                        'Search symbol or company name',
                      ),
                      trailing: [
                        if (_search.text.isNotEmpty)
                          IconButton(
                            tooltip: context.l10n.text('Clear search'),
                            onPressed: () {
                              _search.clear();
                              _queueSearch('');
                              setState(() {});
                            },
                            icon: const Icon(Icons.close),
                          ),
                      ],
                      onChanged: (value) {
                        _queueSearch(value);
                        setState(() {});
                      },
                      onSubmitted: (value) {
                        _debounce?.cancel();
                        setState(
                          () => _future = widget.repository.search(value),
                        );
                      },
                    ),
                  ],
                ),
              ),
            ),
          ),
          FutureBuilder<List<StockSearchResult>>(
            future: _future,
            builder: (context, snapshot) {
              if (snapshot.connectionState != ConnectionState.done) {
                return const SliverPadding(
                  padding: EdgeInsets.all(16),
                  sliver: SliverToBoxAdapter(child: _MarketLoadingState()),
                );
              }
              if (snapshot.hasError) {
                return SliverFillRemaining(
                  hasScrollBody: false,
                  child: _MarketErrorState(
                    onRetry: _refresh,
                    message: context.l10n.text(
                      'Stock information is temporarily unavailable.',
                    ),
                  ),
                );
              }
              final items = snapshot.data ?? const [];
              if (items.isEmpty) {
                return SliverFillRemaining(
                  hasScrollBody: false,
                  child: _MarketEmptyState(
                    icon: Icons.manage_search,
                    title: context.l10n.text('No supported stocks found'),
                    message: context.l10n.text(
                      'Try another symbol or company name.',
                    ),
                  ),
                );
              }
              final columns = constraints.maxWidth >= 1100
                  ? 3
                  : constraints.maxWidth >= 680
                  ? 2
                  : 1;
              return SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
                sliver: SliverGrid.builder(
                  gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: columns,
                    mainAxisExtent: 158,
                    crossAxisSpacing: 12,
                    mainAxisSpacing: 12,
                  ),
                  itemCount: items.length,
                  itemBuilder: (context, index) => _StockResultCard(
                    item: items[index],
                    onTap: () => _openStock(
                      context,
                      widget.user,
                      widget.repository,
                      items[index].symbol,
                    ),
                  ),
                ),
              );
            },
          ),
        ],
      ),
    ),
  );
}

class _StockResultCard extends StatelessWidget {
  const _StockResultCard({required this.item, required this.onTap});

  final StockSearchResult item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final percent = item.changePercent;
    final positive = (percent ?? 0) >= 0;
    return Semantics(
      button: true,
      label: '${item.symbol}, ${item.companyName}',
      child: Card(
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    CircleAvatar(
                      backgroundColor: Theme.of(
                        context,
                      ).colorScheme.primaryContainer,
                      child: Text(
                        item.symbol.characters.first,
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            item.symbol,
                            style: const TextStyle(fontWeight: FontWeight.w900),
                          ),
                          Text(
                            item.companyName,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ],
                      ),
                    ),
                    const Icon(Icons.chevron_right),
                  ],
                ),
                const Spacer(),
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        item.latestPrice == null
                            ? context.l10n.text('Price unavailable')
                            : '${item.currency} ${_price(item.latestPrice!)}',
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.w800),
                      ),
                    ),
                    if (percent != null)
                      _ChangeChip(value: percent, positive: positive),
                  ],
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(child: Text(item.exchange)),
                    _FreshnessBadge(freshness: item.freshness),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _Watchlist extends StatelessWidget {
  const _Watchlist({required this.user, required this.repository});

  final AppUser user;
  final StockRepository repository;

  @override
  Widget build(BuildContext context) => StreamBuilder<List<StockWatchlistItem>>(
    stream: repository.watchlist(user.uid),
    builder: (context, snapshot) {
      if (snapshot.hasError) {
        return _MarketErrorState(
          onRetry: () {},
          message: context.l10n.text('Unable to load your watchlist.'),
        );
      }
      if (!snapshot.hasData) return const _MarketLoadingState();
      final items = snapshot.data!;
      if (items.isEmpty) {
        return _MarketEmptyState(
          icon: Icons.bookmark_add_outlined,
          title: context.l10n.text('Your watchlist is empty'),
          message: context.l10n.text(
            'Search for a stock and tap Add to watchlist.',
          ),
        );
      }
      return ReorderableListView.builder(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
        itemCount: items.length,
        onReorder: (oldIndex, newIndex) {
          final symbols = items.map((item) => item.symbol).toList();
          if (newIndex > oldIndex) newIndex--;
          final moved = symbols.removeAt(oldIndex);
          symbols.insert(newIndex, moved);
          unawaited(
            repository.reorderWatchlist(userId: user.uid, symbols: symbols),
          );
        },
        itemBuilder: (context, index) {
          final item = items[index];
          return Card(
            key: ValueKey(item.symbol),
            margin: const EdgeInsets.only(bottom: 10),
            child: ListTile(
              minTileHeight: 72,
              leading: CircleAvatar(child: Text(item.symbol.characters.first)),
              title: Text(
                item.symbol,
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
              subtitle: Text(
                item.companyName.isEmpty
                    ? context.l10n.text('Company profile')
                    : item.companyName,
              ),
              trailing: const Icon(Icons.drag_handle),
              onTap: () => _openStock(context, user, repository, item.symbol),
            ),
          );
        },
      );
    },
  );
}

class _LatestReports extends StatelessWidget {
  const _LatestReports({required this.repository});

  final StockRepository repository;

  @override
  Widget build(BuildContext context) =>
      StreamBuilder<List<FinancialDailyReport>>(
        stream: repository.latestPublishedReports(),
        builder: (context, snapshot) {
          if (snapshot.hasError) {
            return _MarketErrorState(
              onRetry: () {},
              message: context.l10n.text('Unable to load financial reports.'),
            );
          }
          if (!snapshot.hasData) return const _MarketLoadingState();
          if (snapshot.data!.isEmpty) {
            return _MarketEmptyState(
              icon: Icons.article_outlined,
              title: context.l10n.text('No published reports yet'),
              message: context.l10n.text(
                'Reviewed financial daily reports will appear here.',
              ),
            );
          }
          return ListView.builder(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
            itemCount: snapshot.data!.length,
            itemBuilder: (context, index) => _ReportCard(
              report: snapshot.data![index],
              onTap: () => Navigator.of(context).push(
                MaterialPageRoute<void>(
                  builder: (_) => FinancialReportDetailScreen(
                    report: snapshot.data![index],
                  ),
                ),
              ),
            ),
          );
        },
      );
}

class StockDetailScreen extends StatefulWidget {
  const StockDetailScreen({
    super.key,
    required this.user,
    required this.repository,
    required this.symbol,
  });

  final AppUser user;
  final StockRepository repository;
  final String symbol;

  @override
  State<StockDetailScreen> createState() => _StockDetailScreenState();
}

class _StockDetailScreenState extends State<StockDetailScreen> {
  late Future<StockMarketSnapshot> _future = widget.repository.loadSnapshot(
    widget.symbol,
  );

  Future<void> _refresh() async {
    final next = widget.repository.loadSnapshot(
      widget.symbol,
      forceRefresh: true,
    );
    setState(() => _future = next);
    await next;
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(widget.symbol)),
    body: FutureBuilder<StockMarketSnapshot>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const _MarketLoadingState();
        }
        if (snapshot.hasError || !snapshot.hasData) {
          return _MarketErrorState(
            onRetry: _refresh,
            message: context.l10n.text(
              'Current market data could not be loaded. Try again shortly.',
            ),
          );
        }
        final data = snapshot.data!;
        final quote = data.quote;
        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
            children: [
              _StockHero(
                user: widget.user,
                repository: widget.repository,
                snapshot: data,
              ),
              const SizedBox(height: 14),
              _SectionCard(
                title: context.l10n.text('Market Summary'),
                child: LayoutBuilder(
                  builder: (context, constraints) => GridView.count(
                    crossAxisCount: constraints.maxWidth >= 700 ? 4 : 2,
                    childAspectRatio: constraints.maxWidth >= 700 ? 2.3 : 1.9,
                    mainAxisSpacing: 8,
                    crossAxisSpacing: 8,
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    children: [
                      _Metric('Open', _optionalPrice(quote.open)),
                      _Metric('High', _optionalPrice(quote.high)),
                      _Metric('Low', _optionalPrice(quote.low)),
                      _Metric(
                        'Previous close',
                        _optionalPrice(quote.previousClose),
                      ),
                      _Metric('Volume', _compact(quote.volume)),
                      _Metric(
                        'Market cap',
                        _compact(quote.marketCapitalization),
                      ),
                      _Metric('52-week high', _optionalPrice(quote.yearHigh)),
                      _Metric('52-week low', _optionalPrice(quote.yearLow)),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 14),
              _SectionCard(
                title: context.l10n.text('Price history'),
                child: data.history.length < 2
                    ? _InlineUnavailable(
                        text: context.l10n.text(
                          'Historical chart data is not available from the configured provider.',
                        ),
                      )
                    : SizedBox(
                        height: 220,
                        child: _PriceChart(points: data.history),
                      ),
              ),
              const SizedBox(height: 14),
              _SectionCard(
                title: context.l10n.text('Company Overview'),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        if (data.profile.sector.isNotEmpty)
                          Chip(label: Text(data.profile.sector)),
                        if (data.profile.industry.isNotEmpty)
                          Chip(label: Text(data.profile.industry)),
                        if (data.profile.exchange.isNotEmpty)
                          Chip(label: Text(data.profile.exchange)),
                      ],
                    ),
                    if (data.profile.description.isNotEmpty) ...[
                      const SizedBox(height: 10),
                      Text(data.profile.description),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 14),
              _CalendarCard(calendar: data.calendar),
              const SizedBox(height: 14),
              _NewsCard(news: data.news),
              const SizedBox(height: 14),
              _StockReports(
                symbol: widget.symbol,
                repository: widget.repository,
              ),
              const SizedBox(height: 14),
              _DisclaimerCard(),
            ],
          ),
        );
      },
    ),
  );
}

class _StockHero extends StatelessWidget {
  const _StockHero({
    required this.user,
    required this.repository,
    required this.snapshot,
  });

  final AppUser user;
  final StockRepository repository;
  final StockMarketSnapshot snapshot;

  @override
  Widget build(BuildContext context) {
    final quote = snapshot.quote;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        quote.symbol,
                        style: Theme.of(context).textTheme.headlineSmall
                            ?.copyWith(fontWeight: FontWeight.w900),
                      ),
                      Text(snapshot.profile.companyName),
                      Text(
                        [
                          quote.exchange,
                          quote.currency,
                        ].where((value) => value.isNotEmpty).join(' · '),
                      ),
                    ],
                  ),
                ),
                StreamBuilder<bool>(
                  stream: repository.isOnWatchlist(user.uid, quote.symbol),
                  builder: (context, watchlist) => IconButton.filledTonal(
                    tooltip: context.l10n.text(
                      watchlist.data == true
                          ? 'Remove from watchlist'
                          : 'Add to watchlist',
                    ),
                    onPressed: () async {
                      try {
                        if (watchlist.data == true) {
                          await repository.removeFromWatchlist(
                            userId: user.uid,
                            symbol: quote.symbol,
                          );
                        } else {
                          await repository.addToWatchlist(
                            userId: user.uid,
                            symbol: quote.symbol,
                            companyName: snapshot.profile.companyName,
                          );
                        }
                      } catch (_) {
                        if (!context.mounted) return;
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(
                            content: Text(
                              context.l10n.text('Unable to update watchlist.'),
                            ),
                          ),
                        );
                      }
                    },
                    icon: Icon(
                      watchlist.data == true
                          ? Icons.bookmark
                          : Icons.bookmark_add_outlined,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 18),
            Wrap(
              crossAxisAlignment: WrapCrossAlignment.center,
              spacing: 12,
              runSpacing: 8,
              children: [
                Text(
                  '${quote.currency} ${_price(quote.price)}',
                  style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                _ChangeChip(
                  value: quote.changePercent,
                  positive: quote.isPositive,
                ),
                _FreshnessBadge(freshness: quote.freshness),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              context.l10n.text('Last updated {time}', {
                'time': DateFormat.yMMMd().add_Hm().format(
                  quote.asOf.toLocal(),
                ),
              }),
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ),
      ),
    );
  }
}

class _PriceChart extends StatelessWidget {
  const _PriceChart({required this.points});

  final List<StockPricePoint> points;

  @override
  Widget build(BuildContext context) {
    final values = points.map((point) => point.close).toList();
    final minimum = values.reduce((a, b) => a < b ? a : b);
    final maximum = values.reduce((a, b) => a > b ? a : b);
    final padding = (maximum - minimum).abs() * .12 + .01;
    return Semantics(
      label: context.l10n.text('Historical price line chart'),
      child: LineChart(
        LineChartData(
          minY: minimum - padding,
          maxY: maximum + padding,
          gridData: const FlGridData(show: true, drawVerticalLine: false),
          borderData: FlBorderData(show: false),
          titlesData: const FlTitlesData(
            topTitles: AxisTitles(sideTitles: SideTitles(showTitles: false)),
            rightTitles: AxisTitles(sideTitles: SideTitles(showTitles: false)),
            bottomTitles: AxisTitles(sideTitles: SideTitles(showTitles: false)),
          ),
          lineTouchData: const LineTouchData(enabled: true),
          lineBarsData: [
            LineChartBarData(
              spots: [
                for (var index = 0; index < points.length; index++)
                  FlSpot(index.toDouble(), points[index].close),
              ],
              color: Theme.of(context).colorScheme.primary,
              barWidth: 3,
              dotData: const FlDotData(show: false),
              belowBarData: BarAreaData(
                show: true,
                color: Theme.of(
                  context,
                ).colorScheme.primary.withValues(alpha: .08),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _CalendarCard extends StatelessWidget {
  const _CalendarCard({required this.calendar});

  final FinancialCalendar? calendar;

  @override
  Widget build(BuildContext context) => _SectionCard(
    title: context.l10n.text('Upcoming Events'),
    child:
        calendar == null ||
            (calendar!.nextEarningsAt == null && calendar!.exDividendAt == null)
        ? _InlineUnavailable(
            text: context.l10n.text('No upcoming financial events available.'),
          )
        : Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              if (calendar!.nextEarningsAt != null)
                _EventTile(
                  icon: Icons.event_note_outlined,
                  label: context.l10n.text('Next earnings'),
                  value: DateFormat.yMMMd().format(calendar!.nextEarningsAt!),
                ),
              if (calendar!.exDividendAt != null)
                _EventTile(
                  icon: Icons.payments_outlined,
                  label: context.l10n.text('Ex-dividend date'),
                  value: DateFormat.yMMMd().format(calendar!.exDividendAt!),
                ),
            ],
          ),
  );
}

class _NewsCard extends StatelessWidget {
  const _NewsCard({required this.news});

  final List<MarketNews> news;

  @override
  Widget build(BuildContext context) => _SectionCard(
    title: context.l10n.text('Latest News'),
    child: news.isEmpty
        ? _InlineUnavailable(
            text: context.l10n.text('No sourced company news is available.'),
          )
        : Column(
            children: [
              for (final item in news.take(6))
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: Icon(
                    item.verified
                        ? Icons.verified_outlined
                        : Icons.article_outlined,
                  ),
                  title: Text(item.title),
                  subtitle: Text(
                    '${item.source} · ${DateFormat.yMMMd().format(item.publishedAt)}',
                  ),
                  trailing: item.url.isEmpty
                      ? null
                      : const Icon(Icons.open_in_new, size: 18),
                  onTap: item.url.isEmpty
                      ? null
                      : () => launchUrl(
                          Uri.parse(item.url),
                          mode: LaunchMode.externalApplication,
                        ),
                ),
            ],
          ),
  );
}

class _StockReports extends StatelessWidget {
  const _StockReports({required this.symbol, required this.repository});

  final String symbol;
  final StockRepository repository;

  @override
  Widget build(BuildContext context) => _SectionCard(
    title: context.l10n.text('Financial Daily Reports'),
    child: StreamBuilder<List<FinancialDailyReport>>(
      stream: repository.publishedReports(symbol),
      builder: (context, snapshot) {
        if (!snapshot.hasData) return const LinearProgressIndicator();
        if (snapshot.data!.isEmpty) {
          return _InlineUnavailable(
            text: context.l10n.text('No reviewed report is available yet.'),
          );
        }
        return Column(
          children: [
            for (final report in snapshot.data!.take(5))
              _ReportCard(
                report: report,
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => FinancialReportDetailScreen(report: report),
                  ),
                ),
              ),
          ],
        );
      },
    ),
  );
}

class _ReportCard extends StatelessWidget {
  const _ReportCard({required this.report, required this.onTap});

  final FinancialDailyReport report;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Card(
    margin: const EdgeInsets.only(bottom: 10),
    child: ListTile(
      minTileHeight: 82,
      leading: CircleAvatar(child: Text(report.symbol.characters.first)),
      title: Text(
        report.localizedTitle(Localizations.localeOf(context).languageCode),
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
      ),
      subtitle: Text(
        '${report.symbol} · ${DateFormat.yMMMd().format(report.reportDate)}',
      ),
      trailing: const Icon(Icons.chevron_right),
      onTap: onTap,
    ),
  );
}

class FinancialReportDetailScreen extends StatelessWidget {
  const FinancialReportDetailScreen({super.key, required this.report});

  final FinancialDailyReport report;

  @override
  Widget build(BuildContext context) {
    final language = Localizations.localeOf(context).languageCode;
    return Scaffold(
      appBar: AppBar(title: Text(context.l10n.text('Financial Daily Report'))),
      body: SelectionArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(18, 16, 18, 36),
          children: [
            Text(
              report.localizedTitle(language),
              style: Theme.of(
                context,
              ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                Chip(label: Text(report.symbol)),
                Chip(
                  label: Text(DateFormat.yMMMMd().format(report.reportDate)),
                ),
                if (report.corrected)
                  Chip(label: Text(context.l10n.text('Corrected'))),
              ],
            ),
            const SizedBox(height: 16),
            if (report.localizedSummary(language).isNotEmpty)
              Text(
                report.localizedSummary(language),
                style: Theme.of(context).textTheme.titleMedium,
              ),
            const SizedBox(height: 18),
            Text(report.localizedContent(language)),
            const SizedBox(height: 24),
            Text(
              context.l10n.text('Source references'),
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 8),
            for (final source in report.sourceReferences)
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.link),
                title: Text(source),
                onTap: Uri.tryParse(source)?.hasAbsolutePath == true
                    ? () => launchUrl(
                        Uri.parse(source),
                        mode: LaunchMode.externalApplication,
                      )
                    : null,
              ),
            Text(
              context.l10n.text('Data timestamp: {time}', {
                'time': DateFormat.yMMMd().add_Hm().format(
                  report.dataTimestamp.toLocal(),
                ),
              }),
              style: Theme.of(context).textTheme.bodySmall,
            ),
            const SizedBox(height: 18),
            _DisclaimerCard(),
          ],
        ),
      ),
    );
  }
}

class _DisclaimerCard extends StatelessWidget {
  @override
  Widget build(BuildContext context) => Card(
    color: Theme.of(context).colorScheme.surfaceContainerHighest,
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.info_outline),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              context.l10n.text(
                'This information is provided for education and general market awareness only. It is not personalized financial advice or a guarantee of future performance.',
              ),
            ),
          ),
        ],
      ),
    ),
  );
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: Theme.of(
              context,
            ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 12),
          child,
        ],
      ),
    ),
  );
}

class _Metric extends StatelessWidget {
  const _Metric(this.label, this.value);

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => DecoratedBox(
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surfaceContainerLowest,
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: Theme.of(context).dividerColor),
    ),
    child: Padding(
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(context.l10n.text(label)),
          const SizedBox(height: 4),
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(fontWeight: FontWeight.w900),
          ),
        ],
      ),
    ),
  );
}

class _EventTile extends StatelessWidget {
  const _EventTile({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: 250,
    child: ListTile(
      tileColor: Theme.of(context).colorScheme.surfaceContainerLowest,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      leading: Icon(icon),
      title: Text(label),
      subtitle: Text(value),
    ),
  );
}

class _ChangeChip extends StatelessWidget {
  const _ChangeChip({required this.value, required this.positive});

  final double value;
  final bool positive;

  @override
  Widget build(BuildContext context) => Chip(
    avatar: Icon(
      positive ? Icons.trending_up : Icons.trending_down,
      size: 16,
      color: positive ? Colors.teal.shade700 : Colors.red.shade700,
    ),
    label: Text(
      '${positive ? '+' : ''}${value.toStringAsFixed(2)}%',
      style: TextStyle(
        color: positive ? Colors.teal.shade800 : Colors.red.shade800,
        fontWeight: FontWeight.w800,
      ),
    ),
  );
}

class _FreshnessBadge extends StatelessWidget {
  const _FreshnessBadge({required this.freshness});

  final StockDataFreshness freshness;

  @override
  Widget build(BuildContext context) => Tooltip(
    message: context.l10n.text(
      'Market data timing is determined by provider rights.',
    ),
    child: Chip(
      visualDensity: VisualDensity.compact,
      avatar: const Icon(Icons.schedule, size: 15),
      label: Text(
        context.l10n.text(switch (freshness) {
          StockDataFreshness.realTime => 'Real-time',
          StockDataFreshness.delayed => 'Delayed',
          StockDataFreshness.endOfDay => 'End-of-day',
          StockDataFreshness.unavailable => 'Unavailable',
        }),
      ),
    ),
  );
}

class _InlineUnavailable extends StatelessWidget {
  const _InlineUnavailable({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      const Icon(Icons.info_outline, size: 20),
      const SizedBox(width: 8),
      Expanded(child: Text(text)),
    ],
  );
}

class _MarketLoadingState extends StatelessWidget {
  const _MarketLoadingState();

  @override
  Widget build(BuildContext context) => Center(
    child: ConstrainedBox(
      constraints: const BoxConstraints(maxWidth: 760),
      child: ListView(
        shrinkWrap: true,
        padding: const EdgeInsets.all(20),
        children: [
          const LinearProgressIndicator(),
          const SizedBox(height: 14),
          for (var index = 0; index < 3; index++) ...[
            Card(child: SizedBox(height: 90, child: Container())),
            const SizedBox(height: 10),
          ],
        ],
      ),
    ),
  );
}

class _MarketEmptyState extends StatelessWidget {
  const _MarketEmptyState({
    required this.icon,
    required this.title,
    required this.message,
  });

  final IconData icon;
  final String title;
  final String message;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 52),
          const SizedBox(height: 12),
          Text(
            title,
            textAlign: TextAlign.center,
            style: Theme.of(
              context,
            ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 6),
          Text(message, textAlign: TextAlign.center),
        ],
      ),
    ),
  );
}

class _MarketErrorState extends StatelessWidget {
  const _MarketErrorState({required this.onRetry, required this.message});

  final FutureOr<void> Function() onRetry;
  final String message;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.cloud_off_outlined, size: 52),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 14),
          FilledButton.tonalIcon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh),
            label: Text(context.l10n.text('Try again')),
          ),
        ],
      ),
    ),
  );
}

void _openStock(
  BuildContext context,
  AppUser user,
  StockRepository repository,
  String symbol,
) {
  Navigator.of(context).push(
    MaterialPageRoute<void>(
      builder: (_) =>
          StockDetailScreen(user: user, repository: repository, symbol: symbol),
    ),
  );
}

String _price(double value) => NumberFormat('#,##0.00##').format(value);
String _optionalPrice(double? value) => value == null ? '—' : _price(value);
String _compact(Object? value) => value == null
    ? '—'
    : NumberFormat.compact().format(value is num ? value : 0);
