"use strict";

const crypto = require("crypto");

const DEFAULT_CONFIG = Object.freeze({
  algorithmVersion: "hc-performance-v1.0.0",
  resultWeight: 0.20,
  disciplineWeight: 0.30,
  psychologyWeight: 0.20,
  planComplianceWeight: 0.30,
  penalties: {
    missingStopLoss: 30,
    riskExceeded: 25,
    dailyLossExceeded: 35,
    overtrading: 15,
    revengePattern: 15,
  },
  caps: {seriousDiscipline: 45, seriousPlan: 55},
});

function scoreDaily(input, suppliedConfig = {}) {
  const config = mergeConfig(suppliedConfig);
  const offset = number(input.timezoneOffsetMinutes, 480);
  const localDate = dateKey(input.date || new Date(), offset);
  const source = input.dataSource === "mt5" ? "mt5" : "manual";
  const accountId = text(input.accountId);
  const normalized = normalizeTrades(
      input.records || [], {source, accountId, localDate, offset});
  const trades = normalized.included;
  const plan = planForDate(input.planVersions || [], localDate, offset);
  const closedTrades = trades.filter((trade) =>
    text(trade.status, "closed").toLowerCase() === "closed");
  const netTradingProfit = closedTrades.reduce((sum, trade) =>
    sum + profit(trade), 0);
  const planCapital = plan ? number(
      planRules(plan).effective_risk_capital || planRules(plan).account_capital,
      0,
  ) : 0;
  const initialCapital = number(input.initialCapital, planCapital);
  let runningProfit = 0;
  let peakProfit = 0;
  let maxDrawdownAmount = 0;
  for (const trade of closedTrades) {
    runningProfit += profit(trade);
    peakProfit = Math.max(peakProfit, runningProfit);
    maxDrawdownAmount = Math.max(maxDrawdownAmount, peakProfit - runningProfit);
  }
  const psychology = psychologyForDate(
      input.psychologyChecks || [], localDate, offset);
  const violations = [];
  const positives = [];
  const missingData = [];
  const recommendations = [];
  const resultScore = tradingResultScore(trades, missingData, positives);
  let disciplineScore = 100;
  let planComplianceScore = plan ? 100 : 50;
  let serious = false;
  if (!plan) {
    missingData.push("trading_plan_missing");
    recommendations.push("create_active_trading_plan");
  }

  const missingStop = trades.some(knownMissingStopLoss);
  if (missingStop) {
    disciplineScore -= config.penalties.missingStopLoss;
    planComplianceScore -= config.penalties.missingStopLoss;
    violations.push("missing_stop_loss");
    recommendations.push("always_define_stop_loss");
    serious = true;
  } else if (trades.length && trades.every((trade) => !stopLossKnown(trade))) {
    missingData.push("stop_loss_data_missing");
  }

  if (plan) {
    const rules = planRules(plan);
    if (trades.length > number(rules.max_trades_per_day, 8)) {
      disciplineScore -= config.penalties.overtrading;
      planComplianceScore -= config.penalties.overtrading;
      violations.push("maximum_trades_exceeded");
      recommendations.push("respect_maximum_trades");
    }
    const capital = number(
        rules.effective_risk_capital || rules.account_capital, 0);
    if (capital > 0) {
      const dailyLoss = trades.filter((trade) => profit(trade) < 0)
          .reduce((sum, trade) => sum + Math.abs(profit(trade)), 0);
      if (dailyLoss / capital * 100 > number(rules.daily_max_loss_percent, 5)) {
        disciplineScore -= config.penalties.dailyLossExceeded;
        planComplianceScore -= config.penalties.dailyLossExceeded;
        violations.push("daily_loss_limit_exceeded");
        recommendations.push("stop_at_daily_loss_limit");
        serious = true;
      }
      if (trades.some((trade) => profit(trade) < 0 &&
        Math.abs(profit(trade)) / capital * 100 >
          number(rules.risk_percent, 0.5))) {
        disciplineScore -= config.penalties.riskExceeded;
        planComplianceScore -= config.penalties.riskExceeded;
        violations.push("risk_per_trade_exceeded");
        recommendations.push("reduce_risk_per_trade");
        serious = true;
      }
    } else if (trades.length) {
      missingData.push("capital_basis_missing");
    }
    const allowedPairs = strings(rules.allowed_pairs)
        .map((value) => value.toUpperCase());
    if (allowedPairs.length && trades.some((trade) =>
      !allowedPairs.includes(text(trade.pair || trade.symbol).toUpperCase()))) {
      disciplineScore -= 10;
      planComplianceScore -= 15;
      violations.push("instrument_not_allowed");
    }
    const allowedDays = strings(
        rules.allowed_trading_days || rules.allowedTradingDays)
        .map((value) => value.toLowerCase());
    if (allowedDays.length && !allowedDays.includes(weekday(localDate))) {
      disciplineScore -= 10;
      planComplianceScore -= 15;
      violations.push("trading_day_not_allowed");
    }
    for (const trade of trades) {
      const planId = text(trade.trading_plan_id || trade.tradingPlanId);
      if (!planId) {
        missingData.push("trade_plan_link_missing");
      } else if (!bool(trade.followed_trading_plan ?? trade.followedTradingPlan)) {
        planComplianceScore -= 15;
        violations.push("trading_plan_not_followed");
      }
      const allowedStrategies = strings(rules.allowed_strategy_ids);
      if (allowedStrategies.length) {
        const strategyId = text(trade.strategy_id || trade.strategyId);
        if (!strategyId) missingData.push("strategy_link_missing");
        else if (!allowedStrategies.includes(strategyId)) {
          planComplianceScore -= 10;
          violations.push("strategy_not_allowed");
        }
      }
      const checklist = object(
          trade.plan_compliance_checklist || trade.planComplianceChecklist);
      const checks = Object.values(checklist);
      const riskRewardMet = checklist.risk_reward_met ?? checklist.riskRewardMet;
      if (riskRewardMet === false) {
        disciplineScore -= 10;
        planComplianceScore -= 15;
        violations.push("risk_reward_below_plan");
      }
      if (!checks.length) missingData.push("plan_checklist_incomplete");
      else {
        const failed = checks.filter((value) => value === false).length;
        planComplianceScore -= failed * 5;
        if (failed) violations.push("entry_rule_not_confirmed");
      }
    }
  }

  if (possibleRevengePattern(trades, plan ? planRules(plan) : {})) {
    disciplineScore -= config.penalties.revengePattern;
    violations.push("possible_revenge_trading");
    recommendations.push("pause_after_a_loss");
  }
  if (serious) {
    disciplineScore = Math.min(disciplineScore, config.caps.seriousDiscipline);
    planComplianceScore = Math.min(
        planComplianceScore, config.caps.seriousPlan);
  }
  disciplineScore = score(disciplineScore);
  planComplianceScore = score(planComplianceScore);
  if (trades.length && !violations.length) {
    positives.push("rules_followed_without_detected_violation");
  }

  let psychologyScore = null;
  if (!psychology) {
    missingData.push("psychology_check_missing");
    recommendations.push("complete_psychology_check_before_trading");
  } else {
    psychologyScore = psychologyReadinessScore(psychology);
    const firstTrade = trades[0] && timestamp(trades[0].open_date);
    if (firstTrade && timestamp(
        psychology.completed_at || psychology.created_at) > firstTrade) {
      missingData.push("psychology_check_completed_after_trading");
    } else {
      positives.push("psychology_check_completed_before_trading");
    }
  }
  const overallScore = overall({
    result: resultScore,
    discipline: disciplineScore,
    psychology: psychologyScore,
    plan: planComplianceScore,
  }, config);
  const result = {
    user_id: text(input.userId),
    period_type: "daily",
    period_start: `${localDate}T00:00:00.000`,
    period_end: `${nextDateKey(localDate)}T00:00:00.000`,
    timezone: text(input.timezone, "Asia/Singapore"),
    timezone_offset_minutes: offset,
    data_source: source,
    account_id: accountId || null,
    trading_plan_id: plan ? text(plan.plan_id || plan.id) : null,
    trading_plan_version_id: plan ? text(
        plan.version_id || plan.versionId || plan.id) : null,
    algorithm_version: config.algorithmVersion,
    trading_result_score: resultScore,
    trading_discipline_score: disciplineScore,
    psychology_score: psychologyScore,
    trading_plan_compliance_score: planComplianceScore,
    consistency_score: 100,
    overall_score: overallScore,
    net_trading_profit: Math.round(netTradingProfit * 100) / 100,
    return_percentage: initialCapital > 0 ?
      Math.round(netTradingProfit / initialCapital * 10000) / 100 : 0,
    max_drawdown_percentage: initialCapital > 0 ?
      Math.round(maxDrawdownAmount / initialCapital * 10000) / 100 : 0,
    closed_trade_ids: closedTrades.map((trade) => text(trade.id)),
    rating_band: ratingBand(overallScore),
    evaluated_trade_ids: trades.map((trade) => text(trade.id)),
    excluded_record_ids: normalized.excluded,
    violations: unique(violations),
    positive_behaviours: unique(positives),
    missing_data: unique(missingData),
    recommendations: unique(recommendations),
    score_breakdown: {
      trading_result: resultScore,
      trading_discipline: disciplineScore,
      psychology: psychologyScore,
      trading_plan_compliance: planComplianceScore,
    },
  };
  result.calculation_hash = calculationHash(result);
  return result;
}

