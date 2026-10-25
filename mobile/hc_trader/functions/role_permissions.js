"use strict";

const ROLE_IDS = Object.freeze({
  superAdmin: 1,
  subAdmin: 2,
  founder: 3,
  signalProvider: 201,
  seniorSignalProvider: 202,
  marketAnalyst: 501,
  signalProviderManagement: 502,
  leadClient: 700,
  potentialClient: 700,
  trader: 750,
  leader: 760,
  recruiter: 770,
  assistant: 780,
  adminAssistant: 780,
  financialConsultant: 790,
  evaluator: 800,
});

const ADMIN_ROLES = new Set([
  ROLE_IDS.superAdmin,
  ROLE_IDS.subAdmin,
  ROLE_IDS.founder,
]);
const PRIVILEGED_ROLES = new Set([
  ROLE_IDS.superAdmin,
  ROLE_IDS.subAdmin,
  ROLE_IDS.founder,
  ROLE_IDS.adminAssistant,
]);
const PUBLIC_REGISTRATION_ROLES = new Set([
  ROLE_IDS.trader,
  ROLE_IDS.leadClient,
]);

const ASSIGNABLE_ROLES = Object.freeze({
  [ROLE_IDS.superAdmin]: [
    ROLE_IDS.superAdmin, ROLE_IDS.subAdmin, ROLE_IDS.founder,
    ROLE_IDS.marketAnalyst, ROLE_IDS.signalProvider,
    ROLE_IDS.seniorSignalProvider, ROLE_IDS.signalProviderManagement,
    ROLE_IDS.leader, ROLE_IDS.recruiter, ROLE_IDS.trader,
    ROLE_IDS.leadClient, ROLE_IDS.adminAssistant,
    ROLE_IDS.financialConsultant, ROLE_IDS.evaluator,
  ],
  [ROLE_IDS.subAdmin]: [
    ROLE_IDS.marketAnalyst, ROLE_IDS.signalProvider,
    ROLE_IDS.seniorSignalProvider, ROLE_IDS.signalProviderManagement,
    ROLE_IDS.leader, ROLE_IDS.recruiter, ROLE_IDS.trader,
    ROLE_IDS.leadClient, ROLE_IDS.financialConsultant, ROLE_IDS.evaluator,
  ],
  [ROLE_IDS.founder]: [
    ROLE_IDS.marketAnalyst, ROLE_IDS.signalProvider,
    ROLE_IDS.seniorSignalProvider, ROLE_IDS.signalProviderManagement,
    ROLE_IDS.leader, ROLE_IDS.recruiter, ROLE_IDS.trader,
    ROLE_IDS.leadClient, ROLE_IDS.financialConsultant, ROLE_IDS.evaluator,
  ],
  [ROLE_IDS.leader]: [
    ROLE_IDS.recruiter, ROLE_IDS.trader, ROLE_IDS.leadClient,
  ],
  [ROLE_IDS.marketAnalyst]: [ROLE_IDS.trader, ROLE_IDS.leadClient],
  [ROLE_IDS.signalProvider]: [ROLE_IDS.trader, ROLE_IDS.leadClient],
  [ROLE_IDS.seniorSignalProvider]: [ROLE_IDS.trader, ROLE_IDS.leadClient],
  [ROLE_IDS.signalProviderManagement]: [ROLE_IDS.trader, ROLE_IDS.leadClient],
  [ROLE_IDS.recruiter]: [ROLE_IDS.trader, ROLE_IDS.leadClient],
  [ROLE_IDS.trader]: [ROLE_IDS.trader, ROLE_IDS.leadClient],
});

const ADMIN_PERMISSION_KEYS = Object.freeze([
  "canInviteSuperAdmin", "canInviteSubAdmin", "canInviteMarketAnalyst",
  "canInviteSignalProvider", "canInviteFinancialDepartment",
  "canInviteLeader", "canInviteRecruiter", "canInviteTrader",
  "canInviteLeadClient", "canAccessAdminDashboard", "canManageUsers",
  "canManageSignals", "canManagePayments", "canManageReports",
]);

const PUBLIC_OWN_PERMISSIONS = Object.freeze([
  "public.toolkit.use", "public.riskCalculator.use",
  "journal.manual.readOwn", "journal.manual.createOwn",
  "journal.manual.updateOwn", "journal.manual.deleteOwn",
  "psychology.daily.readOwn", "psychology.daily.createOwn",
  "psychology.daily.updateOwn", "tradingPlan.readOwn",
  "tradingPlan.createOwn", "tradingPlan.updateOwn", "tradingPlan.deleteOwn",
  "tradingStrategy.readOwn", "tradingStrategy.createOwn",
  "tradingStrategy.updateOwn", "tradingStrategy.deleteOwn",
  "statistics.readOwn", "accessApplication.createOwn",
  "accessApplication.readOwn", "accessApplication.withdrawOwn",
]);

