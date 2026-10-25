"use strict";

const assert = require("node:assert/strict");
const {buildAdminSearchTerms} = require("../registration_functions")._test;
const {
  migrationValues,
  normalizedAccess,
} = require("../scripts/migrate_admin_user_search_v1");

assert.equal(normalizedAccess("pending_review"), "pendingReview");
assert.equal(normalizedAccess("APPROVED"), "approved");

const terms = buildAdminSearchTerms({
  id: "user-abc-123",
  name: "Alicia Wong",
  email: "alicia@example.test",
  referral_code: "HC88XY",
  main_mt5_login: "501122",
});
assert.ok(terms.includes("alicia"));
assert.ok(terms.includes("lici"));
assert.ok(terms.includes("hc88xy"));
assert.ok(terms.includes("501122"));

const balancedTerms = buildAdminSearchTerms({
  id: "a-very-long-production-user-identifier-00000000000000000001",
  name: "A Very Long Administrator Search Profile Name",
  email: "searchable.person@example.test",
  referral_code: "REF-LATE-88",
  main_mt5_login: "99887766",
});
assert.ok(balancedTerms.includes("searchable.person@example.test"));
assert.ok(balancedTerms.includes("ref-late-88"));
assert.ok(balancedTerms.includes("99887766"));

const migrated = migrationValues("user-abc-123", {
  name: "Alicia Wong",
  email: "ALICIA@example.test",
  role_id: 750,
  accessStatus: "pending",
  main_mt5_account_id: "account-a",
}, new Set(["user-abc-123"]));
assert.equal(migrated.name_lower, "alicia wong");
assert.equal(migrated.email_lowercase, "alicia@example.test");
assert.equal(migrated.has_pending_application, true);
assert.equal(migrated.has_unresolved_ticket, true);
assert.equal(migrated.mt5_status, "connected");

console.log("admin user search migration tests passed");
