"use strict";

const crypto = require("node:crypto");
const {getFirestore, FieldValue, Timestamp} = require("firebase-admin/firestore");
const {getMessaging} = require("firebase-admin/messaging");
const {onDocumentCreated} = require("firebase-functions/v2/firestore");
const {onSchedule} = require("firebase-functions/v2/scheduler");
const {logger} = require("firebase-functions");

const REGION = "us-central1";
const CHANNEL_ID = "hc_trader_updates";
const DELIVERY_BATCH_SIZE = 500;
const INVALID_TOKEN_CODES = new Set([
  "messaging/invalid-registration-token",
  "messaging/registration-token-not-registered",
]);

function text(value, fallback = "") {
  const resolved = value == null ? "" : String(value).trim();
  return resolved || fallback;
}

function languageCode(value) {
  return text(value, "en").toLowerCase().startsWith("zh") ? "zh" : "en";
}

function localizedText(value, language, fallback = "") {
  const primary = languageCode(language);
  const secondary = primary === "zh" ? "en" : "zh";
  if (value && typeof value === "object") {
    return text(value[primary], text(value[secondary], fallback));
  }
  return text(value, fallback);
}

const UPDATE_LABELS = Object.freeze({
  published: {en: "New trading signal", zh: "新交易信号"},
  details_updated: {en: "Signal details updated", zh: "信号详情已更新"},
  entry_triggered: {en: "Entry triggered", zh: "入场已触发"},
  buy_limit_activated: {en: "Buy limit activated", zh: "买入限价单已触发"},
  sell_limit_activated: {en: "Sell limit activated", zh: "卖出限价单已触发"},
  buy_stop_activated: {en: "Buy stop activated", zh: "买入止损单已触发"},
  sell_stop_activated: {en: "Sell stop activated", zh: "卖出止损单已触发"},
  entry_price_updated: {en: "Entry price updated", zh: "入场价已更新"},
  entry_range_updated: {en: "Entry zone updated", zh: "入场区间已更新"},
  update_stop_loss: {en: "Stop loss updated", zh: "止损已更新"},
  move_sl_to_be: {en: "Stop moved to break even", zh: "止损已移至保本"},
  update_break_even: {en: "Break-even price updated", zh: "保本价已更新"},
  trailing_break_even: {en: "Break even is trailing", zh: "保本位开始追踪"},
  trailing_stop_loss: {en: "Trailing stop activated", zh: "追踪止损已启用"},
  trailing_stop_updated: {en: "Trailing-stop level updated", zh: "追踪止损水平已更新"},
  lock_profit: {en: "Profit locked", zh: "利润已锁定"},
  reduce_risk: {en: "Risk reduced", zh: "风险已降低"},
  risk_changed: {en: "Risk level changed", zh: "风险等级已更改"},
  remove_pending_order: {en: "Pending order cancelled", zh: "挂单已取消"},
  partial_close: {en: "Position partially closed", zh: "仓位已部分平仓"},
  partial_profit_taken: {en: "Partial profit secured", zh: "已锁定部分利润"},
  target_hit: {en: "Take-profit target hit", zh: "止盈目标已达到"},
  stop_loss_hit: {en: "Stop loss reached", zh: "止损已触发"},
  break_even_hit: {en: "Break even reached", zh: "保本位已触发"},
  manually_closed: {en: "Signal manually closed", zh: "信号已手动平仓"},
  closed_in_profit: {en: "Signal closed in profit", zh: "信号盈利平仓"},
  closed_in_loss: {en: "Signal closed in loss", zh: "信号亏损平仓"},
  cancelled: {en: "Signal cancelled", zh: "信号已取消"},
  expired: {en: "Signal expired", zh: "信号已过期"},
  invalidated: {en: "Signal invalidated", zh: "信号已失效"},
  completed: {en: "Signal completed", zh: "信号已完成"},
  reopened: {en: "Signal reopened", zh: "信号已重新开启"},
  provider_update: {en: "Signal-provider update", zh: "信号提供者更新"},
  symbol_corrected: {en: "Instrument corrected", zh: "交易品种已更正"},
  commentary: {en: "Signal update", zh: "交易信号更新"},
  important_note: {en: "Important analyst note", zh: "重要分析师备注"},
  market_commentary: {en: "Market commentary", zh: "市场评论"},
  warning: {en: "Signal warning", zh: "信号警示"},
  chart: {en: "Chart updated", zh: "图表已更新"},
  analysis: {en: "Analysis updated", zh: "分析已更新"},
  archived: {en: "Signal archived", zh: "信号已归档"},
  restored: {en: "Signal restored", zh: "信号已恢复"},
  correction: {en: "Signal correction", zh: "信号更正"},
});

