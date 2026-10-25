# Color contrast and theme guidance

The site has three module configurations. `BackofficeExperience::mode()` selects the `dealership` shell for E-Commerce only, the `trading` shell for Trading Hub only, and the existing shell when both or neither module is enabled. The first two use `public/backend/assets/css/backoffice-experiences.css`; the existing shell uses `public/backend/assets/css/backoffice-contrast.css`. The public store uses `public/frontend/assets/css/estore.css`.

## Add or change a component

- Pick the foreground for the component's actual surface. The trading shell is dark, but many tables, forms, and dashboards inside it are light. Use the light surface tokens in `backoffice-experiences.css` for those components rather than inheriting shell text.
- Use `--bo-table-heading-text` for headings in a table whose header changes by mode. Use the paired table row and header tokens instead of a single text color for the entire table.
- On white or cream panels, use dark secondary text (`#526276` or darker), dark links (`#075985`), and semantic state colors with at least 4.5:1 text contrast. On navy panels, use light text such as `#cbd5e1` and a brighter link or accent. Pair a background and text color on every badge and button variant.
- Apply colors to the component class and its surface. Avoid styling every `table td`, `span`, or `a` from a theme selector. This previously made white table rows nearly invisible in the trading shell.
- Keep visible focus outlines. Check hover, selected, empty, disabled, validation, and modal states. Avoid reducing normal text opacity to convey hierarchy.
- If an inline page script depends on jQuery or DataTables, initialize it on `DOMContentLoaded` or after the layout scripts. Blade content is rendered before shared script tags in `admin_master.blade.php`.

## Check a change

1. Run `php artisan test --compact` and `npm run build`.
2. In a local environment, inspect the affected page with E-Commerce only, Trading Hub only, and both modules enabled. Restore the original settings after testing.
3. At 390px, 768px, and 1440px, inspect the actual component surface, navigation, forms, tables, horizontal scrolling, modal content, hover, and focus states.
4. Run axe-core's `color-contrast` rule on representative pages, then visually inspect charts, pseudo-elements, overlays, and other areas the rule may miss. Target WCAG 2.2 AA: 4.5:1 for normal text and 3:1 for large text or essential non-text UI.
5. Check the latest route inventory and evidence in `output/contrast-audit`. A 403, 404, 500, parameterized route, or non-GET action is not a contrast pass.

`tests/Feature/BackofficeExperienceTest.php` checks that each module configuration loads only its intended backoffice stylesheet. Keep that assertion when adding a new theme asset.
