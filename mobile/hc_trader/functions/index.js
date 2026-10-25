const {initializeApp} = require("firebase-admin/app");
const {getFirestore, FieldValue} = require("firebase-admin/firestore");
const {getMessaging} = require("firebase-admin/messaging");
const {
  onDocumentCreated,
  onDocumentUpdated,
  onDocumentWritten,
} = require("firebase-functions/v2/firestore");
const {logger} = require("firebase-functions");
const {
  registerUserProfileAfterAuth,
  recruitDownlineByReferralCode,
  deleteCurrentUserAccount,
  createDesktopCustomSignInToken,
  getMyUplineRelationship,
  adminCreateUser,
  adminListDeletedUsers,
  adminReviewAccessApplication,
  withdrawAccessApplication,
  ensureWeeklyRecruitmentReferralCode,
  adminUpdateUserAccessControl,
  adminAddUserNote,
  syncPendingUserClaims,
} = require("./registration_functions");
const performanceRatings = require("./performance_rating_functions");
const dailyQuiz = require("./daily_quiz_functions");
const coursework = require("./coursework_functions");
const marketAnalyst = require("./market_analyst_functions");
const tradeCallStatistics = require("./trade_call_statistics_functions");
const mt5Import = require("./mt5_import_functions");
const publisherAudit = require("./publisher_audit_functions");
const tradingSignalUpdates = require("./trading_signal_update_functions");
const stockMarket = require("./stock_market_functions");
const featureConfiguration = require("./feature_configuration_functions");
const leaderboard = require("./leaderboard_functions");
const publishingSchedule = require("./publishing_schedule_functions");

initializeApp();

exports.registerUserProfileAfterAuth = registerUserProfileAfterAuth;
exports.recruitDownlineByReferralCode = recruitDownlineByReferralCode;
exports.deleteCurrentUserAccount = deleteCurrentUserAccount;
exports.createDesktopCustomSignInToken = createDesktopCustomSignInToken;
exports.getMyUplineRelationship = getMyUplineRelationship;
exports.adminCreateUser = adminCreateUser;
exports.adminListDeletedUsers = adminListDeletedUsers;
exports.adminReviewAccessApplication = adminReviewAccessApplication;
exports.withdrawAccessApplication = withdrawAccessApplication;
exports.ensureWeeklyRecruitmentReferralCode = ensureWeeklyRecruitmentReferralCode;
exports.adminUpdateUserAccessControl = adminUpdateUserAccessControl;
exports.adminAddUserNote = adminAddUserNote;
exports.syncPendingUserClaims = syncPendingUserClaims;
exports.searchStocks = stockMarket.searchStocks;
exports.getStockSnapshot = stockMarket.getStockSnapshot;
exports.saveFinancialDailyReportDraft =
  stockMarket.saveFinancialDailyReportDraft;
exports.reviewFinancialDailyReport = stockMarket.reviewFinancialDailyReport;
exports.notifyFinancialDailyReportPublished =
  stockMarket.notifyFinancialDailyReportPublished;
exports.generateFinancialDailyReportDrafts =
  stockMarket.generateFinancialDailyReportDrafts;
exports.updateFeatureConfiguration =
  featureConfiguration.updateFeatureConfiguration;
exports.rollbackFeatureConfiguration =
  featureConfiguration.rollbackFeatureConfiguration;
exports.calculateLeaderboardPeriod = leaderboard.calculateLeaderboardPeriod;
exports.recalculateActiveLeaderboards =
  leaderboard.recalculateActiveLeaderboards;
exports.publishScheduledContent = publishingSchedule.publishScheduledContent;
exports.recalculatePerformanceRatings =
  performanceRatings.recalculatePerformanceRatings;
exports.recalculateRatingOnJournalWrite =
  performanceRatings.recalculateRatingOnJournalWrite;
exports.recalculateRatingOnMt5TradeWrite =
  performanceRatings.recalculateRatingOnMt5TradeWrite;
exports.recalculateRatingOnPsychologyWrite =
  performanceRatings.recalculateRatingOnPsychologyWrite;
exports.snapshotTradingPlanVersion =
  performanceRatings.snapshotTradingPlanVersion;
