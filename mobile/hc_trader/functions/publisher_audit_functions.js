"use strict";

const {getFirestore, FieldValue} = require("firebase-admin/firestore");
const {onDocumentWritten} = require("firebase-functions/v2/firestore");

const REGION = "us-central1";

function mutationAction(beforeExists, afterExists) {
  if (!beforeExists && afterExists) return "create";
  if (beforeExists && !afterExists) return "delete";
  return "update";
}

async function writePublisherAudit(event, moduleName, parameterName) {
  const beforeExists = event.data?.before.exists === true;
  const afterExists = event.data?.after.exists === true;
  if (!beforeExists && !afterExists) return;
  const before = beforeExists ? event.data.before.data() : null;
  const after = afterExists ? event.data.after.data() : null;
  const source = after || before || {};
  const actorId = String(
      source.updated_by || source.published_by || source.created_by || "system",
  ).trim();
  const eventId = String(event.id || `${Date.now()}`)
      .replace(/[^a-zA-Z0-9_-]/g, "_");
  const targetId = String(event.params[parameterName] || "");
  await getFirestore().collection("audit_logs")
      .doc(`${moduleName}_${targetId}_${eventId}`)
      .set({
        actor_id: actorId || "system",
        action: mutationAction(beforeExists, afterExists),
        module: moduleName,
        target_type: moduleName === "trading_signals" ?
          "trading_signal" : "market_analysis",
        target_id: targetId,
        platform: "cloud_function",
        created_at: FieldValue.serverTimestamp(),
      }, {merge: false});
}

const auditTradingSignalWrites = onDocumentWritten(
    {document: "trading_signals/{signalId}", region: REGION},
    (event) => writePublisherAudit(
        event,
        "trading_signals",
        "signalId",
    ),
);

const auditMarketAnalysisWrites = onDocumentWritten(
    {document: "market_analyses/{analysisId}", region: REGION},
    (event) => writePublisherAudit(
        event,
        "market_analyses",
        "analysisId",
    ),
);

module.exports = {
  auditTradingSignalWrites,
  auditMarketAnalysisWrites,
  _test: {mutationAction},
};
