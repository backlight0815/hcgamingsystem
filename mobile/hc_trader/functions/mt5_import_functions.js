"use strict";

const crypto = require("crypto");
const {getFirestore, FieldValue, Timestamp} = require("firebase-admin/firestore");
const {HttpsError, onCall} = require("firebase-functions/v2/https");
const {logger} = require("firebase-functions");
const performanceRatings = require("./performance_rating_functions");

const REGION = "us-central1";
const COMPLETED = new Set(["completed", "completed_with_warnings"]);
const COLLECTIONS = {
  position: "trades",
  order: "orders",
  deal: "deals",
  balance_transaction: "balance_transactions",
};

function text(value, fallback = "") {
  const result = value == null ? "" : String(value).trim();
  return result || fallback;
}

function number(value, fallback = 0) {
  const result = Number(value);
  return Number.isFinite(result) ? result : fallback;
}

function integer(value, fallback = 0) {
  const result = Number(value);
  return Number.isSafeInteger(result) ? result : fallback;
}

function sha256(value) {
  return crypto.createHash("sha256").update(String(value)).digest("hex");
}

function safeTicket(value) {
  const ticket = text(value);
  if (!ticket || ticket.length > 128 || !/^[A-Za-z0-9_.:*|\-]+$/.test(ticket)) {
    throw new HttpsError("invalid-argument", "A source ticket is invalid.");
  }
  return ticket;
}

function normalizeServer(value) {
  return text(value).toLowerCase().replace(/\s+/g, "");
}

function terminalUserStatus(data) {
  const status = text(data?.account_status).toLowerCase();
  const access = text(data?.accessStatus || data?.access_status).toLowerCase();
  return ["inactive", "disabled", "suspended", "revoked", "deleted",
    "deletioninprogress", "deletion_in_progress"].includes(status) ||
    ["suspended", "revoked", "deleted"].includes(access) ||
    data?.is_suspended === true;
}

async function requireScope(request, manifest = request.data || {}) {
  const userId = request.auth?.uid;
  if (!userId) throw new HttpsError("unauthenticated", "Sign in first.");
  const accountId = text(manifest.selected_mt5_connection_id);
  if (!accountId) {
    throw new HttpsError("invalid-argument", "Select an MT5 connection.");
  }
  const db = getFirestore();
  const userRef = db.collection("users").doc(userId);
  const accountRef = userRef.collection("mt5_accounts").doc(accountId);
  const [userSnapshot, accountSnapshot] = await Promise.all([
    userRef.get(), accountRef.get(),
  ]);
  if (!userSnapshot.exists || terminalUserStatus(userSnapshot.data())) {
    throw new HttpsError("permission-denied", "This account is not active.");
  }
  if (!accountSnapshot.exists) {
    throw new HttpsError("not-found", "The selected MT5 connection was not found.");
  }
  const account = accountSnapshot.data() || {};
  const owner = text(account.ownerUid || account.user_id || account.userId);
  if (owner && owner !== userId) {
    throw new HttpsError("permission-denied", "This MT5 connection is not yours.");
  }
  if (account.is_active === false || account.is_deleted === true ||
      account.is_archived === true) {
    throw new HttpsError("failed-precondition", "The selected MT5 connection is inactive.");
  }
  const metadata = manifest.metadata || {};
  if (metadata.account_number != null &&
      text(metadata.account_number) !== text(account.mt5_login || account.login)) {
    throw new HttpsError("failed-precondition", "The report account does not match the selected MT5 connection.");
  }
  if (metadata.broker_server != null &&
      normalizeServer(metadata.broker_server) !== normalizeServer(account.broker_server)) {
    throw new HttpsError("failed-precondition", "The report server does not match the selected MT5 connection.");
  }
  const sourceCurrency = text(metadata.currency).toUpperCase();
  const accountCurrency = text(account.currency).toUpperCase();
  if (sourceCurrency && accountCurrency && sourceCurrency !== accountCurrency) {
    throw new HttpsError("failed-precondition", "The report currency does not match the selected MT5 connection.");
  }
  return {db, userId, accountId, account, accountRef};
}

async function batchesForHash(accountRef, fileHash) {
  const snapshot = await accountRef.collection("import_batches")
      .where("file_hash", "==", fileHash).limit(10).get();
  return snapshot.docs.map((doc) => ({id: doc.id, ref: doc.ref, ...doc.data()}));
}

function identityCollection(kind) {
  const collection = COLLECTIONS[kind];
  if (!collection) throw new HttpsError("invalid-argument", "An import record kind is invalid.");
  return collection;
}