exports.recalculateRatingOnUserScopeChange =
  performanceRatings.recalculateRatingOnUserScopeChange;
exports.submitDailyQuizAttempt = dailyQuiz.submitDailyQuizAttempt;
exports.reviewDailyQuizSubmission = dailyQuiz.reviewDailyQuizSubmission;
exports.assignCourseworkTheme = coursework.assignCourseworkTheme;
exports.updateCourseworkAssignment = coursework.updateCourseworkAssignment;
exports.startCourseworkAttempt = coursework.startCourseworkAttempt;
exports.submitCourseworkAttempt = coursework.submitCourseworkAttempt;
exports.gradeCourseworkAttempt = coursework.gradeCourseworkAttempt;
exports.recalculateOverallResult = coursework.recalculateOverallResult;
exports.toggleMarketAnalystFollow = marketAnalyst.toggleMarketAnalystFollow;
exports.loadTradeCallStatistics = tradeCallStatistics.loadTradeCallStatistics;
exports.previewMt5DesktopImport = mt5Import.previewMt5DesktopImport;
exports.beginMt5DesktopImport = mt5Import.beginMt5DesktopImport;
exports.uploadMt5DesktopImportChunk = mt5Import.uploadMt5DesktopImportChunk;
exports.finalizeMt5DesktopImport = mt5Import.finalizeMt5DesktopImport;
exports.auditTradingSignalWrites = publisherAudit.auditTradingSignalWrites;
exports.auditMarketAnalysisWrites = publisherAudit.auditMarketAnalysisWrites;
exports.notifyTradingSignalUpdate =
  tradingSignalUpdates.notifyTradingSignalUpdate;
exports.retryDeferredSignalNotifications =
  tradingSignalUpdates.retryDeferredSignalNotifications;

// The Firestore database is in nam5; us-central1 keeps event delivery nearby.
const REGION = "us-central1";
const CHANNEL_ID = "hc_trader_updates";
const INVALID_NOTIFICATION_TOKEN_CODES = new Set([
  "messaging/invalid-registration-token",
  "messaging/registration-token-not-registered",
]);

function text(value, fallback = "") {
  const resolved = value == null ? "" : String(value).trim();
  return resolved || fallback;
}

async function globalFeatureEnabled(key) {
  const db = getFirestore();
  const snapshot = await db.collection("featureConfigurations").doc(key).get();
  if (snapshot.exists) {
    const configuration = snapshot.data() || {};
    if (["disabled", "hidden", "maintenance"].includes(
        text(configuration.mode, "enabled"))) return false;
    const now = Date.now();
    const starts = configuration.starts_at?.toMillis?.() || 0;
    const ends = configuration.ends_at?.toMillis?.() || 0;
    return (!starts || starts <= now) && (!ends || ends > now) &&
      Number(configuration.rollout_percentage ?? 100) > 0;
  }
  const legacy = await db.collection("app_configuration").doc("modules").get();
  return legacy.data()?.[key] !== false;
}

function localizedText(value, language, fallback = "") {
  const preferred = String(language || "en").toLowerCase().startsWith("zh") ?
    "zh" : "en";
  const secondary = preferred === "zh" ? "en" : "zh";
  if (value && typeof value === "object") {
    return text(value[preferred], text(value[secondary], fallback));
  }
  return text(value, fallback);
}

async function sendTopic(topic, title, body, category, documentId) {
  if (!await globalFeatureEnabled("push_notifications")) {
    logger.info("Push notification skipped by feature configuration", {
      category, documentId,
    });
    return;
  }
  await getMessaging().send({
    topic,
    notification: {title, body},
    data: {
      category,
      document_id: documentId,
    },
    android: {
      priority: "high",
      notification: {channelId: CHANNEL_ID},
    },
    apns: {
      payload: {aps: {sound: "default"}},
    },
  });
}

function activeSignal(data) {
  return Number(data?.status || 0) > 0 &&
    ![12, 13, 15].includes(Number(data?.status || 0)) &&
    data?.is_done !== true && Number(data?.is_done || 0) !== 1;
}

function publishableSignal(data) {
  if (!data) return false;
  const lifecycle = text(data.lifecycle_status).toLowerCase();
  return activeSignal(data) || [
    "published",
    "pending",
    "pending_entry",
  ].includes(lifecycle);
}

