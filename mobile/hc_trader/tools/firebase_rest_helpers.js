"use strict";

async function resolveFirebaseAuth(projectId) {
  const auth = require(process.env.APPDATA + "/npm/node_modules/firebase-tools/lib/auth");
  const account = auth.getGlobalDefaultAccount();
  if (!account?.tokens?.refresh_token) {
    throw new Error(
        "No Firebase CLI login token available. Run firebase login first.",
    );
  }
  const token = await auth.getAccessToken(account.tokens.refresh_token, []);
  if (!token?.access_token) {
    throw new Error("Firebase CLI token refresh failed.");
  }
  return {
    mode: "firebase-cli-rest",
    projectId,
    accessToken: token.access_token,
  };
}

async function listFinalExamQuestionDocuments({projectId, auth}) {
  return listCollectionDocuments({
    projectId,
    auth,
    collection: "final_exam_questions",
  });
}

async function listCollectionDocuments({projectId, auth, collection}) {
  const docs = [];
  let pageToken = "";
  do {
    const url = new URL(
        `https://firestore.googleapis.com/v1/projects/${projectId}` +
        `/databases/(default)/documents/${collection}`,
    );
    url.searchParams.set("pageSize", "100");
    if (pageToken) url.searchParams.set("pageToken", pageToken);
    const response = await fetch(url, {
      headers: {Authorization: `Bearer ${auth.accessToken}`},
    });
    if (response.status === 404) {
      return [];
    }
    if (!response.ok) {
      throw new Error(
          `Firestore REST read failed: ${response.status} ${await response.text()}`,
      );
    }
    const payload = await response.json();
    for (const doc of payload.documents || []) {
      const id = doc.name.split("/").pop();
      docs.push({
        id,
        path: `${collection}/${id}`,
        data: decodeFields(doc.fields || {}),
      });
    }
    pageToken = payload.nextPageToken || "";
  } while (pageToken);
  return docs.sort((left, right) => left.id.localeCompare(right.id));
}

async function runCollectionGroupQuery({projectId, auth, collectionId}) {
  const url =
    `https://firestore.googleapis.com/v1/projects/${projectId}` +
    "/databases/(default)/documents:runQuery";
  const response = await fetch(url, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${auth.accessToken}`,
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      structuredQuery: {
        from: [{collectionId, allDescendants: true}],
      },
    }),
  });
  if (!response.ok) {
    throw new Error(
        `Firestore REST collection-group query failed: ${response.status} ${await response.text()}`,
    );
  }
  const rows = await response.json();
  return rows
      .filter((row) => row.document)
      .map((row) => {
        const name = row.document.name;
        return {
          id: name.split("/").pop(),
          path: name.split("/documents/").pop(),
          data: decodeFields(row.document.fields || {}),
        };
      })
      .sort((left, right) => left.path.localeCompare(right.path));
}

async function commitWrites({projectId, auth, writes}) {
  const response = await fetch(
      `https://firestore.googleapis.com/v1/projects/${projectId}` +
      "/databases/(default)/documents:commit",
      {
        method: "POST",
        headers: {
          Authorization: `Bearer ${auth.accessToken}`,
          "Content-Type": "application/json",
        },
        body: JSON.stringify({writes}),
      },
  );
  if (!response.ok) {
    throw new Error(
        `Firestore REST commit failed: ${response.status} ${await response.text()}`,
    );
  }
  return response.json();
}

function decodeFields(fields) {
  const result = {};
  for (const [key, value] of Object.entries(fields)) {
    result[key] = decodeValue(value);
  }
  return result;
}

function decodeValue(value) {
  if ("stringValue" in value) return value.stringValue;
  if ("integerValue" in value) return Number(value.integerValue);
  if ("doubleValue" in value) return Number(value.doubleValue);
  if ("booleanValue" in value) return value.booleanValue;
  if ("nullValue" in value) return null;
  if ("timestampValue" in value) return value.timestampValue;
  if ("arrayValue" in value) {
    return (value.arrayValue.values || []).map(decodeValue);
  }
  if ("mapValue" in value) {
    return decodeFields(value.mapValue.fields || {});
  }
  return null;
}

function encodeFields(fields) {
  const encoded = {};
  for (const [key, value] of Object.entries(fields)) {
    encoded[key] = encodeValue(value);
  }
  return encoded;
}

function encodeValue(value) {
  if (value == null) return {nullValue: null};
  if (typeof value === "string") return {stringValue: value};
  if (typeof value === "boolean") return {booleanValue: value};
  if (typeof value === "number") {
    return Number.isInteger(value) ?
      {integerValue: String(value)} :
      {doubleValue: value};
  }
  if (Array.isArray(value)) {
    return {arrayValue: {values: value.map(encodeValue)}};
  }
  if (typeof value === "object") {
    return {mapValue: {fields: encodeFields(value)}};
  }
  return {stringValue: String(value)};
}

module.exports = {
  decodeFields,
  decodeValue,
  encodeFields,
  encodeValue,
  commitWrites,
  listCollectionDocuments,
  listFinalExamQuestionDocuments,
  resolveFirebaseAuth,
  runCollectionGroupQuery,
};
