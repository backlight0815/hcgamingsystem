# MT5 Sync Setup Guide

This feature syncs manual trades from MT5 Desktop/Mobile into the Flutter app through a Python service running on the same Windows machine where MT5 Desktop is open. That Windows machine can be your local PC/laptop or a Windows VPS.

## Architecture

MT5 Mobile / MT5 Desktop manual trade -> MT5 broker account -> Windows machine running MT5 Desktop + Python sync service -> Firebase Spark Firestore -> Flutter Trading Journal synced MT5 tab.

No Laravel backend, Firebase Cloud Functions, Cloud Run, paid Google Cloud services, paid translation APIs, or MQL5 EA are used.

## Linked Accounts

The sync service can work with one or more linked MT5 accounts. Account access
is controlled by accounts linked in Flutter under Trading Journal -> Synced MT5
Trades. The Python agent reads the account currently logged in to MT5 Desktop,
builds an account key from that broker server and account number, and syncs only
when that exact account is active for the configured app user.

Account key format:

```text
{broker_server}_{account_number}
```

Different broker servers with the same account number are stored separately.
The same broker server with different account numbers is also stored separately.
`.env` is for machine settings and database credentials only. Do not place a
fixed MT5 account number, broker server, or MT5 password in `.env`.

## Install MT5 Desktop On The Windows Machine

1. Install the broker's MT5 Desktop terminal.
2. Open MT5 Desktop.
3. Log in to the MT5 account and broker server you linked in Flutter.
4. Keep MT5 Desktop open while syncing.

The Python package can only read the account currently logged in on the same Windows machine where the sync service is running.

## Install Python Dependencies

From the `mt5_sync` folder:

```powershell
python -m pip install --upgrade pip
python -m pip install -r requirements.txt
```

On a Windows VPS without VS Code, use the included CMD helper:

```bat
install_requirements.bat
```

## Configure `.env`

Create `mt5_sync/.env` from `mt5_sync/.env.example`.

Required values:

```env
APP_USER_ID=your_firebase_user_uid
DATABASE_PROVIDER=firebase
FIREBASE_SERVICE_ACCOUNT_PATH=C:\secure\path\firebase-service-account.json
FIREBASE_PROJECT_ID=your-firebase-project-id

MT5_TERMINAL_PATH=
MT5_MACHINE_PROFILE=vps
MT5_AUTO_DISCOVER_TERMINAL=true
MT5_SCAN_ALL_TERMINALS=true
MT5_INITIALIZE_TIMEOUT_MS=60000
MT5_SYNC_MODE=controlled
MT5_SYNC_INTERVAL_SECONDS=300
MT5_RECONNECT_INTERVAL_SECONDS=300
MT5_SYNC_STALE_AFTER_SECONDS=600
REALTIME_MODE=true
SYNC_INTERVAL_SECONDS=300
RECONNECT_INTERVAL_SECONDS=300
MAX_RECONNECT_BACKOFF_SECONDS=300
AUTO_RESUME_AFTER_LOGIN=true
SYNC_HISTORY_OVERLAP_DAYS=3
SYNC_INITIAL_HISTORY_DAYS=7
FREE_TIER_SAFE_MODE=true
SYNC_FLOATING_PROFIT_UPDATES=true
FLOATING_UPDATE_MIN_INTERVAL_SECONDS=300
FLOATING_UPDATE_MIN_PROFIT_CHANGE=1.00
FLOATING_UPDATE_MIN_PRICE_CHANGE_POINTS=10
SYNC_STATE_HEARTBEAT_INTERVAL_SECONDS=300
MOCK_MT5=false
SYNC_RUN_ONCE=false
```

If you use Firebase CLI Application Default Credentials instead of a service
account key, set:

```env
GOOGLE_APPLICATION_CREDENTIALS=C:\Users\backlight\.config\hcgaming\hcgaming-31d5d-application-default-credentials.json
```

Optional machine-specific overrides can be stored in
`mt5_sync/machine_config.json`, created from `machine_config.example.json`.

Do not commit `.env`, `machine_config.json`, service account JSON files, API
keys, private keys, keystore passwords, or MT5 passwords.

## Phase 1 Connection Test

Run:

```powershell
python test_mt5_connection.py
```

The test prints:

- linked active accounts for the configured user
- login
- server
- balance
- equity
- open positions
- active orders
- history deals
- history orders
- whether the currently opened MT5 Desktop account is linked

