"use strict";

const {getFirestore, FieldValue, Timestamp} = require("firebase-admin/firestore");
const {HttpsError, onCall} = require("firebase-functions/v2/https");
const {
  onDocumentUpdated,
  onDocumentWritten,
} = require("firebase-functions/v2/firestore");
const {logger} = require("firebase-functions");
const {hasPermission} = require("./role_permissions");
const {
  DEFAULT_CONFIG,
  dateKey,
  scoreDaily,
  scoreWeekly,
} = require("./performance_rating_engine");

const REGION = "us-central1";

function text(value, fallback = "") {
  const result = value == null ? "" : String(value).trim();
  return result || fallback;
}

function timestamp(value) {
  if (value && typeof value.toMillis === "function") return value.toMillis();
  const parsed = new Date(value || 0).getTime();
  return Number.isFinite(parsed) ? parsed : 0;
}

function dateRange(localDate, offsetMinutes) {
  const start = new Date(`${localDate}T00:00:00.000Z`);
  start.setUTCMinutes(start.getUTCMinutes() - offsetMinutes);
  const end = new Date(start);
  end.setUTCDate(end.getUTCDate() + 1);
  return {start: Timestamp.fromDate(start), end: Timestamp.fromDate(end)};
}

async function loadConfig(db) {
  const snapshot = await db.collection("performance_rating_config")
      .doc("current").get();
  return snapshot.exists ? snapshot.data() || {} : DEFAULT_CONFIG;
}

async function loadDailyInput(db, userId, localDate) {
  const userSnapshot = await db.collection("users").doc(userId).get();
  if (!userSnapshot.exists) throw new Error("User profile not found");
  const user = userSnapshot.data() || {};
  const timezoneOffsetMinutes = Number(
      user.timezone_offset_minutes || user.timezoneOffsetMinutes || 480);
  const timezone = text(user.timezone, "Asia/Singapore");
  const rawSource = text(
      user.trading_data_display_mode || user.tradingDataDisplayMode,
      "manualOnly",
  );
  const dataSource = rawSource.toLowerCase().includes("mt5") ? "mt5" : "manual";
  const accountId = dataSource === "mt5" ? text(
      user.mainMt5AccountId || user.main_mt5_account_id ||
      user.mainMt5AccountKey || user.main_mt5_account_key) : "";
  const range = dateRange(localDate, timezoneOffsetMinutes);
  let tradeQuery;
  if (dataSource === "mt5" && accountId) {
    tradeQuery = db.collection("users").doc(userId)
        .collection("mt5_accounts").doc(accountId).collection("trades")
        .where("open_time", ">=", range.start).where("open_time", "<", range.end);
  } else {
    tradeQuery = db.collection("users").doc(userId)
        .collection("trading_journals")
        .where("open_date", ">=", range.start).where("open_date", "<", range.end);
  }
  const [trades, versions, plans, psychology, config] = await Promise.all([
    tradeQuery.get(),
    db.collection("users").doc(userId)
        .collection("trading_plan_versions").get(),
    db.collection("users").doc(userId).collection("trading_plans").get(),
    db.collection("users").doc(userId).collection("psychology_checks")
        .where("completed_at", ">=", range.start)
        .where("completed_at", "<", range.end).get(),
    loadConfig(db),
  ]);
  let visibleTradeDocs = trades.docs;
  if (dataSource === "mt5" && accountId) {
    const batchIds = [...new Set(trades.docs
        .map((doc) => text(doc.data().import_batch_id)).filter(Boolean))];
    if (batchIds.length) {
      const batchSnapshots = await Promise.all(batchIds.map((batchId) =>
        db.collection("users").doc(userId).collection("mt5_accounts")
            .doc(accountId).collection("import_batches").doc(batchId).get()));
      const completed = new Set(batchSnapshots
          .filter((snapshot) => snapshot.exists &&
            ["completed", "completed_with_warnings"].includes(
                text(snapshot.data()?.status)))
          .map((snapshot) => snapshot.id));
      visibleTradeDocs = trades.docs.filter((doc) => {
        const batchId = text(doc.data().import_batch_id);
        return !batchId || completed.has(batchId);
      });
    }
  }
  const planVersions = versions.empty ? plans : versions;
  return {
    user,
    config,
    input: {
      userId,
      date: `${localDate}T00:00:00.000Z`,
      timezone,
      timezoneOffsetMinutes,
      dataSource,
      accountId: accountId || null,
      initialCapital: Number(
          user.leaderboard_initial_capital || user.trading_capital ||
          user.initial_balance || user.latest_imported_opening_balance || 0,
      ),
      records: visibleTradeDocs.map((doc) => ({id: doc.id, ...doc.data()})),
      planVersions: planVersions.docs.map((doc) => ({id: doc.id, ...doc.data()})),
      psychologyChecks: psychology.docs.map((doc) => ({id: doc.id, ...doc.data()})),
    },
  };
}