function ticketField(kind) {
  return {
    position: "source_position_ticket",
    order: "source_order_ticket",
    deal: "source_deal_ticket",
    balance_transaction: "source_deal_ticket",
  }[kind];
}

function deterministicId(kind, ticket) {
  const prefix = {
    position: "mt5p_", order: "mt5o_", deal: "mt5d_",
    balance_transaction: "mt5b_",
  }[kind];
  return `${prefix}${safeTicket(ticket).replace(/[^A-Za-z0-9_-]/g, "_")}`;
}

async function existingIdentityMaps(accountRef) {
  const result = {};
  await Promise.all(Object.entries(COLLECTIONS).map(async ([kind, collection]) => {
    const snapshot = await accountRef.collection(collection).get();
    const map = new Map();
    for (const doc of snapshot.docs) {
      const data = doc.data() || {};
      if (!isIdentityCandidate(kind, doc.id, data)) continue;
      const ticket = text(data[ticketField(kind)] ||
        (kind === "position" ? data.position_id_string || data.position_id :
          kind === "order" ? data.order_ticket_string || data.order_ticket :
            data.deal_ticket_string || data.deal_ticket));
      if (!ticket) continue;
      const candidate = {id: doc.id, ref: doc.ref, data};
      const previous = map.get(ticket);
      if (!previous || identityCandidateRank(kind, candidate) >
          identityCandidateRank(kind, previous)) {
        map.set(ticket, candidate);
      }
    }
    result[kind] = map;
  }));
  return result;
}

function isIdentityCandidate(kind, id, data) {
  const recordType = text(data?.record_type || data?.recordType).toLowerCase();
  if (kind === "position") {
    // Legacy live sync stored entry/exit deal rows beside position rows in the
    // trades collection. A deal can share the position_id, but it is not the
    // source position and must never win the position-ticket identity map.
    return recordType === "position" || text(id).startsWith("position_") ||
      text(id).startsWith("mt5p_") || text(data?.source_position_ticket) !== "";
  }
  if (kind === "order") {
    return recordType === "" || recordType === "order" ||
      text(id).startsWith("order_") || text(id).startsWith("mt5o_");
  }
  return true;
}

function identityCandidateRank(kind, candidate) {
  const data = candidate?.data || {};
  const id = text(candidate?.id);
  const recordType = text(data.record_type || data.recordType).toLowerCase();
  let rank = 0;
  if (text(data[ticketField(kind)])) rank += 100;
  if (text(data.import_batch_id)) rank += 40;
  if (text(data.source).toLowerCase() === "mt5_desktop_excel") rank += 20;
  if (recordType === kind || (kind === "balance_transaction" &&
      recordType === "balance_transaction")) rank += 10;
  if (id.startsWith({position: "mt5p_", order: "mt5o_", deal: "mt5d_",
    balance_transaction: "mt5b_"}[kind])) rank += 5;
  return rank;
}

function incomingHash(identity) {
  return sha256(text(identity.financial_hash_input));
}

function existingHash(data) {
  return text(data.source_financial_hash || data.financial_hash);
}

function closeEnough(left, right, tolerance = 0.00000001) {
  return Math.abs(number(left) - number(right)) <= tolerance;
}

function firstPresent(...values) {
  return values.find((value) => value != null && text(value) !== "");
}

function normalizedOrderState(value) {
  const state = text(value).toLowerCase().replace(/[\s-]+/g, "_");
  if (["triggered", "executed", "completed", "complete"].includes(state)) {
    return "filled";
  }
  if (["partial", "partiallyfilled", "partial_fill"].includes(state)) {
    return "partially_filled";
  }
  return state;
}

function stablePositionIdentity(existing, incoming) {
  return text(existing.symbol).toLowerCase() === text(incoming.symbol).toLowerCase() &&
    text(existing.trade_type || existing.side).toLowerCase() ===
      text(incoming.side).toLowerCase() &&
    closeEnough(firstPresent(existing.lot_size, existing.volume_decimal,
        existing.executed_volume), incoming.volume) &&
    closeEnough(firstPresent(existing.entry_price, existing.entry_price_decimal),
        incoming.open_price);
}