function publishedContent(data) {
  if (!data || data.approval_status === "rejected") return false;
  if (data.publication_status != null) {
    return data.publication_status === "published";
  }
  if (typeof data.status === "string") {
    return ["published", "approved"].includes(data.status);
  }
  return data.status !== false && data.status !== 0;
}

exports.notifyTradingSignal = onDocumentWritten(
    {document: "trading_signals/{signalId}", region: REGION},
    async (event) => {
      const before = event.data?.before.exists ? event.data.before.data() : null;
      const after = event.data?.after.exists ? event.data.after.data() : null;
      // Lifecycle changes have their own idempotent update notification.
      // This trigger is only for the initial published document.
      if (before || !publishableSignal(after)) return;
      // Initial publications enter the same immutable, per-user delivery
      // pipeline as every other lifecycle event. This avoids a broad topic
      // broadcast and prevents duplicate create/update notifications.
      const updateReference = event.data.after.ref
          .collection("updates").doc("publication-v0");
      const existing = await updateReference.get();
      if (!existing.exists) {
        await updateReference.create({
          update_id: "publication-v0",
          signal_id: event.params.signalId,
          update_type: "published",
          lifecycle_from: "draft",
          lifecycle_to: text(after.lifecycle_status, "published"),
          previous_values: {},
          new_values: {
            lifecycle_status: text(after.lifecycle_status, "published"),
            version: Number(after.version || 0),
          },
          title_i18n: after.signal_title_i18n || after.title_i18n || {},
          message_i18n: after.analysis_i18n || {},
          primary_language: text(after.primary_language, "en"),
          created_by: text(after.created_by || after.published_by),
          created_by_role: text(after.created_by_role, "Signal Provider"),
          source: "signal_publication_trigger",
          notification_requested: true,
          notification_sent: false,
          created_at: FieldValue.serverTimestamp(),
        });
      }
    },
);

exports.notifyMarketAnalysis = onDocumentCreated(
    {document: "market_analyses/{analysisId}", region: REGION},
    async (event) => {
      const data = event.data?.data();
      if (!data || !publishedContent(data) ||
          data.notification_requested !== true) return;
      const language = text(data.primary_language, "en");
      await sendTopic(
          "hc_market_analysis",
          language.startsWith("zh") ? "新的市场分析" : "New market analysis",
          localizedText(
              data.title_i18n || data.title,
              language,
              `${text(data.market, "Market")} outlook is ready`,
          ),
          "market_analysis",
          event.params.analysisId,
      );
    },
);

exports.notifyRecording = onDocumentWritten(
    {document: "trading_recordings/{recordingId}", region: REGION},
    async (event) => {
      const before = event.data?.before.exists ? event.data.before.data() : null;
      const after = event.data?.after.exists ? event.data.after.data() : null;
      if (!after || publishedContent(before) || !publishedContent(after) ||
          after.notification_requested !== true) return;
      await sendTopic(
          "hc_recordings",
          "New recording class",
          text(after.title, "A new trading class is ready"),
          "recordings",
          event.params.recordingId,
      );
    },
);

exports.notifyKnowledgeCentre = onDocumentWritten(
    {document: "knowledge_centres/{knowledgeId}", region: REGION},
    async (event) => {
      const before = event.data?.before.exists ? event.data.before.data() : null;
      const after = event.data?.after.exists ? event.data.after.data() : null;
      if (!after || publishedContent(before) || !publishedContent(after) ||
          after.notification_requested !== true) return;
      await sendTopic(
          "hc_knowledge_centre",
          "New knowledge-centre resource",
          text(after.title, "A new learning resource is ready"),
          "knowledge_centre",
          event.params.knowledgeId,
      );
    },
);

