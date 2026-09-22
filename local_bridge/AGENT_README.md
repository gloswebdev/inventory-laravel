# InvoFlow Sync Agent

One always-on agent on the local PC that pulls sales data from the ERP (MS SQL) and writes
the **same rows** to every target — the live site and the local XAMPP MySQL used for testing.

```
  ERP  MS SQL @ 100.108.74.58 (LOGICDBSY)
        │
        │  one query, one definition
        ▼
  invoflow_agent.py  ──┬──► local XAMPP MySQL   (test here first)
                       └──► https://invoflow.gloswebdev.in  (live)
```

---

## Quick start

```bat
cd C:\xampp\htdocs\inventorymanager\inventory-laravel\local_bridge

agent test                          :: check ERP + both targets
agent bootstrap --target local      :: rebuild local, verify the numbers
agent bootstrap --target cloud      :: rebuild live (only after deploying, see below)
install_agent_service.bat           :: install the always-on task
agent status                        :: confirm it is alive
```

Double-click `agent.bat` with no arguments for a menu.

---

## Commands

| Command | What it does |
|---|---|
| `agent status` | Watermarks, row counts per target, recent runs |
| `agent test` | Connectivity check for the ERP and every target |
| `agent sync` | Fetch bills entered/changed since the last watermark, push, then reconcile |
| `agent sync --full` | Re-pull the whole financial year |
| `agent sync --from 2026-04-01 --to 2026-08-22` | Re-pull one date window |
| `agent reconcile` | Verify the last N days against the ERP and repair only what differs |
| `agent bootstrap` | Wipe and rebuild a target from scratch (asks for confirmation) |
| `agent service` | Stay running and follow the schedule — this is what the task runs |

Every command accepts `--target local`, `--target cloud`, `--target all`, `--year 20262027`
and `--config <path>`.

---

## Configuration — `agent_config.json`

```jsonc
"schedule": {
    "mode": "daily",              // "daily" | "interval" | "off"
    "daily_at": "02:00",          // when mode = daily
    "interval_minutes": 1440,     // when mode = interval
    "run_on_startup": false,      // also sync the moment the agent starts
    "catch_up_if_missed": true,   // PC was off at 02:00 -> run as soon as it is back
    "retry_after_minutes": 30,    // a failed run retries this often
    "max_retries_per_window": 4
},
"sync": {
    "years": ["auto", "20252026", "20242025"],  // "auto" = the FY we are in now
    "series": [],                 // [] = every series; or a whitelist like ["AKSL","AKCS"]
    "safety_overlap_minutes": 180,
    "batch_size": 1000,
    "reconcile": { "enabled": true, "days": 45, "tolerance": 0.01 }
},
"targets": {
    "local": { "enabled": true,  ... },   // set false to stop writing to XAMPP
    "cloud": { "enabled": true,  ... }
}
```

Change the file and restart the task — no code edits needed.

---

## How it stays correct

**Watermark on entry time, not bill date.** The agent resumes from
`Sl_Head.LTZ_Entry_Date`, the moment a bill was actually entered. The older scripts resumed
from `vouch_date`, so a bill keyed in today with last month's date was never picked up.
Entry lag of 2–3 days is normal in this ERP, so this mattered.

**Idempotent upsert.** Every row carries `Sl_Txn.code`, the ERP's own line id, and lands via
an upsert on `(financial_year, txn_code)`. Re-running a sync can never duplicate or lose
rows. The old "delete this date range, then insert" approach lost data whenever a batch
failed after the delete.

**The watermark only advances after a target confirms the write.** A failed push simply
repeats next time instead of leaving a hole.

**Reconcile catches edits and deletes.** After every sync the agent compares day-wise
`COUNT(*)` and `SUM(calc_net_amt)` against each target for the last 45 days, and re-pulls
only the days that disagree, pruning rows whose ERP line no longer exists. This is the
safety net for bills edited or deleted after they were first synced — `LTZ_Entry_Date` may
not move on an edit, so the watermark alone is not enough.

**Series classification comes from the ERP, not from hardcoded lists.** `Bill_Ser.type`
(`SL`/`SR`) decides the sign, and `Bill_Ser.Stock_Trans` marks stock transfers. Both are
stored as `series_type` and `is_stock_transfer`. Reports should filter on those instead of
guessing with `series LIKE '%ST%'`.

**Full tax detail is synced.** `calc_gross_amt`, `calc_tax_1/2/3` and `calc_freight` all
come across, so a valuation-style report can be built from the synced table. The older local
script fetched only `Calc_Tax_1`, which is why gross and CGST were always zero — and stock
transfers put their tax in `Calc_Tax_3`, so those looked untaxed.

---

## Replacing the old agents

These three used to write sales data, each with a *different* query, and the last one to run
won:

| Old | Status |
|---|---|
| `SyncMssqlToCloudCommand` (`artisan mssql:sync-to-cloud`, task `InvoFlow_Sales_AutoSync`) | replaced |
| `invoflow_bridge.py` auto-sync block (task `InvoFlow_MSSQL_Bridge`) | replaced — keep the bridge for on-demand queries, but set `auto_sync.enabled = false` in `bridge_config.json` |
| `sync_to_local_mysql.py` | replaced |

Disable them:

```bat
schtasks /Change /TN "InvoFlow_Sales_AutoSync" /DISABLE
```

and in `bridge_config.json` set `"auto_sync": { "enabled": false }` so the query bridge keeps
serving the web console without touching the sales table.

---

## Before pointing the agent at the live site

The cloud needs the updated `MssqlSyncController` (upsert mode, `checksum`, `prune`,
`agent-health`) and the new columns. Deploy the app, then run **either**:

```bash
php artisan migrate --path=database/migrations/2026_08_22_120000_add_sync_keys_to_mssql_sales_records_table.php
```

or just let the controller add the columns itself on the first request. Then:

```bat
agent bootstrap --target cloud
```

Bootstrap removes the rows the old scripts wrote (they have no `txn_code`, and MySQL never
treats two NULLs as duplicates, so they would sit alongside the new rows and double every
total). Until that runs once, `upsert` mode is refused with HTTP 409 rather than silently
double-counting.

---

## Files

| File | Purpose |
|---|---|
| `invoflow_agent.py` | The agent |
| `agent_config.json` | All settings |
| `agent_state.sqlite` | Watermarks and run history (safe to delete; forces a full re-read) |
| `agent.bat` | Manual controls / menu |
| `install_agent_service.bat`, `setup_agent_task.ps1` | Install as always-on task |
| `uninstall_agent_service.bat` | Remove the task |
| `logs/agent-YYYY-MM-DD.log` | Daily log, kept 30 days |