function financiallyEquivalent(kind, existing, incoming) {
  if (!incoming || typeof incoming !== "object") return false;
  if (kind === "position") {
    return stablePositionIdentity(existing, incoming) &&
      closeEnough(firstPresent(existing.close_price, existing.close_price_decimal),
          incoming.close_price) &&
      closeEnough(existing.profit ?? existing.profit_decimal, incoming.gross_price_profit, 0.0001) &&
      closeEnough(existing.commission ?? existing.commission_decimal, incoming.commission, 0.0001) &&
      closeEnough(existing.fee ?? existing.fee_decimal, incoming.fee, 0.0001) &&
      closeEnough(existing.swap ?? existing.swap_decimal, incoming.swap, 0.0001) &&
      text(existing.status).toLowerCase() === text(incoming.status).toLowerCase();
  }
  if (kind === "order") {
    const existingRequested = firstPresent(existing.requested_volume,
        existing.requested_volume_decimal, existing.lot_size,
        existing.executed_volume);
    const existingExecuted = firstPresent(existing.executed_volume,
        existing.executed_volume_decimal, existing.lot_size);
    const existingPrice = firstPresent(existing.requested_price,
        existing.requested_price_decimal,
        number(existing.entry_price) > 0 ? existing.entry_price : null,
        existing.close_price);
    return text(existing.symbol).toLowerCase() === text(incoming.symbol).toLowerCase() &&
      text(existing.trade_type || existing.order_type).toLowerCase() === text(incoming.order_type).toLowerCase() &&
      normalizedOrderState(existing.status || existing.state) ===
        normalizedOrderState(incoming.state) &&
      closeEnough(existingRequested, incoming.requested_volume) &&
      closeEnough(existingExecuted, incoming.executed_volume) &&
      (incoming.market_price === true ||
        closeEnough(existingPrice, incoming.requested_price));
  }
  if (kind === "deal") {
    return text(existing.symbol) === text(incoming.symbol) &&
      text(existing.classification) === text(incoming.classification) &&
      closeEnough(existing.profit ?? existing.profit_decimal, incoming.profit, 0.0001) &&
      closeEnough(existing.commission ?? existing.commission_decimal, incoming.commission, 0.0001) &&
      closeEnough(existing.fee ?? existing.fee_decimal, incoming.fee, 0.0001) &&
      closeEnough(existing.swap ?? existing.swap_decimal, incoming.swap, 0.0001);
  }
  return text(existing.classification) === text(incoming.classification) &&
    closeEnough(existing.amount ?? existing.amount_decimal, incoming.amount, 0.0001);
}

function legacyNormalizationUpdate(kind, existing, incoming) {
  if (kind !== "position" || !incoming || typeof incoming !== "object") {
    return false;
  }
  // Never relax conflict protection for a previous desktop import. This path
  // exists only to normalize the known legacy live-sync position summary into
  // the reconciled, deal-backed Excel representation for the same ticket.
  if (existingHash(existing) || text(existing.import_batch_id) ||
      text(existing.source).toLowerCase() === "mt5_desktop_excel") {
    return false;
  }
  const source = text(existing.source).toLowerCase();
  if (source && !["mobile_mt5", "mt5", "mt5_sync", "manual_mt5"].includes(source)) {
    return false;
  }
  if (text(existing.record_type || existing.recordType).toLowerCase() !== "position" ||
      text(existing.status).toLowerCase() !== "closed" ||
      text(incoming.status).toLowerCase() !== "closed" ||
      text(incoming.identity_confidence).toLowerCase() !== "sourceticket") {
    return false;
  }
  return stablePositionIdentity(existing, incoming) &&
    Array.isArray(incoming.related_deal_tickets) &&
    incoming.related_deal_tickets.length >= 2;
}

function monotonicUpdate(kind, oldStatus, nextStatus) {
  const before = text(oldStatus).toLowerCase();
  const after = text(nextStatus).toLowerCase();
  if (before === after) return false;
  if (kind === "position") return before === "open" && after === "closed";
  if (kind === "order") {
    return ["placed", "pending", "partially_filled"].includes(before) &&
      ["partially_filled", "filled", "cancelled", "expired", "rejected"].includes(after);
  }
  return false;
}

