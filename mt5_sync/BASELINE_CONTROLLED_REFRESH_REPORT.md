# Baseline: MT5 Controlled Refresh

Created: 2026-07-05 11:44 Asia/Singapore

## Git Safety

- Starting branch: `mt5-sync-optimization-20260705-1025`
- Backup branch: `backup-before-mt5-controlled-refresh-20260705-1144`
- Development branch: `mt5-controlled-refresh-20260705-1144`
- Patch backup: `backups/backup-before-mt5-controlled-refresh-20260705-1144.patch`
- Status inventory: `backups/status-before-mt5-controlled-refresh-20260705-1144.txt`
- Untracked inventory: `backups/untracked-before-mt5-controlled-refresh-20260705-1144.txt`
- Rollback guide: `backups/ROLLBACK_MT5_CONTROLLED_REFRESH.md`

The workspace already had many uncommitted changes before this controlled-refresh pass. Those are treated as pre-existing.

## Baseline Checks

Ran from `mobile/hc_trader`.

| Check | Result |
| --- | --- |
| `flutter pub get` | Passed |
| `flutter analyze` | Passed: no issues found |
| `flutter test` | Passed: all tests passed |
| `flutter build apk --debug` | Passed: `build/app/outputs/flutter-apk/app-debug.apk` built |

## Notes

- The test suite prints an existing final exam translation fallback diagnostic during a passing test run.
- No paid Firebase/Google Cloud service was enabled.
- No secrets were printed or committed.
