# Baseline: MT5 Dynamic Config / VPS Python

Created: 2026-07-05 20:42

## Safety Setup

- Starting branch observed: `mt5-refresh-disconnect-fix-20260705-1632`
- Backup branch created: `backup-before-mt5-dynamic-config-vps-python-20260705-2042`
- Development branch created: `mt5-dynamic-config-vps-python-20260705-2042`
- Dirty tracked patch backup: `backups/backup-before-mt5-dynamic-config-vps-python-20260705-2042.patch`
- Untracked file inventory: `backups/backup-before-mt5-dynamic-config-vps-python-20260705-2042-untracked-files.txt`
- Rollback file: `backups/ROLLBACK_MT5_DYNAMIC_CONFIG_VPS_PYTHON.md`

## Baseline Checks

Run from `mobile/hc_trader`.

- `flutter pub get`: passed
- `flutter analyze`: passed, no issues found
- `flutter test`: passed
- `flutter build apk --debug`: passed

## Baseline Build Output

- Debug APK: `mobile/hc_trader/build/app/outputs/flutter-apk/app-debug.apk`

## Baseline Finding

- `mt5_sync/.env` and `mt5_sync/.env.example` still contain static account-target fields such as `MT5_EXPECTED_LOGIN`, `MT5_EXPECTED_SERVER`, and `MT5_ALLOWED_ACCOUNTS`.
- This static configuration is a likely cause of VPS account switching trouble, especially for the reported account `HFMarketsGlobal-Live8_135139957`.

## Notes

- The worktree had substantial pre-existing uncommitted and untracked work before this enhancement.
- Baseline checks were green.
