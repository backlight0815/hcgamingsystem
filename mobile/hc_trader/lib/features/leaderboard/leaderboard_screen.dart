import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/localization/app_localizations.dart';
import '../../models/app_user.dart';
import '../../models/leaderboard.dart';
import '../../repositories/leaderboard_repository.dart';

class LeaderboardScreen extends StatefulWidget {
  const LeaderboardScreen({
    super.key,
    required this.user,
    required this.repository,
  });

  final AppUser user;
  final LeaderboardRepository repository;

  @override
  State<LeaderboardScreen> createState() => _LeaderboardScreenState();
}

class _LeaderboardScreenState extends State<LeaderboardScreen> {
  var _category = LeaderboardCategory.riskAdjustedReturn;
  var _periodType = LeaderboardPeriodType.monthly;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(context.l10n.text('Leaderboard')),
      actions: [
        IconButton(
          tooltip: context.l10n.text('Leaderboard privacy'),
          onPressed: _openPrivacy,
          icon: const Icon(Icons.privacy_tip_outlined),
        ),
      ],
    ),
    body: StreamBuilder<List<LeaderboardPeriod>>(
      stream: widget.repository.activePeriods(),
      builder: (context, snapshot) {
        if (snapshot.hasError) {
          return _LeaderboardState(
            icon: Icons.cloud_off_outlined,
            title: context.l10n.text('Leaderboard unavailable'),
            message: context.l10n.text(
              'Verified ranking data could not be loaded.',
            ),
          );
        }
        if (!snapshot.hasData) {
          return const Center(child: CircularProgressIndicator());
        }
        final periods = snapshot.data!;
        final period = periods.where((candidate) {
          return candidate.category == _category &&
              candidate.periodType == _periodType;
        }).firstOrNull;
        return Column(
          children: [
            _LeaderboardFilters(
              category: _category,
              periodType: _periodType,
              onCategory: (value) => setState(() => _category = value),
              onPeriod: (value) => setState(() => _periodType = value),
            ),
            Expanded(
              child: period == null
                  ? _LeaderboardState(
                      icon: Icons.emoji_events_outlined,
                      title: context.l10n.text('No active ranking period'),
                      message: context.l10n.text(
                        'A verified leaderboard will appear when this period is finalized.',
                      ),
                    )
                  : _LeaderboardEntries(
                      user: widget.user,
                      repository: widget.repository,
                      period: period,
                    ),
            ),
          ],
        );
      },
    ),
  );

  Future<void> _openPrivacy() async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => _LeaderboardPrivacySheet(
        user: widget.user,
        repository: widget.repository,
      ),
    );
  }
}

class _LeaderboardFilters extends StatelessWidget {
  const _LeaderboardFilters({
    required this.category,
    required this.periodType,
    required this.onCategory,
    required this.onPeriod,
  });

  final LeaderboardCategory category;
  final LeaderboardPeriodType periodType;
  final ValueChanged<LeaderboardCategory> onCategory;
  final ValueChanged<LeaderboardPeriodType> onPeriod;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
    child: LayoutBuilder(
      builder: (context, constraints) => Wrap(
        spacing: 10,
        runSpacing: 10,
        children: [
          SizedBox(
            width: constraints.maxWidth >= 620
                ? constraints.maxWidth - 230
                : constraints.maxWidth,
            child: DropdownButtonFormField<LeaderboardCategory>(
              initialValue: category,
              decoration: InputDecoration(
                labelText: context.l10n.text('Ranking category'),
              ),
              items: [
                for (final item in LeaderboardCategory.values)
                  DropdownMenuItem(
                    value: item,
                    child: Text(context.l10n.text(_categoryLabel(item))),
                  ),
              ],
              onChanged: (value) {
                if (value != null) onCategory(value);
              },
            ),
          ),
          SizedBox(
            width: constraints.maxWidth >= 620 ? 210 : constraints.maxWidth,
            child: DropdownButtonFormField<LeaderboardPeriodType>(
              initialValue: periodType,
              decoration: InputDecoration(
                labelText: context.l10n.text('Period'),
              ),
              items: [
                for (final item in LeaderboardPeriodType.values)
                  DropdownMenuItem(
                    value: item,
                    child: Text(context.l10n.text(_periodLabel(item))),
                  ),
              ],
              onChanged: (value) {
                if (value != null) onPeriod(value);
              },
            ),
          ),
        ],
      ),
    ),
  );
}

