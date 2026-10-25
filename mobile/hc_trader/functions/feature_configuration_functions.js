"use strict";

const {getFirestore, FieldValue} = require("firebase-admin/firestore");
const {onCall, HttpsError} = require("firebase-functions/v2/https");
const {ROLE_IDS, effectiveRoleIds, accountUsable} = require("./role_permissions");

const REGION = "us-central1";
const MODES = new Set([
  "enabled", "disabled", "hidden", "readOnly", "maintenance", "beta",
]);
const PLATFORMS = new Set(["android", "ios", "web", "windows", "macos"]);
const ENVIRONMENTS = new Set(["production", "staging", "development"]);
const PROTECTED_FEATURES = new Set([
  "trading_signals", "push_notifications", "stock_report_publishing",
  "content_publishing", "mt5_sync", "user_registrations",
]);

function text(value, fallback = "") {
  const resolved = value == null ? "" : String(value).trim();
  return resolved || fallback;
}

function strings(value, maximum = 100) {
  if (!Array.isArray(value)) return [];
  return [...new Set(value.map((item) => text(item)).filter(Boolean))]
      .slice(0, maximum);
}

function featureKey(value) {
  const key = text(value);
  if (!/^[a-z][a-z0-9_]{1,79}$/.test(key)) {
    throw new HttpsError("invalid-argument", "Invalid feature key.");
  }
  return key;
}

async function administrator(request) {
  const uid = request.auth?.uid;
  if (!uid) throw new HttpsError("unauthenticated", "Sign in is required.");
  const snapshot = await getFirestore().collection("users").doc(uid).get();
  const user = snapshot.data() || {};
  const roles = effectiveRoleIds(user);
  const permitted = [ROLE_IDS.superAdmin, ROLE_IDS.subAdmin, ROLE_IDS.founder]
      .some((role) => roles.has(role));
  if (!accountUsable(user) || !permitted) {
    throw new HttpsError("permission-denied", "Administrator access is required.");
  }
  return {uid, user, roles};
}

function normalizedConfiguration(data, key, nextVersion) {
  const mode = text(data.mode, "enabled");
  if (!MODES.has(mode)) {
    throw new HttpsError("invalid-argument", "Invalid feature operating mode.");
  }
  const platforms = strings(data.platforms).filter((item) => PLATFORMS.has(item));
  const environments = strings(data.environments)
      .filter((item) => ENVIRONMENTS.has(item));
  const roleIds = strings(data.role_ids).map(Number)
      .filter((item) => Number.isInteger(item) && item > 0);
  const rollout = Number(data.rollout_percentage ?? 100);
  if (!Number.isInteger(rollout) || rollout < 0 || rollout > 100) {
    throw new HttpsError("invalid-argument", "Rollout must be from 0 to 100.");
  }
  return {
    key,
    name: text(data.name, key).slice(0, 120),
    description: text(data.description).slice(0, 500),
    group: text(data.group, "experimental").slice(0, 60),
    mode,
    enabled: !["disabled", "maintenance"].includes(mode),
    visible: mode !== "hidden",
    platforms,
    role_ids: roleIds,
    access_levels: strings(data.access_levels),
    environments: environments.length ? environments : ["production"],
    minimum_app_version: text(data.minimum_app_version).slice(0, 40),
    rollout_percentage: rollout,
    selected_user_ids: strings(data.selected_user_ids, 500),
    regions: strings(data.regions),
    dependencies: strings(data.dependencies).filter((item) => item !== key),
    starts_at: data.starts_at || null,
    ends_at: data.ends_at || null,
    critical: data.critical === true || PROTECTED_FEATURES.has(key),
    version: nextVersion,
  };
}

async function validateDependencies(transaction, configuration) {
  if (!configuration.dependencies.length) return;
  const db = getFirestore();
  const snapshots = await Promise.all(configuration.dependencies.map((key) =>
    transaction.get(db.collection("featureConfigurations").doc(key))));
  const unavailable = snapshots.filter((snapshot) => {
    if (!snapshot.exists) return true;
    return ["disabled", "hidden", "maintenance"].includes(
        text(snapshot.data()?.mode, "enabled"),
    );
  }).map((snapshot) => snapshot.id);
  if (unavailable.length && !["disabled", "hidden", "maintenance"].includes(
      configuration.mode,
  )) {
    throw new HttpsError(
        "failed-precondition",
        `Enable required features first: ${unavailable.join(", ")}.`,
    );
  }
}

function assertCriticalPermission(actor, current, next) {
  const critical = current?.critical === true || next.critical === true;
  const changingAvailability = !current || current.mode !== next.mode ||
    current.rollout_percentage !== next.rollout_percentage;
  if (critical && changingAvailability && !actor.roles.has(ROLE_IDS.superAdmin)) {
    throw new HttpsError(
        "permission-denied",
        "Only a super administrator can change critical feature availability.",
    );
  }
}

function legacyEnabled(configuration) {
  return configuration.enabled && configuration.visible &&
    configuration.rollout_percentage > 0;
}

