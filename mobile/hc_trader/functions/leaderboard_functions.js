"use strict";

const {getFirestore, FieldValue, Timestamp} = require("firebase-admin/firestore");
const {onCall, HttpsError} = require("firebase-functions/v2/https");
const {onSchedule} = require("firebase-functions/v2/scheduler");
const {ROLE_IDS, effectiveRoleIds, accountUsable, hasApprovedAccess} =
  require("./role_permissions");

const REGION = "us-central1";
const FORMULA_VERSION = "risk-adjusted-v1";
const CATEGORIES = new Set([
  "return_percentage", "net_profit", "risk_adjusted_return",
  "lowest_drawdown", "discipline_score", "plan_compliance",
  "weekly_performance", "monthly_performance", "consistency",
  "prop_firm_progress", "signal_provider_performance",
]);

function text(value, fallback = "") {
  const resolved = value == null ? "" : String(value).trim();
  return resolved || fallback;
}

function number(value, fallback = 0) {
  const result = Number(value);
  return Number.isFinite(result) ? result : fallback;
}

function clamp(value, minimum, maximum) {
  return Math.min(maximum, Math.max(minimum, number(value)));
}

function calculateRiskAdjustedScore(input) {
  const tradeCount = Math.max(0, Math.floor(number(input.trade_count)));
  const tradingDays = Math.max(0, Math.floor(number(input.trading_days)));
  if (input.verified !== true || input.suspicious === true ||
      tradeCount < number(input.minimum_trades, 10) ||
      tradingDays < number(input.minimum_trading_days, 5)) return 0;
  const score = clamp(input.return_percentage, -100, 200) * .45 +
    clamp(input.discipline_score, 0, 100) * .30 +
    clamp(input.consistency_score, 0, 100) * .25 -
    Math.abs(number(input.max_drawdown_percentage)) * 1.5;
  return clamp(score, 0, 100);
}

function periodId(category, periodType, startsAt) {
  const date = startsAt instanceof Date ? startsAt : new Date(startsAt);
  return `${category}_${periodType}_${date.toISOString().slice(0, 10)}`;
}

function periodBounds(now, periodType) {
  const date = new Date(now);
  const start = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), 1));
  let end = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + 1, 1));
  if (periodType === "weekly") {
    const weekday = (date.getUTCDay() + 6) % 7;
    start.setUTCDate(date.getUTCDate() - weekday);
    end = new Date(start);
    end.setUTCDate(end.getUTCDate() + 7);
  } else if (periodType === "daily") {
    start.setUTCDate(date.getUTCDate());
    end = new Date(start);
    end.setUTCDate(end.getUTCDate() + 1);
  } else if (periodType === "quarterly") {
    start.setUTCMonth(Math.floor(date.getUTCMonth() / 3) * 3);
    end = new Date(Date.UTC(date.getUTCFullYear(), start.getUTCMonth() + 3, 1));
  }
  return {start, end};
}

function normalizedDailyRating(document) {
  const data = document.data ? document.data() : document;
  return {
    id: document.id || text(data.id),
    user_id: text(data.user_id),
    period_start: data.period_start,
    data_source: text(data.data_source, "manual"),
    account_id: text(data.account_id),
    trading_result_score: number(data.trading_result_score, 50),
    discipline_score: number(data.trading_discipline_score, 0),
    plan_compliance: number(data.trading_plan_compliance_score, 0),
    consistency_score: number(data.consistency_score, 100),
    return_percentage: number(data.return_percentage),
    net_trading_profit: number(data.net_trading_profit || data.net_profit),
    max_drawdown_percentage: Math.abs(number(data.max_drawdown_percentage)),
    trade_ids: Array.isArray(data.closed_trade_ids) ? data.closed_trade_ids :
      Array.isArray(data.evaluated_trade_ids) ? data.evaluated_trade_ids : [],
    calculation_hash: text(data.calculation_hash),
    suspicious: data.suspicious === true || data.integrity_status === "flagged",
  };
}