function scoreWeekly(input, suppliedConfig = {}) {
  const config = mergeConfig(suppliedConfig);
  const start = mondayDateKey(text(input.weekStart || input.date));
  const end = addDays(start, 7);
  const days = (input.dailyRatings || []).filter((day) => {
    const key = text(day.period_start).slice(0, 10);
    return day.period_type === "daily" && key >= start && key < end &&
      day.data_source === input.dataSource &&
      (!input.accountId || day.account_id === input.accountId);
  }).sort((a, b) => text(a.period_start).localeCompare(text(b.period_start)));
  const resultScore = average(days.map((day) => day.trading_result_score), 50);
  const disciplineScore = average(
      days.map((day) => day.trading_discipline_score), 100);
  const psychologyScores = days.map((day) => day.psychology_score)
      .filter((value) => value != null);
  const psychologyScore = psychologyScores.length ?
    average(psychologyScores, 50) : null;
  const planScore = average(
      days.map((day) => day.trading_plan_compliance_score), 100);
  let consistency = 100 - stddev(
      days.map((day) => day.trading_discipline_score)) - stddev(
      days.map((day) => day.trading_plan_compliance_score));
  const counts = {};
  for (const day of days) for (const code of day.violations || []) {
    counts[code] = (counts[code] || 0) + 1;
  }
  consistency -= Object.values(counts)
      .filter((count) => count > 1)
      .reduce((sum, count) => sum + (count - 1) * 8, 0);
  if (days.length) {
    consistency -= days.filter((day) => day.psychology_score == null).length /
      days.length * 15;
  }
  consistency = score(consistency);
  let overallScore = overall({
    result: resultScore, discipline: disciplineScore,
    psychology: psychologyScore, plan: planScore,
  }, config);
  const seriousCodes = new Set([
    "missing_stop_loss", "daily_loss_limit_exceeded", "risk_per_trade_exceeded",
  ]);
  const serious = days.filter((day) =>
    (day.violations || []).some((code) => seriousCodes.has(code)));
  if (serious.length) {
    overallScore = Math.min(overallScore,
        Math.min(...serious.map((day) => day.overall_score)) + 25);
  }
  const result = {
    user_id: text(input.userId),
    period_type: "weekly",
    period_start: `${start}T00:00:00.000`,
    period_end: `${end}T00:00:00.000`,
    timezone: text(input.timezone, "Asia/Singapore"),
    data_source: input.dataSource === "mt5" ? "mt5" : "manual",
    account_id: text(input.accountId) || null,
    trading_plan_id: days.length ? days.at(-1).trading_plan_id : null,
    trading_plan_version_id: days.length ?
      days.at(-1).trading_plan_version_id : null,
    algorithm_version: config.algorithmVersion,
    daily_rating_ids: days.map((day) => day.id),
    trading_result_score: resultScore,
    trading_discipline_score: disciplineScore,
    psychology_score: psychologyScore,
    trading_plan_compliance_score: planScore,
    consistency_score: consistency,
    overall_score: score(overallScore),
    rating_band: ratingBand(overallScore),
    evaluated_trade_ids: unique(days.flatMap((day) =>
      day.evaluated_trade_ids || [])),
    excluded_record_ids: unique(days.flatMap((day) =>
      day.excluded_record_ids || [])),
    violations: unique(days.flatMap((day) => day.violations || [])),
    positive_behaviours: unique(days.flatMap((day) =>
      day.positive_behaviours || [])),
    missing_data: unique(days.flatMap((day) => day.missing_data || [])),
    recommendations: unique(days.flatMap((day) => day.recommendations || [])),
    score_breakdown: {
      trading_result: resultScore,
      trading_discipline: disciplineScore,
      psychology: psychologyScore,
      trading_plan_compliance: planScore,
      consistency,
    },
  };
  result.calculation_hash = calculationHash(result);
  return result;
}

