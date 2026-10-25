import 'market_analysis_item.dart';

class MarketAnalystProfile {
  const MarketAnalystProfile({
    required this.userId,
    required this.displayName,
    this.roleTitle = 'Market Analyst',
    this.avatarUrl = '',
    this.bio = '',
    this.methodology = '',
    this.markets = const [],
    this.verified = false,
    this.yearsExperience,
    this.followerCount = 0,
  });

  final String userId;
  final String displayName;
  final String roleTitle;
  final String avatarUrl;
  final String bio;
  final String methodology;
  final List<String> markets;
  final bool verified;
  final int? yearsExperience;
  final int followerCount;

  factory MarketAnalystProfile.fromMaps({
    required String userId,
    required Map<String, dynamic> profile,
    required Map<String, dynamic> user,
  }) {
    final displayName = _text(
      profile['display_name'] ?? user['name'] ?? user['username'],
      'Market Analyst',
    );
    return MarketAnalystProfile(
      userId: userId,
      displayName: displayName,
      roleTitle: _text(profile['role_title'], 'Market Analyst'),
      avatarUrl: _text(
        profile['avatar_url'] ?? user['photo_url'] ?? user['avatar_url'],
      ),
      bio: _text(profile['bio']),
      methodology: _text(profile['methodology']),
      markets: _strings(profile['markets']),
      verified: profile['verified'] == true || user['is_verified'] == true,
      yearsExperience: int.tryParse('${profile['years_experience'] ?? ''}'),
      followerCount: int.tryParse('${profile['follower_count'] ?? 0}') ?? 0,
    );
  }
}

class MarketAnalystPerformance {
  const MarketAnalystPerformance({
    required this.totalPublished,
    required this.evaluated,
    required this.accurate,
  });

  final int totalPublished;
  final int evaluated;
  final int accurate;

  double? get accuracyPercentage =>
      evaluated == 0 ? null : accurate / evaluated * 100;

  factory MarketAnalystPerformance.fromAnalyses(
    Iterable<MarketAnalysisItem> analyses,
  ) {
    final published = analyses
        .where((item) => item.status != 'draft' && item.status != 'archived')
        .toList(growable: false);
    final evaluated = published.where((item) => item.isEvaluated).toList();
    return MarketAnalystPerformance(
      totalPublished: published.length,
      evaluated: evaluated.length,
      accurate: evaluated.where((item) => item.isAccurateOutcome).length,
    );
  }
}

String _text(Object? value, [String fallback = '']) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? fallback : text;
}

List<String> _strings(Object? value) {
  if (value is! Iterable) return const [];
  return value
      .map((item) => item.toString().trim())
      .where((item) => item.isNotEmpty)
      .toList(growable: false);
}
