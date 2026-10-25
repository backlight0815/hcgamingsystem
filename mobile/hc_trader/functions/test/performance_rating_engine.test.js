"use strict";

const assert = require("assert");
const {
  calculationHash,
  normalizeTrades,
  scoreDaily,
  scoreWeekly,
} = require("../performance_rating_engine");

function trade(id, profit, overrides = {}) {
  return {
    id,
    user_id: "user-1",
    type: "trade",
    source: "manual",
    status: "closed",
    open_date: "2026-07-12T17:00:00.000Z",
    close_date: "2026-07-12T17:10:00.000Z",
    pair: "EURUSD",
    lot_size: 1,
    profit_loss: profit,
    trading_plan_id: "plan-1",
    followed_trading_plan: true,
    plan_compliance_checklist: {stop_loss: true},
    ...overrides,
  };
}

const plan = {
  id: "plan-1",
  plan_id: "plan-1",
  version_id: "plan-v1",
  effective_from: "2026-01-01T00:00:00.000Z",
  is_active: true,
  account_capital: 10000,
  effective_risk_capital: 10000,
  risk_percent: 0.5,
  daily_max_loss_percent: 5,
  max_trades_per_day: 8,
  allowed_pairs: ["EURUSD"],
  cooldown_minutes: 30,
};
const psychology = {
  id: "psych-1",
  completed_at: "2026-07-12T16:30:00.000Z",
  emotion: "Calm",
  confidence: 8,
  discipline: 9,
};

function daily(records, options = {}) {
  return scoreDaily({
    userId: "user-1",
    date: "2026-07-13T00:00:00.000Z",
    timezone: "Asia/Singapore",
    timezoneOffsetMinutes: 480,
    dataSource: "manual",
    records,
    planVersions: [plan],
    psychologyChecks: [psychology],
    ...options,
  });
}

const compliantWin = daily([trade("win", 100)]);
assert.ok(compliantWin.trading_result_score > 50);
assert.equal(compliantWin.trading_discipline_score, 100);
assert.equal(compliantWin.psychology_score, 85);
assert.equal(compliantWin.trading_plan_version_id, "plan-v1");

const depositSafeReturn = daily([
  trade("profit", 100),
  trade("deposit", 5000, {type: "deposit"}),
], {initialCapital: 10000});
assert.equal(depositSafeReturn.net_trading_profit, 100);
assert.equal(depositSafeReturn.return_percentage, 1);
assert.deepEqual(depositSafeReturn.closed_trade_ids, ["profit"]);

const compliantLoss = daily([trade("loss", -30)]);
assert.ok(compliantLoss.trading_result_score < 50);
assert.equal(compliantLoss.trading_discipline_score, 100);

const profitableRisk = daily([trade("loss", -100), trade("win", 300, {
  open_date: "2026-07-12T18:30:00.000Z",
  close_date: "2026-07-12T18:40:00.000Z",
})]);
assert.ok(profitableRisk.trading_result_score > 50);
assert.ok(profitableRisk.violations.includes("risk_per_trade_exceeded"));
assert.ok(profitableRisk.trading_discipline_score <= 45);

const noStop = daily([trade("no-stop", 100, {
  plan_compliance_checklist: {stop_loss: false},
})]);
assert.ok(noStop.violations.includes("missing_stop_loss"));

const dailyLoss = daily([trade("daily-loss", -600)]);
assert.ok(dailyLoss.violations.includes("daily_loss_limit_exceeded"));

const overtrading = daily(Array.from({length: 9}, (_, index) =>
  trade(`trade-${index}`, 1, {
    open_date: `2026-07-12T17:${String(index).padStart(2, "0")}:00.000Z`,
  })));
assert.ok(overtrading.violations.includes("maximum_trades_exceeded"));

const revenge = daily([
  trade("revenge-loss", -10),
  trade("revenge-reentry", 20, {
    open_date: "2026-07-12T17:20:00.000Z",
    lot_size: 1.5,
  }),
]);
assert.ok(revenge.violations.includes("possible_revenge_trading"));

const missingPsych = daily([trade("trade", 10)], {psychologyChecks: []});
assert.equal(missingPsych.psychology_score, null);
assert.ok(missingPsych.missing_data.includes("psychology_check_missing"));

const excluded = daily([
  trade("deposit", 1000, {type: "deposit"}),
  trade("withdrawal", -500, {type: "withdrawal"}),
  trade("cancelled", 0, {status: "cancelled"}),
]);
assert.equal(excluded.evaluated_trade_ids.length, 0);
assert.equal(excluded.trading_result_score, 50);

const duplicate = trade("duplicate", 10);
const normalized = normalizeTrades([duplicate, duplicate], {
  source: "manual", accountId: "", localDate: "2026-07-13", offset: 480,
});
assert.equal(normalized.included.length, 1);

const mainMt5 = daily([
  trade("main", 20, {source: "mt5_sync", account_key: "account-a"}),
  trade("other", 200, {source: "mt5_sync", account_key: "account-b"}),
], {dataSource: "mt5", accountId: "account-a"});
assert.deepEqual(mainMt5.evaluated_trade_ids, ["main"]);

const repeat = daily([trade("stable", 50)]);
assert.equal(repeat.calculation_hash, daily([trade("stable", 50)]).calculation_hash);
assert.equal(calculationHash(repeat), repeat.calculation_hash);

const nextDay = {...noStop, id: "daily:next", period_start: "2026-07-14T00:00:00.000"};
const weekly = scoreWeekly({
  userId: "user-1",
  date: "2026-07-13",
  timezone: "Asia/Singapore",
  dataSource: "manual",
  dailyRatings: [{...compliantWin, id: "daily:safe"}, nextDay],
});
assert.equal(weekly.daily_rating_ids.length, 2);
assert.ok(weekly.violations.includes("missing_stop_loss"));
assert.ok(weekly.overall_score <= noStop.overall_score + 25);

const yearBoundary = scoreWeekly({
  userId: "user-1",
  date: "2026-01-02",
  timezone: "Asia/Singapore",
  dataSource: "manual",
  dailyRatings: [
    {...compliantWin, id: "a", period_start: "2025-12-29T00:00:00.000"},
    {...compliantWin, id: "b", period_start: "2026-01-02T00:00:00.000"},
  ],
});
assert.equal(yearBoundary.period_start.slice(0, 10), "2025-12-29");
assert.equal(yearBoundary.period_end.slice(0, 10), "2026-01-05");

console.log("Performance rating engine tests passed.");
