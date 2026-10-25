"use strict";

const assert = require("assert");
const {
  ROLE_IDS,
  accountUsable,
  effectiveRoleIds,
  hasPermission,
  isPrivilegedRole,
} = require("../role_permissions");

const potential = {role_id: ROLE_IDS.potentialClient, account_status: "active"};
assert.equal(hasPermission(potential, "public.toolkit.use"), true);
assert.equal(hasPermission(potential, "journal.manual.createOwn", {
  actorUserId: "a", ownerUserId: "a",
}), true);
assert.equal(hasPermission(potential, "journal.manual.readOwn", {
  actorUserId: "a", ownerUserId: "b",
}), false);
assert.equal(hasPermission(potential, "mt5.syncOwn"), false);

const trader = {
  role_id: ROLE_IDS.trader,
  role_ids: [ROLE_IDS.trader],
  access_status: "approved",
  account_status: "active",
};
assert.equal(hasPermission(trader, "mt5.syncOwn"), true);
assert.equal(hasPermission(trader, "users.readAll"), false);

const unapprovedAnalyst = {
  role_id: ROLE_IDS.marketAnalyst,
  role_ids: [ROLE_IDS.marketAnalyst],
  access_status: "unapproved",
  account_status: "active",
};
assert.equal(hasPermission(unapprovedAnalyst, "accessApplication.createOwn"), true);
assert.equal(hasPermission(unapprovedAnalyst, "mt5.connectOwn"), false);
assert.equal(hasPermission({
  ...unapprovedAnalyst,
  access_status: "approved",
}, "mt5.connectOwn"), true);

const multiRole = {
  ...trader,
  role_ids: [ROLE_IDS.trader, ROLE_IDS.signalProvider, ROLE_IDS.marketAnalyst],
};
assert.equal(hasPermission(multiRole, "signals.publishOwn"), true);
assert.equal(hasPermission(multiRole, "marketAnalysis.publishOwn"), true);
assert.deepEqual([...effectiveRoleIds(multiRole)].sort(), [201, 501, 750]);

const leader = {
  role_id: ROLE_IDS.leader,
  account_status: "active",
  team_ids: ["team-a"],
};
assert.equal(hasPermission(leader, "team.readAssigned", {teamId: "team-a"}), true);
assert.equal(hasPermission(leader, "team.readAssigned", {teamId: "team-b"}), false);

const consultant = {
  role_id: ROLE_IDS.financialConsultant,
  account_status: "active",
  assigned_client_ids: ["client-a"],
};
assert.equal(hasPermission(consultant, "clients.readAssigned", {
  clientUserId: "client-a",
}), true);
assert.equal(hasPermission(consultant, "clients.readAssigned", {
  clientUserId: "client-b",
}), false);

const evaluator = {
  role_id: ROLE_IDS.evaluator,
  account_status: "active",
  assigned_evaluation_ids: ["case-a"],
};
assert.equal(hasPermission(evaluator, "evaluations.readAssigned", {
  evaluationId: "case-a",
}), true);

const suspendedAdmin = {
  role_id: ROLE_IDS.superAdmin,
  account_status: "active",
  is_suspended: true,
};
assert.equal(accountUsable(suspendedAdmin), false);
assert.equal(hasPermission(suspendedAdmin, "system.security.manage"), false);

const deniedAdmin = {
  role_id: ROLE_IDS.superAdmin,
  account_status: "active",
  permission_overrides: {"users.readAll": false},
};
assert.equal(hasPermission(deniedAdmin, "users.readAll"), false);
assert.equal(isPrivilegedRole(ROLE_IDS.superAdmin), true);
assert.equal(isPrivilegedRole(ROLE_IDS.founder), true);
assert.equal(isPrivilegedRole(ROLE_IDS.trader), false);

console.log("Role permission tests passed.");
