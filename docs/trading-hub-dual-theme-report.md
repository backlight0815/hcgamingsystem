# HC Trading Hub dual-theme implementation report

Date: 11 October 2026. Scope: local Laravel application in `C:\xampp\htdocs\hcgaming`. Production deployment was not performed.

## A. Architecture summary

The existing Laravel 10 application now has two independent public presentations. The light HC Gaming store retains its existing commerce controllers and redesigned store views. The dark HC Trading Hub has its own layout, responsive styles, and research, signal, and information pages. Authentication, catalog data, trading data, and administrative views remain shared.

`FrontendExperience` reads the existing `module_trading` and `module_dealership_ecommerce` feature toggles plus a separate homepage preference. It caches the resolved settings for five minutes, invalidates that cache when settings change, and falls back to direct database reads if the cache is unavailable. If module settings cannot be read, public resolution fails closed to the neutral landing.

## B. Module switching

| Trading | E-commerce | `/` | Trading pages | Store pages |
| --- | --- | --- | --- | --- |
| On | Off | Trading Hub | Available | 404 |
| Off | On | Store | 404 | Available |
| On | On | Admin preference: Trading Hub, Store, or Combined Portal | Available | Available |
| Off | Off | Neutral landing with sign-in | 404 | 404 |

When one module is enabled, it takes precedence over the homepage preference. An invalid preference falls back to Store when both modules are on. The module flags and preference can be changed in **Admin → Features** without a rebuild or redeployment. The settings screen is limited to admin roles 1 and 2. Public route middleware enforces module availability beyond navigation links; administrative and mobile API routes are unaffected.

The dedicated URLs `/trading-hub` and `/store` retain a stable destination even when `/` changes its presentation. Each theme links to the other when both are enabled. The Combined Portal offers distinct entry points and selected real research and catalog content.

## C. Completed UI pages

- Trading Hub: `/trading-hub`, `/trading-hub/insights`, `/trading-hub/signals`, `/trading-hub/about`, `/trading-hub/services`, and `/trading-hub/contact`.
- Member trading articles: existing `/trading/blogs` list and detail pages, with a featured article treatment, reading progress, sharing, related reading, and risk disclosure. Existing member authentication remains required for full articles.
- Member signal detail: the existing signal URL has a dark detail layout showing only saved values and an explicit policy check.
- Combined Portal at `/` when selected and both modules are enabled; neutral landing at `/` when both are disabled.
- Existing light store pages retain the e-commerce redesign and now use `/store` for store-home links when the root is assigned elsewhere.

The Trading homepage includes a financial hero, actual published research, permitted published member signals, educational discovery, About entry, risk disclosure, and closing links. It intentionally omits live market quotes and performance metrics because the repository has no verified source for them.

## D. Components created

- Trading layout, responsive navigation, article and signal cards, filters, empty states, risk block, and neutral landing.
- Dark financial design tokens and responsive styles in `public/frontend/assets/css/trading-hub.css`, plus a small menu and reading-progress script in `public/frontend/assets/js/trading-hub.js`.
- Shared homepage/module resolver and web route gate.
- Store and Trading theme cross-navigation, shown only for enabled experiences.

The abstract bars in the Trading hero are labeled as an illustration and carry no market values. They are not a price or performance chart.

## E. Backend changes

- Added `site_preferences` with a unique key/value record for `homepage_experience`; no duplicate module flags were created.
- Added `SitePreference` and `FrontendExperience`, with model-event cache invalidation for preference and feature-toggle changes.
- Added `FrontendExperienceController` for root resolution and Trading public pages, and `EnforcePublicModules` to the web middleware stack.
- Added the homepage selector to the existing feature management screen and restricted feature-management actions to admin roles 1 and 2.
- Added the website-only `TradingBlog::websitePublished()` scope to omit explicitly labeled `[Demo]` articles from website discovery. The records stay in the CMS and mobile API. Direct website access to those demo article URLs now returns 404.
- Applied the existing signal policy to member signal detail requests. Public preview pages only query published signals for members authorized by `viewAny`; guests see the access state.

