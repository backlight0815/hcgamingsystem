"use strict";

const crypto = require("node:crypto");
const {getFirestore, FieldValue, Timestamp} = require("firebase-admin/firestore");
const {getMessaging} = require("firebase-admin/messaging");
const {onCall, HttpsError} = require("firebase-functions/v2/https");
const {onDocumentUpdated} = require("firebase-functions/v2/firestore");
const {onSchedule} = require("firebase-functions/v2/scheduler");
const {defineSecret, defineString} = require("firebase-functions/params");
const {logger} = require("firebase-functions");
const {
  ROLE_IDS,
  effectiveRoleIds,
  accountUsable,
  hasApprovedAccess,
} = require("./role_permissions");

const REGION = "us-central1";
const CHANNEL_ID = "hc_trader_updates";
const STOCK_DATA_API_KEY = defineSecret("STOCK_DATA_API_KEY");
const STOCK_DATA_PROVIDER_BASE_URL = defineString(
    "STOCK_DATA_PROVIDER_BASE_URL",
    {default: ""},
);
const INVALID_TOKEN_CODES = new Set([
  "messaging/invalid-registration-token",
  "messaging/registration-token-not-registered",
]);

function text(value, fallback = "") {
  const resolved = value == null ? "" : String(value).trim();
  return resolved || fallback;
}

function symbolValue(value) {
  const symbol = text(value).toUpperCase();
  if (!/^[A-Z0-9.\-]{1,15}$/.test(symbol)) {
    throw new HttpsError("invalid-argument", "Enter a valid stock symbol.");
  }
  return symbol;
}

function languageCode(value) {
  return text(value, "en").toLowerCase().startsWith("zh") ? "zh" : "en";
}

function localized(value, language, fallback = "") {
  const primary = languageCode(language);
  const secondary = primary === "zh" ? "en" : "zh";
  if (value && typeof value === "object") {
    return text(value[primary], text(value[secondary], fallback));
  }
  return text(value, fallback);
}

function mapValue(value) {
  return value && typeof value === "object" && !Array.isArray(value) ? value : {};
}

function listValue(value) {
  return Array.isArray(value) ? value : [];
}

async function authenticatedUser(request) {
  const uid = request.auth?.uid;
  if (!uid) throw new HttpsError("unauthenticated", "Sign in is required.");
  const document = await getFirestore().collection("users").doc(uid).get();
  const user = document.data() || {};
  if (!accountUsable(user)) {
    throw new HttpsError("permission-denied", "This account is not active.");
  }
  return {
    uid,
    user,
    reference: document.ref,
    appVersion: text(request.data?.app_version),
  };
}

function compareVersions(left, right) {
  const parts = (value) => text(value).split("+")[0].split(".")
      .map((item) => Number.parseInt(item, 10) || 0);
  const a = parts(left);
  const b = parts(right);
  for (let index = 0; index < Math.max(a.length, b.length); index++) {
    const result = Number(a[index] || 0) - Number(b[index] || 0);
    if (result) return result;
  }
  return 0;
}

function canAuthorReport(user) {
  const roles = effectiveRoleIds(user);
  return [ROLE_IDS.superAdmin, ROLE_IDS.subAdmin, ROLE_IDS.founder,
    ROLE_IDS.marketAnalyst].some((role) => roles.has(role));
}

function canPublishReport(user) {
  const roles = effectiveRoleIds(user);
  return [ROLE_IDS.superAdmin, ROLE_IDS.subAdmin, ROLE_IDS.founder]
      .some((role) => roles.has(role));
}

function configurationTimestamp(value) {
  if (value && typeof value.toMillis === "function") return value.toMillis();
  const parsed = new Date(value || 0).getTime();
  return Number.isFinite(parsed) ? parsed : 0;
}

function rolloutBucket(uid, key) {
  const hex = crypto.createHash("sha256").update(`${key}|${uid}`)
      .digest("hex").slice(0, 8);
  return Number.parseInt(hex, 16) % 100;
}

