"use strict";

// Applies only the nested Multiple Account Strategy result rule to the rules
// source that is already released in production. This deliberately avoids
// deploying unrelated local rules from a busy development worktree.
const crypto = require("node:crypto");

const firebaseToolsRoot =
    "C:/Users/backlight/AppData/Roaming/npm/node_modules/firebase-tools/lib/";
const auth = require(`${firebaseToolsRoot}auth.js`);
const {requireAuth} = require(`${firebaseToolsRoot}requireAuth.js`);
const rules = require(`${firebaseToolsRoot}gcp/rules.js`);

const projectId = "hcgaming-31d5d";
const releaseName = "cloud.firestore";

const eventsBlock = `          match /events/{eventId} {
            allow read: if owns(userId) || isAdmin();
            allow create, update: if (owns(userId)
                && request.resource.data.owner_uid == userId
                && request.resource.data.id == eventId
                && request.resource.data.schema_version is int)
              || isAdmin();
            allow delete: if false;
          }`;

const resultsBlock = `

          match /results/{resultId} {
            allow read: if owns(userId) || isAdmin();
            allow create, update: if (owns(userId)
                && request.resource.data.owner_uid == userId
                && request.resource.data.created_by == userId
                && request.resource.data.id == resultId
                && request.resource.data.session_id == sessionId
                && request.resource.data.schema_version is int
                && request.resource.data.normalized_amount is number
                && request.resource.data.balance_before is number
                && request.resource.data.balance_after is number)
              || isAdmin();
            allow delete: if false;
          }`;

function digest(value) {
  return crypto.createHash("sha256").update(value).digest("hex").slice(0, 16);
}

async function main() {
  const account = auth.getGlobalDefaultAccount();
  if (!account) throw new Error("Firebase CLI is not signed in.");
  await requireAuth({
    project: projectId,
    user: account.user,
    tokens: account.tokens,
  });

  const currentRuleset = await rules.getLatestRulesetName(
      projectId,
      releaseName,
  );
  if (!currentRuleset) throw new Error("No released Firestore ruleset found.");
  const files = await rules.getRulesetContent(currentRuleset);
  const rulesFile = files.find((file) => file.name.endsWith("firestore.rules")) ||
      files[0];
  if (!rulesFile || typeof rulesFile.content !== "string") {
    throw new Error("The released Firestore rules source is unavailable.");
  }
  if (rulesFile.content.includes("match /results/{resultId}")) {
    console.log(`No change: result rules already deployed in ${currentRuleset}.`);
    return;
  }
  const anchor = `${eventsBlock}\n        }`;
  const matches = rulesFile.content.split(anchor).length - 1;
  if (matches !== 1) {
    throw new Error(`Expected one session-events anchor, found ${matches}.`);
  }
  const patched = rulesFile.content.replace(
      anchor,
      `${eventsBlock}${resultsBlock}\n        }`,
  );
  const patchedFiles = files.map((file) =>
    file === rulesFile ? {...file, content: patched} : file,
  );
  const createdRuleset = await rules.createRuleset(projectId, patchedFiles);
  await rules.updateRelease(projectId, createdRuleset, releaseName);
  console.log(`Deployed ${createdRuleset}.`);
  console.log(
      `Source SHA-256 changed ${digest(rulesFile.content)} -> ${digest(patched)}.`,
  );
  console.log("Only the Multiple Account Strategy session result block changed.");
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