function normalizeTrades(records, scope) {
  const included = [];
  const excluded = [];
  const seen = new Set();
  for (const original of records) {
    const trade = {...original};
    const id = text(trade.id || trade.lifecycle_key || trade.ticket);
    const source = text(trade.source || trade.record_source, "manual");
    const isMt5 = source === "mt5_sync" || source === "mt5" ||
      source === "mt5_desktop_excel";
    const status = text(trade.status, "closed").toLowerCase();
    const recordType = text(trade.record_type || trade.type, "trade").toLowerCase();
    const trading = !["balance", "credit", "bonus", "deposit", "withdrawal",
      "transfer", "adjustment"].includes(recordType);
    const executed = trading && ![
      "cancelled", "canceled", "deleted", "expired", "pending", "draft",
    ].includes(status);
    const account = text(trade.account_key || trade.trading_account_id);
    const stable = [source, account, id || text(trade.position_id),
      text(trade.order_ticket), text(trade.deal_ticket)].join(":");
    const sameSource = scope.source === "mt5" ? isMt5 : !isMt5;
    const sameAccount = scope.source !== "mt5" || !scope.accountId ||
      account === scope.accountId;
    const sameDate = dateKey(trade.open_date || trade.open_time,
        scope.offset) === scope.localDate;
    if (!id || !sameSource || !sameAccount || !sameDate || !executed ||
        seen.has(stable)) {
      excluded.push(id || stable);
      continue;
    }
    seen.add(stable);
    included.push(trade);
  }
  included.sort((a, b) => timestamp(a.open_date || a.open_time) -
    timestamp(b.open_date || b.open_time));
  return {included, excluded: unique(excluded)};
}