async function featureAvailable(key, actor = null, write = false) {
  const db = getFirestore();
  const current = await db.collection("featureConfigurations").doc(key).get();
  if (current.exists) {
    const configuration = current.data() || {};
    const mode = text(configuration.mode, "enabled");
    if (["disabled", "maintenance", "hidden"].includes(mode) ||
        (write && mode === "readOnly")) return false;
    const now = Date.now();
    const starts = configurationTimestamp(configuration.starts_at);
    const ends = configurationTimestamp(configuration.ends_at);
    if ((starts && starts > now) || (ends && ends <= now)) return false;
    if (actor) {
      const uid = text(actor.uid);
      const user = actor.user || {};
      const roles = [...effectiveRoleIds(user)].map(String);
      const enabledRoles = listValue(configuration.role_ids).map(String);
      const accessLevels = listValue(configuration.access_levels).map(String);
      const selectedUsers = listValue(configuration.selected_user_ids).map(String);
      const regions = listValue(configuration.regions)
          .map((value) => text(value).toUpperCase());
      const access = text(user.subscription_level || user.access_level);
      const region = text(user.country_code || user.region).toUpperCase();
      if (enabledRoles.length && !enabledRoles.some((role) => roles.includes(role))) {
        return false;
      }
      if (accessLevels.length && !accessLevels.includes(access)) return false;
      if (selectedUsers.length && !selectedUsers.includes(uid)) return false;
      if (regions.length && (!region || !regions.includes(region))) return false;
      const minimumVersion = text(configuration.minimum_app_version);
      if (minimumVersion && compareVersions(actor.appVersion, minimumVersion) < 0) {
        return false;
      }
      const rollout = Math.min(100, Math.max(0,
          Number(configuration.rollout_percentage ?? 100)));
      if (rolloutBucket(uid, key) >= rollout) return false;
    } else if (Number(configuration.rollout_percentage ?? 100) <= 0) {
      return false;
    }
    return true;
  }
  const legacy = await db.collection("app_configuration").doc("modules").get();
  return legacy.data()?.[key] !== false;
}

async function enforceRateLimit(uid, operation, maximum = 60) {
  const db = getFirestore();
  const bucket = Math.floor(Date.now() / 60000);
  const reference = db.collection("rateLimits")
      .doc(`${uid}_${operation}_${bucket}`);
  await db.runTransaction(async (transaction) => {
    const snapshot = await transaction.get(reference);
    const count = Number(snapshot.data()?.count || 0);
    if (count >= maximum) {
      throw new HttpsError(
          "resource-exhausted",
          "Too many market-data requests. Please wait a moment.",
      );
    }
    transaction.set(reference, {
      user_id: uid,
      operation,
      bucket,
      count: count + 1,
      expires_at: Timestamp.fromMillis((bucket + 2) * 60000),
    }, {merge: true});
  });
}

class StockMarketDataProvider {
  async searchStocks(_query, _limit) {
    throw new Error("searchStocks is not implemented");
  }
  async getSnapshot(_symbol) {
    throw new Error("getSnapshot is not implemented");
  }
}

class FirestoreStockMarketDataProvider extends StockMarketDataProvider {
  async searchStocks(query, limit) {
    const snapshot = await getFirestore().collection("stocks")
        .orderBy("symbol").limit(Math.min(100, Math.max(limit, 30))).get();
    const normalized = text(query).toLowerCase();
    return snapshot.docs.map((document) => ({
      symbol: document.id,
      ...document.data(),
    })).filter((stock) => !normalized || [
      stock.symbol,
      stock.company_name,
      stock.exchange,
      ...listValue(stock.search_terms),
    ].some((value) => text(value).toLowerCase().includes(normalized)))
        .slice(0, limit);
  }

  async getSnapshot(symbol) {
    const db = getFirestore();
    const stock = await db.collection("stocks").doc(symbol).get();
    if (!stock.exists) {
      throw new HttpsError("not-found", "No supported stock was found.");
    }
    const data = stock.data() || {};
    const quoteSnapshot = await stock.ref.collection("quotes")
        .orderBy("as_of", "desc").limit(1).get();
    const newsSnapshot = await stock.ref.collection("news")
        .orderBy("published_at", "desc").limit(10).get();
    const historySnapshot = await stock.ref.collection("priceHistory")
        .orderBy("at", "desc").limit(260).get();
    const quote = quoteSnapshot.empty ? mapValue(data.quote) :
      quoteSnapshot.docs[0].data();
    return {
      symbol,
      quote: {symbol, exchange: data.exchange, currency: data.currency, ...quote},
      profile: {symbol, ...mapValue(data.profile), ...data},
      news: newsSnapshot.docs.map((document) => ({
        id: document.id, ...document.data(),
      })),
      calendar: mapValue(data.financial_calendar),
      history: historySnapshot.docs.reverse().map((document) => document.data()),
      provider: text(data.provider, "firestore_cache"),
      source_references: listValue(data.source_references),
    };
  }
}

