# HC Gaming storefront redesign report

## Architecture

The site is Laravel 10 with Blade views, Eloquent CMS models, legacy Bootstrap/theme assets, and a Vite bundle used by the account layouts. The public shop uses the existing `product`, `product_categories`, `abouts`, `home_slides`, `services`, `blogs`, `blog_categories`, and `footers` tables. Cart and checkout remain on the existing controllers and routes. No API response shape or database schema was changed.

The redesign adds one centralized storefront stylesheet (`public/frontend/assets/css/estore.css`) and a small script (`public/frontend/assets/js/estore.js`). Existing public routes remain in use.

## Completed pages and visual work

- Shared sticky header, accessible mobile menu, live cart count, footer, typography, color tokens, controls, cards, empty states, and focus styles.
- Home: editorial CMS hero, real category discovery, product collection, CMS About preview, conditional services, real journal preview, and final shop action.
- About: editorial introduction and CMS story, with existing skill and education records displayed conditionally.
- Journal: featured article, real category navigation, URL based search, pagination, article cards, article detail, reading progress, share action, rich text styling, and related posts.
- Shop: category filter, title search, price sort, product grid, stock and price display, and existing add to cart action on product details.
- Services, Contact, guest cart, and 404 pages received matching layouts. Contact submission now validates fields.

## CMS and backend behavior

- Existing home slide, About, product, category, blog, service, and footer records remain the source of public content.
- Explicit `[Demo]` products, posts, and services are excluded from public discovery; the database records remain intact. Broken `?` blog titles and unusable `?` category labels are also hidden from public navigation.
- Missing local images fall back to a designed surface instead of a broken image icon.
- CMS rich text is sanitized through `App\Support\StorefrontHtml` before rendering.
- Guest cart items now carry the guest session ID, appear in the guest cart, and merge into the user cart when the signed in cart opens. Checkout and payment calculations were not modified.
- The Vite entry now starts the existing Alpine account UI. Its previous import pointed to an absent `App.vue`, which prevented builds.
- No migrations or new environment variables are required.

## Important modified files

- `resources/views/frontend/main_master.blade.php`, `body/header.blade.php`, and `body/footer.blade.php`: shared storefront shell and navigation.
- `resources/views/frontend/index.blade.php`, `about_page.blade.php`, `blog.blade.php`, and `blog_details.blade.php`: priority pages.
- `resources/views/frontend/stock.blade.php`, `stock_details_estore.blade.php`, `service.blade.php`, `service_details.blade.php`, `contact.blade.php`, and `guest_cart_summary.blade.php`: supporting pages.
- `resources/views/frontend/partials/`: shared product and article cards; `resources/views/errors/404.blade.php`: missing-page state.
- `public/frontend/assets/css/estore.css` and `public/frontend/assets/js/estore.js`: design system and small interactions.
- `app/Http/Controllers/Demo/demoController.php`, `Home/BlogController.php`, `Home/ServiceController.php`, `Stock/StockController.php`, and `Cart/CartController.php`: public queries, filters, and guest cart repair.
- `app/Models/Blog.php`, `Product.php`, and `Service.php`: explicit public discovery scopes; `app/Support/StorefrontHtml.php`: rich-text sanitization.
- `routes/web.php`: removes the duplicate blog detail registration. Existing unrelated staged route edits were left intact.
- `resources/js/app.js`: restores the Vite entry; `tests/Feature/StorefrontRedesignTest.php`: focused regression coverage.

## Verification

- `npm run build` passed.
- `php artisan view:cache` passed.
- `php artisan test` passed: 114 tests, 1 skipped, 4,617 assertions.
- Targeted storefront tests passed for blog search and category filtering, guest cart transfer, and rich text sanitization.
- Local HTTP checks returned 200 for Home, About, Journal listing/detail, Shop listing/detail, Contact, Services, and guest cart; a missing path returned 404.
- Desktop screenshots were inspected at 1440px. Compact screenshots were inspected at 600px. The headless browser clamped narrower requested window sizes, so 360px and 390px visual verification remains outstanding.
- Core Web Vitals were not measured; no performance score is claimed.

## Preview images

- [Home overview](../output/estore-redesign-qa/home-preview.png)
- [About overview](../output/estore-redesign-qa/about-preview.png)
- [Journal overview](../output/estore-redesign-qa/journal-overview.png)
- [Journal compact](../output/estore-redesign-qa/journal-compact.png)
- [Article overview](../output/estore-redesign-qa/article-overview.png)
- [Shop overview](../output/estore-redesign-qa/shop-preview.png)
- [Product detail](../output/estore-redesign-qa/product-preview-final.png)

## Content and scope limits

- The current CMS home slide describes a B2B2C platform but uses a cooking photograph. The About record is a personal biography rather than a company story. Both need editorial updates in the existing admin CMS to match the storefront identity.
- All current service records are explicitly demo records, so the public Services page shows its empty state.
- The legacy blog table has no publication flag. The redesign preserves its existing public visibility model and excludes clearly marked demo or damaged titles.
- Verified testimonials, team data, company milestones, collection records, and FAQ content are absent; those sections were not invented.
- True 360px and 390px browser verification, Core Web Vitals measurement, and a production content review remain to be done before release.

## Deployment

Run `npm run build` for the account layouts and deploy the updated Blade views plus `public/frontend/assets/css/estore.css` and `public/frontend/assets/js/estore.js`. `php artisan view:cache` is supported. No migration is required. Review CMS hero, About, product, image, and contact content before publishing. Production deployment was not performed.

## Outstanding enhancements

- Replace mismatched Home and About CMS text and images with verified brand content; publish real service records when available.
- Run visual checks in a browser at true 360px, 390px, 768px, 1024px, and 1920px widths; measure Core Web Vitals and audit WCAG 2.2 AA before release.
- Add FAQ content and a page only when approved support answers exist in the CMS.
- Extend article SEO with canonical, Open Graph, and structured data after confirming the production hostname and editorial metadata policy.