function announcementAudienceMatches(userId, userData, announcement) {
  const target = text(announcement.audience, "all");
  if (target === "all") return true;
  const accessStatus = text(userData.accessStatus || userData.access_status);
  if (target === "approved_clients") return accessStatus === "approved";
  if (target === "pending_users") {
    return accessStatus === "pendingReview" || accessStatus === "pending";
  }
  if (target.startsWith("role:")) {
    return String(userData.role_id || "") === target.substring(5);
  }
  if (target === "roles") {
    const targetRoles = (announcement.target_role_ids || []).map(String);
    const roles = [userData.role_id, ...(userData.role_ids || [])].map(String);
    return targetRoles.some((role) => roles.includes(role));
  }
  if (target === "users") {
    return (announcement.target_user_ids || []).map(String).includes(userId);
  }
  if (target === "prop_firm_groups") {
    const targetGroups = (announcement.target_prop_firm_group_ids || [])
        .map(String);
    const userGroups = (userData.prop_firm_group_ids || []).map(String);
    return targetGroups.some((group) => userGroups.includes(group));
  }
  if (target === "subscription_levels") {
    const access = text(userData.subscription_level || userData.access_level);
    return (announcement.target_access_levels || []).map(String).includes(access);
  }
  return false;
}

function localizedAnnouncementValue(announcement, key, language, fallback) {
  const values = announcement[`${key}_i18n`];
  if (values && typeof values === "object") {
    const primary = String(language || "en").toLowerCase().startsWith("zh") ?
      "zh" : "en";
    const secondary = primary === "zh" ? "en" : "zh";
    return text(values[primary], text(values[secondary], fallback));
  }
  return text(announcement[key], fallback);
}

function activeAnnouncementRecipient(user) {
  return !["inactive", "disabled", "suspended", "revoked", "deleted"]
      .includes(text(user.account_status, "active")) &&
    !["suspended", "revoked", "deleted"].includes(
        text(user.accessStatus || user.access_status),
    ) && user.is_suspended !== true;
}

async function announcementDevices(announcement) {
  const users = await getFirestore().collection("users").get();
  const devices = [];
  const seen = new Set();
  for (const user of users.docs) {
    const userData = user.data();
    if (!activeAnnouncementRecipient(userData) ||
      userData.announcementNotificationsEnabled === false ||
      userData.announcement_notifications_enabled === false ||
      !announcementAudienceMatches(user.id, userData, announcement)) {
      continue;
    }
    const preferenceSnapshot = await user.ref
        .collection("notificationPreferences").doc("default").get();
    const userPreferences = preferenceSnapshot.data() || {};
    if (userPreferences.announcements === false) continue;
    let snapshot = await user.ref.collection("devices").get();
    if (snapshot.empty) {
      snapshot = await user.ref.collection("notification_tokens").get();
    }
    for (const tokenDoc of snapshot.docs) {
      const data = tokenDoc.data();
      const preferences = data.preferences || {};
      if (data.announcements_enabled === false ||
        preferences.announcements === false) {
        continue;
      }
      const token = text(data.token);
      if (!token || seen.has(token)) continue;
      seen.add(token);
      devices.push({
        token,
        userId: user.id,
        userReference: user.ref,
        deviceId: tokenDoc.id,
        deviceReference: tokenDoc.ref,
        language: text(data.language || userData.language, "en"),
        preferences: {...userPreferences, ...preferences},
      });
    }
  }
  return devices;
}