function tradingResultScore(trades, missingData, positives) {
  const closed = trades.filter((trade) => text(trade.status, "closed") === "closed");
  if (!closed.length) {
    missingData.push(trades.length ? "no_closed_trades" : "no_trades_for_period");
    return 50;
  }
  const wins = closed.filter((trade) => profit(trade) > 0);
  const losses = closed.filter((trade) => profit(trade) < 0);
  const grossProfit = wins.reduce((sum, trade) => sum + profit(trade), 0);
  const grossLoss = losses.reduce((sum, trade) => sum + Math.abs(profit(trade)), 0);
  const net = grossProfit - grossLoss;
  const decisive = wins.length + losses.length;
  const winRate = decisive ? wins.length / decisive * 100 : 50;
  const profitFactor = grossLoss ? grossProfit / grossLoss : (grossProfit ? 3 : 1);
  let value = 50 + (net > 0 ? 15 : net < 0 ? -15 : 0);
  value += clamp((winRate - 50) * 0.3, -15, 15);
  value += clamp((profitFactor - 1) * 10, -15, 15);
  if (net > 0) positives.push("positive_realised_result");
  return score(clamp(value, 5, 95));
}

function psychologyReadinessScore(check) {
  const normalize = (raw) => {
    const value = number(raw, 5);
    return clamp(value <= 10 ? value * 10 : value, 0, 100);
  };
  const revenge = bool(check.revenge_urge || check.revengeUrge);
  const mood = revenge ? "revenge" : canonicalMood(check.emotion);
  const profiles = {
    great: {score: 100},
    focused: {score: 90},
    calm: {score: 85},
    neutral: {score: 65},
    excited: {score: 70},
    anxious: {score: 45, cap: 80},
    frustrated: {score: 30, cap: 75},
    angry: {score: 20, cap: 60},
    fearful: {score: 25, cap: 65},
    revenge: {score: 10, cap: 45},
  };
  const profile = profiles[mood] || {score: 65};
  const confidence = normalize(check.confidence);
  const discipline = normalize(check.discipline);
  let value = discipline * .35 + confidence * .35 + profile.score * .30;
  if (bool(check.fomo)) value -= 10;
  if (revenge) value -= 15;
  if (bool(check.overconfidence)) value -= 8;
  let cap = profile.cap;
  if (confidence <= 20) cap = cap == null ? 55 : Math.min(cap, 55);
  if (confidence <= 20 && profile.cap != null) {
    cap = cap == null ? 45 : Math.min(cap, 45);
  }
  if (revenge) cap = cap == null ? 45 : Math.min(cap, 45);
  value = clamp(value, 0, 100);
  return score(cap == null ? value : Math.min(value, cap));
}