const EVENT_PREFERENCE_KEYS = Object.freeze({
  published: "new_signals",
  entry_triggered: "entry_activation",
  buy_limit_activated: "entry_activation",
  sell_limit_activated: "entry_activation",
  buy_stop_activated: "entry_activation",
  sell_stop_activated: "entry_activation",
  entry_price_updated: "entry_activation",
  entry_range_updated: "entry_activation",
  update_stop_loss: "stop_loss_updates",
  trailing_stop_loss: "stop_loss_updates",
  trailing_stop_updated: "stop_loss_updates",
  move_sl_to_be: "breakeven_updates",
  update_break_even: "breakeven_updates",
  trailing_break_even: "breakeven_updates",
  break_even_hit: "breakeven_updates",
  target_hit: "take_profit_events",
  partial_close: "take_profit_events",
  partial_profit_taken: "take_profit_events",
  cancelled: "signal_cancellation",
  expired: "signal_cancellation",
  invalidated: "signal_cancellation",
  remove_pending_order: "signal_cancellation",
  manually_closed: "signal_closure",
  closed_in_profit: "signal_closure",
  closed_in_loss: "signal_closure",
  completed: "signal_closure",
  stop_loss_hit: "signal_closure",
  commentary: "important_signal_notes",
  important_note: "important_signal_notes",
  provider_update: "important_signal_notes",
  warning: "important_signal_notes",
  correction: "important_signal_notes",
  reopened: "important_signal_notes",
});

function notificationEventId(signalId, updateId) {
  return `${text(signalId)}_${text(updateId)}`;
}

function notificationClaimDecision(state = {}, now = Date.now()) {
  if (state.status === "sent") return "duplicate";
  if (state.status === "sending") {
    const claimedAt = state.claimed_at?.toMillis?.() || 0;
    if (now - claimedAt < 2 * 60 * 1000) return "leased";
  }
  return "claim";
}

function buildSignalUpdateNotification(update, signal = {}, requestedLanguage) {
  const language = languageCode(
      requestedLanguage || update.primary_language || signal.primary_language,
  );
  const type = text(update.update_type || update.event_type, "commentary");
  const labels = UPDATE_LABELS[type] || UPDATE_LABELS.commentary;
  const pair = text(signal.trading_pair || signal.symbol,
      language === "zh" ? "交易信号" : "Signal");
  const direction = localizedText(
      signal.immediate_action_i18n || signal.immediate_action || signal.direction,
      language,
  );
  const provider = text(
      signal.provider_name || signal.published_by_name || signal.analyst_name,
  );
  const importantPrice = text(
      update.target_price || update.new_values?.current_stop_loss ||
      update.new_values?.break_even_price ||
      update.new_values?.current_entry_price ||
      update.new_values?.entry_price,
  );
  const signalContext = [pair, direction].filter(Boolean).join(" ");
  const detailSuffix = [
    importantPrice ? `@ ${importantPrice}` : "",
    provider,
  ].filter(Boolean).join(" · ");
  const title = localizedText(update.title_i18n, language,
      labels[language]);
  const reason = text(update.reason);
  const message = localizedText(update.message_i18n, language, reason);
  const target = Number(update.target_number || 0);
  const fallbackBody = type === "target_hit" && target > 0 ?
    (language === "zh" ? `${signalContext}：TP${target} 已达到` :
      `${signalContext}: TP${target} reached`) :
    `${signalContext}: ${labels[language]}`;
  const body = message ? `${signalContext}: ${message}` : fallbackBody;
  return {
    language,
    title,
    body: `${body}${detailSuffix ? ` · ${detailSuffix}` : ""}`.slice(0, 180),
  };
}

function activeUser(user = {}) {
  const status = text(user.account_status || user.accountStatus, "active");
  const access = text(user.access_status || user.accessStatus);
  return !["inactive", "disabled", "suspended", "revoked", "deleted",
    "deletionInProgress", "deletion_in_progress"].includes(status) &&
    !["suspended", "revoked", "deleted"].includes(access) &&
    user.is_suspended !== true;
}