If the logged-in MT5 account is not linked, the test refuses to sync
trade data and prints `This MT5 account is not linked yet.`

## Run The Continuous Sync Service

Run from the `mt5_sync` folder:

```powershell
python run_sync_service.py
```

By default the service runs in controlled mode every 5 minutes. Each cycle scans
the configured/discovered MT5 terminals on that machine, detects the currently
logged-in account for each terminal, validates it against active linked accounts,
reads open positions, active orders, recent history deals, recent history
orders, and writes sync state.

For a one-cycle verification run:

```powershell
$env:SYNC_RUN_ONCE='true'
python run_sync_service.py
Remove-Item Env:\SYNC_RUN_ONCE
```

On a Windows VPS without VS Code:

```bat
check_python.bat
install_requirements.bat
test_connection.bat
start_sync.bat
```

## Keep The Script Running On Local PC Or VPS

Recommended free options:

- Windows Task Scheduler at startup. See `mt5_sync/setup_windows_task_scheduler.md`.
- NSSM Windows service. See `mt5_sync/setup_nssm_service.md`.
- A visible PowerShell session while testing.

Avoid paid cloud hosting for this feature.

The MT5 sync does not run inside the Flutter app. It runs from the Windows
machine where MT5 Desktop and the Python sync service are installed. For local
testing, that can be your laptop/PC. For production, use a Windows VPS if you
want sync to continue while your laptop is shut down. If the sync machine is
shut down, sync pauses and recovers missing trades after it starts again.

The VPS IP address and RDP port, such as `103.75.190.182:5633`, are only for
opening Remote Desktop. The sync agent does not connect to MT5 over that IP.
Install/run the Python agent inside the VPS Windows session where MT5 Desktop is
open.

## Reconnect And Account Switching

The service keeps running when MT5 Desktop is closed, disconnected, logged out,
or switched to another account. It retries automatically using:

```env
RECONNECT_INTERVAL_SECONDS=10
MAX_RECONNECT_BACKOFF_SECONDS=60
AUTO_RESUME_AFTER_LOGIN=true
```

When a linked account is detected again, the service automatically resumes. When
the user switches from one linked account to another linked account, the sync
target switches to the matching account key and starts recovery from that
account's last sync time.

If the current MT5 Desktop account is not linked, the service pauses trade sync
and updates sync status as `account_not_linked`.

## Restart The Script

1. Stop the current terminal with `Ctrl+C`.
2. Confirm MT5 Desktop is still open and logged in.
3. Run `python mt5_sync/run_sync_service.py` again.

Manual restart is not required for normal logout/login recovery when
`AUTO_RESUME_AFTER_LOGIN=true`.

## Offline Recovery

The service reads `last_synced_time` from:

`users/{userId}/mt5_accounts/{accountKey}/sync_state/default`

On startup, after reconnect, and after switching to a linked account, the
service queries MT5 history from `last_synced_time - 3 days`. If no previous sync
state exists, it reads the last 7 days. This catches trades made while the
script was offline. Unique document IDs and `record_hash` values prevent
duplicate writes.

## Duplicate Prevention

Document keys:

- Open position: `position_{mt5_login}*{broker_server}*{position_id}`
- Closed deal: `deal_{mt5_login}*{broker_server}*{deal_ticket}`
- Order: `order_{mt5_login}*{broker_server}*{order_ticket}`

Existing MT5 records update only MT5-owned fields such as profit, status, close price, current price, SL, TP, commission, swap, timestamps, and raw MT5 metadata.

The realtime watcher compares normalized record hashes in memory and sends only new or changed records to the database adapter. The adapter also checks `record_hash` before writing an existing Firestore document.

Every cycle also reconciles live MT5 state against Firestore. If a previously open `position_*` document is no longer returned by `positions_get()`, the service marks it `closed` so Flutter does not keep showing stale open trades after a position is closed.

Floating profit/current-price updates are throttled by default:

```env
FREE_TIER_SAFE_MODE=true
SYNC_FLOATING_PROFIT_UPDATES=true
FLOATING_UPDATE_MIN_INTERVAL_SECONDS=300
FLOATING_UPDATE_MIN_PROFIT_CHANGE=1.00
FLOATING_UPDATE_MIN_PRICE_CHANGE_POINTS=10
SYNC_STATE_HEARTBEAT_INTERVAL_SECONDS=300
```