const APPROVED_CLIENT_PERMISSIONS = Object.freeze([
  "mt5.connectOwn", "mt5.syncOwn", "mt5.selectMainAccount",
  "statistics.readOwn", "performanceRating.readOwn", "reports.exportOwn",
  "signals.readApproved", "marketAnalysis.read",
]);

const ROLE_PERMISSIONS = Object.freeze({
  [ROLE_IDS.leadClient]: PUBLIC_OWN_PERMISSIONS,
  [ROLE_IDS.trader]: [...PUBLIC_OWN_PERMISSIONS, ...APPROVED_CLIENT_PERMISSIONS],
  [ROLE_IDS.signalProvider]: [
    "signals.createOwn", "signals.updateOwn", "signals.publishOwn",
    "signals.cancelOwn", "signals.readApproved",
  ],
  [ROLE_IDS.seniorSignalProvider]: [
    "signals.createOwn", "signals.updateOwn", "signals.publishOwn",
    "signals.cancelOwn", "signals.readApproved",
  ],
  [ROLE_IDS.signalProviderManagement]: [
    "signals.createOwn", "signals.updateOwn", "signals.publishOwn",
    "signals.cancelOwn", "signals.readApproved", "signals.manageAll",
  ],
  [ROLE_IDS.marketAnalyst]: [
    "marketAnalysis.read", "marketAnalysis.createOwn",
    "marketAnalysis.updateOwn", "marketAnalysis.publishOwn",
  ],
  [ROLE_IDS.leader]: [
    "team.readAssigned", "team.manageAssigned", "users.readAssigned",
    "accessApplication.reviewAssigned", "referral.viewConversions",
  ],
  [ROLE_IDS.recruiter]: [
    "referral.readOwn", "referral.manageOwn", "referral.viewConversions",
    "users.readAssigned",
  ],
  [ROLE_IDS.financialConsultant]: [
    "clients.readAssigned", "clients.manageAssigned", "users.readAssigned",
  ],
  [ROLE_IDS.evaluator]: [
    "evaluations.readAssigned", "evaluations.recommend",
    "accessApplication.reviewAssigned", "users.readAssigned",
  ],
  [ROLE_IDS.adminAssistant]: [
    "roles.read", "users.readAll", "users.updateBasic",
    "accessApplication.reviewAssigned", "auditLog.readAssigned",
  ],
  [ROLE_IDS.subAdmin]: [
    "roles.read", "users.readAll", "users.updateBasic", "users.suspend",
    "users.assignNonPrivilegedRole", "accessApplication.approve",
    "accessApplication.reject", "referral.manageAll", "auditLog.readAssigned",
  ],
  [ROLE_IDS.founder]: [
    "roles.read", "users.readAll", "users.updateBasic",
    "users.assignNonPrivilegedRole", "accessApplication.approve",
    "accessApplication.reject", "referral.manageAll", "auditLog.readAll",
    "system.settings.manage", "signals.manageAll", "marketAnalysis.manageAll",
  ],
});

function roleName(roleId) {
  return {
    [ROLE_IDS.superAdmin]: "Super Administrator",
    [ROLE_IDS.subAdmin]: "Sub Administrator",
    [ROLE_IDS.founder]: "Founder",
    [ROLE_IDS.signalProvider]: "Signal Provider",
    [ROLE_IDS.seniorSignalProvider]: "Senior Signal Provider",
    [ROLE_IDS.marketAnalyst]: "Market Analyst",
    [ROLE_IDS.signalProviderManagement]: "Signal Provider Management",
    [ROLE_IDS.leadClient]: "Potential Client",
    [ROLE_IDS.trader]: "Trader",
    [ROLE_IDS.leader]: "Trading Leadership",
    [ROLE_IDS.recruiter]: "Trading Recruiter",
    [ROLE_IDS.assistant]: "Admin Assistant",
    [ROLE_IDS.financialConsultant]: "Financial Consultant",
    [ROLE_IDS.evaluator]: "Evaluator",
  }[Number(roleId)] || "Member";
}

function assignableRoleIds(actorRoleId) {
  return new Set(ASSIGNABLE_ROLES[Number(actorRoleId)] || []);
}

function canAssignRole(actorRoleId, targetRoleId) {
  return assignableRoleIds(actorRoleId).has(Number(targetRoleId));
}

function isAdminRole(roleId) {
  return ADMIN_ROLES.has(Number(roleId));
}

function isPrivilegedRole(roleId) {
  return PRIVILEGED_ROLES.has(Number(roleId));
}

function isPublicRegistrationRole(roleId) {
  return PUBLIC_REGISTRATION_ROLES.has(Number(roleId));
}

