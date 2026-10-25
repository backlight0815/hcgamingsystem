"use strict";

const assert = require("assert");
const { _test } = require("../mt5_import_functions");

assert.equal(_test.safeTicket("219520165"), "219520165");
assert.equal(
    _test.safeTicket("999999999999999999999999"),
    "999999999999999999999999",
    "tickets must stay strings and must not be truncated to 32-bit integers",
);
assert.equal(_test.deterministicId("position", "289358202"), "mt5p_289358202");
assert.equal(_test.monotonicUpdate("position", "open", "closed"), true);
assert.equal(_test.monotonicUpdate("position", "closed", "open"), false);
assert.equal(_test.monotonicUpdate("order", "pending", "filled"), true);
assert.equal(_test.monotonicUpdate("order", "filled", "cancelled"), false);
assert.equal(_test.financiallyEquivalent("position", {
  symbol: "XAUUSD.s",
  trade_type: "buy",
  status: "closed",
  lot_size: 0.11,
  entry_price: 4112.39,
  close_price: 4111.27,
  profit: -12.32,
  commission: 0,
  fee: 0,
  swap: 0,
}, {
  symbol: "XAUUSD.s",
  side: "buy",
  status: "closed",
  volume: "0.11",
  open_price: "4112.39",
  close_price: "4111.27",
  gross_price_profit: "-12.32",
  commission: "0",
  fee: "0",
  swap: "0",
}), true, "an overlapping desktop report must reuse an identical live-sync ticket");

assert.equal(_test.financiallyEquivalent("order", {
  symbol: "XAUUSD.s",
  trade_type: "buy",
  status: "triggered",
  lot_size: 0.11,
  entry_price: 0,
  close_price: 4112.39,
  source: "mobile_mt5",
}, {
  symbol: "XAUUSD.s",
  order_type: "buy",
  state: "filled",
  requested_volume: "0.11",
  executed_volume: "0.11",
  requested_price: null,
  market_price: true,
}), true, "legacy triggered market orders must match filled Excel orders");

const reconciledPosition = {
  symbol: "XAUUSD.s",
  side: "buy",
  status: "closed",
  volume: "0.11",
  open_price: "4112.39",
  close_price: "4111.27",
  gross_price_profit: "-12.32",
  commission: "0",
  fee: "0",
  swap: "0",
  identity_confidence: "sourceTicket",
  related_deal_tickets: ["219519817", "219520165"],
};
const staleLivePosition = {
  symbol: "XAUUSD.s",
  trade_type: "buy",
  record_type: "position",
  status: "closed",
  lot_size: 0.11,
  entry_price: 4112.39,
  close_price: 4111.67,
  profit: -7.92,
  source: "mobile_mt5",
};
assert.equal(
    _test.legacyNormalizationUpdate(
        "position", staleLivePosition, reconciledPosition),
    true,
    "a reconciled report may correct the matching legacy live position",
);
assert.equal(
    _test.legacyNormalizationUpdate("position", {
      ...staleLivePosition,
      source: "mt5_desktop_excel",
      import_batch_id: "prior-batch",
    }, reconciledPosition),
    false,
    "a prior desktop import conflict must remain blocking",
);
assert.equal(_test.isIdentityCandidate("position", "deal_219520165", {
  record_type: "deal",
  position_id: 289358202,
}), false, "legacy deal rows must not replace the position identity candidate");
assert.equal(_test.isIdentityCandidate("position", "position_289358202", {
  record_type: "position",
  position_id: 289358202,
}), true);

_test.validateFinancialSummary({
  financial_summary: {
    reconciled: true,
    reconciliation_difference: "0",
    gross_profit: "0",
    gross_loss: "-24.64",
    commission: "0",
    fee: "0",
    swap: "0",
    net_trading_pnl: "-24.64",
  },
});
assert.throws(() => _test.validateFinancialSummary({
  financial_summary: {
    reconciled: false,
    reconciliation_difference: "1000",
  },
}));

const normalized = _test.timestampFromSource("2026-07-14T19:05:29.000", 180);
assert.equal(normalized.toDate().toISOString(), "2026-07-14T16:05:29.000Z");

console.log("mt5_import_functions tests passed");