class ConfiguredHttpStockMarketDataProvider extends StockMarketDataProvider {
  constructor(baseUrl, apiKey) {
    super();
    this.baseUrl = baseUrl.replace(/\/$/, "");
    this.apiKey = apiKey;
  }

  async request(path, parameters) {
    const url = new URL(`${this.baseUrl}${path}`);
    Object.entries(parameters).forEach(([key, value]) =>
      url.searchParams.set(key, String(value)));
    const response = await fetch(url, {
      headers: {"Authorization": `Bearer ${this.apiKey}`},
      signal: AbortSignal.timeout(10000),
    });
    if (response.status === 429) {
      throw new HttpsError("resource-exhausted", "Market-data rate limit reached.");
    }
    if (!response.ok) {
      throw new HttpsError("unavailable", "The market-data provider is unavailable.");
    }
    return response.json();
  }

  async searchStocks(query, limit) {
    const response = await this.request("/search", {q: query, limit});
    return listValue(response.items || response.results || response.data);
  }

  async getSnapshot(symbol) {
    const response = await this.request("/snapshot", {symbol});
    return mapValue(response.data || response);
  }
}

function provider() {
  const baseUrl = text(STOCK_DATA_PROVIDER_BASE_URL.value());
  const apiKey = text(STOCK_DATA_API_KEY.value());
  if (baseUrl && apiKey) {
    return new ConfiguredHttpStockMarketDataProvider(baseUrl, apiKey);
  }
  return new FirestoreStockMarketDataProvider();
}

function publicStockRow(row) {
  return {
    symbol: text(row.symbol).toUpperCase(),
    company_name: text(row.company_name || row.name),
    exchange: text(row.exchange),
    currency: text(row.currency, "USD"),
    latest_price: row.latest_price ?? row.price ?? null,
    change_percent: row.change_percent ?? null,
    freshness: text(row.freshness || row.data_type, "unavailable"),
  };
}

const searchStocks = onCall(
    {region: REGION, secrets: [STOCK_DATA_API_KEY]},
    async (request) => {
      const actor = await authenticatedUser(request);
      if (!await featureAvailable("stock_information", actor)) {
        throw new HttpsError("failed-precondition", "Stock information is paused.");
      }
      await enforceRateLimit(actor.uid, "stock_search", 40);
      const query = text(request.data?.query).slice(0, 80);
      const limit = Math.min(30, Math.max(1, Number(request.data?.limit || 20)));
      try {
        const items = await provider().searchStocks(query, limit);
        return {items: items.map(publicStockRow)};
      } catch (error) {
        logger.warn("External stock search failed; using Firestore cache", {
          code: text(error?.code),
        });
        const items = await new FirestoreStockMarketDataProvider()
            .searchStocks(query, limit);
        return {items: items.map(publicStockRow), cached: true};
      }
    },
);

async function cacheSnapshot(symbol, snapshot) {
  const db = getFirestore();
  const reference = db.collection("stocks").doc(symbol);
  const quote = {...mapValue(snapshot.quote), symbol};
  const asOf = quote.as_of ? new Date(quote.as_of) : new Date();
  const quoteId = `${asOf.toISOString().replace(/[^0-9]/g, "").slice(0, 14)}`;
  const batch = db.batch();
  batch.set(reference, {
    symbol,
    company_name: text(snapshot.profile?.company_name || snapshot.company_name),
    exchange: text(snapshot.quote?.exchange || snapshot.profile?.exchange),
    currency: text(snapshot.quote?.currency || snapshot.profile?.currency, "USD"),
    profile: mapValue(snapshot.profile),
    financial_calendar: mapValue(snapshot.calendar),
    quote,
    latest_price: quote.price ?? null,
    change_percent: quote.change_percent ?? null,
    freshness: text(quote.freshness, "unavailable"),
    source_references: listValue(snapshot.source_references),
    provider_updated_at: Timestamp.fromDate(asOf),
    updated_at: FieldValue.serverTimestamp(),
  }, {merge: true});
  batch.set(reference.collection("quotes").doc(quoteId), {
    ...quote,
    as_of: Timestamp.fromDate(asOf),
    cached_at: FieldValue.serverTimestamp(),
  }, {merge: true});
  await batch.commit();
}