`SYNC_STATE_HEARTBEAT_INTERVAL_SECONDS` controls the `sync_state/default` heartbeat so Flutter can detect stale/offline service state without writing to Firestore every second. Use `300` for a 5-minute Firestore-safe cadence.

Journal fields are not overwritten:

- mood
- confidence
- discipline
- followed_trading_plan
- mistake_reason
- notes
- screenshot_url
- custom_tags
- strategy_id
- trading_plan_id

## Firestore Structure

```text
users/{userId}/mt5_accounts/{accountKey}
users/{userId}/mt5_accounts/{accountKey}/trades/{tradeKey}
users/{userId}/mt5_accounts/{accountKey}/orders/{orderKey}
users/{userId}/mt5_accounts/{accountKey}/sync_state/default
users/{userId}/prop_firm_challenges/{challengeId}
users/{userId}/prop_firm_challenges/{challengeId}/daily_snapshots/{yyyyMMdd}
users/{userId}/prop_firm_challenges/{challengeId}/metrics/current
users/{userId}/prop_firm_challenges/{challengeId}/events/{eventId}
```

Account key example:

```text
PUPrime-Demo_700106106
```

## Flutter App

Open Trading Journal -> Synced MT5 Trades.

The tab includes:

- MT5 sync feature toggle.
- Add/edit MT5 account form.
- Multiple linked MT5 accounts.
- Optional Local PC / VPS machine label.
- Account status card.
- Product status: Connected, Updating, Disconnected, Account not linked, or Update temporarily unavailable.
- Open/closed/pending filters.
- Symbol/date/profit filters.
- Manual Refresh fallback.
- Load More History pagination for older closed trades.
- MT5 record detail sheet with journal fields.

The app does not log in to MT5 directly. It only displays data synced by the Python sync service.

## Prop Firm 2-Step Metrics

The Prop Firm dashboard is isolated from the manual Trading Journal and Excel import flows. It reads MT5 synced accounts/trades/orders only.

In Flutter, open Prop Firm to:

- create or edit a configurable 2-Step challenge
- link it to a synced MT5 account
- track profit target progress
- track daily loss and maximum loss usage
- track minimum trading days and Phase 1/Phase 2 status
- see MT5 realtime sync status

The Python service can calculate and write Prop Firm metrics after MT5 sync cycles:

```env
PROP_FIRM_METRICS_ENABLED=true
PROP_FIRM_METRICS_INTERVAL_SECONDS=300
```

Flutter also calculates the same metrics locally as a display fallback while waiting for Python-written `metrics/current`.

No Firebase Cloud Functions, Cloud Run, Pub/Sub, BigQuery, Firebase Extensions, or paid Google Cloud APIs are used.

## Mock Mode

Python:

```env
MOCK_MT5=true
REALTIME_MODE=true
SYNC_INTERVAL_SECONDS=1
```

Flutter debug builds can show mock data when launched with:

```powershell
flutter run --dart-define=MT5_MOCK_DATA=true --dart-define=MT5_SYNC_DEFAULT_ENABLED=true
```

Mock data does not overwrite real database records.

## Disable MT5 Sync

In Flutter, turn off the MT5 sync toggle in the Synced MT5 Trades tab.

For builds, default the toggle off:

```powershell
flutter build apk --debug --dart-define=MT5_SYNC_DEFAULT_ENABLED=false
```

Stop the Python sync service to stop database writes.

## Free-Tier Safety

- Uses Firestore client/admin writes only.
- Does not use Cloud Functions.
- Does not use Firebase Extensions.
- Does not use Cloud Run.
- Does not use BigQuery.
- Does not require Firebase Blaze.
- Queries are scoped under one user and one MT5 account.
- Flutter listens to only the selected account's sync state, open trades, pending orders, and recent closed trades.
- Older closed history loads only when the user taps Load More History.

## Terminal Path Guide

`MT5_TERMINAL_PATH` is optional. If it is empty, the service first tries to
connect to the currently opened MT5 terminal through `mt5.initialize()`, then
tries discovered `terminal64.exe` paths when `MT5_AUTO_DISCOVER_TERMINAL=true`.
Set `MT5_SCAN_ALL_TERMINALS=true` when one VPS may run multiple broker terminals
at the same time, such as HFM and PUPrime.