function canonicalMood(raw) {
  const mood = text(raw).trim().toLowerCase();
  if (mood.includes("great") || mood.includes("很好")) return "great";
  if (mood.includes("focused")) return "focused";
  if (mood.includes("calm") || mood.includes("平静")) return "calm";
  if (mood.includes("neutral") || mood.includes("普通")) return "neutral";
  if (mood.includes("anxious") || mood.includes("焦虑")) return "anxious";
  if (mood.includes("frustrated") || mood.includes("discouraged") ||
      mood.includes("沮丧")) return "frustrated";
  if (mood.includes("angry") || mood.includes("生气")) return "angry";
  if (mood.includes("fearful") || mood.includes("afraid") ||
      mood.includes("害怕")) return "fearful";
  if (mood.includes("revenge") || mood.includes("报复性交易")) return "revenge";
  if (mood.includes("excited")) return "excited";
  return mood;
}

function possibleRevengePattern(trades, plan) {
  const cooldown = number(plan.cooldown_minutes, 30);
  for (let index = 1; index < trades.length; index++) {
    const previous = trades[index - 1];
    const current = trades[index];
    if (profit(previous) >= 0) continue;
    const minutes = (timestamp(current.open_date || current.open_time) -
      timestamp(previous.close_date || previous.close_time)) / 60000;
    if (minutes >= 0 && minutes <= cooldown &&
      number(current.lot_size, 0) > number(previous.lot_size, 0) * 1.25) {
      return true;
    }
  }
  return false;
}

function knownMissingStopLoss(trade) {
  const checks = object(
      trade.plan_compliance_checklist || trade.planComplianceChecklist);
  const tags = strings(trade.mistake_tags).map((tag) => tag.toLowerCase());
  return checks.stop_loss === false || checks.stopLoss === false ||
    ["missing_stop_loss", "no_stop_loss", "without_sl"]
        .some((tag) => tags.includes(tag));
}

function stopLossKnown(trade) {
  const checks = object(
      trade.plan_compliance_checklist || trade.planComplianceChecklist);
  return Object.hasOwn(checks, "stop_loss") || Object.hasOwn(checks, "stopLoss") ||
    bool(trade.followed_sl_rule) || knownMissingStopLoss(trade) ||
    number(trade.sl, 0) !== 0;
}

function planForDate(plans, localDate, offset) {
  return plans.filter((plan) => {
    const rules = planRules(plan);
    const active = rules.is_active !== false && plan.is_active !== false;
    const start = dateKey(plan.effective_from || plan.created_at, offset);
    const end = plan.effective_to ? dateKey(plan.effective_to, offset) : null;
    return active && start <= localDate && (!end || end > localDate);
  }).sort((a, b) => timestamp(b.effective_from || b.created_at) -
    timestamp(a.effective_from || a.created_at))[0] || null;
}

function psychologyForDate(checks, localDate, offset) {
  return checks.filter((check) => dateKey(
      check.completed_at || check.created_at, offset) === localDate)
      .sort((a, b) => timestamp(b.completed_at || b.created_at) -
        timestamp(a.completed_at || a.created_at))[0] || null;
}

function planRules(plan) {
  return plan && typeof plan.plan === "object" ? {...plan, ...plan.plan} : plan || {};
}

