"use strict";

const {initializeApp} = require("firebase-admin/app");
const {getFirestore, FieldValue} = require("firebase-admin/firestore");

initializeApp();
const db = getFirestore();
const apply = process.argv.includes("--apply");
const summary = {users: 0, journals: 0, announcements: 0, referrals: 0, skipped: 0, failed: 0};

async function write(reference, values) {
  if (!apply) return;
  await reference.set({...values, migration_version: 2, migrated_at: FieldValue.serverTimestamp()}, {merge: true});
}

async function migrateUser(document) {
  const user = document.data() || {};
  const accountSnapshot = await document.ref.collection("mt5_accounts").get();
  const accounts = accountSnapshot.docs.filter((account) => {
    const data = account.data() || {};
    return data.is_active !== false && data.is_deleted !== true &&
      data.is_archived !== true;
  });
  const mainId = String(user.mainMt5AccountId || user.main_mt5_account_id || "");
  const updates = {};
  if (!mainId && accounts.length === 1) {
    const account = accounts[0];
    updates.mainMt5AccountId = account.id;
    updates.main_mt5_account_id = account.id;
    updates.mainMt5AccountKey = account.id;
    updates.main_mt5_account_key = account.id;
    updates.mainMt5AccountLogin = Number(account.data().mt5_login || 0);
    updates.main_mt5_account_login = Number(account.data().mt5_login || 0);
  } else if (!mainId && accounts.length > 1) {
    summary.skipped++;
  }
  if (!user.accessStatus && !user.access_status) {
    updates.accessStatus = "notSubmitted";
    updates.access_status = "notSubmitted";
  }
  if (!user.requested_role_id) {
    updates.requested_role_id = Number(user.role_id || 750);
  }
  if (Object.keys(updates).length) {
    await write(document.ref, updates);
    summary.users++;
  }

  const journals = await document.ref.collection("trading_journals").get();
  for (const journal of journals.docs) {
    const data = journal.data() || {};
    if (data.sourceType || data.source_type) continue;
    const mt5 = Boolean(
        data.mt5_ticket_id || data.mt5TicketId || data.mt5_position_id ||
        data.mt5PositionId || data.mt5_account_id || data.mt5AccountId,
    );
    await write(journal.ref, {
      sourceType: mt5 ? "mt5" : "manual",
      source_type: mt5 ? "mt5" : "manual",
    });
    summary.journals++;
  }
}

async function main() {
  const users = await db.collection("users").get();
  for (const user of users.docs) {
    try {
      await migrateUser(user);
    } catch (error) {
      summary.failed++;
      console.error(`user ${user.id}: ${error.code || error.message}`);
    }
  }
  const announcements = await db.collection("announcements").get();
  for (const item of announcements.docs) {
    const data = item.data() || {};
    const updates = {};
    if (!data.audience) updates.audience = "all";
    if (!data.category) updates.category = "general";
    if (!data.priority) updates.priority = "normal";
    if (data.is_pinned == null) updates.is_pinned = false;
    if (!Array.isArray(data.target_role_ids)) updates.target_role_ids = [];
    if (!Array.isArray(data.target_user_ids)) updates.target_user_ids = [];
    if (Object.keys(updates).length) {
      await write(item.ref, updates);
      summary.announcements++;
    }
  }
  const referrals = await db.collection("referral_codes").get();
  for (const item of referrals.docs) {
    const data = item.data() || {};
    const updates = {};
    if (!data.status) updates.status = data.is_active === false ? "expired" : "active";
    if (data.usage_count == null) updates.usage_count = 0;
    if (Object.keys(updates).length) {
      await write(item.ref, updates);
      summary.referrals++;
    }
  }
  console.log(JSON.stringify({mode: apply ? "apply" : "dry-run", ...summary}));
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
