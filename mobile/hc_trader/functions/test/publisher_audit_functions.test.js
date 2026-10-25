"use strict";

const assert = require("node:assert/strict");
const {mutationAction} = require("../publisher_audit_functions")._test;

assert.equal(mutationAction(false, true), "create");
assert.equal(mutationAction(true, true), "update");
assert.equal(mutationAction(true, false), "delete");

console.log("Publisher audit function tests passed.");
