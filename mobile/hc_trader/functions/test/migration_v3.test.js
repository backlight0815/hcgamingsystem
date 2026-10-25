"use strict";

const assert = require("assert");
const {
  normalizedAccessStatus,
  referralMigrationUpdates,
  userMigrationUpdates,
  needsUpdate,
} = require("../scripts/migrate_rbac_ratings_v3");

assert.equal(normalizedAccessStatus("approved_user"), "approved");
assert.equal(normalizedAccessStatus("pending_review"), "pendingReview");
assert.equal(normalizedAccessStatus(""), "unapproved");

const approved = userMigrationUpdates({
  role_id: 750,
  access_status: "verified",
  referrer_id: "referrer-a",
});
assert.deepEqual(approved.role_ids, [750]);
assert.equal(approved.access_status, "approved");
assert.equal(approved.permission_overrides.constructor, Object);

const multi = userMigrationUpdates({role_id: 750, role_ids: [201, 750]});
assert.deepEqual(multi.role_ids, [201, 750]);

const referral = referralMigrationUpdates({
  registration_role_id: 750,
  owner_user_id: "leader-a",
  usage_count: 2,
  maximum_usage: 10,
});
assert.deepEqual(referral.default_role_ids, [750]);
assert.equal(referral.referrer_user_id, "leader-a");
assert.equal(referral.current_uses, 2);
assert.equal(referral.max_uses, 10);
assert.equal(referral.requires_approval, true);

assert.deepEqual(
    userMigrationUpdates({role_id: 750, access_status: "approved"}),
    userMigrationUpdates({role_id: 750, access_status: "approved"}),
);
assert.equal(needsUpdate({migration_version: 3, role_ids: [750]}, {
  role_ids: [750],
}), false);
assert.equal(needsUpdate({migration_version: 2, role_ids: [750]}, {
  role_ids: [750],
}), true);
assert.equal(needsUpdate({migration_version: 3, role_ids: [700]}, {
  role_ids: [750],
}), true);

console.log("Migration v3 tests passed.");