Examples:

```env
MT5_TERMINAL_PATH=C:\Program Files\MetaTrader 5\terminal64.exe
MT5_TERMINAL_PATH=C:\Program Files\PU Prime MT5 Terminal\terminal64.exe
MT5_TERMINAL_PATH=C:\Program Files\MetaTrader 5 Terminal\terminal64.exe
```

On a local PC, use the local installed `terminal64.exe` path. On a Windows VPS,
use the broker terminal installed on the VPS. Python and MT5 Desktop must run on
the same Windows machine.

Do not set `MT5_TERMINAL_PATH` to the VPS IP address. It must be blank for auto
discovery or point to a local file path on that same Windows machine, such as a
`terminal64.exe` under `C:\Program Files\...`.

For machine-specific setup, create `machine_config.json` from
`machine_config.example.json`:

```json
{
  "machine_profile": "vps",
  "terminal_path": "",
  "auto_discover_terminal": true,
  "sync_mode": "controlled",
  "log_dir": "mt5_sync/logs"
}
```

## Troubleshooting

- Duplicate active/closed MT5 journal records: dry-run the lifecycle repair first:

```powershell
cd C:\xampp\htdocs\hcgaming
python mt5_sync\repair_mt5_duplicates.py --login 135139957 --server HFMarketsGlobal-Live8
```

If the dry run only reports same-account lifecycle duplicates, apply it:

```powershell
python mt5_sync\repair_mt5_duplicates.py --login 135139957 --server HFMarketsGlobal-Live8 --apply
```

The repair groups records inside the same broker/server/account only. It keeps
journal notes, screenshots, tags, psychology fields, plan/strategy fields, and
manual annotations while using the latest closed MT5 financial fields.

- Prompt to paste into VPS Codex:

```text
We are debugging the Python MT5 sync for account 135139957 on server HFMarketsGlobal-Live8.
Please inspect C:\xampp\htdocs\hcgaming\mt5_sync before editing.
Focus on Python files normalizers.py, realtime_watcher.py, db_adapter.py, run_sync_service.py, and repair_mt5_duplicates.py.
Verify that MT5 history deals are collapsed/upserted by lifecycle key position_{login}*{server}*{position_id}, not saved as separate deal_* journal rows when they close an existing open position.
Run:
python -m unittest mt5_sync.test_mt5_lifecycle_sync
python -m py_compile mt5_sync\normalizers.py mt5_sync\db_adapter.py mt5_sync\realtime_watcher.py mt5_sync\repair_mt5_duplicates.py
Then dry-run duplicate repair:
python mt5_sync\repair_mt5_duplicates.py --login 135139957 --server HFMarketsGlobal-Live8
If the dry run shows correct same-lifecycle duplicates only, apply it:
python mt5_sync\repair_mt5_duplicates.py --login 135139957 --server HFMarketsGlobal-Live8 --apply
Do not merge across different broker/server/login combinations. Preserve notes, tags, screenshots, psychology fields, plan/strategy fields, and manual annotations.
```

- Account not linked: link the exact MT5 account number and broker server in Flutter, then keep that account active.
- Disconnected status: confirm MT5 Desktop is open, logged in, and connected to the broker.
- Strategy Tester trades do not sync. They are simulated tester orders and are not returned by the MT5 Python API. Place a real demo/live account order from the normal MT5 terminal trade flow to test syncing.
- No synced trades in Flutter: check Firestore rules, `APP_USER_ID`, and account key.
- Permission denied in Flutter: deploy the updated Firestore rules for `mt5_accounts`.
- Python cannot connect: confirm MT5 Desktop is open, leave `MT5_TERMINAL_PATH` blank for auto discovery, or set the correct VPS/local `terminal64.exe` path.
- No Firebase writes: confirm `FIREBASE_SERVICE_ACCOUNT_PATH` and `FIREBASE_PROJECT_ID`.

## Rollback

See `backups/ROLLBACK_MT5_DYNAMIC_CONFIG_VPS_PYTHON.md`.

Main rollback options:

1. Turn off the Flutter MT5 sync toggle.
2. Stop the Python sync service.
3. Restore from `backup-before-mt5-dynamic-config-vps-python-20260705-2042` if a full rollback is required.
4. Apply `backups/backup-before-mt5-dynamic-config-vps-python-20260705-2042.patch` only after confirming the target branch.
