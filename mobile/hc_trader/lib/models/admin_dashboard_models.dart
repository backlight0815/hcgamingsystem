enum AdminDashboardRange {
  last7Days(7, 'Last 7 days'),
  last30Days(30, 'Last 30 days'),
  last90Days(90, 'Last 90 days');

  const AdminDashboardRange(this.days, this.label);

  final int days;
  final String label;

  DateTime start(DateTime now) =>
      DateTime(now.year, now.month, now.day).subtract(Duration(days: days - 1));
}

class AdminDashboardMetric {
  const AdminDashboardMetric({
    required this.id,
    required this.label,
    required this.value,
    this.previousValue,
  });

  final String id;
  final String label;
  final int value;
  final int? previousValue;

  double? get changePercentage {
    final previous = previousValue;
    if (previous == null) return null;
    if (previous == 0) return value == 0 ? 0 : 100;
    return ((value - previous) / previous) * 100;
  }
}

class AdminDashboardTrendPoint {
  const AdminDashboardTrendPoint({
    required this.date,
    required this.users,
    required this.signals,
    required this.analyses,
  });

  final DateTime date;
  final int users;
  final int signals;
  final int analyses;
}

class AdminPendingAction {
  const AdminPendingAction({
    required this.id,
    required this.kind,
    required this.title,
    required this.subtitle,
    required this.targetTab,
    this.createdAt,
  });

  final String id;
  final String kind;
  final String title;
  final String subtitle;
  final int targetTab;
  final DateTime? createdAt;
}

class AdminActivityItem {
  const AdminActivityItem({
    required this.id,
    required this.action,
    required this.module,
    required this.actor,
    required this.target,
    this.createdAt,
  });

  final String id;
  final String action;
  final String module;
  final String actor;
  final String target;
  final DateTime? createdAt;
}

class AdminDashboardSnapshot {
  const AdminDashboardSnapshot({
    required this.metrics,
    required this.trend,
    required this.pendingActions,
    required this.activities,
    required this.fetchedAt,
  });

  final List<AdminDashboardMetric> metrics;
  final List<AdminDashboardTrendPoint> trend;
  final List<AdminPendingAction> pendingActions;
  final List<AdminActivityItem> activities;
  final DateTime fetchedAt;
}