async function sendAnnouncementNotification(data, announcementId) {
  if (data.notification_requested !== true && data.push_notification !== true) {
    logger.info("Announcement push was not requested", {announcementId});
    return;
  }
  if (!await globalFeatureEnabled("push_notifications")) {
    logger.info("Announcement push paused by feature configuration", {
      announcementId,
    });
    return;
  }
  const db = getFirestore();
  const eventId = `announcement_${announcementId}`;
  const eventReference = db.collection("notificationEvents").doc(eventId);
  const eventSnapshot = await eventReference.get();
  if (eventSnapshot.data()?.status === "sent") return;
  await eventReference.set({
    event_id: eventId,
    event_type: "announcement_published",
    announcement_id: announcementId,
    status: "sending",
    attempt_count: FieldValue.increment(1),
    requested_at: FieldValue.serverTimestamp(),
  }, {merge: true});
  const devices = await announcementDevices(data);
  if (!devices.length) {
    await eventReference.set({
      status: "sent", requested_count: 0, successful_count: 0,
      completed_at: FieldValue.serverTimestamp(),
    }, {merge: true});
    await db.collection("announcements").doc(announcementId).set({
      delivery_requested_count: 0,
      delivery_success_count: 0,
      delivery_failure_count: 0,
      delivery_metrics_updated_at: FieldValue.serverTimestamp(),
    }, {merge: true});
    logger.info("Announcement published with no eligible notification tokens", {
      announcementId,
      audience: text(data.audience, "all"),
    });
    return;
  }
  const deliveries = [];
  for (const device of devices) {
    const title = localizedAnnouncementValue(
        data, "title", device.language, "HC Trader announcement",
    );
    const fallbackBody = text(
        data.short_message || data.shortMessage || data.message,
        "Open HC Trader to read the latest announcement",
    );
    const body = localizedAnnouncementValue(
        data, "short_message", device.language, fallbackBody,
    ).slice(0, 180);
    const deliveryReference = db.collection("notificationDeliveries")
        .doc(`${eventId}_${device.userId}_${device.deviceId}`);
    const existing = await deliveryReference.get();
    if (existing.data()?.status === "sent") continue;
    await device.userReference.collection("notifications").doc(eventId).set({
      event_id: eventId,
      category: "announcements",
      entity_id: announcementId,
      document_id: announcementId,
      title_i18n: data.title_i18n || {en: text(data.title)},
      message_i18n: data.short_message_i18n || {en: fallbackBody},
      deep_link: text(data.deep_link, `hc-trader://announcements/${announcementId}`),
      read_at: null,
      archived_at: null,
      created_at: FieldValue.serverTimestamp(),
    }, {merge: true});
    await deliveryReference.set({
      delivery_id: deliveryReference.id,
      event_id: eventId,
      event_type: "announcement_published",
      category: "announcements",
      user_id: device.userId,
      device_id: device.deviceId,
      announcement_id: announcementId,
      status: "sending",
      requested_at: FieldValue.serverTimestamp(),
    }, {merge: true});
    deliveries.push({
      reference: deliveryReference,
      deviceReference: device.deviceReference,
      message: {
        token: device.token,
        notification: {title, body},
        data: {
          category: "announcements",
          document_id: announcementId,
          announcement_id: announcementId,
          event_id: eventId,
          deep_link: text(data.deep_link,
              `hc-trader://announcements/${announcementId}`),
          click_action: "FLUTTER_NOTIFICATION_CLICK",
        },
        android: {priority: "high", notification: {channelId: CHANNEL_ID}},
        apns: {payload: {aps: {
          sound: device.preferences.sound === false ? undefined : "default",
        }}},
      },
    });
  }
  let successful = 0;
  let failed = 0;
  for (let index = 0; index < deliveries.length; index += 500) {
    const group = deliveries.slice(index, index + 500);
    const response = await getMessaging().sendEach(
        group.map((delivery) => delivery.message),
    );
    await Promise.all(response.responses.map(async (result, offset) => {
      const delivery = group[offset];
      if (result.success) {
        successful++;
        await delivery.reference.set({
          status: "sent", message_id: result.messageId,
          sent_at: FieldValue.serverTimestamp(),
        }, {merge: true});
      } else {
        failed++;
        const code = text(result.error?.code, "unknown");
        await delivery.reference.set({
          status: "failed", error_code: code,
          failed_at: FieldValue.serverTimestamp(),
        }, {merge: true});
        if (INVALID_NOTIFICATION_TOKEN_CODES.has(code)) {
          await delivery.deviceReference.delete().catch(() => null);
        }
      }
    }));
    if (response.failureCount) {
      logger.warn("Some announcement notifications failed", {
        announcementId, failures: response.failureCount,
      });
    }
  }
  await eventReference.set({
    status: failed ? "partial" : "sent",
    requested_count: deliveries.length,
    successful_count: successful,
    failed_count: failed,
    completed_at: FieldValue.serverTimestamp(),
  }, {merge: true});
  await db.collection("announcements").doc(announcementId).set({
    delivery_requested_count: deliveries.length,
    delivery_success_count: successful,
    delivery_failure_count: failed,
    delivery_metrics_updated_at: FieldValue.serverTimestamp(),
  }, {merge: true});
}

