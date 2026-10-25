# Trading Blog API Integration

## Architecture

Laravel/MySQL is the authoritative source of truth for trading blogs. Flutter reads and writes through Laravel when an API client is configured. The legacy `trading_blogs` Firestore collection is not used as a website API fallback, so an API failure cannot be mislabeled as a successful but stale Firestore result.

Published blog reads are public: the Flutter app loads the Laravel list, detail, and SSE stream without a bearer token or a matching Laravel user. Blog creation, editing, and publishing require Laravel Sanctum or a Firebase ID token mapped to an active Laravel account with the appropriate role. The app reloads the authoritative paginated REST snapshot after stream change hints and keeps a published-only offline cache.

Existing website records remain valid. The migration adds only mobile source metadata, request idempotency, optimistic versioning, and a publication index. Trading Blog API responses use the website's `title`, `excerpt`, and `content` columns directly. Existing multilingual columns, if present from an earlier deployment, are left untouched but are not read, written, searched, or required by this integration.

The Flutter transport isolates response-envelope differences, preserves cached values when a detail response is partial, and treats an empty successful response as a completed load. The model reads the verified snake-case API fields directly. It does not choose article content from the device locale or infer unverified field aliases.

## 2026-10-09 Critical Synchronization Fix

The failed synchronization was caused by multiple issues in the website-to-mobile path:

- The admin `datetime-local` value represents Singapore wall-clock time, but Laravel previously parsed it in the server/application timezone. A newly published post could therefore be stored up to eight hours in the future and be excluded by the public `published_at <= now()` scope.
- The Flutter blog tab could display bundled/demo or saved data without clearly identifying it as a fallback, had no pull-to-refresh action, and swallowed REST failures after emitting the old snapshot.
- Category names and counts were reconstructed from the currently loaded cards instead of the CMS API contract, so zero-count categories and CMS label changes were not represented.
- Relative image paths could resolve below `/api/mobile/` instead of the website root.
- Server search did not include article content, and overlapping paginator pages could create duplicate cards.
- Flutter loaded the legacy Firestore `trading_blogs` collection instead of the website's MySQL records before the Laravel endpoint was added, so website publication and mobile retrieval were disconnected data paths.
- The first API adapter still synthesized localized maps and the UI selected article text by device language. Missing or stale localized values could therefore override the website's English fields.

The admin controller now converts the configured `TRADING_BLOG_PUBLICATION_TIMEZONE` to the application timezone before persistence. Flutter always attempts an authoritative REST refresh, labels saved data when that refresh fails, keeps a 15-minute cache freshness threshold for the warning state, replaces the saved snapshot after every successful full fetch, and removes unpublished/deleted records because snapshots are replaced rather than merged.

## API

Base path: `/api/mobile/v1/blogs`

`GET` requests need no login. Write requests require `Authorization: Bearer <token>` and `Accept: application/json`.

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/` | Paginated list; filters: `category`, `status`, `search`, ISO-8601 `since`, `per_page`; includes category labels/counts and `synced_at` |
| `GET` | `/stream` | Authenticated SSE change hints; reconnect after each 25-second response |
| `POST` | `/images` | Multipart cover upload using the `image` field, maximum 4 MB, JPG/PNG/WEBP |
| `POST` | `/` | Create a draft or publish a post; use `X-Idempotency-Key` |
| `GET` | `/{blog}` | Load a visible post and record a view |
| `PUT` | `/{blog}` | Partial update with optional `expected_version` |
| `POST` | `/{blog}/publish` | Publish a draft with optional `expected_version` |
| `POST` | `/{blog}/unpublish` | Return a post to draft with optional `expected_version` |
| `DELETE` | `/{blog}` | Delete an authorized post |

Success and error envelopes match the trading-signal API:

```json
{
  "success": true,
  "message": "Trading blogs loaded.",
  "data": {}
}
```

```json
{
  "success": false,
  "message": "The submitted data is invalid.",
  "errors": {}
}
```

Example English-only publication:

```json
{
  "title": "Price Action Basics",
  "category": "knowledge_sharing",
  "excerpt": "A concise introduction to reading price action.",
  "content": "<p>Read structure before planning an entry.</p>",
  "cover_image_url": "https://www.hctradinghub.com/upload/trading_blogs/example.webp",
  "tags": ["price action", "structure"],
  "status": "published",
  "is_featured": false
}
```

Read responses include `title_zh`, `excerpt_zh`, and `content_zh` as `""`. Legacy structured localization keys remain type-safe empty objects/arrays for older clients, but no article content is duplicated into them. `cover_image` and every other optional text property are `""` when missing; arrays, booleans, timestamps, identifiers, and pagination retain their established types.

The public list and detail return only posts whose status is `published` and whose publication time has arrived. Drafts are never exposed by these read endpoints. For writes, author and source identity come from the authenticated request; client-supplied identity fields are ignored.

List responses retain the backward-compatible `categories` key-to-label map and additionally provide `category_counts` plus `category_options`. Counts always use eligible published posts, including zero for configured categories. Responses send `Cache-Control: private, no-store, max-age=0`; offline behavior is owned by the mobile cache rather than an intermediary HTTP cache.

Search is case-insensitive according to the configured database collation and covers title, excerpt, tags, and article content. Flutter also searches the complete downloaded snapshot locally so search, category selection, and cleared-search restoration do not trigger excessive requests.

## Deployment

Run the migration and clear cached configuration:

```shell
php artisan migrate --force
php artisan config:clear
php artisan cache:clear
```

The relevant environment settings are:

```dotenv
TRADING_BLOG_REALTIME_ENABLED=true
TRADING_BLOG_PUBLICATION_TIMEZONE=Asia/Singapore
TRADING_BLOG_CREATE_ROLE_IDS=1,2,3
TRADING_BLOG_PUBLISH_ROLE_IDS=1,2,3
TRADING_BLOG_MANAGE_ROLE_IDS=1,2,3
```

Firebase token verification for blog writes continues to use `FIREBASE_WEB_API_KEY` and `FIREBASE_AUTH_TIMEOUT_SECONDS`, shared with the trading-signal transport. Neither setting is required to read published blogs. The mobile app registers users in Firebase and Firestore; blog reading does not depend on provisioning a Laravel `users` row.

After deployment, run `php artisan route:clear` if production previously cached routes. Confirm that `APP_URL` uses HTTPS so absolute cover URLs remain secure.

If older blog posts exist only in Firestore, export/import them into `trading_blogs` before rollout. The API intentionally does not merge two live blog databases.

## Verification

```shell
php artisan test --filter=TradingBlog
cd mobile/hc_trader
flutter test test/trading_blog_api_service_test.dart test/trading_blog_screen_test.dart
flutter analyze
```

## System Log

- Date: 2026-10-09
- Module: Trading Hub — Blog
- Change type: Critical Bug Fix
- Description: Corrected website publication timezone handling and completed the Laravel-to-Flutter blog synchronization path, including published-only retrieval, content search, dynamic category counts, image URL normalization, detail refresh, pull-to-refresh, cache replacement, stale-cache disclosure, retries, pagination deduplication, and non-sensitive diagnostics.
