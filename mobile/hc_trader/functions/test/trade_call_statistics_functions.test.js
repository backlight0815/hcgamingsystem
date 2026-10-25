"use strict";

const assert = require("assert");
const {HttpsError} = require("firebase-functions/v2/https");
const {ROLE_IDS} = require("../role_permissions");
const {
  pageSize,
  providerId,
  signalProjection,
  statisticsScope,
} = require("../trade_call_statistics_functions")._test;

function user(roleId) {
  return {
    role_id: roleId,
    role_ids: [roleId],
    account_status: "active",
  };
}

assert.deepStrictEqual(
    statisticsScope("admin-1", user(ROLE_IDS.superAdmin), "provider-2"),
    {isAdministrator: true, providerId: "provider-2"},
);
assert.deepStrictEqual(
    statisticsScope("provider-1", user(ROLE_IDS.signalProvider), ""),
    {isAdministrator: false, providerId: "provider-1"},
);
assert.throws(
    () => statisticsScope(
        "provider-1",
        user(ROLE_IDS.signalProvider),
        "provider-2",
    ),
    (error) => error instanceof HttpsError && error.code === "permission-denied",
);
assert.throws(
    () => statisticsScope("trader-1", user(ROLE_IDS.trader), ""),
    (error) => error instanceof HttpsError && error.code === "permission-denied",
);
assert.throws(
    () => statisticsScope("provider-1", {
      ...user(ROLE_IDS.signalProvider),
      is_suspended: true,
    }, ""),
    (error) => error instanceof HttpsError && error.code === "permission-denied",
);

assert.strictEqual(providerId({created_by: "legacy-provider"}), "legacy-provider");
assert.strictEqual(pageSize(undefined), 500);
assert.strictEqual(pageSize(250), 250);
assert.strictEqual(pageSize(10000), 500);
assert.strictEqual(pageSize("invalid"), 500);
const projected = signalProjection("call-1", {
  provider_id: "provider-1",
  trading_pair: "EURUSD",
  private_moderation_notes: "must not leave backend",
  target_1: 1.2,
  created_at: new Date("2026-07-01T00:00:00Z"),
});
assert.strictEqual(projected.id, "call-1");
assert.strictEqual(projected.provider_id, "provider-1");
assert.strictEqual(projected.target_1, "1.2");
assert.strictEqual(projected.created_at, "2026-07-01T00:00:00.000Z");
assert.strictEqual(projected.private_moderation_notes, undefined);

console.log("trade-call statistics callable tests passed");