const updateFeatureConfiguration = onCall({region: REGION}, async (request) => {
  const actor = await administrator(request);
  const key = featureKey(request.data?.feature_key);
  const expected = Number(request.data?.expected_version ?? 0);
  if (!Number.isInteger(expected) || expected < 0) {
    throw new HttpsError("invalid-argument", "Invalid expected version.");
  }
  const db = getFirestore();
  const reference = db.collection("featureConfigurations").doc(key);
  let version = expected + 1;
  await db.runTransaction(async (transaction) => {
    const snapshot = await transaction.get(reference);
    const current = snapshot.data() || null;
    const currentVersion = Number(current?.version || 0);
    if (currentVersion !== expected) {
      throw new HttpsError(
          "aborted",
          "This feature configuration changed. Refresh and try again.",
      );
    }
    version = currentVersion + 1;
    const next = normalizedConfiguration(request.data || {}, key, version);
    assertCriticalPermission(actor, current, next);
    await validateDependencies(transaction, next);
    const legacySnapshot = current ? null : await transaction.get(
        db.collection("app_configuration").doc("modules"),
    );
    const now = FieldValue.serverTimestamp();
    if (current) {
      transaction.set(reference.collection("history").doc(String(currentVersion)), {
        ...current,
        snapshot_version: currentVersion,
        replaced_by_version: version,
        replaced_by: actor.uid,
        replaced_at: now,
      });
    } else {
      const legacyOn = legacySnapshot?.data()?.[key] !== false;
      transaction.set(reference.collection("history").doc("0"), {
        ...next,
        mode: legacyOn ? "enabled" : "disabled",
        enabled: legacyOn,
        visible: true,
        rollout_percentage: legacyOn ? 100 : 0,
        version: 0,
        snapshot_version: 0,
        replaced_by_version: version,
        replaced_by: actor.uid,
        replaced_at: now,
        source: "legacy_app_configuration",
      });
    }
    transaction.set(reference, {
      ...next,
      created_at: current?.created_at || now,
      created_by: current?.created_by || actor.uid,
      updated_at: now,
      updated_by: actor.uid,
    });
    transaction.set(db.collection("app_configuration").doc("modules"), {
      [key]: legacyEnabled(next),
      updated_at: now,
      updated_by: actor.uid,
    }, {merge: true});
    transaction.create(db.collection("featureConfigurationAudit").doc(), {
      feature_key: key,
      action: current ? "update" : "create",
      previous_version: currentVersion,
      version,
      previous_value: current,
      new_value: next,
      actor_id: actor.uid,
      created_at: now,
    });
  });
  return {feature_key: key, version};
});

const rollbackFeatureConfiguration = onCall({region: REGION}, async (request) => {
  const actor = await administrator(request);
  const key = featureKey(request.data?.feature_key);
  const expected = Number(request.data?.expected_version);
  if (!Number.isInteger(expected) || expected < 1) {
    throw new HttpsError("invalid-argument", "Invalid expected version.");
  }
  const db = getFirestore();
  const reference = db.collection("featureConfigurations").doc(key);
  const targetVersion = request.data?.target_version == null ? expected - 1 :
    Number(request.data.target_version);
  if (!Number.isInteger(targetVersion) || targetVersion < 0 || targetVersion >= expected) {
    throw new HttpsError("invalid-argument", "Invalid rollback version.");
  }
  await db.runTransaction(async (transaction) => {
    const currentSnapshot = await transaction.get(reference);
    const current = currentSnapshot.data();
    if (!current || Number(current.version || 0) !== expected) {
      throw new HttpsError("aborted", "This feature changed. Refresh and try again.");
    }
    const historySnapshot = await transaction.get(
        reference.collection("history").doc(String(targetVersion)),
    );
    if (!historySnapshot.exists) {
      throw new HttpsError("not-found", "The requested configuration version is unavailable.");
    }
    const restored = normalizedConfiguration(
        historySnapshot.data() || {}, key, expected + 1,
    );
    assertCriticalPermission(actor, current, restored);
    await validateDependencies(transaction, restored);
    const now = FieldValue.serverTimestamp();
    transaction.set(reference.collection("history").doc(String(expected)), {
      ...current,
      snapshot_version: expected,
      replaced_by_version: expected + 1,
      replaced_by: actor.uid,
      replaced_at: now,
    });
    transaction.set(reference, {
      ...restored,
      created_at: current.created_at || now,
      created_by: current.created_by || actor.uid,
      updated_at: now,
      updated_by: actor.uid,
      rolled_back_from_version: expected,
      rolled_back_to_snapshot: targetVersion,
    });
    transaction.set(db.collection("app_configuration").doc("modules"), {
      [key]: legacyEnabled(restored), updated_at: now, updated_by: actor.uid,
    }, {merge: true});
    transaction.create(db.collection("featureConfigurationAudit").doc(), {
      feature_key: key,
      action: "rollback",
      previous_version: expected,
      restored_snapshot_version: targetVersion,
      version: expected + 1,
      previous_value: current,
      new_value: restored,
      actor_id: actor.uid,
      created_at: now,
    });
  });
  return {feature_key: key, version: expected + 1, restored_version: targetVersion};
});

module.exports = {
  updateFeatureConfiguration,
  rollbackFeatureConfiguration,
  normalizedConfiguration,
  legacyEnabled,
  featureKey,
};
