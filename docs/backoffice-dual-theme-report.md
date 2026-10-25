# Conditional Backoffice redesign

## Codebase assessment

- Stack: Laravel 10, PHP 8.1+, Blade, Bootstrap/Upcube CSS, jQuery, and existing JavaScript plugins. Backoffice layout is `admin_master.blade.php` with shared header, sidebar, footer, and dashboard views.
- Authoritative module state: `feature_toggles` rows `module_dealership_ecommerce` and `module_trading`. `/admin/features` and its authenticated update route save those rows through `FeatureToggle`; model events clear the `FrontendExperience` cache. The existing homepage preference is separate from Backoffice mode.
- Access: existing authentication, role checks, onboarding middleware, and menu filtering remain in place. The new web middleware blocks direct requests to disabled module controllers. API middleware, payloads, and mobile/Discord flows were not changed.
- Data: the existing dashboard controller retains its metrics and serves the original dashboard for combined and neither modes. Single-module dashboards add queries for real orders, products, published signals, and published research.

## Implementation

| E-Commerce | Trading | Backoffice | Verified presentation |
| --- | --- | --- | --- |
| On | Off | `dealership` | Light workspace, navy navigation, automotive orange, dealership dashboard |
| Off | On | `trading` | Dark financial workspace, teal accents, trading dashboard |
| On | On | `existing` | Original header, sidebar order, dashboard, CSS, and service worker registration |
| Off | Off | `existing` | Original shell with its existing “No Module Enabled” state |

`BackofficeExperience` resolves mode on the server from persisted flags before Blade renders, so the wrong theme cannot flash during initialization. `BackofficeNavigation` groups only links already authorized and present in the original sidebar. New tokens and styles are scoped to `body[data-backoffice-mode]`; the stylesheet and drawer script do not load in `existing` mode. Header wordmarks and favicons identify each specialized workspace while the combined mode keeps the HC Gaming Studio logo.

The dealership dashboard emphasizes products, order flow, approved sales, recent orders, and existing actions. The trading dashboard emphasizes published, open, and closed signals plus website research. Unsupported metrics are omitted. Existing product, order, signal, blog, research, news, analyst, community, document, and TP pages retain their CRUD and publication behavior and receive theme-specific surfaces, forms, table styling, and readable states. No database schema or business calculation changed for this Backoffice work.

Contrast repairs include a darker dealership orange for text and filled controls (5.18:1 against white), full-opacity sidebar labels (5.50:1 against navy), CSS submenu chevrons, and corrected dark-theme panel text. Trading Signal forms/details, TinyMCE controls, Knowledge Centre, Market Analyst, News, Community, Documents, and TP screens were sampled and corrected where custom page styles conflicted with the dark shell.

## Verification

- Automated: `php artisan test --compact` passed with 122 tests, one skipped, and 4,697 assertions. The dedicated Backoffice feature tests cover all four modes, cache invalidation after toggles, navigation preservation, direct-route blocking, and shared settings access.
- Blade compilation: `php artisan view:cache` passed.
- Browser QA: both dashboard modes were rendered at 375, 430, 768, 1024, 1440, and 1920 px. Measured document scroll width never exceeded client width in successful captures. The mobile drawer, product list/create, orders, signal list/create/edit/detail, blog list/create, Knowledge Centre, Market Analyst, News, Community, Documents, and TP views were inspected.
- Combined-mode preservation: browser output showed no `data-backoffice-mode`, no specialized stylesheet or script, the original 240 px logo container, original navigation groups, and the original dashboard view. A source-level conditional keeps original markup/assets on this path. There was no pre-change pixel baseline for a byte-for-byte screenshot diff.
- Representative screenshots: [Dealership dashboard](../output/backoffice-qa/dealer-final-375.png), [dealer product form](../output/backoffice-qa/dealer-add-product-final-375.png), [trading dashboard](../output/backoffice-qa/trading-final-1440.png), [signal form](../output/backoffice-qa/trading-add-signal-final-375.png), [trading blog editor](../output/backoffice-qa/trading-blog-create-final-375.png), [combined original](../output/backoffice-qa/existing-both-final-1440.png), [neither fallback](../output/backoffice-qa/existing-neither-final-1440.png).

The product and shipping order pages scope records to the current account; the admin dashboard shows platform totals. Thus the local QA admin sees zero account-scoped records on those lists alongside nonzero platform dashboard totals. This is existing query behavior, and no placeholder figures were added.

## Deployment

No migration or new package is required for this Backoffice change. Deploy the PHP, Blade, CSS, JS, and SVG files together. Clear compiled views if the deployment process retains old templates (`php artisan view:clear` or rebuild with `php artisan view:cache`). The static Backoffice assets are served directly; no npm build is required for them. Module changes take effect on the next server-rendered response without deployment or logout. Single-module admin pages skip the legacy PWA registration to avoid its large asset precache; combined mode keeps its original registration. The public storefront, public Trading Hub design, service worker file, and mobile/API schemas are unchanged.