function adminPermissionsForRole(roleId) {
  const assignable = assignableRoleIds(roleId);
  const isAdmin = isAdminRole(roleId);
  const permissions = Object.fromEntries(
      ADMIN_PERMISSION_KEYS.map((key) => [key, false]),
  );
  permissions.canInviteSuperAdmin = assignable.has(ROLE_IDS.superAdmin);
  permissions.canInviteSubAdmin = assignable.has(ROLE_IDS.subAdmin);
  permissions.canInviteMarketAnalyst = assignable.has(ROLE_IDS.marketAnalyst);
  permissions.canInviteSignalProvider =
    assignable.has(ROLE_IDS.signalProvider) ||
    assignable.has(ROLE_IDS.seniorSignalProvider) ||
    assignable.has(ROLE_IDS.signalProviderManagement);
  permissions.canInviteFinancialDepartment =
    assignable.has(ROLE_IDS.financialConsultant);
  permissions.canInviteLeader = assignable.has(ROLE_IDS.leader);
  permissions.canInviteRecruiter = assignable.has(ROLE_IDS.recruiter);
  permissions.canInviteTrader = assignable.has(ROLE_IDS.trader);
  permissions.canInviteLeadClient = assignable.has(ROLE_IDS.leadClient);
  permissions.canAccessAdminDashboard = isAdmin;
  permissions.canManageUsers = isAdmin;
  permissions.canManageSignals = isAdmin;
  permissions.canManagePayments = isAdmin;
  permissions.canManageReports = isAdmin;
  return permissions;
}

function publicRegistrationRoleOrDefault(roleId) {
  const numeric = Number(roleId);
  return isPublicRegistrationRole(numeric) ? numeric : ROLE_IDS.trader;
}

function effectiveRoleIds(user = {}) {
  const values = Array.isArray(user.role_ids) ? user.role_ids : [];
  const roles = new Set(values.map(Number).filter(Number.isFinite));
  const legacy = Number(user.role_id || 0);
  if (legacy) roles.add(legacy);
  return roles;
}

function accountUsable(user = {}) {
  const accountStatus = String(user.account_status || "active")
      .replace(/[-_\s]/g, "").toLowerCase();
  const accessStatus = String(user.access_status || user.accessStatus || "")
      .replace(/[-_\s]/g, "").toLowerCase();
  return user.is_suspended !== true && ![
    "inactive", "disabled", "suspended", "revoked", "deleted",
    "deletioninprogress",
  ].includes(accountStatus) &&
    !["suspended", "revoked", "deleted"].includes(accessStatus);
}

function hasApprovedAccess(user = {}) {
  const access = String(user.access_status || user.accessStatus || "")
      .replace(/[-_\s]/g, "").toLowerCase();
  return accountUsable(user) && (
    user.is_admin === true || access === "approved" ||
    effectiveRoleIds(user).has(ROLE_IDS.superAdmin)
  );
}

function permissionOverrides(user = {}) {
  const value = user.permission_overrides || user.permissionOverrides;
  return value && typeof value === "object" && !Array.isArray(value) ? value : {};
}

function hasPermission(user, permission, resource = {}) {
  if (!accountUsable(user)) return false;
  const overrides = permissionOverrides(user);
  if (overrides[permission] === false || overrides[permission] === "deny") {
    return false;
  }
  if (permission.endsWith("Own") && resource.ownerUserId &&
      resource.ownerUserId !== resource.actorUserId) return false;
  const roles = effectiveRoleIds(user);
  if (roles.has(ROLE_IDS.superAdmin)) return true;
  if (overrides[permission] === true || overrides[permission] === "allow") {
    return true;
  }
  if (PUBLIC_OWN_PERMISSIONS.includes(permission)) return true;
  if (APPROVED_CLIENT_PERMISSIONS.includes(permission)) {
    return hasApprovedAccess(user);
  }
  const allowed = [...roles].some((roleId) =>
    (ROLE_PERMISSIONS[roleId] || []).includes(permission));
  if (!allowed) return false;
  if (permission.includes("Assigned")) {
    if (resource.clientUserId &&
      !(user.assigned_client_ids || []).includes(resource.clientUserId)) {
      return false;
    }
    if (resource.teamId && !(user.team_ids || []).includes(resource.teamId)) {
      return false;
    }
    if (resource.evaluationId &&
      !(user.assigned_evaluation_ids || []).includes(resource.evaluationId)) {
      return false;
    }
  }
  return true;
}

module.exports = {
  ROLE_IDS,
  APPROVED_CLIENT_PERMISSIONS,
  PUBLIC_OWN_PERMISSIONS,
  ROLE_PERMISSIONS,
  accountUsable,
  adminPermissionsForRole,
  assignableRoleIds,
  canAssignRole,
  effectiveRoleIds,
  hasApprovedAccess,
  hasPermission,
  isAdminRole,
  isPrivilegedRole,
  isPublicRegistrationRole,
  publicRegistrationRoleOrDefault,
  roleName,
};