async function duplicateSummary(accountRef, manifest) {
  const fileHash = text(manifest.file_hash).toLowerCase();
  if (!/^[a-f0-9]{64}$/.test(fileHash)) {
    throw new HttpsError("invalid-argument", "The workbook hash is invalid.");
  }
  const prior = await batchesForHash(accountRef, fileHash);
  const successful = prior.find((batch) => COMPLETED.has(text(batch.status)));
  if (successful) {
    return {
      new_records: 0,
      existing_identical: integer(successful.total_imported) + integer(successful.total_reused),
      updated_records: 0,
      conflicts: 0,
      previous_batch_id: successful.id,
      same_file_already_imported: true,
    };
  }
  const identities = Array.isArray(manifest.record_identities) ?
    manifest.record_identities : [];
  if (identities.length > 100000) {
    throw new HttpsError("resource-exhausted", "The import contains too many records.");
  }
  const existing = await existingIdentityMaps(accountRef);
  let created = 0;
  let identical = 0;
  let updated = 0;
  let conflicts = 0;
  for (const identity of identities) {
    const kind = text(identity.kind);
    identityCollection(kind);
    const ticket = safeTicket(identity.ticket);
    const found = existing[kind].get(ticket);
    if (!found) {
      created++;
      continue;
    }
    if (existingHash(found.data) === incomingHash(identity) ||
        financiallyEquivalent(kind, found.data, identity.financial_values)) {
      identical++;
    } else if (monotonicUpdate(kind, found.data.status || found.data.state,
        identity.status) || legacyNormalizationUpdate(kind, found.data,
        identity.financial_values)) {
      updated++;
    } else {
      conflicts++;
    }
  }
  return {
    new_records: created,
    existing_identical: identical,
    updated_records: updated,
    conflicts,
    previous_batch_id: "",
    same_file_already_imported: false,
  };
}

function validateFinancialSummary(manifest) {
  const summary = manifest.financial_summary || {};
  const difference = Math.abs(number(summary.reconciliation_difference, Number.NaN));
  if (summary.reconciled !== true || !Number.isFinite(difference) || difference > 0.02) {
    throw new HttpsError("failed-precondition", "The report closing balance does not reconcile.");
  }
  const pnl = number(summary.gross_profit) + number(summary.gross_loss) +
    number(summary.commission) + number(summary.fee) + number(summary.swap);
  if (Math.abs(pnl - number(summary.net_trading_pnl)) > 0.02) {
    throw new HttpsError("failed-precondition", "The report trading result does not reconcile.");
  }
}

exports.previewMt5DesktopImport = onCall(
    {region: REGION, timeoutSeconds: 120, memory: "512MiB"},
    async (request) => {
      const scope = await requireScope(request);
      validateFinancialSummary(request.data || {});
      const summary = await duplicateSummary(scope.accountRef, request.data || {});
      logger.info("MT5 desktop import previewed", {
        userId: scope.userId,
        accountId: scope.accountId,
        parserProfile: text(request.data?.parser_profile),
        duplicateSummary: summary,
      });
      return {duplicate_summary: summary};
    },
);

exports.beginMt5DesktopImport = onCall(
    {region: REGION, timeoutSeconds: 120, memory: "512MiB"},
    async (request) => {
      const manifest = request.data || {};
      const scope = await requireScope(request, manifest);
      validateFinancialSummary(manifest);
      const duplicate = await duplicateSummary(scope.accountRef, manifest);
      if (duplicate.same_file_already_imported) {
        return {
          batch_id: duplicate.previous_batch_id,
          previous_batch_id: duplicate.previous_batch_id,
          same_file_already_imported: true,
          status: "completed",
          imported: 0,
          reused: duplicate.existing_identical,
          updated: 0,
          conflicts: 0,
          downstream_recalculation_status: "already_completed",
        };
      }
      if (duplicate.conflicts > 0) {
        throw new HttpsError("failed-precondition", "Conflicting source tickets must be resolved before import.");
      }
      const fileHash = text(manifest.file_hash).toLowerCase();
      const batchId = `import_${fileHash.slice(0, 24)}`;
      const batchRef = scope.accountRef.collection("import_batches").doc(batchId);
      const existing = await batchRef.get();
      if (existing.exists && COMPLETED.has(text(existing.data()?.status))) {
        return {
          batch_id: batchId,
          previous_batch_id: batchId,
          same_file_already_imported: true,
          status: text(existing.data()?.status),
          imported: 0,
          reused: integer(existing.data()?.total_imported) + integer(existing.data()?.total_reused),
          updated: 0,
          conflicts: 0,
          downstream_recalculation_status: "already_completed",
        };
      }
      await batchRef.set({
        ownerUid: scope.userId,
        user_id: scope.userId,
        mt5_account_id: scope.accountId,
        account_key: scope.accountId,
        source_filename: text(manifest.filename).slice(0, 255),
        source_file_size: integer(manifest.file_size),
        file_hash: fileHash,
        parser_profile: text(manifest.parser_profile),
        parser_version: text(manifest.parser_version),
        metadata: manifest.metadata || {},
        counts: manifest.counts || {},
        financial_summary: manifest.financial_summary || {},
        result_comparisons: Array.isArray(manifest.result_comparisons) ?
          manifest.result_comparisons.slice(0, 100) : [],
        validation_issues: Array.isArray(manifest.issues) ?
          manifest.issues.slice(0, 100) : [],
        duplicate_summary: duplicate,
        received_chunk_indexes: [],
        status: "selected",
        started_at: existing.data()?.started_at || FieldValue.serverTimestamp(),
        updated_at: FieldValue.serverTimestamp(),
      }, {merge: true});
      logger.info("MT5 desktop import batch started", {
        batchId, userId: scope.userId, accountId: scope.accountId,
        parserProfile: text(manifest.parser_profile),
      });
      return {batch_id: batchId, same_file_already_imported: false};
    },
);