class _LeaderboardEntries extends StatelessWidget {
  const _LeaderboardEntries({
    required this.user,
    required this.repository,
    required this.period,
  });

  final AppUser user;
  final LeaderboardRepository repository;
  final LeaderboardPeriod period;

  @override
  Widget build(BuildContext context) => StreamBuilder<List<LeaderboardEntry>>(
    stream: repository.entries(period.id),
    builder: (context, snapshot) {
      if (snapshot.hasError) {
        return _LeaderboardState(
          icon: Icons.error_outline,
          title: context.l10n.text('Ranking data unavailable'),
          message: context.l10n.text('Please try again later.'),
        );
      }
      if (!snapshot.hasData) {
        return const Center(child: CircularProgressIndicator());
      }
      final entries = snapshot.data!;
      if (entries.isEmpty) {
        return _LeaderboardState(
          icon: Icons.hourglass_empty,
          title: context.l10n.text('No eligible traders yet'),
          message: context.l10n.text(
            'Minimum trades, trading days, verification, and privacy settings apply.',
          ),
        );
      }
      final top = entries.take(3).toList();
      final remaining = entries.skip(3).toList();
      return CustomScrollView(
        slivers: [
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            sliver: SliverToBoxAdapter(
              child: Column(
                children: [
                  _PeriodNotice(period: period),
                  const SizedBox(height: 14),
                  _Podium(entries: top, currentUserId: user.uid),
                ],
              ),
            ),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 32),
            sliver: SliverList.builder(
              itemCount: remaining.length,
              itemBuilder: (context, index) => _RankingTile(
                entry: remaining[index],
                current: remaining[index].userId == user.uid,
              ),
            ),
          ),
        ],
      );
    },
  );
}

class _PeriodNotice extends StatelessWidget {
  const _PeriodNotice({required this.period});

  final LeaderboardPeriod period;

  @override
  Widget build(BuildContext context) => Card(
    color: Theme.of(context).colorScheme.surfaceContainerHighest,
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Row(
        children: [
          const Icon(Icons.verified_user_outlined),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              context.l10n.text(
                'Risk-adjusted ranking · minimum {trades} trades and {days} trading days · {start} to {end}',
                {
                  'trades': period.minimumTrades,
                  'days': period.minimumTradingDays,
                  'start': DateFormat.yMMMd().format(period.startsAt),
                  'end': DateFormat.yMMMd().format(period.endsAt),
                },
              ),
            ),
          ),
        ],
      ),
    ),
  );
}

class _Podium extends StatelessWidget {
  const _Podium({required this.entries, required this.currentUserId});

  final List<LeaderboardEntry> entries;
  final String currentUserId;

  @override
  Widget build(BuildContext context) {
    final ordered = <LeaderboardEntry>[
      if (entries.length > 1) entries[1],
      if (entries.isNotEmpty) entries[0],
      if (entries.length > 2) entries[2],
    ];
    return LayoutBuilder(
      builder: (context, constraints) {
        if (constraints.maxWidth < 520) {
          return Column(
            children: [
              for (final entry in entries)
                _PodiumCard(
                  entry: entry,
                  current: entry.userId == currentUserId,
                ),
            ],
          );
        }
        return Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            for (final entry in ordered)
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 5),
                  child: _PodiumCard(
                    entry: entry,
                    current: entry.userId == currentUserId,
                    elevated: entry.rank == 1,
                  ),
                ),
              ),
          ],
        );
      },
    );
  }
}

class _PodiumCard extends StatelessWidget {
  const _PodiumCard({
    required this.entry,
    required this.current,
    this.elevated = false,
  });

  final LeaderboardEntry entry;
  final bool current;
  final bool elevated;