const getStockSnapshot = onCall(
    {region: REGION, secrets: [STOCK_DATA_API_KEY]},
    async (request) => {
      const actor = await authenticatedUser(request);
      if (!await featureAvailable("stock_information", actor)) {
        throw new HttpsError("failed-precondition", "Stock information is paused.");
      }
      await enforceRateLimit(actor.uid, "stock_snapshot", 60);
      const symbol = symbolValue(request.data?.symbol);
      const db = getFirestore();
      const cachedDocument = await db.collection("stocks").doc(symbol).get();
      const cachedAt = cachedDocument.data()?.provider_updated_at?.toMillis?.() || 0;
      const forceRefresh = request.data?.force_refresh === true;
      if (!forceRefresh && cachedAt && Date.now() - cachedAt < 5 * 60 * 1000) {
        return new FirestoreStockMarketDataProvider().getSnapshot(symbol);
      }
      try {
        const snapshot = await provider().getSnapshot(symbol);
        if (text(STOCK_DATA_PROVIDER_BASE_URL.value())) {
          await cacheSnapshot(symbol, snapshot);
        }
        return snapshot;
      } catch (error) {
        if (cachedDocument.exists) {
          logger.warn("Stock provider failed; returning cached data", {
            symbol,
            code: text(error?.code),
          });
          return new FirestoreStockMarketDataProvider().getSnapshot(symbol);
        }
        throw error instanceof HttpsError ? error :
          new HttpsError("unavailable", "Market data is temporarily unavailable.");
      }
    },
);

function localizedMap(english, chinese) {
  return {
    ...(text(english) ? {en: text(english)} : {}),
    ...(text(chinese) ? {zh: text(chinese)} : {}),
  };
}

function reportIdForDate(date) {
  return date.toISOString().slice(0, 10);
}

const saveFinancialDailyReportDraft = onCall(
    {region: REGION},
    async (request) => {
      const actor = await authenticatedUser(request);
      if (!canAuthorReport(actor.user)) {
        throw new HttpsError("permission-denied", "Report authoring is required.");
      }
      if (!await featureAvailable("stock_report_publishing", actor, true)) {
        throw new HttpsError("failed-precondition", "Stock-report publishing is paused.");
      }
      const symbol = symbolValue(request.data?.symbol);
      const reportDate = new Date(request.data?.report_date);
      if (Number.isNaN(reportDate.getTime())) {
        throw new HttpsError("invalid-argument", "A valid report date is required.");
      }
      const title = localizedMap(request.data?.title_en, request.data?.title_zh);
      const content = localizedMap(
          request.data?.content_en, request.data?.content_zh,
      );
      if (!Object.keys(title).length || !Object.keys(content).length) {
        throw new HttpsError(
            "invalid-argument",
            "A title and report content are required in English or Chinese.",
        );
      }
      const reportId = text(request.data?.report_id, reportIdForDate(reportDate));
      if (!/^[A-Za-z0-9._-]{1,100}$/.test(reportId)) {
        throw new HttpsError("invalid-argument", "Invalid report identifier.");
      }
      const db = getFirestore();
      const reference = db.collection("stocks").doc(symbol)
          .collection("reports").doc(reportId);
      await db.runTransaction(async (transaction) => {
        const previous = await transaction.get(reference);
        const currentVersion = Number(previous.data()?.version || 0);
        const expected = request.data?.expected_version;
        if (expected != null && Number(expected) !== currentVersion) {
          throw new HttpsError(
              "aborted",
              "This report changed while you were editing it.",
          );
        }
        if (["published", "archived"].includes(
            text(previous.data()?.report_status))) {
          throw new HttpsError(
              "failed-precondition",
              "Published or archived reports cannot be overwritten as drafts.",
          );
        }
        const now = FieldValue.serverTimestamp();
        transaction.set(reference, {
          report_id: reportId,
          symbol,
          company_name: text(request.data?.company_name),
          report_date: Timestamp.fromDate(reportDate),
          title_i18n: title,
          summary_i18n: localizedMap(
              request.data?.summary_en, request.data?.summary_zh,
          ),
          content_i18n: content,
          source_references: listValue(request.data?.source_references)
              .map(String).map((source) => source.trim()).filter(Boolean),
          market_data_snapshot: mapValue(request.data?.market_data_snapshot),
          data_timestamp: Timestamp.now(),
          report_status: "draft",
          generated_by: previous.data()?.generated_by || actor.uid,
          created_by: previous.data()?.created_by || actor.uid,
          created_at: previous.data()?.created_at || now,
          updated_by: actor.uid,
          updated_at: now,
          version: currentVersion + 1,
        }, {merge: true});
        transaction.create(db.collection("audit_logs").doc(), {
          action: previous.exists ? "update" : "create",
          module: "stock_reports",
          entity_id: reportId,
          target_id: reportId,
          target_type: "financial_daily_report",
          previous_value: previous.data() || null,
          new_value: {symbol, report_status: "draft", version: currentVersion + 1},
          performed_by: actor.uid,
          actor_id: actor.uid,
          performed_at: now,
          created_at: now,
          device_or_source: "callable",
        });
      });
      return {report_id: reportId, symbol};
    },
);