exports.uploadMt5DesktopImportChunk = onCall(
    {region: REGION, timeoutSeconds: 120, memory: "512MiB"},
    async (request) => {
      const scope = await requireScope(request);
      const batchId = text(request.data?.batch_id);
      const chunkIndex = integer(request.data?.chunk_index, -1);
      const records = Array.isArray(request.data?.records) ? request.data.records : [];
      if (!batchId || chunkIndex < 0 || records.length > 100) {
        throw new HttpsError("invalid-argument", "The import chunk is invalid.");
      }
      const serialized = JSON.stringify(records);
      if (Buffer.byteLength(serialized, "utf8") > 800000) {
        throw new HttpsError("resource-exhausted", "The import chunk is too large.");
      }
      for (const record of records) {
        identityCollection(text(record?.kind));
        const data = record?.data || {};
        safeTicket(data[ticketField(text(record.kind))]);
      }
      const batchRef = scope.accountRef.collection("import_batches").doc(batchId);
      const batch = await batchRef.get();
      if (!batch.exists || text(batch.data()?.file_hash).length !== 64) {
        throw new HttpsError("not-found", "The import batch was not found.");
      }
      if (COMPLETED.has(text(batch.data()?.status))) {
        return {batch_id: batchId, chunk_index: chunkIndex, reused: true};
      }
      if (text(batch.data()?.ownerUid || batch.data()?.user_id) !== scope.userId) {
        throw new HttpsError("permission-denied", "The import batch is not yours.");
      }
      const chunkId = `chunk_${String(chunkIndex).padStart(6, "0")}`;
      const chunkRef = batchRef.collection("source_chunks").doc(chunkId);
      const hash = sha256(serialized);
      const previous = await chunkRef.get();
      if (previous.exists && text(previous.data()?.content_hash) !== hash) {
        throw new HttpsError("failed-precondition", "An uploaded import chunk has conflicting content.");
      }
      await chunkRef.set({
        ownerUid: scope.userId,
        chunk_index: chunkIndex,
        content_hash: hash,
        records,
        uploaded_at: FieldValue.serverTimestamp(),
      }, {merge: false});
      await batchRef.set({
        status: "uploading",
        received_chunk_indexes: FieldValue.arrayUnion(chunkIndex),
        updated_at: FieldValue.serverTimestamp(),
      }, {merge: true});
      return {batch_id: batchId, chunk_index: chunkIndex, reused: previous.exists};
    },
);

function timestampFromSource(value, offsetMinutes) {
  const source = text(value);
  const match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(?:\.(\d+))?/.exec(source);
  if (!match) return null;
  const millis = Number(`0.${match[7] || "0"}`) * 1000;
  const utc = Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3]),
      Number(match[4]), Number(match[5]), Number(match[6]), Math.round(millis));
  const normalized = new Date(utc - number(offsetMinutes, 0) * 60000);
  return Number.isFinite(normalized.getTime()) ? Timestamp.fromDate(normalized) : null;
}

function legacyTicket(ticket) {
  const parsed = Number(ticket);
  return Number.isSafeInteger(parsed) ? parsed : null;
}

function commonRecord(scope, batch, source) {
  return {
    ownerUid: scope.userId,
    user_id: scope.userId,
    mt5AccountId: scope.accountId,
    mt5_account_id: scope.accountId,
    account_key: scope.accountId,
    mt5_login: integer(scope.account.mt5_login || scope.account.login),
    broker_server: text(scope.account.broker_server),
    import_batch_id: batch.id,
    import_parser_version: text(batch.parser_version),
    source_filename: text(batch.source_filename),
    source_file_hash: text(batch.file_hash),
    source_timezone: text(batch.metadata?.source_timezone),
    source_timezone_offset_minutes: batch.metadata?.source_timezone_offset_minutes ?? null,
    time_normalization_status: batch.metadata?.source_timezone_offset_minutes == null ?
      "timezone_unresolved" : "normalized",
    source: "mt5_desktop_excel",
    synced_at: FieldValue.serverTimestamp(),
    last_seen_at: FieldValue.serverTimestamp(),
  };
}

