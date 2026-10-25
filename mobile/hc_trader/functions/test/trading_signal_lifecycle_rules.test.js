"use strict";

const fs = require("node:fs");
const path = require("node:path");
const {
  assertFails,
  assertSucceeds,
  initializeTestEnvironment,
} = require("@firebase/rules-unit-testing");
const {
  deleteDoc,
  doc,
  serverTimestamp,
  setDoc,
  updateDoc,
  writeBatch,
} = require("firebase/firestore");

const projectId = "hc-trader-signal-lifecycle-rules-test";

function user(roleId) {
  return {
    name: `Role ${roleId}`,
    email: `role-${roleId}@example.test`,
    role_id: roleId,
    role_ids: [roleId],
    is_admin: [1, 2, 3].includes(roleId),
    account_status: "active",
    is_suspended: false,
    accessStatus: "approved",
    access_status: "approved",
  };
}

function signal(owner, overrides = {}) {
  return {
    created_by: owner,
    provider_id: owner,
    published_by: owner,
    trading_pair: "XAUUSD",
    immediate_action: "BUY",
    order_type: "market_execution",
    entry_price: "2300",
    current_entry_price: "2300",
    original_entry_price: "2300",
    stop_loss: "2290",
    current_stop_loss: "2290",
    original_stop_loss: "2290",
    target_1: "2310",
    signal_title: "Gold setup",
    signal_title_i18n: {en: "Gold setup"},
    disclaimer: "Educational content only.",
    disclaimer_i18n: {en: "Educational content only."},
    primary_language: "en",
    available_languages: ["en"],
    status: 1,
    lifecycle_status: "active",
    version: 0,
    last_update_id: "publication-v0",
    last_audit_id: "publication-v0",
    ...overrides,
  };
}

function legacySignal(owner, overrides = {}) {
  const data = signal(owner, overrides);
  delete data.lifecycle_status;
  delete data.version;
  delete data.last_update_id;
  delete data.last_audit_id;
  return data;
}

function lifecycleEvent(owner, signalId, updateId, overrides = {}) {
  return {
    update_id: updateId,
    signal_id: signalId,
    update_type: "update_stop_loss",
    lifecycle_from: "active",
    lifecycle_to: "active",
    version_from: 0,
    version_to: 1,
    primary_language: "en",
    available_languages: [],
    reason: "Reduce open risk",
    previous_values: {current_stop_loss: "2290", version: 0},
    new_values: {current_stop_loss: "2295", version: 1},
    created_by: owner,
    created_by_name: "Provider A",
    created_by_role: "Signal Provider",
    notification_requested: true,
    notification_sent: false,
    is_correction: false,
    effective_at: serverTimestamp(),
    created_at: serverTimestamp(),
    ...overrides,
  };
}

function audit(owner, signalId, updateId, overrides = {}) {
  return {
    audit_id: updateId,
    signal_id: signalId,
    update_id: updateId,
    action: "update_stop_loss",
    actor_id: owner,
    lifecycle_from: "active",
    lifecycle_to: "active",
    version_from: 0,
    version_to: 1,
    created_at: serverTimestamp(),
    ...overrides,
  };
}

async function validUpdateBatch(
    db,
    owner = "provider-a",
    signalId = "signal-a",
) {
  const updateId = "update-v1";
  const batch = writeBatch(db);
  batch.update(doc(db, `trading_signals/${signalId}`), {
    stop_loss: "2295",
    current_stop_loss: "2295",
    lifecycle_status: "active",
    status: 1,
    version: 1,
    last_update_id: updateId,
    last_audit_id: updateId,
    last_update_type: "update_stop_loss",
    last_updated_by: owner,
    updated_by: owner,
    updated_at: serverTimestamp(),
  });
  batch.set(
      doc(db, `trading_signals/${signalId}/updates/${updateId}`),
      lifecycleEvent(owner, signalId, updateId),
  );
  batch.set(
      doc(db, `trading_signals/${signalId}/audit_logs/${updateId}`),
      audit(owner, signalId, updateId),
  );
  return batch.commit();
}

async function validLegacyBreakEvenBatch(db) {
  const signalId = "legacy-active-signal";
  const updateId = "legacy-break-even-v1";
  const batch = writeBatch(db);
  batch.update(doc(db, `trading_signals/${signalId}`), {
    break_even_price: "4010",
    IsSetBE: 1,
    lifecycle_status: "break_even",
    status: 1,
    version: 1,
    percentage_remaining: "100",
    last_update_id: updateId,
    last_audit_id: updateId,
    last_update_type: "update_break_even",
    last_updated_by: "provider-a",
    updated_by: "provider-a",
    updated_at: serverTimestamp(),
  });
  batch.set(
      doc(db, `trading_signals/${signalId}/updates/${updateId}`),
      lifecycleEvent("provider-a", signalId, updateId, {
        update_type: "update_break_even",
        lifecycle_from: "active",
        lifecycle_to: "break_even",
        previous_values: {version: 0},
        new_values: {break_even_price: "4010", version: 1},
      }),
  );
  batch.set(
      doc(db, `trading_signals/${signalId}/audit_logs/${updateId}`),
      audit("provider-a", signalId, updateId, {
        action: "update_break_even",
        lifecycle_from: "active",
        lifecycle_to: "break_even",
      }),
  );
  return batch.commit();
}