async function saveDailyAndWeekly(db, input, config) {
  const daily = scoreDaily(input, config);
  const dailyId = ["daily", daily.period_start.slice(0, 10), daily.data_source,
    daily.account_id || "none"].join(":");
  const reference = db.collection("users").doc(input.userId)
      .collection("performance_ratings").doc(dailyId);
  const existing = await reference.get();
  await reference.set({
    ...daily,
    id: dailyId,
    period_start: Timestamp.fromDate(new Date(`${daily.period_start}Z`)),
    period_end: Timestamp.fromDate(new Date(`${daily.period_end}Z`)),
    calculated_at: existing.exists ?
      existing.data().calculated_at || FieldValue.serverTimestamp() :
      FieldValue.serverTimestamp(),
    recalculated_at: FieldValue.serverTimestamp(),
  }, {merge: false});
  const weekly = await recalculateWeek(
      db, input.userId, daily.period_start.slice(0, 10),
      daily.data_source, daily.account_id, input.timezone, config);
  return {daily: {...daily, id: dailyId}, weekly};
}

async function recalculateWeek(
    db, userId, date, dataSource, accountId, timezone, config) {
  const snapshot = await db.collection("users").doc(userId)
      .collection("performance_ratings")
      .where("period_type", "==", "daily").limit(120).get();
  const dailyRatings = snapshot.docs.map((doc) => {
    const data = doc.data();
    return {
      id: doc.id,
      ...data,
      period_start: data.period_start?.toDate?.().toISOString() ||
        data.period_start,
      period_end: data.period_end?.toDate?.().toISOString() || data.period_end,
    };
  });
  const weekly = scoreWeekly({
    userId,
    date,
    timezone,
    dataSource,
    accountId,
    dailyRatings,
  }, config);
  const weekKey = weekly.period_start.slice(0, 10);
  const weeklyId = ["weekly", weekKey, dataSource, accountId || "none"].join(":");
  const reference = db.collection("users").doc(userId)
      .collection("performance_ratings").doc(weeklyId);
  const existing = await reference.get();
  await reference.set({
    ...weekly,
    id: weeklyId,
    period_start: Timestamp.fromDate(new Date(`${weekly.period_start}Z`)),
    period_end: Timestamp.fromDate(new Date(`${weekly.period_end}Z`)),
    calculated_at: existing.exists ?
      existing.data().calculated_at || FieldValue.serverTimestamp() :
      FieldValue.serverTimestamp(),
    recalculated_at: FieldValue.serverTimestamp(),
  }, {merge: false});
  return {...weekly, id: weeklyId};
}

async function recalculateDate(userId, localDate) {
  const db = getFirestore();
  const loaded = await loadDailyInput(db, userId, localDate);
  return saveDailyAndWeekly(db, loaded.input, loaded.config);
}

exports.recalculateImportedMt5Dates = async (userId, dates) => {
  const uniqueDates = [...new Set((dates || []).map(text)
      .filter((date) => /^\d{4}-\d{2}-\d{2}$/.test(date)))];
  for (const date of uniqueDates) await recalculateDate(userId, date);
  return {dates: uniqueDates, ratings: uniqueDates.length};
};

exports.recalculatePerformanceRatings = onCall(
    {region: REGION, timeoutSeconds: 120, memory: "512MiB"},
    async (request) => {
      const actorId = request.auth?.uid;
      if (!actorId) throw new HttpsError("unauthenticated", "Sign in first.");
      const targetId = text(request.data?.userId, actorId);
      const db = getFirestore();
      const actorSnapshot = await db.collection("users").doc(actorId).get();
      const actor = actorSnapshot.data() || {};
      if (targetId !== actorId && !hasPermission(actor, "users.readAll")) {
        throw new HttpsError("permission-denied", "Rating recalculation denied.");
      }
      const offset = Number(actor.timezone_offset_minutes || 480);
      const requestedDate = request.data?.date ? new Date(request.data.date) : null;
      const dates = [];
      if (requestedDate && Number.isFinite(requestedDate.getTime())) {
        dates.push(dateKey(requestedDate, offset));
      } else {
        for (let daysAgo = 0; daysAgo < 7; daysAgo++) {
          dates.push(dateKey(Date.now() - daysAgo * 86400000, offset));
        }
      }
      const results = [];
      for (const date of dates) results.push(await recalculateDate(targetId, date));
      if (targetId !== actorId) {
        await db.collection("admin_audit_logs").add({
          actorUserId: actorId,
          targetUserId: targetId,
          action: "performance_rating_recalculated",
          resourceType: "performance_rating",
          resourceId: dates.join(","),
          before: null,
          after: {dates},
          reason: text(request.data?.reason, "manual_recalculation"),
          source: "cloud_function",
          createdAt: FieldValue.serverTimestamp(),
          created_at: FieldValue.serverTimestamp(),
        });
      }
      return {success: true, dates, ratings: results.length};
    },
);

