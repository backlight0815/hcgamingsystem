class RolePermissionMatrix {
  const RolePermissionMatrix._();

  static const superAdmin = 1;
  static const subAdmin = 2;
  static const founder = 3;
  static const marketAnalyst = 501;
  static const signalProvider = 201;
  static const seniorSignalProvider = 202;
  static const signalProviderManagement = 502;
  static const leadClient = 700;
  static const potentialClient = leadClient;
  static const trader = 750;
  static const leader = 760;
  static const recruiter = 770;
  static const assistant = 780;
  static const adminAssistant = assistant;
  static const financialConsultant = 790;
  static const evaluator = 800;

  static const adminRoleIds = {superAdmin, subAdmin, founder};
  static const publicRegistrationRoleIds = {trader, leadClient};

  static const _superAdminAssignable = {
    superAdmin,
    subAdmin,
    founder,
    marketAnalyst,
    signalProvider,
    seniorSignalProvider,
    signalProviderManagement,
    leader,
    recruiter,
    trader,
    leadClient,
    assistant,
    financialConsultant,
    evaluator,
  };

  static const _subAdminAssignable = {
    marketAnalyst,
    signalProvider,
    seniorSignalProvider,
    signalProviderManagement,
    leader,
    recruiter,
    trader,
    leadClient,
    financialConsultant,
    evaluator,
  };

  static const _leaderAssignable = {recruiter, trader, leadClient};
  static const _publisherAssignable = {trader, leadClient};
  static const _traderAssignable = {trader, leadClient};

  static bool isAdminRole(int roleId) => adminRoleIds.contains(roleId);

  static bool isPublicRegistrationRole(int roleId) =>
      publicRegistrationRoleIds.contains(roleId);

  static Set<int> assignableRoleIds(int actorRoleId) => switch (actorRoleId) {
    superAdmin => _superAdminAssignable,
    founder => _subAdminAssignable,
    subAdmin => _subAdminAssignable,
    leader => _leaderAssignable,
    marketAnalyst ||
    signalProvider ||
    seniorSignalProvider ||
    signalProviderManagement ||
    recruiter => _publisherAssignable,
    trader => _traderAssignable,
    _ => const <int>{},
  };

  static bool canInviteRole(int actorRoleId, int targetRoleId) =>
      assignableRoleIds(actorRoleId).contains(targetRoleId);

  static bool canManageExistingUser(int actorRoleId, int targetRoleId) {
    if (actorRoleId == superAdmin) return true;
    if (actorRoleId == subAdmin) return !adminRoleIds.contains(targetRoleId);
    return false;
  }
}