function userRoleIds(user = {}) {
  const values = Array.isArray(user.role_ids) ? user.role_ids : [];
  if (user.role_id != null) values.push(user.role_id);
  return new Set(values.map(String));
}

function userCanReceiveSignal(userId, user = {}, signal = {}) {
  if (!activeUser(user)) return false;
  const roles = userRoleIds(user);
  const isAdmin = user.is_admin === true ||
    ["1", "2", "3"].some((role) => roles.has(role));
  const approved = text(user.access_status || user.accessStatus) === "approved";
  if (!isAdmin && !approved) return false;
  if (user.signal_notifications_enabled === false) return false;

  const targetUsers = (signal.target_user_ids || []).map(String);
  if (targetUsers.length && !targetUsers.includes(String(userId))) return false;
  const targetRoles = (signal.target_role_ids || []).map(String);
  if (targetRoles.length && !targetRoles.some((role) => roles.has(role))) {
    return false;
  }
  const accessLevels = (signal.target_access_levels || []).map(String);
  if (accessLevels.length &&
      !accessLevels.includes(text(user.subscription_level || user.access_level))) {
    return false;
  }
  const groups = (signal.target_prop_firm_groups || []).map(String);
  const userGroups = (user.prop_firm_group_ids || []).map(String);
  if (groups.length && !groups.some((group) => userGroups.includes(group))) {
    return false;
  }
  return true;
}

function preferenceEnabled(preferences = {}, eventType) {
  if (preferences.signals === false) return false;
  const key = EVENT_PREFERENCE_KEYS[eventType] || "signals";
  return preferences[key] !== false;
}

function quietHoursState(preferences = {}, offsetMinutes = 0, now = Date.now()) {
  if (preferences.quiet_hours_enabled !== true) return {quiet: false};
  const start = Number(preferences.quiet_hours_start_minutes ?? 1320);
  const end = Number(preferences.quiet_hours_end_minutes ?? 420);
  const local = new Date(now + Number(offsetMinutes || 0) * 60 * 1000);
  const minute = local.getUTCHours() * 60 + local.getUTCMinutes();
  const quiet = start === end ? true : start < end ?
    minute >= start && minute < end : minute >= start || minute < end;
  if (!quiet) return {quiet: false};
  const untilEnd = start < end ? end - minute :
    minute < end ? end - minute : 1440 - minute + end;
  return {quiet: true, deliverAfter: now + Math.max(1, untilEnd) * 60 * 1000};
}

function deliveryId(eventId, userId, deviceId) {
  return crypto.createHash("sha256")
      .update(`${eventId}|${userId}|${deviceId}`)
      .digest("hex");
}

function featureConfigurationAllows(configuration = {}, userId = "", user = {}) {
  const mode = text(configuration.mode, "enabled");
  if (["disabled", "hidden", "maintenance"].includes(mode)) return false;
  const now = Date.now();
  const timestamp = (value) => value?.toMillis?.() ||
    new Date(value || 0).getTime() || 0;
  const starts = timestamp(configuration.starts_at);
  const ends = timestamp(configuration.ends_at);
  if ((starts && starts > now) || (ends && ends <= now)) return false;
  if (!userId) return Number(configuration.rollout_percentage ?? 100) > 0;
  const roles = [...userRoleIds(user)];
  const enabledRoles = (configuration.role_ids || []).map(String);
  const accessLevels = (configuration.access_levels || []).map(String);
  const selectedUsers = (configuration.selected_user_ids || []).map(String);
  const regions = (configuration.regions || [])
      .map((value) => text(value).toUpperCase());
  const access = text(user.subscription_level || user.access_level);
  const region = text(user.country_code || user.region).toUpperCase();
  if (enabledRoles.length && !enabledRoles.some((role) => roles.includes(role))) {
    return false;
  }
  if (accessLevels.length && !accessLevels.includes(access)) return false;
  if (selectedUsers.length && !selectedUsers.includes(String(userId))) return false;
  if (regions.length && (!region || !regions.includes(region))) return false;
  const rollout = Math.min(100, Math.max(0,
      Number(configuration.rollout_percentage ?? 100)));
  const bucket = Number.parseInt(crypto.createHash("sha256")
      .update(`${configuration.key || "feature"}|${userId}`)
      .digest("hex").slice(0, 8), 16) % 100;
  return bucket < rollout;
}

