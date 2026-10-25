"use strict";

const {getFirestore, FieldValue, Timestamp} = require("firebase-admin/firestore");
const {onSchedule} = require("firebase-functions/v2/scheduler");
const {logger} = require("firebase-functions");

const REGION = "us-central1";
const CONTENT_COLLECTIONS = Object.freeze([
  {name: "contents", booleanStatus: false},
  {name: "trading_blogs", booleanStatus: false},
  {name: "trading_news", booleanStatus: false},
  {name: "market_analyses", booleanStatus: false},
  {name: "knowledge_centres", booleanStatus: true},
  {name: "trading_recordings", booleanStatus: true},
  {name: "trading_resources", booleanStatus: false},
]);

function millis(value) {
  if (value && typeof value.toMillis === "function") return value.toMillis();
  const parsed = new Date(value || 0).getTime();
  return Number.isFinite(parsed) ? parsed : 0;
}

function configurationAllowsWrite(configuration = {}, now = Date.now()) {
  const mode = String(configuration.mode || "enabled").trim();
  const startsAt = millis(configuration.starts_at);
  const endsAt = millis(configuration.ends_at);
  return !["disabled", "hidden", "maintenance", "readOnly"].includes(mode) &&
    Number(configuration.rollout_percentage ?? 100) > 0 &&
    (!startsAt || startsAt <= now) && (!endsAt || endsAt > now);
}

async function featureAllowsWrite(db, key, now = Date.now()) {
  const snapshot = await db.collection("featureConfigurations").doc(key).get();
  if (snapshot.exists) {
    return configurationAllowsWrite(snapshot.data() || {}, now);
  }
  const legacy = await db.collection("app_configuration").doc("modules").get();
  return legacy.data()?.[key] !== false;
}

async function publishScheduledCollection(db, definition, now) {
  const snapshot = await db.collection(definition.name)
      .where("publication_status", "==", "scheduled").limit(250).get();
  const due = snapshot.docs.filter((document) => {
    const data = document.data() || {};
    const scheduled = millis(data.scheduled_at || data.start_at);
    return scheduled > 0 && scheduled <= now.toMillis();
  });
  for (let offset = 0; offset < due.length; offset += 400) {
    const batch = db.batch();
    due.slice(offset, offset + 400).forEach((document) => batch.update(
        document.ref,
        {
          publication_status: "published",
          status: definition.booleanStatus ? true : "published",
          published_at: FieldValue.serverTimestamp(),
          updated_at: FieldValue.serverTimestamp(),
          published_by_scheduler: true,
        },
    ));
    await batch.commit();
  }
  return due.length;
}

async function publishScheduledAnnouncements(db, now) {
  const scheduled = await db.collection("announcements")
      .where("status", "==", "scheduled").limit(250).get();
  const due = scheduled.docs.filter((document) => {
    const start = millis(document.data()?.start_at);
    return start > 0 && start <= now.toMillis();
  });
  const active = await db.collection("announcements")
      .where("status", "==", "published").limit(500).get();
  const expired = active.docs.filter((document) => {
    const expires = millis(document.data()?.expires_at);
    return expires > 0 && expires <= now.toMillis();
  });
  const changes = [
    ...due.map((document) => ({document, values: {
      status: "published",
      published_at: FieldValue.serverTimestamp(),
      updated_at: FieldValue.serverTimestamp(),
      published_by_scheduler: true,
    }})),
    ...expired.map((document) => ({document, values: {
      status: "expired",
      updated_at: FieldValue.serverTimestamp(),
      expired_by_scheduler: true,
    }})),
  ];
  for (let offset = 0; offset < changes.length; offset += 400) {
    const batch = db.batch();
    changes.slice(offset, offset + 400).forEach((change) =>
      batch.update(change.document.ref, change.values));
    await batch.commit();
  }
  return {published: due.length, expired: expired.length};
}

const publishScheduledContent = onSchedule(
    {schedule: "every 5 minutes", timeZone: "UTC", region: REGION,
      retryCount: 2},
    async () => {
      const db = getFirestore();
      const now = Timestamp.now();
      const results = {};
      if (await featureAllowsWrite(db, "content_publishing", now.toMillis())) {
        for (const definition of CONTENT_COLLECTIONS) {
          results[definition.name] = await publishScheduledCollection(
              db, definition, now,
          );
        }
      } else {
        results.contentPublishing = "paused_by_feature_configuration";
      }
      if (await featureAllowsWrite(db, "announcements", now.toMillis())) {
        results.announcements = await publishScheduledAnnouncements(db, now);
      } else {
        results.announcements = "paused_by_feature_configuration";
      }
      logger.info("Scheduled publishing sweep complete", results);
    },
);

module.exports = {
  publishScheduledContent,
  publishScheduledCollection,
  publishScheduledAnnouncements,
  configurationAllowsWrite,
  featureAllowsWrite,
  millis,
  CONTENT_COLLECTIONS,
};
