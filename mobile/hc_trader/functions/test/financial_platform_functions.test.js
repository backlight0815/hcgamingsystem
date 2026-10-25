"use strict";

const assert = require("node:assert/strict");
const {
  symbolValue,
  reportIdForDate,
  localExchangeParts,
  generatedReportContent,
  REPORT_TRANSITIONS,
  compareVersions,
} = require("../stock_market_functions");
const {
  normalizedConfiguration,
  legacyEnabled,
  featureKey,
} = require("../feature_configuration_functions");
const {
  calculateRiskAdjustedScore,
  selectCanonicalRatings,
  aggregateRatings,
  periodBounds,
  periodId,
  rankLeaderboardEntries,
} = require("../leaderboard_functions");
const {
  configurationAllowsWrite,
  millis,
} = require("../publishing_schedule_functions");

assert.equal(symbolValue(" brk.b "), "BRK.B");
assert.throws(() => symbolValue("../../secret"));
assert.equal(reportIdForDate(new Date("2026-07-16T12:00:00Z")), "2026-07-16");
assert.deepEqual(localExchangeParts(
    new Date("2026-07-16T01:00:00Z"), "Asia/Singapore"), {
  date: "2026-07-16", hour: 9, weekday: "Thu",
});
const generated = generatedReportContent({
  symbol: "AAPL", company_name: "Apple",
}, {quote: {price: 200, change_percent: 1.25}},
new Date("2026-07-16T00:00:00Z"));
assert.match(generated.title_i18n.en, /AAPL/);
assert.match(generated.title_i18n.zh, /财经日报/);
assert.match(generated.content_i18n.en, /does not make a price prediction/i);
assert.equal(REPORT_TRANSITIONS.draft.has("under_review"), true);
assert.equal(REPORT_TRANSITIONS.published.has("draft"), false);
assert.equal(compareVersions("1.7.3+1023", "1.7.2"), 1);
assert.equal(compareVersions("1.7.3", "1.7.3"), 0);

assert.equal(featureKey("stock_information"), "stock_information");
assert.throws(() => featureKey("Stock information"));
const feature = normalizedConfiguration({
  name: "Stock reports",
  mode: "beta",
  rollout_percentage: 25,
  platforms: ["ios", "android", "invalid"],
  environments: ["production"],
  dependencies: ["stock_information"],
}, "stock_reports", 3);
assert.equal(feature.version, 3);
assert.deepEqual(feature.platforms, ["ios", "android"]);
assert.equal(legacyEnabled(feature), true);
assert.equal(legacyEnabled({...feature, mode: "hidden", visible: false}), false);

assert.equal(calculateRiskAdjustedScore({
  return_percentage: 20,
  max_drawdown_percentage: 4,
  discipline_score: 90,
  consistency_score: 80,
  trade_count: 20,
  trading_days: 8,
  verified: true,
}), 50);
assert.equal(calculateRiskAdjustedScore({
  return_percentage: 200,
  max_drawdown_percentage: 0,
  discipline_score: 100,
  consistency_score: 100,
  trade_count: 2,
  trading_days: 1,
  verified: true,
}), 0);

function rating(id, day, source, tradeCount) {
  return {
    id,
    data: () => ({
      id,
      user_id: "u1",
      period_start: `${day}T00:00:00Z`,
      data_source: source,
      calculation_hash: `hash-${id}`,
      evaluated_trade_ids: Array.from({length: tradeCount}, (_, index) =>
        `${id}-trade-${index}`),
      trading_discipline_score: 90,
      trading_plan_compliance_score: 85,
      consistency_score: 80,
      return_percentage: 2,
      net_trading_profit: 200,
      max_drawdown_percentage: 3,
    }),
  };
}

const canonical = selectCanonicalRatings([
  rating("manual", "2026-07-15", "manual", 5),
  rating("mt5", "2026-07-15", "mt5", 3),
], "mt5");
assert.equal(canonical.length, 1);
assert.equal(canonical[0].data_source, "mt5");
const metric = aggregateRatings([
  rating("a", "2026-07-14", "mt5", 6),
  rating("b", "2026-07-15", "mt5", 6),
], {trading_data_display_mode: "mt5Only", trading_capital: 10000}, {
  minimum_trades: 10, minimum_trading_days: 2,
});
assert.equal(metric.trade_count, 12);
assert.equal(metric.trading_days, 2);
assert.equal(metric.verified, true);

const bounds = periodBounds(new Date("2026-07-16T12:00:00Z"), "monthly");
assert.equal(bounds.start.toISOString(), "2026-07-01T00:00:00.000Z");
assert.equal(bounds.end.toISOString(), "2026-08-01T00:00:00.000Z");
assert.equal(periodId("risk_adjusted_return", "monthly", bounds.start),
    "risk_adjusted_return_monthly_2026-07-01");
const tied = rankLeaderboardEntries([
  {user_id: "c", score: 80, return_percentage: 8, max_drawdown_percentage: 2},
  {user_id: "a", score: 90, return_percentage: 10, max_drawdown_percentage: 3},
  {user_id: "b", score: 90, return_percentage: 9, max_drawdown_percentage: 2},
]);
assert.deepEqual(tied.map((entry) => entry.rank), [1, 1, 3]);
assert.equal(millis({toMillis: () => 1234}), 1234);
assert.equal(configurationAllowsWrite({mode: "enabled"}), true);
assert.equal(configurationAllowsWrite({mode: "readOnly"}), false);
assert.equal(configurationAllowsWrite({
  mode: "enabled",
  starts_at: "2026-07-17T00:00:00Z",
}, Date.parse("2026-07-16T00:00:00Z")), false);

console.log("financial platform function tests passed");
