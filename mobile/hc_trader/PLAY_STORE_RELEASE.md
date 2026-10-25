# Play Store Release

## 1.8.7+1049 Real XAUUSD Market Insight local candidate — 14 October 2026

Status: built and tested locally; not published or deployed. The AI Insight screen now reads the backend's source-specific, timestamped Twelve Data quote and persisted six-indicator analysis, displays REAL/MOCK and freshness states, and never embeds the provider credential. Build number increased from `1048` to `1049`. The matching Phase 2 Laravel migration, API, scheduler and environment configuration must accompany any future release. The supplied Twelve Data Basic account does not establish external display or redistribution rights, so production and standard-user real-data display remain blocked until those rights are confirmed.

Release bundle: `releases/HC-Trader-1.8.7-build-1049-real-xauusd-market-insight.aab` (76,937,383 bytes). SHA-256: `C8BDA086D14FF0F9234064DF4286C6AB3B656C5CA6A96F44E3763AB160D3B37E`. `jarsigner -verify` returned `jar verified` with the existing self-signed/no-timestamp certificate warnings. Flutter's full suite passed 620 tests, and targeted static analysis found no issues. Backend integration, live account findings, licensing gate, and deployment instructions: [Phase 2 report](../../docs/ai-market-insight-phase2.md).

## 1.8.7+1048 AI Market Insight Phase 1 local candidate — 14 October 2026

Status: built and tested locally; not published or deployed. Backend migration and API must be deployed alongside any future mobile release. The new Trading Hub AI Insight tab reads the versioned Laravel analysis contract and labels all prices and signals **DEMO / SIMULATED DATA**. The app performs no indicator calculation or live market data request.

| Type | Module | Change |
|---|---|---|
| New | AI Market Insight | Six indicator panels, consolidated rule-based signal, factor/risk explanation, selectors and saved snapshot history. |
| New | Backend | Explicit deterministic mock datasets, versioned configurations and analyses, authenticated mobile API and admin management. |
| Enhance | Navigation | AI Insight tab in the existing Flutter Trading Hub. |
| Fix | Phase 1 QA | Use MySQL-compatible market timestamps, reject invalid saved candles cleanly, and keep web page titles below the fixed header. |

Build number increased from `1047` to `1048`. The archived release bundle is `releases/HC-Trader-1.8.7-build-1048-ai-market-insight.aab` (76,931,040 bytes), SHA-256 `3F362F4EC435DE0F99A403BF781CF8AF783288AB1CB60DC57DB459D280249BBB`. The merged Android manifest reports package `com.hcgaming.hc_trader`, version `1.8.7` / code `1048`. `jarsigner -verify` reports `jar verified`, with the existing self-signed/no-timestamp certificate warnings. Flutter's full suite passed 619 tests and targeted static analysis found no issues. Phase 1 engineering and QA details: [AI Market Insight](../../docs/ai-market-insight-phase1.md). Do not publish until the matching backend and demo datasets are available and accepted.

## 1.8.7+1047 journal repair candidate — 14 October 2026

Status: built locally, not published. Production acceptance is pending account
email verification and deployment/verification of the matching backend patch.
Build number increased once from the inspected working version `1.8.7+1046`.

| Type | Module | Change |
|---|---|---|
| Fix | Manual journal sync | Preserve null values, mobile account IDs, local edits made during uploads, deletion queues, and cursor replay for unresolved changes. Batch uploads/deletes in groups of 50. |
| Fix | Laravel journal | Distinguish zero/null, preserve journal wall-clock dates, reject foreign IDs and conflicting create retries, and exclude unknown realized amounts from closed statistics. |
| Enhance | API/authentication | Keep HTTP status-specific errors for HTML responses; provide a Verify email action and distinguish identity-provider outages from expired credentials. |
| New | QA | Add isolated Firebase/Laravel HTTP and Android integration fixtures, plus emulator-only debug HTTP configuration. |

Release AAB: `releases/HC-Trader-1.8.7-build-1047-journal-repair.aab`
(76,902,177 bytes), SHA-256
`FE688C691EA55C17C1813BE7E41779B08AA67377F574773B708C604A237C7872`.

Debug APK: `releases/HC-Trader-1.8.7-build-1047-journal-repair-debug.apk`
(215,368,630 bytes), SHA-256
`A94BB42806FE627E13660DC1233466CDD9611BDFE3FC57D69E80D2D306BFE000`.

The existing upload key signs the AAB; `jarsigner -verify` reports `jar verified`
with the expected self-signed certificate/no timestamp warnings. Package
`com.hcgaming.hc_trader`, minimum SDK 24, target SDK 36, ARM32, ARM64 and x86_64
are preserved. Flutter 3.41.7 / Dart 3.11.5 / JDK 21 were used.
Run `flutter build appbundle --release` with pub generation after integration
testing; `--no-pub` reused a development plugin registrant in the first attempt.

See [engineering and QA evidence](../../docs/trading-journal-repair-20261013.md)
and [backend deployment instructions](../../release_packages/trading_journal_repair_20261014/DEPLOYMENT.md).

## Previous documented release

Android package name: `com.hcgaming.hc_trader`
Release bundle: `build/app/outputs/bundle/release/app-release.aab`
Current release: `1.8.6+1041`
Minimum Android SDK: `24` (Android 7.0)
Archived bundle: `releases/HC-Trader-1.8.6-build-1041-trading-blog-api-sync-fix.aab`
Bundle size: `76,823,304 bytes (73.3 MiB)`
SHA-256: `87D35234C9EA13998BFD67B5301633D2F6134A09F90819E3821DA88F87E5E863`
Built: `2026-10-09 18:03:57 +08:00`
Signature check: `jarsigner -verify` returned `jar verified` using the configured upload key.

## Build

```bash
cd mobile/hc_trader
flutter build appbundle --release
```

This release was built with Flutter `3.41.7`, Dart `3.11.5`, Android SDK 36,
minimum Android SDK 24,
and JDK 21. Its release API is `https://www.hctradinghub.com/api/mobile/v1` and its
Firebase project is `hcgaming-31d5d`. Local debug API endpoints are absent from
the release AOT snapshot. The bundle contains
ARM32 (`armeabi-v7a`), ARM64 (`arm64-v8a`), and x86_64 native libraries.

This release is compatible with Google Play Automatic protection. Android 6.0
(API 23) devices are not supported while Automatic protection is enabled.

## Upload

1. Open Google Play Console.
2. Create or open the HC Trader app.
3. Go to Testing or Production, create a release, and upload `app-release.aab`.
4. Complete app content forms, including Data safety, privacy policy, target audience, app access, content rating, and financial features declarations where applicable.
5. Review and roll out.

Keep `android/app/upload-keystore.jks` and `android/key.properties` backed up securely. They are ignored by Git and are required for future release builds.