  @override
  Widget build(BuildContext context) => Card(
    color: current ? Theme.of(context).colorScheme.primaryContainer : null,
    child: Padding(
      padding: EdgeInsets.fromLTRB(12, elevated ? 24 : 15, 12, 15),
      child: Column(
        children: [
          Text(
            entry.rank == 1 ? '🏆' : '#${entry.rank}',
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          const SizedBox(height: 8),
          CircleAvatar(
            radius: elevated ? 31 : 25,
            backgroundImage: entry.avatarUrl.isEmpty
                ? null
                : NetworkImage(entry.avatarUrl),
            child: entry.avatarUrl.isEmpty
                ? Text(entry.displayName.characters.firstOrNull ?? 'T')
                : null,
          ),
          const SizedBox(height: 8),
          Text(
            entry.displayName,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(fontWeight: FontWeight.w900),
          ),
          Text('${entry.score.toStringAsFixed(1)} pts'),
          Text(
            '${entry.returnPercentage >= 0 ? '+' : ''}${entry.returnPercentage.toStringAsFixed(2)}%',
          ),
          Text(
            context.l10n.text('{drawdown}% drawdown', {
              'drawdown': entry.maxDrawdownPercentage.toStringAsFixed(1),
            }),
            style: Theme.of(context).textTheme.labelSmall,
          ),
        ],
      ),
    ),
  );
}

class _RankingTile extends StatelessWidget {
  const _RankingTile({required this.entry, required this.current});

  final LeaderboardEntry entry;
  final bool current;

  @override
  Widget build(BuildContext context) => Card(
    color: current ? Theme.of(context).colorScheme.primaryContainer : null,
    child: ListTile(
      minTileHeight: 78,
      leading: SizedBox(
        width: 40,
        child: Center(
          child: Text(
            '#${entry.rank}',
            style: const TextStyle(fontWeight: FontWeight.w900),
          ),
        ),
      ),
      title: Text(
        entry.displayName,
        style: const TextStyle(fontWeight: FontWeight.w800),
      ),
      subtitle: Text(
        '${entry.returnPercentage.toStringAsFixed(2)}% · ${entry.maxDrawdownPercentage.toStringAsFixed(1)}% DD · ${entry.disciplineScore.toStringAsFixed(0)} discipline${entry.badge.isEmpty ? '' : ' · ${entry.badge}'}',
      ),
      trailing: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(
            entry.score.toStringAsFixed(1),
            style: const TextStyle(fontWeight: FontWeight.w900),
          ),
          if (entry.rankChange != 0)
            Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  entry.rankChange > 0
                      ? Icons.arrow_upward
                      : Icons.arrow_downward,
                  size: 14,
                ),
                Text('${entry.rankChange.abs()}'),
              ],
            ),
        ],
      ),
    ),
  );
}

class _LeaderboardPrivacySheet extends StatefulWidget {
  const _LeaderboardPrivacySheet({
    required this.user,
    required this.repository,
  });

  final AppUser user;
  final LeaderboardRepository repository;

  @override
  State<_LeaderboardPrivacySheet> createState() =>
      _LeaderboardPrivacySheetState();
}

class _LeaderboardPrivacySheetState extends State<_LeaderboardPrivacySheet> {
  LeaderboardPrivacy? _value;
  late final TextEditingController _nickname = TextEditingController();
  var _saving = false;

