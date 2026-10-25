(function (root) {
    'use strict';

    function matches(module, query, filter, domains, enabled) {
        if (filter === 'active' && !enabled) return false;
        if (filter === 'inactive' && enabled) return false;
        const normalized = query.trim().toLocaleLowerCase();
        if (!normalized) return true;
        const domain = domains[module.domain]?.label || 'Trading Workspace';
        return [module.label, module.description, domain, module.key]
            .some((value) => String(value ?? '').toLocaleLowerCase().includes(normalized));
    }

    root.TradingModuleFilters = { matches };
    if (typeof module !== 'undefined' && module.exports) module.exports = { matches };
})(typeof window === 'undefined' ? globalThis : window);