function overall(values, config) {
  let weighted = values.result * config.resultWeight +
    values.discipline * config.disciplineWeight +
    values.plan * config.planComplianceWeight;
  let weights = config.resultWeight + config.disciplineWeight +
    config.planComplianceWeight;
  if (values.psychology != null) {
    weighted += values.psychology * config.psychologyWeight;
    weights += config.psychologyWeight;
  }
  return score(weighted / weights);
}

function ratingBand(value) {
  if (value >= 90) return "excellent";
  if (value >= 75) return "good";
  if (value >= 60) return "developing";
  if (value >= 40) return "needs_attention";
  return "high_risk";
}

function calculationHash(value) {
  const copy = {...value};
  delete copy.calculated_at;
  delete copy.recalculated_at;
  delete copy.calculation_hash;
  return crypto.createHash("sha256").update(stableStringify(copy)).digest("hex");
}

function stableStringify(value) {
  if (Array.isArray(value)) return `[${value.map(stableStringify).join(",")}]`;
  if (value && typeof value === "object") return `{${Object.keys(value).sort()
      .map((key) => `${JSON.stringify(key)}:${stableStringify(value[key])}`)
      .join(",")}}`;
  return JSON.stringify(value);
}

function mergeConfig(value) {
  return {
    ...DEFAULT_CONFIG, ...value,
    penalties: {...DEFAULT_CONFIG.penalties, ...value.penalties},
    caps: {...DEFAULT_CONFIG.caps, ...value.caps},
  };
}

function dateKey(value, offsetMinutes = 0) {
  const date = new Date(timestamp(value) + offsetMinutes * 60000);
  return `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, "0")}-${String(date.getUTCDate()).padStart(2, "0")}`;
}
function nextDateKey(value) { return addDays(value, 1); }
function addDays(value, days) {
  const date = new Date(`${value}T00:00:00.000Z`);
  date.setUTCDate(date.getUTCDate() + days);
  return date.toISOString().slice(0, 10);
}
function mondayDateKey(value) {
  const key = /^\d{4}-\d{2}-\d{2}$/.test(value) ? value : dateKey(value);
  const date = new Date(`${key}T00:00:00.000Z`);
  const day = date.getUTCDay() || 7;
  return addDays(key, 1 - day);
}
function weekday(value) {
  return ["sunday", "monday", "tuesday", "wednesday", "thursday", "friday", "saturday"]
      [new Date(`${value}T00:00:00.000Z`).getUTCDay()];
}
function timestamp(value) {
  if (value && typeof value.toMillis === "function") return value.toMillis();
  if (value instanceof Date) return value.getTime();
  const parsed = new Date(value || 0).getTime();
  return Number.isFinite(parsed) ? parsed : 0;
}
function profit(trade) { return number(
    trade.profit_loss ?? trade.net_profit ?? trade.profit, 0); }
function average(values, fallback) {
  return values.length ? score(values.reduce((a, b) => a + number(b), 0) /
    values.length) : fallback;
}
function stddev(values) {
  if (values.length < 2) return 0;
  const mean = values.reduce((a, b) => a + number(b), 0) / values.length;
  return Math.sqrt(values.reduce((sum, value) =>
    sum + Math.pow(number(value) - mean, 2), 0) / values.length);
}
function score(value) { return Math.round(clamp(number(value), 0, 100) * 100) / 100; }
function clamp(value, min, max) { return Math.max(min, Math.min(max, value)); }
function number(value, fallback = 0) {
  const parsed = Number(value); return Number.isFinite(parsed) ? parsed : fallback;
}
function text(value, fallback = "") {
  const result = value == null ? "" : String(value).trim(); return result || fallback;
}
function bool(value) { return value === true || value === 1 || value === "1" || value === "true"; }
function strings(value) { return Array.isArray(value) ? value.map(String) : []; }
function object(value) { return value && typeof value === "object" && !Array.isArray(value) ? value : {}; }
function unique(values) { return [...new Set(values.filter(Boolean))]; }

module.exports = {
  DEFAULT_CONFIG,
  calculationHash,
  dateKey,
  normalizeTrades,
  ratingBand,
  scoreDaily,
  scoreWeekly,
};
