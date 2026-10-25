"use strict";

/**
 * Idempotent repair audit for legacy MT5 balance rows stored as trades.
 *
 * Dry-run (default):
 *   node scripts/audit_mt5_balance_misclassification.js
 *
 * Explicit repair after reviewing the dry-run output:
 *   node scripts/audit_mt5_balance_misclassification.js \
 *     --apply --confirm=RECLASSIFY_MT5_BALANCE_RECORDS
 */

const {initializeApp, getApps} = require("firebase-admin/app");
const {getFirestore, FieldValue} = require("firebase-admin/firestore");

if (!getApps().length) initializeApp();

const APPLY = process.argv.includes("--apply");
const CONFIRMED = process.argv.includes(
    "--confirm=RECLASSIFY_MT5_BALANCE_RECORDS",
);

function text(value) {
  return value == null ? "" : String(value).trim();
}

function amount(data) {
  const value = Number(data.profit_decimal ?? data.profit ?? 0) +
    Number(data.commission_decimal ?? data.commission ?? 0) +
    Number(data.fee_decimal ?? data.fee ?? 0) +
    Number(data.swap_decimal ?? data.swap ?? 0);
  return Number.isFinite(value) ? value : 0;
}

function classification(data) {
  const type = text(data.trade_type || data.deal_type || data.record_type)
      .toLowerCase();
  const comment = text(data.comment).toLowerCase();
  if (type.includes("deposit") || comment.includes("deposit") ||
      comment.includes("入金") || comment.includes("存款")) return "deposit";
  if (type.includes("withdraw") || comment.includes("withdraw") ||
      comment.includes("出金") || comment.includes("提款")) return "withdrawal";
  if (type.includes("credit")) return amount(data) < 0 ? "creditRemoval" : "credit";
  if (type.includes("bonus")) return "bonus";
  if (type === "balance" && (comment.includes("demo") ||
      comment.includes("fund"))) return amount(data) >= 0 ? "deposit" : "withdrawal";
  return null;
}

async function main() {
  if (APPLY && !CONFIRMED) {
    throw new Error("Repair requires --confirm=RECLASSIFY_MT5_BALANCE_RECORDS");
  }
  const db = getFirestore();
  const snapshot = await db.collectionGroup("trades").get();
  const affected = [];
  for (const doc of snapshot.docs) {
    const parts = doc.ref.path.split("/");
    if (parts.length !== 6 || parts[0] !== "users" ||
        parts[2] !== "mt5_accounts" || parts[4] !== "trades") continue;
    const data = doc.data() || {};
    if (data.balance_reclassification_version === 1) continue;
    const category = classification(data);
    if (!category) continue;
    affected.push({
      ref: doc.ref,
      userId: parts[1],
      accountId: parts[3],
      recordId: doc.id,
      category,
      amount: amount(data),
      oldTradingPnl: amount(data),
      correctedTradingPnl: 0,
      data,
    });
  }

  const grouped = new Map();
  for (const item of affected) {
    const key = `${item.userId}/${item.accountId}`;
    const current = grouped.get(key) || {
      userId: item.userId,
      accountId: item.accountId,
      records: 0,
      oldTradingPnl: 0,
      correctedTradingPnl: 0,
      capitalFlow: 0,
    };
    current.records++;
    current.oldTradingPnl += item.oldTradingPnl;
    current.capitalFlow += item.amount;
    grouped.set(key, current);
  }
  const report = {
    mode: APPLY ? "apply" : "dry-run",
    scannedRecords: snapshot.size,
    affectedRecords: affected.length,
    affectedAccounts: grouped.size,
    accounts: [...grouped.values()],
  };
  console.log(JSON.stringify(report, null, 2));
  if (!APPLY || !affected.length) return;

  for (let offset = 0; offset < affected.length; offset += 200) {
    const batch = db.batch();
    for (const item of affected.slice(offset, offset + 200)) {
      batch.set(item.ref, {
        record_type: "balance",
        trade_type: item.category,
        classification: item.category,
        is_trading_deal: false,
        is_trading_pnl_eligible: false,
        is_trade_count_eligible: false,
        is_performance_score_eligible: false,
        balance_reclassification_version: 1,
        balance_reclassified_at: FieldValue.serverTimestamp(),
      }, {merge: true});
      const accountRef = item.ref.parent.parent;
      const transactionRef = accountRef.collection("balance_transactions")
          .doc(`repair_${item.recordId}`);
      batch.set(transactionRef, {
        ownerUid: item.userId,
        user_id: item.userId,
        mt5_account_id: item.accountId,
        account_key: item.accountId,
        source_trade_record_id: item.recordId,
        classification: item.category,
        amount: item.amount,
        amount_decimal: String(item.amount),
        transaction_time: item.data.close_time || item.data.open_time || null,
        comment: text(item.data.comment),
        repair_version: 1,
        created_at: FieldValue.serverTimestamp(),
      }, {merge: true});
    }
    await batch.commit();
  }
  await db.collection("admin_audit_logs").add({
    action: "mt5_balance_misclassification_repair",
    resourceType: "mt5_trade_records",
    source: "audit_mt5_balance_misclassification_v1",
    affectedRecords: affected.length,
    affectedAccounts: grouped.size,
    report,
    createdAt: FieldValue.serverTimestamp(),
    created_at: FieldValue.serverTimestamp(),
  });
  console.log("Repair completed. Recalculate affected performance-rating dates before closing the maintenance window.");
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