function canonicalRecord(scope, batch, kind, data, financialHash) {
  const common = commonRecord(scope, batch, data.source || {});
  const offset = batch.metadata?.source_timezone_offset_minutes;
  if (kind === "position") {
    const ticket = safeTicket(data.source_position_ticket);
    return {
      ...common,
      unique_mt5_id: `excel_position_${ticket}`,
      trade_key: `position:${scope.accountId}:${ticket}`,
      lifecycle_key: `position_${scope.accountId}_${ticket}`,
      source_position_ticket: ticket,
      position_id_string: ticket,
      position_id: legacyTicket(ticket),
      symbol: text(data.symbol),
      original_symbol: text(data.symbol),
      trade_type: text(data.side),
      record_type: "position",
      status: text(data.status),
      lot_size: number(data.volume),
      volume_decimal: text(data.volume),
      entry_price: number(data.open_price),
      entry_price_decimal: text(data.open_price),
      close_price: number(data.close_price),
      close_price_decimal: data.close_price == null ? null : text(data.close_price),
      sl: data.stop_loss == null ? null : number(data.stop_loss),
      sl_decimal: data.stop_loss == null ? null : text(data.stop_loss),
      tp: data.take_profit == null ? null : number(data.take_profit),
      tp_decimal: data.take_profit == null ? null : text(data.take_profit),
      open_time: timestampFromSource(data.open_time, offset),
      close_time: timestampFromSource(data.close_time, offset),
      open_time_source: text(data.open_time_source),
      close_time_source: text(data.close_time_source),
      profit: number(data.gross_price_profit),
      profit_decimal: text(data.gross_price_profit),
      commission: number(data.commission),
      commission_decimal: text(data.commission),
      fee: number(data.fee),
      fee_decimal: text(data.fee),
      swap: number(data.swap),
      swap_decimal: text(data.swap),
      net_trading_pnl_decimal: text(data.net_trading_pnl),
      requested_volume: number(data.volume),
      executed_volume: number(data.volume),
      remaining_volume: 0,
      has_entry_deal: Array.isArray(data.related_deal_tickets) && data.related_deal_tickets.length > 0,
      has_exit_deal: text(data.status) === "closed",
      was_triggered: true,
      related_order_tickets: data.related_order_tickets || [],
      related_deal_tickets: data.related_deal_tickets || [],
      identity_confidence: text(data.identity_confidence),
      source_trace: data.source || {},
      source_financial_hash: financialHash,
    };
  }
  if (kind === "order") {
    const ticket = safeTicket(data.source_order_ticket);
    const state = text(data.state);
    return {
      ...common,
      unique_mt5_id: `excel_order_${ticket}`,
      trade_key: `order:${scope.accountId}:${ticket}`,
      lifecycle_key: `order_${scope.accountId}_${ticket}`,
      source_order_ticket: ticket,
      order_ticket_string: ticket,
      order_ticket: legacyTicket(ticket),
      symbol: text(data.symbol),
      original_symbol: text(data.symbol),
      trade_type: text(data.order_type),
      record_type: "order",
      status: state,
      requested_volume: number(data.requested_volume),
      requested_volume_decimal: text(data.requested_volume),
      executed_volume: number(data.executed_volume),
      executed_volume_decimal: text(data.executed_volume),
      remaining_volume: Math.max(0, number(data.requested_volume) - number(data.executed_volume)),
      lot_size: number(data.executed_volume),
      entry_price: number(data.requested_price),
      market_price: data.market_price === true,
      sl: data.stop_loss == null ? null : number(data.stop_loss),
      tp: data.take_profit == null ? null : number(data.take_profit),
      open_time: timestampFromSource(data.open_time, offset),
      close_time: timestampFromSource(data.close_time, offset),
      raw_order_state_name: state,
      comment: text(data.comment),
      related_position_ticket: data.related_position_ticket || null,
      has_entry_deal: ["filled", "partially_filled"].includes(state),
      was_triggered: ["filled", "partially_filled"].includes(state),
      source_trace: data.source || {},
      source_financial_hash: financialHash,
    };
  }
  if (kind === "deal") {
    const ticket = safeTicket(data.source_deal_ticket);
    return {
      ...common,
      source_deal_ticket: ticket,
      deal_ticket_string: ticket,
      deal_ticket: legacyTicket(ticket),
      symbol: text(data.symbol),
      original_symbol: text(data.symbol),
      deal_type: text(data.deal_type),
      direction: text(data.direction),
      classification: text(data.classification),
      volume_decimal: text(data.volume),
      price_decimal: data.price == null ? null : text(data.price),
      time: timestampFromSource(data.time, offset),
      time_source: text(data.time_source),
      related_order_ticket: data.related_order_ticket || null,
      related_position_ticket: data.related_position_ticket || null,
      commission_decimal: text(data.commission),
      fee_decimal: text(data.fee),
      swap_decimal: text(data.swap),
      profit_decimal: text(data.profit),
      resulting_balance_decimal: data.resulting_balance == null ? null : text(data.resulting_balance),
      comment: text(data.comment),
      is_trading_deal: data.is_trading_deal === true,
      is_trade_entry: data.is_trade_entry === true,
      is_trade_exit: data.is_trade_exit === true,
      is_cash_flow: data.is_cash_flow === true,
      is_deposit: data.is_deposit === true,
      is_withdrawal: data.is_withdrawal === true,
      is_trading_pnl_eligible: data.is_trading_pnl_eligible === true,
      is_trade_count_eligible: data.is_trade_count_eligible === true,
      is_performance_score_eligible: data.is_performance_score_eligible === true,
      identity_confidence: text(data.identity_confidence),
      source_trace: data.source || {},
      source_financial_hash: financialHash,
    };
  }
  const ticket = safeTicket(data.source_deal_ticket);
  return {
    ...common,
    source_deal_ticket: ticket,
    deal_ticket_string: ticket,
    deal_ticket: legacyTicket(ticket),
    transaction_time: timestampFromSource(data.time, offset),
    amount: number(data.amount),
    amount_decimal: text(data.amount),
    resulting_balance_decimal: data.resulting_balance == null ? null : text(data.resulting_balance),
    classification: text(data.classification),
    comment: text(data.comment),
    source_trace: data.source || {},
    source_financial_hash: financialHash,
  };
}