## F. API and integration compatibility

The new module gate runs on web routes. It does not alter `/api` routes, Trading Signal or Trading Blog resource schemas, Flutter consumers, Discord webhook delivery, payments, checkout calculations, or order code. Module flags govern the public website presentations only; existing API access rules continue to govern mobile clients independently. Store and trading data are read from their existing models.

## G. Testing results

- `php artisan test`: **119 passed, 1 skipped, 4,661 assertions** in the final full run.
- `FrontendExperienceTest` exercises all four module combinations, all three dual-mode preferences, invalid-preference fallback, rapid setting changes, admin recovery from both modules off, forbidden non-admin changes, website demo filtering, and published/member signal access.
- `npm run build`, `php artisan view:cache`, PHP lint on the new controller, resolver, and middleware, and scoped `git diff --check` all passed after the final changes.
- Browser previews were inspected for Trading at 360, 390, 768, 1024, 1440, and 1920 CSS pixels; Combined Portal at 390 and 1440; and Store at 360 and 390. At each measured Trading width, document scroll width equaled viewport width. The 390px mobile menu opened and exposed the correct expanded state. Store checks were made under temporary module settings, then restored.
- Local HTTP checks returned 200 for enabled pages and 404 for disabled module pages. Administrative settings remained reachable while both modules were disabled.

## H. Screenshots

| Preview | File |
| --- | --- |
| Trading homepage, 1440px | [trading-home-final.png](../output/trading-hub-qa/trading-home-final.png) |
| Trading homepage, 390px | [trading-home-390.png](../output/trading-hub-qa/trading-home-390.png) |
| Trading homepage, 360px | [trading-home-360.png](../output/trading-hub-qa/trading-home-360.png) |
| Trading research, 390px | [trading-insights-390.png](../output/trading-hub-qa/trading-insights-390.png) |
| Trading signals, 390px | [trading-signals-390.png](../output/trading-hub-qa/trading-signals-390.png) |
| Combined Portal, 1440px and 390px | [desktop](../output/trading-hub-qa/combined-desktop.png) · [mobile](../output/trading-hub-qa/combined-390.png) |
| Store, 390px | [store-390.png](../output/trading-hub-qa/store-390.png) |

The 1440px and 390px homepage screenshots were refreshed after the final About and closing sections were added.

## I. Known issues and limits

- The existing published Trading Blog records in the local database are all explicitly `[Demo]`. Consequently, website research sections correctly show a publication empty state until real articles are published. Demo records remain available through the CMS and existing mobile API.
- No verified market-price feed or audited signal-performance dataset is configured, so live quotations, market-status claims, and performance charts are intentionally absent.
- Real-user Core Web Vitals and a formal WCAG audit were not measured. Responsive layout, menu state, reduced-motion CSS, keyboard focus styles, semantic landmarks, labels, and empty states were checked in implementation; production telemetry and assistive-technology testing remain advisable.
- Checkout and payment were protected from code changes and covered by the repository test suite, but a live transaction was not performed.

## J. Deployment requirements

1. Deploy the application changes through the normal release process and run `php artisan migrate --force` to create `site_preferences`.
2. Install/build frontend dependencies using the project's standard `npm ci` and `npm run build` flow; deploy the static `trading-hub.css` and `trading-hub.js` files under `public/frontend/assets`.
3. Warm Laravel views with `php artisan view:cache` after deployment. Clear stale application caches through the normal release process if the deployment preserves an old cache store.
4. In Admin → Features, confirm both module flags and choose Trading Hub, E-commerce, or Combined Portal as the preferred homepage. One enabled module always overrides that preference.
5. Publish approved real Trading Blog content to replace the current website empty state. Configure and verify any future market data source before adding live market values.

The local settings after QA were restored to their starting state: **Trading on, E-commerce off, preference default E-commerce**, resulting in the Trading Hub at `/`. No production settings were changed.
