const { test } = require('node:test');
const assert = require('node:assert/strict');
const { matches } = require('../../public/backend/assets/js/trading-module-filters.js');

const domains = {
    workspace: { label: 'Trading Workspace' },
    content: { label: 'Content & Community' },
};
const analyst = {
    key: 'market_analyst', label: 'Market Analyst',
    description: 'AI-driven market analysis and insights for traders.', domain: 'workspace',
};

test('search is trimmed and case insensitive across name, description, domain, and key', () => {
    for (const query of ['  MARKET analyst  ', 'INSIGHTS', 'trading WORKSPACE', 'market_analyst']) {
        assert.equal(matches(analyst, query, 'all', domains, true), true);
    }
    assert.equal(matches(analyst, 'community', 'all', domains, true), false);
});

test('status filters combine with search and use the current draft state', () => {
    assert.equal(matches(analyst, 'analyst', 'active', domains, true), true);
    assert.equal(matches(analyst, 'analyst', 'inactive', domains, true), false);
    assert.equal(matches(analyst, 'analyst', 'inactive', domains, false), true);
    assert.equal(matches(analyst, 'news', 'inactive', domains, false), false);
});