async function main() {
  const rules = fs.readFileSync(
      path.resolve(__dirname, "../../firestore.rules"),
      "utf8",
  );
  const testEnv = await initializeTestEnvironment({
    projectId,
    firestore: {rules},
  });
  try {
    await testEnv.withSecurityRulesDisabled(async (context) => {
      const db = context.firestore();
      await setDoc(doc(db, "users/provider-a"), user(201));
      await setDoc(doc(db, "users/provider-b"), user(202));
      await setDoc(doc(db, "users/super-a"), user(1));
      await setDoc(doc(db, "users/trader-a"), user(750));
      await setDoc(doc(db, "users/suspended-a"), {
        ...user(201),
        account_status: "suspended",
        is_suspended: true,
      });
      await setDoc(doc(db, "trading_signals/signal-a"), signal("provider-a"));
      await setDoc(
          doc(db, "trading_signals/assigned-signal"),
          signal("provider-a", {assigned_provider_id: "provider-b"}),
      );
      await setDoc(
          doc(db, "trading_signals/super-signal"),
          signal("provider-a"),
      );
      await setDoc(
          doc(db, "trading_signals/trader-signal"),
          signal("trader-a"),
      );
      await setDoc(
          doc(db, "trading_signals/suspended-signal"),
          signal("suspended-a"),
      );
      await setDoc(
          doc(db, "trading_signals/draft-a"),
          signal("provider-a", {lifecycle_status: "draft", status: 0}),
      );
      await setDoc(
          doc(db, "trading_signals/legacy-active-signal"),
          legacySignal("provider-a", {
            entry_price: "4000",
            current_entry_price: "4000",
            stop_loss: "3999",
            current_stop_loss: "3999",
          }),
      );
    });

    const providerA = testEnv.authenticatedContext("provider-a").firestore();
    const providerB = testEnv.authenticatedContext("provider-b").firestore();
    const superAdmin = testEnv.authenticatedContext("super-a").firestore();
    const trader = testEnv.authenticatedContext("trader-a").firestore();
    const suspended = testEnv.authenticatedContext("suspended-a").firestore();

    const publication = writeBatch(providerA);
    publication.set(
        doc(providerA, "trading_signals/new-signal"),
        signal("provider-a", {
          created_at: serverTimestamp(),
          updated_at: serverTimestamp(),
          published_at: serverTimestamp(),
        }),
    );
    publication.set(
        doc(
            providerA,
            "trading_signals/new-signal/updates/publication-v0",
        ),
        lifecycleEvent("provider-a", "new-signal", "publication-v0", {
          update_type: "published",
          lifecycle_from: "draft",
          lifecycle_to: "active",
          version_from: -1,
          version_to: 0,
          available_languages: ["en"],
          notification_requested: false,
        }),
    );
    publication.set(
        doc(
            providerA,
            "trading_signals/new-signal/audit_logs/publication-v0",
        ),
        audit("provider-a", "new-signal", "publication-v0", {
          action: "published",
          lifecycle_from: "draft",
          lifecycle_to: "active",
          version_from: -1,
          version_to: 0,
        }),
    );
    await assertSucceeds(publication.commit());

    await assertFails(updateDoc(doc(providerA, "trading_signals/signal-a"), {
      current_stop_loss: "2295",
      stop_loss: "2295",
    }));
    await assertFails(validUpdateBatch(providerB, "provider-b"));
    await assertSucceeds(
        validUpdateBatch(providerB, "provider-b", "assigned-signal"),
    );
    await assertSucceeds(
        validUpdateBatch(superAdmin, "super-a", "super-signal"),
    );
    await assertFails(
        validUpdateBatch(trader, "trader-a", "trader-signal"),
    );
    await assertFails(
        validUpdateBatch(suspended, "suspended-a", "suspended-signal"),
    );
    await assertSucceeds(validUpdateBatch(providerA));
    await assertSucceeds(validLegacyBreakEvenBatch(providerA));

    await assertFails(updateDoc(
        doc(providerA, "trading_signals/signal-a/updates/update-v1"),
        {reason: "Changed history"},
    ));
    await assertFails(deleteDoc(
        doc(providerA, "trading_signals/signal-a/audit_logs/update-v1"),
    ));
    await assertFails(deleteDoc(doc(providerA, "trading_signals/draft-a")));
    await assertSucceeds(deleteDoc(doc(superAdmin, "trading_signals/draft-a")));

    const invalidBatch = writeBatch(providerA);
    invalidBatch.update(doc(providerA, "trading_signals/signal-a"), {
      lifecycle_status: "pending_entry",
      status: 0,
      version: 2,
      last_update_id: "invalid-v2",
      updated_by: "provider-a",
    });
    invalidBatch.set(
        doc(providerA, "trading_signals/signal-a/updates/invalid-v2"),
        lifecycleEvent("provider-a", "signal-a", "invalid-v2", {
          update_type: "correction",
          lifecycle_to: "pending_entry",
          version_from: 1,
          version_to: 2,
        }),
    );
    await assertFails(invalidBatch.commit());

    console.log("Trading signal lifecycle rules tests passed.");
  } finally {
    await testEnv.cleanup();
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
