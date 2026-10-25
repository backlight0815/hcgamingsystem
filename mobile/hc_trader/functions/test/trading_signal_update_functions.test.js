const assert = require("node:assert/strict");
const {
  notificationEventId,
  notificationClaimDecision,
  buildSignalUpdateNotification,
  userCanReceiveSignal,
  preferenceEnabled,
  quietHoursState,
  deliveryId,
  featureConfigurationAllows,
} = require("../trading_signal_update_functions");

assert.equal(
    notificationEventId("signal-1", "update-2"),
    "signal-1_update-2",
);

const now = 1_000_000;
assert.equal(notificationClaimDecision({status: "sent"}, now), "duplicate");
assert.equal(notificationClaimDecision({status: "failed"}, now), "claim");
assert.equal(notificationClaimDecision({
  status: "sending",
  claimed_at: {toMillis: () => now - 30_000},
}, now), "leased");
assert.equal(notificationClaimDecision({
  status: "sending",
  claimed_at: {toMillis: () => now - 180_000},
}, now), "claim");

assert.deepEqual(
    buildSignalUpdateNotification(
        {
          update_type: "target_hit",
          target_number: 2,
          primary_language: "zh",
        },
        {trading_pair: "XAUUSD"},
    ),
    {
      language: "zh",
      title: "止盈目标已达到",
      body: "XAUUSD：TP2 已达到",
    },
);

assert.deepEqual(
    buildSignalUpdateNotification(
        {
          update_type: "commentary",
          primary_language: "en",
          message_i18n: {zh: "等待确认"},
        },
        {trading_pair: "EURUSD"},
    ),
    {
      language: "en",
      title: "Signal update",
      body: "EURUSD: 等待确认",
    },
);

assert.equal(userCanReceiveSignal("u1", {
  account_status: "active",
  access_status: "approved",
  role_id: 750,
}, {target_role_ids: [750]}), true);
assert.equal(userCanReceiveSignal("u1", {
  account_status: "suspended",
  access_status: "approved",
  role_id: 750,
}, {}), false);
assert.equal(userCanReceiveSignal("u1", {
  account_status: "active",
  access_status: "approved",
  role_id: 750,
}, {target_user_ids: ["u2"]}), false);

assert.equal(preferenceEnabled({signals: true, take_profit_events: false},
    "target_hit"), false);
assert.equal(preferenceEnabled({signals: true, take_profit_events: true},
    "target_hit"), true);
assert.equal(preferenceEnabled({signals: false}, "published"), false);

const midnight = Date.UTC(2026, 0, 1, 23, 0);
const quiet = quietHoursState({
  quiet_hours_enabled: true,
  quiet_hours_start_minutes: 1320,
  quiet_hours_end_minutes: 420,
}, 0, midnight);
assert.equal(quiet.quiet, true);
assert.ok(quiet.deliverAfter > midnight);
assert.equal(quietHoursState({quiet_hours_enabled: false}, 0, midnight).quiet,
    false);

assert.equal(deliveryId("event", "user", "device"),
    deliveryId("event", "user", "device"));
assert.notEqual(deliveryId("event", "user", "device"),
    deliveryId("event", "user", "other-device"));

assert.equal(featureConfigurationAllows({mode: "maintenance"}, "u1", {}),
    false);
assert.equal(featureConfigurationAllows({
  key: "signals",
  mode: "enabled",
  role_ids: [750],
  rollout_percentage: 100,
}, "u1", {role_id: 750}), true);
assert.equal(featureConfigurationAllows({
  key: "signals",
  mode: "enabled",
  role_ids: [501],
  rollout_percentage: 100,
}, "u1", {role_id: 750}), false);

console.log("trading signal update function tests passed");