async function loadFeatureConfiguration(key) {
  const db = getFirestore();
  const snapshot = await db.collection("featureConfigurations").doc(key).get();
  if (snapshot.exists) return {key, ...snapshot.data()};
  const legacy = await db.collection("app_configuration").doc("modules").get();
  return {key, mode: legacy.data()?.[key] === false ? "disabled" : "enabled"};
}

async function claimNotificationEvent(reference, payload) {
  return getFirestore().runTransaction(async (transaction) => {
    const existing = await transaction.get(reference);
    if (existing.exists) {
      const state = existing.data() || {};
      const decision = notificationClaimDecision(state);
      if (decision === "duplicate") return false;
      if (decision === "leased") {
        const error = new Error("Notification delivery lease is active.");
        error.code = "notification-lease-active";
        throw error;
      }
      transaction.set(reference, {
        ...payload,
        status: "sending",
        attempt_count: FieldValue.increment(1),
        claimed_at: FieldValue.serverTimestamp(),
        last_error_code: FieldValue.delete(),
      }, {merge: true});
      return true;
    }
    transaction.create(reference, {
      ...payload,
      status: "sending",
      attempt_count: 1,
      claimed_at: FieldValue.serverTimestamp(),
    });
    return true;
  });
}

async function deviceDocuments(userReference) {
  const devices = await userReference.collection("devices").get();
  if (!devices.empty) return devices.docs;
  const legacy = await userReference.collection("notification_tokens").get();
  return legacy.docs;
}

async function createImmutableEvent(signalId, updateId, update, signal) {
  const reference = getFirestore().collection("trading_signals")
      .doc(signalId).collection("events").doc(updateId);
  await reference.set({
    event_id: updateId,
    signal_id: signalId,
    event_type: text(update.update_type, "commentary"),
    previous_status: text(update.lifecycle_from || update.previous_status),
    new_status: text(update.lifecycle_to || update.new_status),
    previous_values: update.previous_values || {},
    new_values: update.new_values || {},
    message_i18n: update.message_i18n || {},
    created_at: update.created_at || FieldValue.serverTimestamp(),
    created_by: text(update.created_by),
    created_by_role: text(update.created_by_role),
    source: text(update.source, "signal_lifecycle"),
    notification_required: update.notification_requested === true,
    notification_status: "processing",
    symbol: text(signal.trading_pair || signal.symbol),
  }, {merge: false});
  return reference;
}