function selectCanonicalRatings(ratings, preferredSource) {
  const byDay = new Map();
  for (const rating of ratings) {
    const normalized = normalizedDailyRating(rating);
    const at = normalized.period_start?.toDate?.() || new Date(normalized.period_start);
    const day = Number.isFinite(at?.getTime?.()) ? at.toISOString().slice(0, 10) : "";
    if (!day || !normalized.calculation_hash) continue;
    const existing = byDay.get(day);
    const sourcePreferred = normalized.data_source === preferredSource;
    const existingPreferred = existing?.data_source === preferredSource;
    if (!existing || (sourcePreferred && !existingPreferred) ||
        (sourcePreferred === existingPreferred &&
         normalized.trade_ids.length > existing.trade_ids.length)) {
      byDay.set(day, normalized);
    }
  }
  return [...byDay.values()];
}

function aggregateRatings(ratings, user, period) {
  const preferredSource = text(
      user.trading_data_display_mode || user.tradingDataDisplayMode,
      "manualOnly",
  ).toLowerCase().includes("mt5") ? "mt5" : "manual";
  const selected = selectCanonicalRatings(ratings, preferredSource);
  const uniqueTrades = new Set(selected.flatMap((rating) => rating.trade_ids));
  const average = (field, fallback = 0) => selected.length ?
    selected.reduce((sum, item) => sum + number(item[field], fallback), 0) /
      selected.length : fallback;
  const initialCapital = Math.max(1, number(
      user.leaderboard_initial_capital || user.trading_capital ||
      user.initial_balance || user.latest_imported_opening_balance,
      1,
  ));
  const netProfit = selected.reduce((sum, item) =>
    sum + number(item.net_trading_profit), 0);
  const reportedReturn = selected.reduce((sum, item) =>
    sum + number(item.return_percentage), 0);
  const returnPercentage = reportedReturn || netProfit / initialCapital * 100;
  const metric = {
    return_percentage: returnPercentage,
    net_trading_profit: netProfit,
    max_drawdown_percentage: Math.max(0, ...selected.map((item) =>
      item.max_drawdown_percentage)),
    discipline_score: average("discipline_score"),
    plan_compliance: average("plan_compliance"),
    consistency_score: average("consistency_score", 100),
    result_score: average("trading_result_score", 50),
    trade_count: uniqueTrades.size,
    trading_days: selected.filter((item) => item.trade_ids.length > 0).length,
    suspicious: selected.some((item) => item.suspicious),
    verified: selected.length > 0 && selected.every((item) =>
      Boolean(item.calculation_hash)),
  };
  metric.risk_adjusted_score = calculateRiskAdjustedScore({
    ...metric,
    minimum_trades: number(period.minimum_trades, 10),
    minimum_trading_days: number(period.minimum_trading_days, 5),
  });
  return metric;
}

function categoryScore(category, metric, user) {
  switch (category) {
    case "return_percentage": return metric.return_percentage;
    case "net_profit": return metric.net_trading_profit;
    case "lowest_drawdown": return -Math.abs(metric.max_drawdown_percentage);
    case "discipline_score": return metric.discipline_score;
    case "plan_compliance": return metric.plan_compliance;
    case "consistency": return metric.consistency_score;
    case "prop_firm_progress": return number(user.prop_firm_progress_score);
    case "signal_provider_performance":
      return number(user.signal_provider_performance_score);
    case "weekly_performance":
    case "monthly_performance":
    case "risk_adjusted_return":
    default: return metric.risk_adjusted_score;
  }
}