  @override
  void dispose() {
    _nickname.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => StreamBuilder<LeaderboardPrivacy>(
    stream: widget.repository.privacy(widget.user.uid),
    builder: (context, snapshot) {
      if (!snapshot.hasData) {
        return const SizedBox(
          height: 260,
          child: Center(child: CircularProgressIndicator()),
        );
      }
      if (_value == null) {
        _value = snapshot.data!;
        _nickname.text = _value!.nickname;
      }
      final value = _value!;
      return SafeArea(
        child: SingleChildScrollView(
          padding: EdgeInsets.fromLTRB(
            20,
            0,
            20,
            24 + MediaQuery.viewInsetsOf(context).bottom,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                context.l10n.text('Leaderboard privacy'),
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 6),
              Text(
                context.l10n.text(
                  'Participation is opt-in. Private account identifiers are never displayed.',
                ),
              ),
              SwitchListTile.adaptive(
                contentPadding: EdgeInsets.zero,
                title: Text(context.l10n.text('Participate in leaderboard')),
                value: value.participate,
                onChanged: (enabled) => _set(
                  LeaderboardPrivacy(
                    participate: enabled,
                    hideRealName: value.hideRealName,
                    nickname: value.nickname,
                    hideExactBalance: value.hideExactBalance,
                    percentageOnly: value.percentageOnly,
                    hideFromPublic: value.hideFromPublic,
                  ),
                ),
              ),
              TextField(
                controller: _nickname,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Display nickname'),
                ),
              ),
              SwitchListTile.adaptive(
                contentPadding: EdgeInsets.zero,
                title: Text(context.l10n.text('Hide real name')),
                value: value.hideRealName,
                onChanged: (enabled) =>
                    _set(_copy(value, hideRealName: enabled)),
              ),
              SwitchListTile.adaptive(
                contentPadding: EdgeInsets.zero,
                title: Text(context.l10n.text('Hide exact account balance')),
                value: value.hideExactBalance,
                onChanged: (enabled) =>
                    _set(_copy(value, hideExactBalance: enabled)),
              ),
              SwitchListTile.adaptive(
                contentPadding: EdgeInsets.zero,
                title: Text(context.l10n.text('Show percentage only')),
                value: value.percentageOnly,
                onChanged: (enabled) =>
                    _set(_copy(value, percentageOnly: enabled)),
              ),
              SwitchListTile.adaptive(
                contentPadding: EdgeInsets.zero,
                title: Text(context.l10n.text('Hide from public ranking')),
                value: value.hideFromPublic,
                onChanged: (enabled) =>
                    _set(_copy(value, hideFromPublic: enabled)),
              ),
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  onPressed: _saving ? null : _save,
                  icon: const Icon(Icons.save_outlined),
                  label: Text(
                    context.l10n.text(_saving ? 'Saving...' : 'Save'),
                  ),
                ),
              ),
            ],
          ),
        ),
      );
    },
  );

  void _set(LeaderboardPrivacy value) => setState(() => _value = value);

  LeaderboardPrivacy _copy(
    LeaderboardPrivacy value, {
    bool? hideRealName,
    bool? hideExactBalance,
    bool? percentageOnly,
    bool? hideFromPublic,
  }) => LeaderboardPrivacy(
    participate: value.participate,
    hideRealName: hideRealName ?? value.hideRealName,
    nickname: value.nickname,
    hideExactBalance: hideExactBalance ?? value.hideExactBalance,
    percentageOnly: percentageOnly ?? value.percentageOnly,
    hideFromPublic: hideFromPublic ?? value.hideFromPublic,
  );

  Future<void> _save() async {
    final value = _value!;
    final updated = LeaderboardPrivacy(
      participate: value.participate,
      hideRealName: value.hideRealName,
      nickname: _nickname.text,
      hideExactBalance: value.hideExactBalance,
      percentageOnly: value.percentageOnly,
      hideFromPublic: value.hideFromPublic,
    );
    setState(() => _saving = true);
    try {
      await widget.repository.updatePrivacy(widget.user.uid, updated);
      if (mounted) Navigator.pop(context);
    } catch (_) {
      if (mounted) setState(() => _saving = false);
    }
  }
}

class _LeaderboardState extends StatelessWidget {
  const _LeaderboardState({
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

String _categoryLabel(LeaderboardCategory value) => switch (value) {
  LeaderboardCategory.returnPercentage => 'Highest return percentage',
  LeaderboardCategory.netProfit => 'Highest net profit',
  LeaderboardCategory.riskAdjustedReturn => 'Best risk-adjusted return',
  LeaderboardCategory.lowestDrawdown => 'Lowest drawdown',
  LeaderboardCategory.disciplineScore => 'Highest discipline score',
  LeaderboardCategory.planCompliance => 'Best trading-plan compliance',
  LeaderboardCategory.weeklyPerformance => 'Best weekly performance',
  LeaderboardCategory.monthlyPerformance => 'Best monthly performance',
  LeaderboardCategory.consistency => 'Most consistent trader',
  LeaderboardCategory.propFirmProgress => 'Prop firm challenge progress',
  LeaderboardCategory.signalProviderPerformance =>
    'Signal-provider performance',
};

String _periodLabel(LeaderboardPeriodType value) => switch (value) {
  LeaderboardPeriodType.allTime => 'All time',
  _ => '${value.name[0].toUpperCase()}${value.name.substring(1)}',
};
