import '../models/referral_relationship.dart';

typedef UplineRelationshipLoader = Future<UplineRelationship> Function();

/// Keeps the public upline resolution out of profile widgets and caches the
/// trusted response until an explicit refresh is requested.
class ReferralRelationshipService {
  ReferralRelationshipService({required UplineRelationshipLoader loader})
    : _loader = loader;

  final UplineRelationshipLoader _loader;
  Future<UplineRelationship>? _cached;

  Future<UplineRelationship> load({bool refresh = false}) {
    if (refresh) _cached = null;
    return _cached ??= _loader();
  }

  void invalidate() => _cached = null;
}