function publicEntry(userId, user, privacy, metric, score) {
  const nickname = text(privacy.nickname);
  const hiddenName = privacy.hide_real_name !== false;
  const displayName = nickname || (hiddenName ? `Trader ${userId.slice(0, 6)}` :
    text(user.display_name || user.name, "Trader"));
  return {
    user_id: userId,
    display_name: displayName,
    avatar_url: privacy.hide_avatar === true ? "" : text(user.photo_url),
    score,
    return_percentage: metric.return_percentage,
    net_trading_profit: privacy.percentage_only !== false ||
      privacy.hide_exact_balance !== false ? 0 : metric.net_trading_profit,
    max_drawdown_percentage: metric.max_drawdown_percentage,
    discipline_score: metric.discipline_score,
    plan_compliance_score: metric.plan_compliance,
    consistency_score: metric.consistency_score,
    trade_count: metric.trade_count,
    trading_days: metric.trading_days,
    balance_hidden: privacy.hide_exact_balance !== false,
    verified: metric.verified,
  };
}

function rankLeaderboardEntries(entries, oldRanks = new Map()) {
  const ranked = [...entries].sort((a, b) => b.score - a.score ||
    b.return_percentage - a.return_percentage ||
    a.max_drawdown_percentage - b.max_drawdown_percentage ||
    a.user_id.localeCompare(b.user_id));
  let previousScore = null;
  let visibleRank = 0;
  return ranked.map((entry, index) => {
    if (previousScore == null || Math.abs(entry.score - previousScore) > 1e-9) {
      visibleRank = index + 1;
    }
    previousScore = entry.score;
    const badge = visibleRank === 1 ? "Champion" : visibleRank <= 3 ?
      "Podium" : visibleRank <= 10 ? "Top 10" : "Verified";
    return {...entry, rank: visibleRank, badge,
      previous_rank: oldRanks.get(entry.user_id) || null};
  });
}

async function adminActor(request) {
  const uid = request.auth?.uid;
  if (!uid) throw new HttpsError("unauthenticated", "Sign in is required.");
  const snapshot = await getFirestore().collection("users").doc(uid).get();
  const user = snapshot.data() || {};
  const roles = effectiveRoleIds(user);
  if (!accountUsable(user) || ![ROLE_IDS.superAdmin, ROLE_IDS.subAdmin,
    ROLE_IDS.founder].some((role) => roles.has(role))) {
    throw new HttpsError("permission-denied", "Administrator access is required.");
  }
  return {uid, user};
}

async function deleteOldEntries(reference) {
  while (true) {
    const snapshot = await reference.collection("entries").limit(400).get();
    if (snapshot.empty) return;
    const batch = getFirestore().batch();
    snapshot.docs.forEach((document) => batch.delete(document.ref));
    await batch.commit();
  }
}

