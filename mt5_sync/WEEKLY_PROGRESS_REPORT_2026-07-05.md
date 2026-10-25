# Weekly Progress Report - HC Trader

Date: 5 Jul 2026
App version: 1.6.11+27

## Summary

This week focused on making MT5 synchronization production-ready for different brokers, different MT5 accounts, and VPS usage, while keeping Firestore writes under control. The mobile app now supports multiple linked MT5 accounts, manual refresh, account-specific history loading, and clearer connection status handling. The Python sync side now uses dynamic account detection instead of hard-coded account numbers.

## Mobile App

- Built the Android App Bundle release for Play Console upload.
- Improved the synced MT5 trade journal screen so the selected MT5 account controls refresh, trade loading, and load-more history.
- Added support for binding, editing, unlinking, and selecting multiple MT5 accounts in the app.
- Added localized MT5 account and sync text for English and Chinese UI.
- Improved stale connection handling so an old sync service cannot incorrectly show a different broker/account as connected.
- Updated the sync stale threshold to match the lower-frequency 5-minute sync cadence.

## MT5 Python Sync

- Removed dependency on fixed `.env` account values such as `MT5_EXPECTED_LOGIN`, `MT5_EXPECTED_SERVER`, and `MT5_ALLOWED_ACCOUNTS`.
- Added dynamic account resolution from Firestore linked accounts.
- Added machine profile support so accounts can be assigned to `local`, `vps`, or both.
- Added better MT5 terminal discovery for multiple brokers and installations.
- Added multi-terminal scanning so the service can find accounts across HFM, PUPrime, and other broker terminals on the same VPS.
- Added account mismatch protection so trades are not synced when the detected MT5 account is not linked to the signed-in app user.
- Added retry and stale-status safeguards for disconnected, unlinked, or unreachable accounts.

## VPS Support

- Prepared a VPS sync deployment package for the Windows RDP server.
- Added quick-start and diagnostic batch files for checking Python, installing requirements, testing MT5 connection, and restarting sync.
- Added VPS setup documentation for Python installation, `.env` setup, Windows Task Scheduler, and broker terminal checks.
- Added diagnostics to show which linked accounts are active, which profile they belong to, and whether the current MT5 terminal matches them.
- Confirmed the deployment is designed for the VPS server at `103.75.190.182:5633`; RDP access alone is not the sync connection, the Python service still needs to run on that VPS.

## Firestore Usage Reduction

- Changed the sync service to a 5-minute cadence to reduce Firestore reads and writes.
- Reduced heartbeat/status updates so Firestore is not updated every few seconds when no important MT5 data changed.
- Updated account-change detection to ignore volatile heartbeat/status timestamp fields.
- Preserved trade activity updates, but throttled repeated balance/equity/floating metric writes.

## Prop Firm And Journal Safety

- Kept prop-firm MT5 sync separate from manual journal and Excel-imported trades.
- Preserved manual journal fields and app-side trade metadata.
- Kept trade sync scoped to the linked MT5 account and user.
- Avoided requiring paid Firebase services, Cloud Functions, MT5 passwords, or Laravel changes.

## Verification Completed

- `flutter pub get` completed successfully.
- `flutter analyze` passed with no issues.
- `flutter test` passed all tests.
- `flutter build appbundle --release` completed successfully.
- Python sync modules were syntax-checked during the implementation work.

## Build Artifacts

- Android AAB: `mobile/hc_trader/build/app/outputs/bundle/release/app-release.aab`
  - Size: `55,440,674` bytes
  - Generated: `5 Jul 2026, 11:56:38 PM`
  - Target platforms: `android-arm`, `android-arm64`, `android-x64`
- Versioned upload copy: `release_packages/hc_trader_1.6.11_27_all_devices.aab`
- VPS sync package: `release_packages/mt5_sync_vps_package_multi_terminal_5min.zip`
- Main implementation report: `mt5_sync/DYNAMIC_CONFIG_VPS_PYTHON_IMPLEMENTATION_REPORT.md`

## Current Operational Notes

- The HFM account was able to connect after the VPS-side Python sync was running.
- The new PUPrime demo account required multi-terminal scanning because it can be open in a different MT5 terminal than the HFM account.
- On the VPS, use the quick-start/diagnostic scripts after copying the latest package so the sync service can find all broker terminals and linked accounts.