const REPORT_TRANSITIONS = Object.freeze({
  draft: new Set(["under_review", "archived"]),
  under_review: new Set(["draft", "scheduled", "published", "rejected"]),
  scheduled: new Set(["draft", "published", "archived"]),
  rejected: new Set(["draft", "archived"]),
  published: new Set(["archived"]),
  archived: new Set([]),
});

const reviewFinancialDailyReport = onCall(
    {region: REGION},
    async (request) => {
      const actor = await authenticatedUser(request);
      if (!canAuthorReport(actor.user)) {
        throw new HttpsError("permission-denied", "Report review is required.");
      }
      if (!await featureAvailable("stock_report_publishing", actor, true)) {
        throw new HttpsError(
            "failed-precondition",
            "Stock-report publishing is paused or read-only.",
        );
      }
      const symbol = symbolValue(request.data?.symbol);
      const reportId = text(request.data?.report_id);
      const nextStatus = text(request.data?.status);
      if (!REPORT_TRANSITIONS[nextStatus] && nextStatus !== "draft") {
        throw new HttpsError("invalid-argument", "Invalid report status.");
      }
      if (["scheduled", "published", "archived"].includes(nextStatus) &&
          !canPublishReport(actor.user)) {
        throw new HttpsError(
            "permission-denied",
            "An administrator must approve this workflow change.",
        );
      }
      const db = getFirestore();
      const reference = db.collection("stocks").doc(symbol)
          .collection("reports").doc(reportId);
      await db.runTransaction(async (transaction) => {
        const snapshot = await transaction.get(reference);
        if (!snapshot.exists) throw new HttpsError("not-found", "Report not found.");
        const report = snapshot.data() || {};
        const current = text(report.report_status, "draft");
        if (!REPORT_TRANSITIONS[current]?.has(nextStatus)) {
          throw new HttpsError(
              "failed-precondition",
              `A ${current} report cannot move to ${nextStatus}.`,
          );
        }
        const version = Number(report.version || 0);
        if (request.data?.expected_version != null &&
            Number(request.data.expected_version) !== version) {
          throw new HttpsError("aborted", "This report changed. Refresh and try again.");
        }
        if (nextStatus === "published" &&
            (!Object.keys(mapValue(report.title_i18n)).length ||
             !Object.keys(mapValue(report.content_i18n)).length ||
             !listValue(report.source_references).length)) {
          throw new HttpsError(
              "failed-precondition",
              "Verify the title, content, and source references before publishing.",
          );
        }
        const now = FieldValue.serverTimestamp();
        transaction.update(reference, {
          report_status: nextStatus,
          reviewed_by: ["under_review", "rejected"].includes(nextStatus) ?
            actor.uid : report.reviewed_by || actor.uid,
          published_by: nextStatus === "published" ? actor.uid :
            report.published_by || null,
          published_at: nextStatus === "published" ? now :
            report.published_at || null,
          review_note: text(request.data?.review_note),
          updated_by: actor.uid,
          updated_at: now,
          version: version + 1,
        });
        transaction.create(db.collection("audit_logs").doc(), {
          action: nextStatus === "published" ? "publish" : "status_change",
          module: "stock_reports",
          entity_id: reportId,
          target_id: reportId,
          target_type: "financial_daily_report",
          previous_value: {status: current, version},
          new_value: {status: nextStatus, version: version + 1},
          performed_by: actor.uid,
          actor_id: actor.uid,
          performed_at: now,
          created_at: now,
          device_or_source: "callable",
        });
      });
      return {report_id: reportId, status: nextStatus};
    },
);

