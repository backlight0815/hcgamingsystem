"use strict";

const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");

const functionsRoot = path.resolve(__dirname, "..");
const backendSource = fs.readFileSync(
    path.join(functionsRoot, "registration_functions.js"),
    "utf8",
);
const repositorySource = fs.readFileSync(
    path.join(functionsRoot, "..", "lib", "repositories", "auth_repository.dart"),
    "utf8",
);

const backendStart = backendSource.indexOf(
    "exports.deleteCurrentUserAccount = onCall(",
);
const backendEnd = backendSource.indexOf(
    "exports.createDesktopCustomSignInToken = onCall(",
    backendStart,
);
assert.ok(backendStart >= 0 && backendEnd > backendStart);
const deletionHandler = backendSource.slice(backendStart, backendEnd);

assert.match(deletionHandler, /const uid = request\.auth\?\.uid;/);
assert.match(deletionHandler, /auth\.deleteUser\(uid\)/);
assert.doesNotMatch(deletionHandler, /\b(?:listUsers|deleteUsers)\s*\(/);
assert.doesNotMatch(
    deletionHandler,
    /request\.data[^;]*(?:uid|userId|targetUserId)/i,
);

const authDeletionTargets = [...deletionHandler.matchAll(
    /auth\.deleteUser\(([^)]*)\)/g,
)].map((match) => match[1].trim());
assert.deepEqual(authDeletionTargets, ["uid"]);

const clientStart = repositorySource.indexOf(
    "Future<void> deletePreparedCurrentAccount(",
);
const clientEnd = repositorySource.indexOf(
    "bool get _usesDesktopHttpTransport",
    clientStart,
);
assert.ok(clientStart >= 0 && clientEnd > clientStart);
const clientDeletion = repositorySource.slice(clientStart, clientEnd);
assert.match(clientDeletion, /final payload = \{'reason': reason\.trim\(\)\};/);
assert.doesNotMatch(
    clientDeletion,
    /['"](?:uid|userId|targetUserId)['"]\s*:/,
);

console.log("account deletion scope tests passed");