async function prepareDeliveries({
  eventId, signalId, updateId, update, signal, featureConfigurations = [],
}) {
  const db = getFirestore();
  const users = await db.collection("users").get();
  const eventType = text(update.update_type, "commentary");
  const prepared = [];
  let requested = 0;
  let quiet = 0;

  for (const userDocument of users.docs) {
    const user = userDocument.data() || {};
    if (!userCanReceiveSignal(userDocument.id, user, signal)) continue;
    if (featureConfigurations.some((configuration) =>
      !featureConfigurationAllows(configuration, userDocument.id, user))) {
      continue;
    }
    const preferenceDocument = await userDocument.ref
        .collection("notificationPreferences").doc("default").get();
    const preferences = preferenceDocument.data() || {};
    if (!preferenceEnabled(preferences, eventType)) continue;

    const language = languageCode(
        preferences.language || user.language || user.locale || "en",
    );
    const localized = buildSignalUpdateNotification(update, signal, language);
    await userDocument.ref.collection("notifications").doc(eventId).set({
      event_id: eventId,
      category: "signals",
      entity_id: signalId,
      document_id: signalId,
      symbol: text(signal.trading_pair || signal.symbol),
      title_i18n: {
        en: buildSignalUpdateNotification(update, signal, "en").title,
        zh: buildSignalUpdateNotification(update, signal, "zh").title,
      },
      message_i18n: {
        en: buildSignalUpdateNotification(update, signal, "en").body,
        zh: buildSignalUpdateNotification(update, signal, "zh").body,
      },
      deep_link: `hc-trader://signals/${signalId}?event=${updateId}`,
      read_at: null,
      archived_at: null,
      created_at: FieldValue.serverTimestamp(),
    }, {merge: true});

    const devices = await deviceDocuments(userDocument.ref);
    for (const deviceDocument of devices) {
      const device = deviceDocument.data() || {};
      const devicePreferences = device.preferences || preferences;
      if (device.notifications_enabled === false ||
          device.signals_enabled === false ||
          !preferenceEnabled(devicePreferences, eventType) ||
          !text(device.token)) continue;
      const id = deliveryId(eventId, userDocument.id, deviceDocument.id);
      const deliveryReference = db.collection("notificationDeliveries").doc(id);
      const existing = await deliveryReference.get();
      if (existing.exists && ["sent", "sending", "deferred"].includes(
          text(existing.data()?.status))) continue;
      const deviceLanguage = languageCode(device.language || language);
      const notification = buildSignalUpdateNotification(
          update, signal, deviceLanguage,
      );
      const quietState = quietHoursState(
          devicePreferences,
          device.timezone_offset_minutes || user.timezone_offset_minutes || 0,
      );
      const delivery = {
        reference: deliveryReference,
        token: text(device.token),
        deviceReference: deviceDocument.ref,
        message: {
          token: text(device.token),
          notification: {title: notification.title, body: notification.body},
          data: {
            category: "signals",
            document_id: signalId,
            signal_id: signalId,
            update_id: updateId,
            event_id: eventId,
            symbol: text(signal.trading_pair || signal.symbol),
            deep_link: `hc-trader://signals/${signalId}?event=${updateId}`,
            click_action: "FLUTTER_NOTIFICATION_CLICK",
          },
          android: {
            priority: "high",
            notification: {channelId: CHANNEL_ID},
          },
          apns: {payload: {aps: {
            sound: devicePreferences.sound === false ? undefined : "default",
          }}},
        },
      };
      const baseLog = {
        delivery_id: id,
        event_id: eventId,
        event_type: eventType,
        category: "signals",
        user_id: userDocument.id,
        device_id: deviceDocument.id,
        signal_id: signalId,
        update_id: updateId,
        language: deviceLanguage,
        requested_at: FieldValue.serverTimestamp(),
        attempt_count: FieldValue.increment(1),
      };
      requested++;
      if (quietState.quiet) {
        quiet++;
        await deliveryReference.set({
          ...baseLog,
          status: "deferred",
          deliver_after: Timestamp.fromMillis(quietState.deliverAfter),
          payload: delivery.message,
        }, {merge: true});
      } else {
        await deliveryReference.set({...baseLog, status: "sending"},
            {merge: true});
        prepared.push(delivery);
      }
    }
  }
  return {prepared, requested, quiet};
}

async function sendPreparedDeliveries(deliveries) {
  let successful = 0;
  let failed = 0;
  let invalid = 0;
  for (let offset = 0; offset < deliveries.length;
    offset += DELIVERY_BATCH_SIZE) {
    const batch = deliveries.slice(offset, offset + DELIVERY_BATCH_SIZE);
    const response = await getMessaging().sendEach(
        batch.map((delivery) => delivery.message),
    );
    await Promise.all(response.responses.map(async (result, index) => {
      const delivery = batch[index];
      if (result.success) {
        successful++;
        await delivery.reference.set({
          status: "sent",
          message_id: result.messageId,
          sent_at: FieldValue.serverTimestamp(),
          payload: FieldValue.delete(),
        }, {merge: true});
        return;
      }
      failed++;
      const code = text(result.error?.code, "unknown");
      await delivery.reference.set({
        status: "failed",
        error_code: code,
        failed_at: FieldValue.serverTimestamp(),
      }, {merge: true});
      if (INVALID_TOKEN_CODES.has(code)) {
        invalid++;
        await delivery.deviceReference.delete().catch(() => null);
      }
    }));
  }
  return {successful, failed, invalid};
}

