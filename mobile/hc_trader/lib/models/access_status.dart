enum AccessStatus {
  public('public'),
  unapproved('unapproved'),
  notSubmitted('notSubmitted'),
  pendingReview('pendingReview'),
  approved('approved'),
  rejected('rejected'),
  moreInfoRequired('moreInfoRequired'),
  withdrawn('withdrawn'),
  cancelled('cancelled'),
  suspended('suspended'),
  revoked('revoked'),
  deleted('deleted');

  const AccessStatus(this.storageValue);

  final String storageValue;

  static AccessStatus fromStorage(Object? value) {
    final normalized = value?.toString().trim().toLowerCase();
    return switch (normalized) {
      'public' => AccessStatus.public,
      'unapproved' => AccessStatus.unapproved,
      'approved' => AccessStatus.approved,
      'pendingreview' ||
      'pending_review' ||
      'pending' => AccessStatus.pendingReview,
      'rejected' || 'denied' => AccessStatus.rejected,
      'moreinforequired' ||
      'more_info_required' ||
      'changesrequested' ||
      'changes_requested' => AccessStatus.moreInfoRequired,
      'withdrawn' || 'withdraw' => AccessStatus.withdrawn,
      'cancelled' || 'canceled' => AccessStatus.cancelled,
      'suspended' => AccessStatus.suspended,
      'revoked' => AccessStatus.revoked,
      'deleted' => AccessStatus.deleted,
      _ => AccessStatus.notSubmitted,
    };
  }

  bool get canWithdraw => this == AccessStatus.pendingReview;

  bool get canReapply => const {
    AccessStatus.public,
    AccessStatus.unapproved,
    AccessStatus.notSubmitted,
    AccessStatus.rejected,
    AccessStatus.moreInfoRequired,
    AccessStatus.withdrawn,
    AccessStatus.cancelled,
  }.contains(this);

  bool canTransitionTo(AccessStatus next, {required bool administrator}) {
    if (this == AccessStatus.pendingReview) {
      return administrator
          ? next == AccessStatus.approved ||
                next == AccessStatus.rejected ||
                next == AccessStatus.moreInfoRequired ||
                next == AccessStatus.cancelled
          : next == AccessStatus.withdrawn;
    }
    return canReapply && next == AccessStatus.pendingReview;
  }
}