exports.notifyAnnouncementPublished = onDocumentWritten(
    {document: "announcements/{announcementId}", region: REGION},
    async (event) => {
      const before = event.data?.before.exists ? event.data.before.data() : null;
      const after = event.data?.after.exists ? event.data.after.data() : null;
      if (!after || after.status !== "published") return;
      if (before?.status === "published") return;
      logger.info("Published announcement detected", {
        announcementId: event.params.announcementId,
        audience: text(after.audience, "all"),
      });
      await sendAnnouncementNotification(after, event.params.announcementId);
    },
);

exports.aggregateAnnouncementEngagement = onDocumentWritten(
    {
      document: "announcements/{announcementId}/reads/{userId}",
      region: REGION,
    },
    async (event) => {
      const before = event.data?.before.exists ? event.data.before.data() : {};
      const after = event.data?.after.exists ? event.data.after.data() : {};
      if (!event.data?.after.exists) return;
      const increments = {};
      if (!before.read_at && after.read_at) increments.read_count =
        FieldValue.increment(1);
      if (!before.acknowledged_at && after.acknowledged_at) {
        increments.acknowledgement_count = FieldValue.increment(1);
      }
      if (!before.dismissed_at && after.dismissed_at) {
        increments.dismissal_count = FieldValue.increment(1);
      }
      if (!Object.keys(increments).length) return;
      await getFirestore().collection("announcements")
          .doc(event.params.announcementId).set({
            ...increments,
            engagement_metrics_updated_at: FieldValue.serverTimestamp(),
          }, {merge: true});
    },
);

function timestampValue(value) {
  if (value && typeof value.toMillis === "function") return value.toMillis();
  return value == null ? "" : String(value);
}

function appointmentChanged(before, after) {
  if (!before) return true;
  return before.status !== after.status ||
    before.location !== after.location ||
    timestampValue(before.scheduled_start_at) !==
      timestampValue(after.scheduled_start_at);
}

async function appointmentTokens(participantIds) {
  const db = getFirestore();
  const snapshots = await Promise.all(
      participantIds.map((uid) =>
        db.collection("users").doc(uid).collection("notification_tokens").get(),
      ),
  );
  const tokens = [];
  for (const snapshot of snapshots) {
    for (const tokenDoc of snapshot.docs) {
      const data = tokenDoc.data();
      if (data.appointments_enabled !== false && text(data.token)) {
        tokens.push(data.token);
      }
    }
  }
  return [...new Set(tokens)];
}

async function userTokens(uid) {
  if (!text(uid)) return [];
  const snapshot = await getFirestore()
      .collection("users")
      .doc(String(uid))
      .collection("notification_tokens")
      .get();
  return [...new Set(snapshot.docs
      .map((document) => text(document.data().token))
      .filter(Boolean))];
}

async function sendUserNotification(uid, title, body, category, documentId) {
  const tokens = await userTokens(uid);
  for (let index = 0; index < tokens.length; index += 500) {
    const response = await getMessaging().sendEachForMulticast({
      tokens: tokens.slice(index, index + 500),
      notification: {title, body},
      data: {category, document_id: String(documentId)},
      android: {
        priority: "high",
        notification: {channelId: CHANNEL_ID},
      },
      apns: {payload: {aps: {sound: "default"}}},
    });
    if (response.failureCount) {
      logger.warn("Some direct notifications failed", {
        category,
        documentId,
        failures: response.failureCount,
      });
    }
  }
}

async function sendAppointmentNotification(data, appointmentId, isNew) {
  const participantIds = Array.isArray(data.participant_ids) ?
    data.participant_ids.map(String).filter(Boolean) : [];
  if (!participantIds.length) return;
  const tokens = await appointmentTokens(participantIds);
  if (!tokens.length) return;

  const status = text(data.status, "pending");
  const title = isNew ? "Trading appointment received" :
    `Appointment ${status}`;
  const body = text(data.subject, "Open HC Trader for appointment details");
  for (let index = 0; index < tokens.length; index += 500) {
    const batch = tokens.slice(index, index + 500);
    const response = await getMessaging().sendEachForMulticast({
      tokens: batch,
      notification: {title, body},
      data: {category: "appointments", document_id: appointmentId},
      android: {
        priority: "high",
        notification: {channelId: CHANNEL_ID},
      },
      apns: {payload: {aps: {sound: "default"}}},
    });
    if (response.failureCount) {
      logger.warn("Some appointment notifications failed", {
        appointmentId,
        failures: response.failureCount,
      });
    }
  }
}

