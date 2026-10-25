# MT5 Sync Baseline Check Report

Created: 2026-07-04 16:53 Asia/Singapore

## Branch And Backup

- Current branch: `mt5-sync-feature-20260704-1653`
- Backup branch: `backup-before-mt5-sync-20260704-1653`
- Status snapshot: `backups/status-before-mt5-sync-20260704-1653.txt`
- Patch backup: `backups/backup-before-mt5-sync-20260704-1653.patch`
- Clean HEAD zip: `backups/project-backup-before-mt5-sync-20260704-1653.zip`

## Baseline Commands

All commands were run from `mobile/hc_trader`.

| Check | Result | Notes |
| --- | --- | --- |
| `flutter pub get` | Passed | Dependencies resolved. 36 packages have newer versions outside current constraints. |
| `flutter analyze` | Passed | `No issues found!` |
| `flutter test` | Passed | All tests passed. Existing test output includes a deliberate missing-translation fallback log. |
| `flutter build apk --debug` | Passed | First piped run produced the APK but PowerShell reported a native-command error because Gradle wrote warnings to stderr. A non-piped rerun returned exit code `0`; `build/app/outputs/flutter-apk/app-debug.apk` exists. |

## Baseline Logs

- `mt5_sync/baseline_logs/flutter_pub_get.txt`
- `mt5_sync/baseline_logs/flutter_analyze.txt`
- `mt5_sync/baseline_logs/flutter_test.txt`
- `mt5_sync/baseline_logs/flutter_build_apk_debug.txt`
- `mt5_sync/baseline_logs/flutter_build_apk_debug_rerun.txt`

## Pre-Existing Worktree State

The repository had many modified, added, and untracked files before MT5 implementation began, including Laravel and Flutter files. This MT5 work must not overwrite those changes and must not alter Laravel files.
