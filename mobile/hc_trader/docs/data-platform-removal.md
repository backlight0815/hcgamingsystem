# Data Platform module removal

The Settings-only `Data platform` informational module was removed from the
Flutter navigation surface. It had no route, permission, feature flag,
analytics event, repository, Firestore collection, Cloud Function, Laravel
endpoint, deep link, or exclusive asset.

Firebase Authentication, Cloud Firestore, Firebase Storage, MT5 sync, reports,
and shared infrastructure remain active dependencies and were intentionally
retained. No production data or historical Firebase metadata was deleted.
Legacy deep links have no matching route and continue through the app's normal
unknown-link fallback.
