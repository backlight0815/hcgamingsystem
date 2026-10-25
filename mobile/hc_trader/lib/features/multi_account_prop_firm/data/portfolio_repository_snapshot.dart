import '../domain/account_sequence_models.dart';
import '../domain/prop_firm_models.dart';

enum PortfolioSyncState { synchronized, saving, pending, offline, failed }

class PortfolioRepositorySnapshot {
  const PortfolioRepositorySnapshot({
    required this.portfolio,
    required this.sequenceState,
    required this.syncState,
    this.lastSynchronizedAt,
    this.errorMessage,
  });

  final PropFirmPortfolio? portfolio;
  final AccountSequenceWorkspaceState sequenceState;
  final PortfolioSyncState syncState;
  final DateTime? lastSynchronizedAt;
  final String? errorMessage;
}
