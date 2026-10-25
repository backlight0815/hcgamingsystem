# Baseline Prop Firm Check Report

Generated: 2026-07-04 21:24 Asia/Singapore

## Scope

Baseline was captured before adding MT5-linked Prop Firm 2-Step challenge tracking on branch `mt5-propfirm-sync-20260704-2124`.

Laravel code is out of scope for this feature and was not modified for this baseline.

## Backup

- Backup branch: `backup-before-mt5-propfirm-sync-20260704-2124`
- Development branch: `mt5-propfirm-sync-20260704-2124`
- Status snapshot: `backups/status-before-mt5-propfirm-sync-20260704-2124.txt`
- Patch backup: `backups/backup-before-mt5-propfirm-sync-20260704-2124.patch`
- Rollback notes: `backups/ROLLBACK_MT5_PROPFIRM_SYNC.md`

## Flutter Baseline

Working directory: `mobile/hc_trader`

| Check | Result | Log |
| --- | --- | --- |
| `flutter pub get` | Passed | `mt5_sync/baseline_propfirm_logs/flutter_pub_get.txt` |
| `flutter analyze` | Passed | `mt5_sync/baseline_propfirm_logs/flutter_analyze.txt` |
| `flutter test` | Passed | `mt5_sync/baseline_propfirm_logs/flutter_test.txt` |
| `flutter build apk --debug` | Passed | `mt5_sync/baseline_propfirm_logs/flutter_build_apk_debug.txt` |

## Notes

- Existing manual Trading Journal and Excel import code were not changed before this baseline.
- Existing MT5 sync work is already present and will be extended without merging synced records into manual journals.
- The repository already had many unrelated dirty Laravel/mobile files before this feature. This task will avoid Laravel changes.