exports.notifyNewAppointment = onDocumentCreated(
    {document: "trading_appointments/{appointmentId}", region: REGION},
    async (event) => {
      const data = event.data?.data();
      if (data) {
        await sendAppointmentNotification(
            data,
            event.params.appointmentId,
            true,
        );
      }
    },
);

exports.notifyAppointmentUpdate = onDocumentUpdated(
    {document: "trading_appointments/{appointmentId}", region: REGION},
    async (event) => {
      const before = event.data?.before.data();
      const after = event.data?.after.data();
      if (after && appointmentChanged(before, after)) {
        await sendAppointmentNotification(
            after,
            event.params.appointmentId,
            false,
        );
      }
    },
);

exports.notifySupportTicketUpdate = onDocumentUpdated(
    {document: "support_tickets/{ticketId}", region: REGION},
    async (event) => {
      const before = event.data?.before.data();
      const after = event.data?.after.data();
      if (!after || !after.user_id) return;
      const statusChanged = before?.status !== after.status;
      const staffReplied = after.last_reply_is_admin === true &&
        timestampValue(before?.updated_at) !== timestampValue(after.updated_at);
      if (!statusChanged && !staffReplied) return;
      await sendUserNotification(
          after.user_id,
          `Support ticket ${text(after.status, "updated")}`,
          text(after.last_message || after.closing_note,
              "Your support ticket has been updated"),
          "support_tickets",
          event.params.ticketId,
      );
    },
);

exports.notifyDeleteRequestDecision = onDocumentUpdated(
    {document: "delete_requests/{requestId}", region: REGION},
    async (event) => {
      const before = event.data?.before.data();
      const after = event.data?.after.data();
      if (!after || before?.status === after.status ||
        !["approved", "rejected"].includes(after.status)) return;
      await sendUserNotification(
          after.requester_id,
          `Delete request ${after.status}`,
          text(after.review_note, "Your delete request has been reviewed"),
          "delete_requests",
          event.params.requestId,
      );
    },
);

exports.notifyDeleteRequestSubmitted = onDocumentCreated(
    {document: "delete_requests/{requestId}", region: REGION},
    async (event) => {
      const data = event.data?.data();
      if (!data) return;
      const admins = await getFirestore()
          .collection("users")
          .where("role_id", "==", 1)
          .get();
      await Promise.all(admins.docs.map((admin) => sendUserNotification(
          admin.id,
          "Delete request submitted",
          `${text(data.target_type, "Content")} requires review`,
          "delete_requests",
          event.params.requestId,
      )));
    },
);

exports.notifyPropFirmReview = onDocumentUpdated(
    {document: "users/{userId}", region: REGION},
    async (event) => {
      const before = event.data?.before.data();
      const after = event.data?.after.data();
      const status = text(after?.prop_firm_review_status);
      if (!status || text(before?.prop_firm_review_status) === status) return;
      await sendUserNotification(
          event.params.userId,
          "Prop-firm review updated",
          text(after.prop_firm_review_note,
              `Your review status is ${status.replaceAll("_", " ")}`),
          "prop_firm_results",
          event.params.userId,
      );
    },
);

async function notifyExamResult(event, examLabel) {
  const data = event.data?.data();
  if (!data) return;
  const score = data.percentage ?? data.score ?? data.total_score;
  const result = data.passed === true || data.result === "passed" ?
    "passed" : "available";
  await sendUserNotification(
      event.params.userId,
      `${examLabel} result ${result}`,
      score == null ? "Open HC Trader to view your result" :
        `Your score is ${score}`,
      "exam_results",
      event.params.attemptId,
  );
}

exports.notifyTradingExamResult = onDocumentCreated(
    {
      document: "users/{userId}/trading_exam_attempts/{attemptId}",
      region: REGION,
    },
    (event) => notifyExamResult(event, "Trading exam"),
);

exports.notifyFinalExamResult = onDocumentCreated(
    {
      document: "users/{userId}/final_exam_attempts/{attemptId}",
      region: REGION,
    },
    (event) => notifyExamResult(event, "Final examination"),
);
