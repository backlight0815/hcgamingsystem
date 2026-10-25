"use strict";

const {FieldPath, getFirestore} = require("firebase-admin/firestore");
const {HttpsError, onCall} = require("firebase-functions/v2/https");
const {ROLE_IDS, effectiveRoleIds, accountUsable} = require("./role_permissions");

const REGION = "us-central1";
const DEFAULT_PAGE_SIZE = 500;
const MAX_PAGE_SIZE = 500;
const ADMIN_ROLES = new Set([
  ROLE_IDS.superAdmin,
  ROLE_IDS.subAdmin,
  ROLE_IDS.founder,
  ROLE_IDS.signalProviderManagement,
]);
const PROVIDER_ROLES = new Set([
  ROLE_IDS.signalProvider,
  ROLE_IDS.seniorSignalProvider,
]);

function text(value) {
  return value == null ? "" : String(value).trim();
}

function hasAnyRole(roles, allowed) {
  return [...roles].some((role) => allowed.has(Number(role)));
}

function pageSize(value) {
  const parsed = Number(value);
  if (!Number.isInteger(parsed) || parsed <= 0) return DEFAULT_PAGE_SIZE;
  return Math.min(parsed, MAX_PAGE_SIZE);
}

function statisticsScope(userId, user, requestedProvider = "") {
  if (!accountUsable(user)) {
    throw new HttpsError("permission-denied", "This account is not active.");
  }
  const roles = effectiveRoleIds(user);
  const isAdministrator = hasAnyRole(roles, ADMIN_ROLES);
  const isProvider = hasAnyRole(roles, PROVIDER_ROLES);
  if (!isAdministrator && !isProvider) {
    throw new HttpsError(
        "permission-denied",
        "Trade-call statistics are restricted to administrators and signal providers.",
    );
  }
  const providerId = text(requestedProvider);
  if (isProvider && providerId && providerId !== userId) {
    throw new HttpsError(
        "permission-denied",
        "Signal providers can view only their own statistics.",
    );
  }
  return {
    isAdministrator,
    providerId: isProvider ? userId : providerId,
  };
}

function providerId(data = {}) {
  return text(
      data.provider_id || data.created_by || data.created_by_id || data.user_id,
  );
}

function isoDate(value) {
  if (!value) return null;
  const date = typeof value.toDate === "function" ? value.toDate() : new Date(value);
  return Number.isNaN(date.getTime()) ? null : date.toISOString();
}

// The callable deliberately returns a narrow, statistics-only projection. It
// never returns unpublished notes, internal moderation fields, or user data.
function signalProjection(id, data = {}) {
  const projected = {
    id,
    trading_pair: text(data.trading_pair),
    signal_code: text(data.signal_code),
    signal_title: data.signal_title || data.title || "",
    immediate_action: data.immediate_action || "",
    entry_price: data.entry_price == null ? "" : String(data.entry_price),
    stop_loss: data.stop_loss == null ? "" : String(data.stop_loss),
    status: Number(data.status || 0),
    is_done: data.is_done || data.IsDone || 0,
    IsBE: data.IsBE || false,
    IsSetBE: data.IsSetBE || false,
    pips_result: data.pips_result == null ? null : Number(data.pips_result),
    current_price: data.current_price || data.market_price || "",
    analyst_name: text(
        data.analyst_name || data.source_name || data.created_by_name,
    ),
    provider_id: providerId(data),
    signal_source: text(data.signal_source || data.source || data.source_name),
    lifecycle_status: text(data.lifecycle_status || data.status_name),
    order_type: text(data.order_type),
    expiry_time: isoDate(data.expiry_time || data.expires_at),
    activated_at: isoDate(
        data.activated_at || data.actual_entry_at || data.trigger_time,
    ),
    trigger_time: isoDate(data.trigger_time),
    closed_at: isoDate(
        data.closed_at || data.result_at || data.completed_at,
    ),
    created_at: isoDate(data.created_at),
    updated_at: isoDate(data.updated_at),
    actual_exit_price: data.actual_exit_price == null ?
      (data.close_price == null ? data.exit_price : data.close_price) :
      data.actual_exit_price,
    realized_risk_reward: data.realized_risk_reward == null ?
      data.realized_rr : data.realized_risk_reward,
    is_test: data.is_test === true || data.test_record === true,
    is_deleted: data.is_deleted === true || data.deleted_at != null ||
      data.duplicate === true || data.is_duplicate === true,
  };
  if (Array.isArray(data.targets)) projected.targets = data.targets;
  for (let index = 1; index <= 10; index += 1) {
    if (data[`target_${index}`] != null) {
      projected[`target_${index}`] = String(data[`target_${index}`]);
    }
  }
  return projected;
}

exports.loadTradeCallStatistics = onCall(
    {region: REGION, timeoutSeconds: 60, memory: "256MiB"},
    async (request) => {
      const userId = request.auth?.uid;
      if (!userId) {
        throw new HttpsError(
            "unauthenticated",
            "Sign in before viewing trade-call statistics.",
        );
      }
      const database = getFirestore();
      const userSnapshot = await database.collection("users").doc(userId).get();
      if (!userSnapshot.exists) {
        throw new HttpsError("permission-denied", "User profile not found.");
      }
      const scope = statisticsScope(
          userId,
          userSnapshot.data() || {},
          request.data?.providerId,
      );
      const limit = pageSize(request.data?.pageSize);
      const token = text(request.data?.pageToken);
      let query = database.collection("trading_signals")
          .orderBy(FieldPath.documentId())
          .limit(limit + 1);
      if (token) query = query.startAfter(token);
      const snapshot = await query.get();
      const pageDocuments = snapshot.docs.slice(0, limit);
      const calls = pageDocuments
          .filter((document) => {
            const data = document.data();
            return !scope.providerId || providerId(data) === scope.providerId;
          })
          .map((document) => signalProjection(document.id, document.data()));
      return {
        calls,
        scope: scope.isAdministrator ? "administrator" : "own-provider",
        nextPageToken: snapshot.size > limit && pageDocuments.length > 0 ?
          pageDocuments[pageDocuments.length - 1].id : null,
        pageSize: limit,
      };
    },
);

exports._test = {
  DEFAULT_PAGE_SIZE,
  MAX_PAGE_SIZE,
  pageSize,
  providerId,
  signalProjection,
  statisticsScope,
};