async function recalculateEventDate(userId, before, after, field) {
  const value = after?.[field] || before?.[field];
  if (!value) return;
  const user = await getFirestore().collection("users").doc(userId).get();
  const offset = Number(user.data()?.timezone_offset_minutes || 480);
  const dates = new Set();
  if (before?.[field]) dates.add(dateKey(before[field], offset));
  if (after?.[field]) dates.add(dateKey(after[field], offset));
  for (const date of dates) {
    try {
      await recalculateDate(userId, date);
    } catch (error) {
      logger.error("Performance rating recalculation failed", {
        userId, date, error: error?.message,
      });
    }
  }
}

exports.recalculateRatingOnJournalWrite = onDocumentWritten(
    {document: "users/{userId}/trading_journals/{journalId}", region: REGION},
    async (event) => recalculateEventDate(
        event.params.userId,
        event.data?.before.exists ? event.data.before.data() : null,
        event.data?.after.exists ? event.data.after.data() : null,
        "open_date",
    ),
);

exports.recalculateRatingOnMt5TradeWrite = onDocumentWritten(
    {
      document: "users/{userId}/mt5_accounts/{accountId}/trades/{tradeId}",
      region: REGION,
    },
    async (event) => recalculateEventDate(
        event.params.userId,
        event.data?.before.exists ? event.data.before.data() : null,
        event.data?.after.exists ? event.data.after.data() : null,
        "open_time",
    ),
);

exports.recalculateRatingOnPsychologyWrite = onDocumentWritten(
    {document: "users/{userId}/psychology_checks/{checkId}", region: REGION},
    async (event) => recalculateEventDate(
        event.params.userId,
        event.data?.before.exists ? event.data.before.data() : null,
        event.data?.after.exists ? event.data.after.data() : null,
        "completed_at",
    ),
);

exports.snapshotTradingPlanVersion = onDocumentWritten(
    {document: "users/{userId}/trading_plans/{planId}", region: REGION},
    async (event) => {
      const after = event.data?.after.exists ? event.data.after.data() : null;
      if (!after) return;
      const versionId = text(after.version_id || after.versionId,
          `${event.params.planId}-${timestamp(after.updated_at || Date.now())}`);
      await getFirestore().collection("users").doc(event.params.userId)
          .collection("trading_plan_versions").doc(versionId).set({
            ...after,
            plan_id: event.params.planId,
            version_id: versionId,
            effective_from: after.effective_from || after.created_at ||
              FieldValue.serverTimestamp(),
            snapshot_created_at: FieldValue.serverTimestamp(),
          }, {merge: false});
      const offset = Number((await getFirestore().collection("users")
          .doc(event.params.userId).get()).data()?.timezone_offset_minutes || 480);
      await recalculateDate(
          event.params.userId,
          dateKey(after.effective_from || after.created_at || Date.now(), offset),
      );
    },
);

exports.recalculateRatingOnUserScopeChange = onDocumentUpdated(
    {document: "users/{userId}", region: REGION},
    async (event) => {
      const before = event.data?.before.data() || {};
      const after = event.data?.after.data() || {};
      const beforeScope = [
        before.trading_data_display_mode || before.tradingDataDisplayMode,
        before.main_mt5_account_id || before.mainMt5AccountId,
      ].join(":");
      const afterScope = [
        after.trading_data_display_mode || after.tradingDataDisplayMode,
        after.main_mt5_account_id || after.mainMt5AccountId,
      ].join(":");
      if (beforeScope === afterScope) return;
      const offset = Number(after.timezone_offset_minutes || 480);
      for (let daysAgo = 0; daysAgo < 7; daysAgo++) {
        await recalculateDate(
            event.params.userId,
            dateKey(Date.now() - daysAgo * 86400000, offset),
        );
      }
    },
);

exports._test = {
  dateRange,
  loadDailyInput,
  recalculateDate,
  recalculateWeek,
  saveDailyAndWeekly,
};