async function writeOperations(operations) {
  const db = getFirestore();
  for (let offset = 0; offset < operations.length; offset += 400) {
    const writeBatch = db.batch();
    for (const operation of operations.slice(offset, offset + 400)) {
      writeBatch.set(operation.ref, operation.data, {merge: operation.merge});
    }
    await writeBatch.commit();
  }
}

exports.finalizeMt5DesktopImport = onCall(
    {region: REGION, timeoutSeconds: 540, memory: "1GiB"},
    async (request) => {
      const scope = await requireScope(request);
      const batchId = text(request.data?.batch_id);
      const expectedChunks = integer(request.data?.expected_chunks, -1);
      if (!batchId || expectedChunks < 0) {
        throw new HttpsError("invalid-argument", "The import finalization request is invalid.");
      }
      const batchRef = scope.accountRef.collection("import_batches").doc(batchId);
      const batchSnapshot = await batchRef.get();
      if (!batchSnapshot.exists) throw new HttpsError("not-found", "The import batch was not found.");
      const batch = {id: batchId, ...batchSnapshot.data()};
      if (COMPLETED.has(text(batch.status))) {
        return {
          batch_id: batchId,
          status: text(batch.status),
          imported: 0,
          reused: integer(batch.total_imported) + integer(batch.total_reused),
          updated: 0,
          conflicts: 0,
          downstream_recalculation_status: text(batch.downstream_recalculation_status, "completed"),
        };
      }
      if (text(batch.ownerUid || batch.user_id) !== scope.userId ||
          text(batch.file_hash) !== text(request.data?.file_hash)) {
        throw new HttpsError("permission-denied", "The import batch scope is invalid.");
      }
      validateFinancialSummary(batch);
      const chunks = await batchRef.collection("source_chunks").orderBy("chunk_index").get();
      if (chunks.size !== expectedChunks) {
        throw new HttpsError("failed-precondition", "Not all import chunks have been uploaded.");
      }
      const records = chunks.docs.flatMap((doc) => doc.data().records || []);
      const expectedRecords = integer(batch.counts?.positions) +
        integer(batch.counts?.orders) + integer(batch.counts?.deals) +
        integer(batch.counts?.balance_transactions);
      if (records.length !== expectedRecords) {
        throw new HttpsError("failed-precondition", "The staged record count is incomplete.");
      }
      await batchRef.set({status: "committing", updated_at: FieldValue.serverTimestamp()}, {merge: true});
      const existing = await existingIdentityMaps(scope.accountRef);
      const operations = [];
      const importedDates = new Set();
      let imported = 0;
      let reused = 0;
      let updated = 0;
      let conflicts = 0;
      for (const record of records) {
        const kind = text(record.kind);
        const data = record.data || {};
        const ticket = safeTicket(data[ticketField(kind)]);
        const identityInput = Object.entries(data)
            .filter(([key]) => !["source", "related_order_tickets", "related_deal_tickets"].includes(key))
            .sort(([a], [b]) => a.localeCompare(b))
            .map(([key, value]) => `${key}=${value}`).join("|");
        const hash = sha256(identityInput);
        const found = existing[kind].get(ticket);
        if (found && (existingHash(found.data) === hash ||
            financiallyEquivalent(kind, found.data, data))) {
          reused++;
          continue;
        }
        if (found && !monotonicUpdate(kind, found.data.status || found.data.state,
            data.status || data.state) &&
            !legacyNormalizationUpdate(kind, found.data, data)) {
          conflicts++;
          continue;
        }
        const collection = identityCollection(kind);
        const ref = found?.ref || scope.accountRef.collection(collection)
            .doc(deterministicId(kind, ticket));
        operations.push({
          ref,
          data: canonicalRecord(scope, batch, kind, data, hash),
          merge: !!found,
        });
        if (found) updated++; else imported++;
        if (kind === "position") {
          for (const sourceTime of [data.open_time, data.close_time]) {
            const date = text(sourceTime).slice(0, 10);
            if (/^\d{4}-\d{2}-\d{2}$/.test(date)) importedDates.add(date);
          }
        }
      }
      if (conflicts) {
        await batchRef.set({
          status: "failed",
          conflict_count: conflicts,
          error_code: "conflicting_source_records",
          updated_at: FieldValue.serverTimestamp(),
        }, {merge: true});
        throw new HttpsError("failed-precondition", "Conflicting source records were detected during commit.");
      }
      await writeOperations(operations);
      const summary = batch.financial_summary || {};
      const generatedAt = timestampFromSource(
          batch.metadata?.report_generated_at,
          batch.metadata?.source_timezone_offset_minutes,
      ) ||
        FieldValue.serverTimestamp();
      await scope.db.runTransaction(async (transaction) => {
        const accountSnapshot = await transaction.get(scope.accountRef);
        const current = accountSnapshot.data() || {};
        const currentAt = current.latest_imported_balance_at?.toMillis?.() || 0;
        const candidateAt = generatedAt?.toMillis?.() || Date.now();
        if (candidateAt >= currentAt) {
          transaction.set(scope.accountRef, {
            latest_imported_closing_balance: number(summary.source_closing_balance),
            latest_imported_balance_at: generatedAt,
            latest_import_batch_id: batchId,
            updated_at: FieldValue.serverTimestamp(),
          }, {merge: true});
        }
        transaction.set(batchRef, {
          status: (batch.validation_issues || []).length ?
            "completed_with_warnings" : "completed",
          total_imported: imported,
          total_reused: reused,
          total_updated: updated,
          conflict_count: 0,
          completed_at: FieldValue.serverTimestamp(),
          updated_at: FieldValue.serverTimestamp(),
          downstream_recalculation_status: "pending",
        }, {merge: true});
      });
      let recalculationStatus = "completed";
      try {
        await performanceRatings.recalculateImportedMt5Dates(
            scope.userId, [...importedDates]);
      } catch (error) {
        recalculationStatus = "retry_required";
        logger.error("MT5 import downstream recalculation failed", {
          batchId, userId: scope.userId, accountId: scope.accountId,
          error: error?.message,
        });
      }
      await batchRef.set({
        downstream_recalculation_status: recalculationStatus,
        recalculated_at: FieldValue.serverTimestamp(),
      }, {merge: true});
      logger.info("MT5 desktop import completed", {
        batchId, userId: scope.userId, accountId: scope.accountId,
        imported, reused, updated, conflicts: 0,
        netTradingPnl: text(summary.net_trading_pnl),
        closingBalance: text(summary.source_closing_balance),
        reconciliationDifference: text(summary.reconciliation_difference),
        recalculationStatus,
      });
      return {
        batch_id: batchId,
        status: (batch.validation_issues || []).length ?
          "completed_with_warnings" : "completed",
        imported,
        reused,
        updated,
        conflicts: 0,
        downstream_recalculation_status: recalculationStatus,
      };
    },
);

exports._test = {
  safeTicket,
  monotonicUpdate,
  validateFinancialSummary,
  deterministicId,
  timestampFromSource,
  financiallyEquivalent,
  legacyNormalizationUpdate,
  normalizedOrderState,
  isIdentityCandidate,
};