function reportNotification(report, language) {
  const symbol = text(report.symbol);
  return {
    title: language === "zh" ? `${symbol} 财经日报` : `${symbol} Financial Daily Report`,
    body: localized(
        report.summary_i18n,
        language,
        language === "zh" ? "经审核的最新股票日报已发布。" :
          "The latest reviewed stock report is now available.",
    ).slice(0, 180),
  };
}

async function reportSubscribers(symbol) {
  const watchlist = await getFirestore().collectionGroup("watchlist")
      .where("symbol", "==", symbol).get();
  return watchlist.docs.filter((document) =>
    document.data().daily_reports_enabled !== false).map((document) => ({
      uid: document.ref.parent.parent.id,
      watchlist: document.data(),
    }));
}

async function sendPublishedReport(report, reportId) {
  const db = getFirestore();
  const symbol = text(report.symbol);
  const eventId = `stock_report_${symbol}_${reportId}_v${Number(report.version || 1)}`;
  const eventReference = db.collection("notificationEvents").doc(eventId);
  const claimed = await db.runTransaction(async (transaction) => {
    const existing = await transaction.get(eventReference);
    if (existing.exists && existing.data()?.status === "sent") return false;
    transaction.set(eventReference, {
      event_id: eventId,
      event_type: "financial_daily_report_published",
      category: "stock_reports",
      symbol,
      report_id: reportId,
      status: "sending",
      claimed_at: FieldValue.serverTimestamp(),
      attempt_count: FieldValue.increment(1),
    }, {merge: true});
    return true;
  });
  if (!claimed) return;

  const subscribers = await reportSubscribers(symbol);
  const pushAvailable = await featureAvailable("push_notifications");
  const messages = [];
  const deliveries = [];
  for (const subscriber of subscribers) {
    const userReference = db.collection("users").doc(subscriber.uid);
    const userSnapshot = await userReference.get();
    const user = userSnapshot.data() || {};
    if (!activeStockSubscriber(user)) continue;
    const preferenceSnapshot = await userReference
        .collection("notificationPreferences").doc("default").get();
    const preferences = preferenceSnapshot.data() || {};
    if (preferences.financial_daily_reports === false ||
        user.financial_daily_report_notifications_enabled === false) continue;
    await userReference.collection("notifications").doc(eventId).set({
      event_id: eventId,
      category: "stock_reports",
      entity_id: reportId,
      document_id: reportId,
      symbol,
      title_i18n: {
        en: reportNotification(report, "en").title,
        zh: reportNotification(report, "zh").title,
      },
      message_i18n: {
        en: reportNotification(report, "en").body,
        zh: reportNotification(report, "zh").body,
      },
      deep_link: `hc-trader://stocks/${symbol}/reports/${reportId}`,
      read_at: null,
      archived_at: null,
      created_at: FieldValue.serverTimestamp(),
    }, {merge: true});
    if (!pushAvailable) continue;
    let devices = await userReference.collection("devices").get();
    if (devices.empty) devices = await userReference.collection("notification_tokens").get();
    for (const device of devices.docs) {
      const data = device.data() || {};
      if (!text(data.token) || data.financial_daily_reports_enabled === false ||
          mapValue(data.preferences).financial_daily_reports === false) continue;
      const language = languageCode(data.language || user.language);
      const notification = reportNotification(report, language);
      const deliveryId = crypto.createHash("sha256")
          .update(`${eventId}|${subscriber.uid}|${device.id}`).digest("hex");
      const deliveryReference = db.collection("notificationDeliveries").doc(deliveryId);
      const existing = await deliveryReference.get();
      if (existing.exists && existing.data()?.status === "sent") continue;
      await deliveryReference.set({
        delivery_id: deliveryId,
        event_id: eventId,
        event_type: "financial_daily_report_published",
        category: "stock_reports",
        user_id: subscriber.uid,
        device_id: device.id,
        symbol,
        report_id: reportId,
        status: "sending",
        requested_at: FieldValue.serverTimestamp(),
      }, {merge: true});
      messages.push({
        token: text(data.token),
        notification,
        data: {
          category: "stock_reports",
          document_id: reportId,
          report_id: reportId,
          symbol,
          event_id: eventId,
          deep_link: `hc-trader://stocks/${symbol}/reports/${reportId}`,
        },
        android: {priority: "high", notification: {channelId: CHANNEL_ID}},
        apns: {payload: {aps: {sound: preferences.sound === false ? undefined : "default"}}},
      });
      deliveries.push({reference: deliveryReference, device: device.ref});
    }
  }

  let successful = 0;
  let failed = 0;
  for (let offset = 0; offset < messages.length; offset += 500) {
    const batch = messages.slice(offset, offset + 500);
    const response = await getMessaging().sendEach(batch);
    await Promise.all(response.responses.map(async (result, index) => {
      const delivery = deliveries[offset + index];
      if (result.success) {
        successful++;
        await delivery.reference.set({
          status: "sent",
          message_id: result.messageId,
          sent_at: FieldValue.serverTimestamp(),
        }, {merge: true});
      } else {
        failed++;
        const code = text(result.error?.code, "unknown");
        await delivery.reference.set({
          status: "failed", error_code: code, failed_at: FieldValue.serverTimestamp(),
        }, {merge: true});
        if (INVALID_TOKEN_CODES.has(code)) await delivery.device.delete().catch(() => null);
      }
    }));
  }
  await eventReference.set({
    status: failed ? "partial" : "sent",
    requested_count: messages.length,
    successful_count: successful,
    failed_count: failed,
    completed_at: FieldValue.serverTimestamp(),
  }, {merge: true});
}