const notifyTradingSignalUpdate = onDocumentCreated(
    {
      document: "trading_signals/{signalId}/updates/{updateId}",
      region: REGION,
      retry: true,
    },
    async (event) => {
      const update = event.data?.data();
      if (!update) return;
      const {signalId, updateId} = event.params;
      const db = getFirestore();
      const signalSnapshot = await db.collection("trading_signals")
          .doc(signalId).get();
      const signal = signalSnapshot.data() || {};
      const immutableEventReference = await createImmutableEvent(
          signalId, updateId, update, signal,
      );
      if (update.notification_requested !== true) {
        await immutableEventReference.set({notification_status: "not_requested"},
            {merge: true});
        return;
      }

      const featureConfigurations = await Promise.all([
        loadFeatureConfiguration("trading_signals"),
        loadFeatureConfiguration("push_notifications"),
      ]);
      if (featureConfigurations.some((configuration) =>
        !featureConfigurationAllows(configuration))) {
        await immutableEventReference.set({
          notification_status: "paused_by_feature_configuration",
        }, {merge: true});
        return;
      }

      const eventId = notificationEventId(signalId, updateId);
      const eventReference = db.collection("notificationEvents").doc(eventId);
      const claimed = await claimNotificationEvent(eventReference, {
        event_id: eventId,
        event_type: text(update.update_type, "commentary"),
        signal_id: signalId,
        update_id: updateId,
      });
      if (!claimed) {
        logger.info("Trading signal update notification already delivered", {
          eventId,
        });
        return;
      }

      try {
        const delivery = await prepareDeliveries({
          eventId, signalId, updateId, update, signal, featureConfigurations,
        });
        const result = await sendPreparedDeliveries(delivery.prepared);
        const status = result.failed > 0 ? "partial" :
          delivery.quiet > 0 ? "sent_with_deferred" : "sent";
        await Promise.all([
          eventReference.set({
            status,
            requested_count: delivery.requested,
            successful_count: result.successful,
            failed_count: result.failed,
            deferred_count: delivery.quiet,
            invalid_token_count: result.invalid,
            completed_at: FieldValue.serverTimestamp(),
          }, {merge: true}),
          immutableEventReference.set({
            notification_status: status,
            notification_event_id: eventId,
          }, {merge: true}),
          event.data.ref.set({
            notification_sent: result.failed === 0,
            notification_event_id: eventId,
            notification_sent_at: FieldValue.serverTimestamp(),
          }, {merge: true}),
        ]);
      } catch (error) {
        await eventReference.set({
          status: "failed",
          error_code: text(error?.code, "unknown"),
          failed_at: FieldValue.serverTimestamp(),
        }, {merge: true});
        await immutableEventReference.set({
          notification_status: "failed",
          notification_error_code: text(error?.code, "unknown"),
        }, {merge: true});
        logger.error("Trading signal update notification failed", {
          eventId,
          error,
        });
        throw error;
      }
    },
);

const retryDeferredSignalNotifications = onSchedule(
    {
      schedule: "every 15 minutes",
      region: REGION,
      retryCount: 3,
      timeZone: "UTC",
    },
    async () => {
      const db = getFirestore();
      const snapshot = await db.collection("notificationDeliveries")
          .where("status", "==", "deferred")
          .where("deliver_after", "<=", Timestamp.now())
          .limit(500)
          .get();
      const deliveries = [];
      for (const document of snapshot.docs) {
        const data = document.data() || {};
        const userId = text(data.user_id);
        const deviceId = text(data.device_id);
        const payload = data.payload;
        if (!userId || !deviceId || !payload) continue;
        let deviceReference = db.collection("users").doc(userId)
            .collection("devices").doc(deviceId);
        let device = await deviceReference.get();
        if (!device.exists) {
          deviceReference = db.collection("users").doc(userId)
              .collection("notification_tokens").doc(deviceId);
          device = await deviceReference.get();
        }
        const token = text(device.data()?.token);
        if (!token) {
          await document.ref.set({status: "invalid_token"}, {merge: true});
          continue;
        }
        deliveries.push({
          reference: document.ref,
          deviceReference,
          message: {...payload, token},
        });
        await document.ref.set({status: "sending"}, {merge: true});
      }
      const result = await sendPreparedDeliveries(deliveries);
      logger.info("Deferred signal notification retry complete", result);
    },
);

module.exports = {
  notifyTradingSignalUpdate,
  retryDeferredSignalNotifications,
  notificationEventId,
  notificationClaimDecision,
  buildSignalUpdateNotification,
  userCanReceiveSignal,
  preferenceEnabled,
  quietHoursState,
  deliveryId,
  featureConfigurationAllows,
};