async function calculatePeriod(periodIdValue, actorId = "scheduler") {
  const db = getFirestore();
  const reference = db.collection("leaderboardPeriods").doc(periodIdValue);
  const periodSnapshot = await reference.get();
  if (!periodSnapshot.exists) throw new HttpsError("not-found", "Period not found.");
  const period = periodSnapshot.data() || {};
  const category = text(period.category, "risk_adjusted_return");
  if (!CATEGORIES.has(category)) {
    throw new HttpsError("failed-precondition", "Unsupported leaderboard category.");
  }
  const startsAt = period.starts_at;
  const endsAt = period.ends_at;
  if (!startsAt || !endsAt) {
    throw new HttpsError("failed-precondition", "Leaderboard dates are required.");
  }
  const ratingsSnapshot = await db.collectionGroup("performance_ratings")
      .where("period_type", "==", "daily")
      .where("period_start", ">=", startsAt)
      .where("period_start", "<", endsAt)
      .limit(10000).get();
  const ratingsByUser = new Map();
  for (const document of ratingsSnapshot.docs) {
    const userId = document.ref.parent.parent?.id;
    if (!userId) continue;
    if (!ratingsByUser.has(userId)) ratingsByUser.set(userId, []);
    ratingsByUser.get(userId).push(document);
  }
  const ranked = [];
  for (const [userId, ratings] of ratingsByUser) {
    const userReference = db.collection("users").doc(userId);
    const [userSnapshot, privacySnapshot] = await Promise.all([
      userReference.get(),
      userReference.collection("preferences").doc("leaderboard").get(),
    ]);
    const user = userSnapshot.data() || {};
    const privacy = privacySnapshot.data() || {};
    if (!accountUsable(user) || !hasApprovedAccess(user) ||
        privacy.participate !== true || privacy.hide_from_public !== false) continue;
    const metric = aggregateRatings(ratings, user, period);
    if (!metric.verified || metric.suspicious ||
        metric.trade_count < number(period.minimum_trades, 10) ||
        metric.trading_days < number(period.minimum_trading_days, 5)) continue;
    const score = categoryScore(category, metric, user);
    ranked.push(publicEntry(userId, user, privacy, metric, score));
  }
  const oldEntries = await reference.collection("entries").get();
  const oldRanks = new Map(oldEntries.docs.map((document) =>
    [document.id, number(document.data().rank)]));
  const entries = rankLeaderboardEntries(ranked, oldRanks);
  await deleteOldEntries(reference);
  for (let offset = 0; offset < entries.length; offset += 400) {
    const batch = db.batch();
    for (const entry of entries.slice(offset, offset + 400)) {
      batch.set(reference.collection("entries").doc(entry.user_id), {
        ...entry, calculated_at: FieldValue.serverTimestamp(),
      });
    }
    await batch.commit();
  }
  await reference.set({
    entry_count: entries.length,
    formula_version: FORMULA_VERSION,
    calculated_at: FieldValue.serverTimestamp(),
    calculated_by: actorId,
    calculation_status: "complete",
  }, {merge: true});
  return {period_id: periodIdValue, entries: entries.length};
}

const calculateLeaderboardPeriod = onCall(
    {region: REGION, timeoutSeconds: 300, memory: "1GiB"},
    async (request) => {
      const actor = await adminActor(request);
      const id = text(request.data?.period_id);
      if (!/^[a-z0-9_-]{3,150}$/.test(id)) {
        throw new HttpsError("invalid-argument", "Invalid period identifier.");
      }
      return calculatePeriod(id, actor.uid);
    },
);

async function ensureActivePeriods(now = new Date()) {
  const db = getFirestore();
  const categories = [...CATEGORIES];
  const periods = [];
  for (const category of categories) {
    const type = category === "weekly_performance" ? "weekly" : "monthly";
    const bounds = periodBounds(now, type);
    const id = periodId(category, type, bounds.start);
    await db.collection("leaderboardPeriods").doc(id).set({
      category,
      period_type: type,
      starts_at: Timestamp.fromDate(bounds.start),
      ends_at: Timestamp.fromDate(bounds.end),
      status: "active",
      minimum_trades: 10,
      minimum_trading_days: type === "weekly" ? 3 : 5,
      formula_version: FORMULA_VERSION,
      updated_at: FieldValue.serverTimestamp(),
    }, {merge: true});
    periods.push(id);
  }
  return periods;
}

const recalculateActiveLeaderboards = onSchedule(
    {schedule: "20 1 * * *", timeZone: "UTC", region: REGION,
      timeoutSeconds: 1800, memory: "1GiB"},
    async () => {
      const db = getFirestore();
      const feature = await db.collection("featureConfigurations")
          .doc("leaderboard").get();
      if (feature.exists && ["disabled", "hidden", "maintenance"].includes(
          text(feature.data()?.mode),
      )) return;
      const periods = await ensureActivePeriods();
      for (const id of periods) await calculatePeriod(id);
    },
);

module.exports = {
  calculateLeaderboardPeriod,
  recalculateActiveLeaderboards,
  calculateRiskAdjustedScore,
  selectCanonicalRatings,
  aggregateRatings,
  categoryScore,
  periodBounds,
  periodId,
  ensureActivePeriods,
  rankLeaderboardEntries,
};