function activeStockSubscriber(user) {
  return accountUsable(user) && hasApprovedAccess(user);
}

const notifyFinancialDailyReportPublished = onDocumentUpdated(
    {
      document: "stocks/{symbol}/reports/{reportId}",
      region: REGION,
      retry: true,
    },
    async (event) => {
      const before = event.data?.before.data() || {};
      const after = event.data?.after.data() || {};
      if (after.report_status !== "published" ||
          before.report_status === "published") return;
      await sendPublishedReport(after, event.params.reportId);
    },
);

function localExchangeParts(date, timezone) {
  const parts = new Intl.DateTimeFormat("en-CA", {
    timeZone: timezone,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    hourCycle: "h23",
    weekday: "short",
  }).formatToParts(date);
  const value = Object.fromEntries(parts.map((part) => [part.type, part.value]));
  return {
    date: `${value.year}-${value.month}-${value.day}`,
    hour: Number(value.hour),
    weekday: value.weekday,
  };
}

function generatedReportContent(stock, snapshot, date) {
  const quote = mapValue(snapshot.quote);
  const company = text(stock.company_name || snapshot.profile?.company_name, stock.symbol);
  const price = Number(quote.price || 0).toFixed(2);
  const change = Number(quote.change_percent || 0).toFixed(2);
  const timestamp = text(quote.as_of || snapshot.data_timestamp, date.toISOString());
  return {
    title_i18n: {
      en: `${company} (${stock.symbol}) Financial Daily Report`,
      zh: `${company}（${stock.symbol}）财经日报`,
    },
    summary_i18n: {
      en: `${stock.symbol} last traded at ${price}, a ${change}% daily move based on the latest available provider data.`,
      zh: `根据数据提供商的最新可用数据，${stock.symbol} 最新价格为 ${price}，单日变动 ${change}%。`,
    },
    content_i18n: {
      en: `Verified facts and data observations\n\nLatest available price: ${price}. Daily change: ${change}%. Data timestamp: ${timestamp}.\n\nAnalyst interpretation\n\nThis draft does not make a price prediction. Review volume, company news, sector developments, earnings dates, and material risk factors before publication.\n\nWhat to monitor next\n\nConfirm the next market session, company disclosures, and whether delayed data has been replaced by a newer verified snapshot.\n\nRisk disclaimer\n\nThis information is provided for education and general market awareness only. It is not personalized financial advice or a guarantee of future performance.`,
      zh: `已核实事实与数据观察\n\n最新可用价格：${price}。单日变动：${change}%。数据时间戳：${timestamp}。\n\n分析解读\n\n本草稿不作价格预测。发布前请审核成交量、公司新闻、行业发展、财报日期及重大风险因素。\n\n后续关注\n\n确认下一个交易时段、公司披露，以及延迟数据是否已由更新且经核实的数据快照取代。\n\n风险声明\n\n本资讯仅供教育及一般市场参考，不构成个性化投资建议，也不保证任何未来表现。`,
    },
  };
}

