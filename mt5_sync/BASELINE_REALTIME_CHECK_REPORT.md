# Baseline Realtime Check Report

Generated: 2026-07-04 20:28 Asia/Singapore

## Scope

Baseline was captured before the realtime MT5 watcher changes on branch `mt5-realtime-sync-20260704-2028`.

Laravel code was not changed for this realtime sync task.

## Flutter Baseline

Working directory: `mobile/hc_trader`

| Check | Result | Log |
| --- | --- | --- |
| `flutter pub get` | Passed | `mt5_sync/baseline_realtime_logs/flutter_pub_get.txt` |
| `flutter analyze` | Passed | `mt5_sync/baseline_realtime_logs/flutter_analyze.txt` |
| `flutter test` | Passed | `mt5_sync/baseline_realtime_logs/flutter_test.txt` |
| `flutter build apk --debug` | Passed | `mt5_sync/baseline_realtime_logs/flutter_build_apk_debug.txt` |

## Notes

- Dependency resolution completed with newer package versions available, but no baseline failure.
- Existing MT5 sync app files were already present before this realtime enhancement.
- Existing repository-wide worktree changes include many Laravel files from prior work; this task will avoid editing Laravel.