async function generateScheduledDraft(stockDocument, now) {
  const stock = {symbol: stockDocument.id, ...stockDocument.data()};
  const timezone = text(stock.exchange_timezone, "UTC");
  const local = localExchangeParts(now, timezone);
  if (["Sat", "Sun"].includes(local.weekday)) return "weekend";
  if (listValue(stock.market_holidays).includes(local.date)) return "holiday";
  if (local.hour !== Number(stock.report_generation_hour ?? 18)) return "wrong_hour";
  const reportReference = stockDocument.ref.collection("reports").doc(local.date);
  if ((await reportReference.get()).exists) return "duplicate";
  const snapshot = await provider().getSnapshot(stockDocument.id);
  const quote = mapValue(snapshot.quote);
  if (!quote.price || text(quote.freshness) === "unavailable") return "missing_data";
  const content = generatedReportContent(stock, snapshot, now);
  await reportReference.create({
    report_id: local.date,
    symbol: stockDocument.id,
    company_name: text(stock.company_name),
    report_date: Timestamp.fromDate(new Date(`${local.date}T00:00:00Z`)),
    ...content,
    source_references: listValue(snapshot.source_references || stock.source_references),
    market_data_snapshot: {quote, calendar: mapValue(snapshot.calendar)},
    report_status: "draft",
    generated_by: "scheduled_stock_report",
    data_timestamp: quote.as_of ? Timestamp.fromDate(new Date(quote.as_of)) : Timestamp.now(),
    version: 1,
    created_at: FieldValue.serverTimestamp(),
    updated_at: FieldValue.serverTimestamp(),
  });
  return "created";
}

const generateFinancialDailyReportDrafts = onSchedule(
    {
      schedule: "15 * * * *",
      timeZone: "UTC",
      region: REGION,
      retryCount: 2,
      secrets: [STOCK_DATA_API_KEY],
    },
    async () => {
      if (!await featureAvailable("stock_report_publishing", null, true)) {
        logger.info("Scheduled stock reports paused by feature configuration");
        return;
      }
      const db = getFirestore();
      const stocks = await db.collection("stocks")
          .where("report_generation_enabled", "==", true).limit(250).get();
      const job = db.collection("scheduledJobs").doc();
      const results = {};
      for (const stock of stocks.docs) {
        try {
          const result = await generateScheduledDraft(stock, new Date());
          results[result] = Number(results[result] || 0) + 1;
        } catch (error) {
          results.failed = Number(results.failed || 0) + 1;
          logger.error("Scheduled stock report failed", {
            symbol: stock.id,
            code: text(error?.code, "unknown"),
          });
        }
      }
      await job.set({
        job_type: "financial_daily_report_drafts",
        results,
        status: results.failed ? "partial" : "complete",
        executed_at: FieldValue.serverTimestamp(),
      });
    },
);

module.exports = {
  searchStocks,
  getStockSnapshot,
  saveFinancialDailyReportDraft,
  reviewFinancialDailyReport,
  notifyFinancialDailyReportPublished,
  generateFinancialDailyReportDrafts,
  StockMarketDataProvider,
  FirestoreStockMarketDataProvider,
  ConfiguredHttpStockMarketDataProvider,
  symbolValue,
  reportIdForDate,
  localExchangeParts,
  generatedReportContent,
  REPORT_TRANSITIONS,
  rolloutBucket,
  featureAvailable,
  compareVersions,
};
