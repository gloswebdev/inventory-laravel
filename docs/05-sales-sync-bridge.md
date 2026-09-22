# 05 — Sales Data Sync & the Local Bridge(s)

**Read this whole file before touching anything under `local_bridge/`, `mssql_sales_records`,
`Api\MssqlSyncController`, or `Api\BridgeApiController`.** This is the part of the system that has
already caused real confusion (twice, in one evening) because two unrelated things share the word
"bridge". It also has a documented incident below — read that before assuming missing agent/sales
data means "the ERP didn't tag it".

## There are TWO independent local Python agents

Both live in `local_bridge/`, both run on the machine that has ODBC access to the on-premise Busy
ERP (MS SQL Server), both authenticate with a shared-secret token compared via `hash_equals`
against an `AppSetting`. **They are otherwise unrelated** — different scripts, different API
endpoints, different health-tracking keys, different failure modes.

| | `invoflow_agent.py` | `invoflow_bridge.py` |
|---|---|---|
| Purpose | Sync **sales transaction data** (Busy → cloud + local MySQL mirror) | Execute **ad-hoc SQL** dispatched from the Query Executor screen |
| Talks to | `/api/sync/mssql-sales*` (`Api\MssqlSyncController`) | `/api/v1/bridge/*` (`Api\BridgeApiController`) |
| Config file | `agent_config.json` | `bridge_config.json` |
| Health/heartbeat setting | `sync_agent_health` (JSON blob: status, last_run_at, next_run_at, error) | `bridge_agent_last_seen` / `_version` / `_db_name` |
| Where health shows in UI | Nowhere currently rendered (only via the raw `/api/sync/mssql-sales/agent-health` GET, or `agent.py status`) | The green "Local Bridge Connected / Last ping Ns ago" badge on `reports/query_executor.blade.php` |
| Run mode | `service` (always-on loop, follows a schedule) or one-shot CLI commands | Polls every 2-3 seconds |
| CLI | `sync`, `bootstrap`, `reconcile`, `status`, `test`, `service` | (simpler poll/submit loop, no equivalent CLI surface documented here — check the script) |

**The practical trap**: the Query Executor's "Local Bridge Connected" badge only reflects
`invoflow_bridge.py`'s heartbeat. It tells you nothing about whether `invoflow_agent.py` (the one
that actually keeps `mssql_sales_records` up to date) is healthy. Seeing "Connected" there is not
evidence that sales sync is working — check `sync_agent_health` / the agent's own log
(`local_bridge/logs/agent-YYYY-MM-DD.log`) for that.

Both point at the same production `base_url` (`https://invoflow.gloswebdev.in`) by default —
neither writes to the local XAMPP MySQL unless explicitly run with `--target local` (which the
sales-sync agent does, in parallel, as its "local mirror" target — see `agent_config.json`'s
`targets` list).

## `mssql_sales_records` and the `txn_code` rule

`MssqlSyncController::ingestSales()` supports two write modes:

- **`replace`** (used by `sync --full` and `bootstrap`) — deletes and re-writes a whole
  financial-year window from a complete ERP row set.
- **`upsert`** (used by normal incremental `sync` and `reconcile`) — but only if:
  ```php
  $blocked = !$this->indexExists('mssql_sales_uniq_line')
      || DB::table('mssql_sales_records')->whereNull('txn_code')->exists();
  ```
  i.e. if **any row anywhere in the table** (any financial year) lacks `txn_code`, upsert is
  refused table-wide with HTTP 409: *"Legacy rows without txn_code are still present, so an
  upsert would double-count. Run the agent once with `bootstrap` (sends allow_reset) to rebuild
  this table before using upsert mode."*

This means one bad/partial write into `mssql_sales_records` — from anywhere — jams incremental
sync for **every** financial year until someone runs `bootstrap`.

`invoflow_agent.py`'s SQL query (`build_query()`, in `_run_year`) selects the **full** column set
per row: `txn_code, branch_name/code, vouch_date/time, vouch_num/code, series, act_name, act_code,
agent_code, agent_name (via a Busy `Agents_Brokers` join), item/lot/qty/amount fields, ...`. This
is the only code path that should ever populate `mssql_sales_records`.

## Incident (2026-09-15): "No Agent" showing for every branch since April 2026

**Symptom**: Sales Report drill-down, at the `agent` level, showed 100% `(No Agent)` for every
branch, for every bill dated 2026-04-11 onward — both mobile and desktop (they share the same
`ReportController::salesDrilldown()` code, so this was never a mobile-vs-desktop bug).

**Investigation** (see full DB queries in this session's transcript if needed, summarized here):

1. `mssql_sales_records.agent_code` was `NULL` for exactly 20,953 rows, 100% of every branch's
   bills from 2026-04-11 (the branches' FY-2026-27 start) onward, 0% before that date.
2. Ruled out a code bug: `invoflow_agent.py`'s query template is identical across financial
   years (only the table-name suffix changes), and it correctly populated `agent_code` for
   FY24-25 and FY25-26. Same code, same query, different result → the difference had to be in the
   data, not the sync logic.
3. Cross-checked other columns on the same 20,953 rows: `txn_code`, `vouch_code`, and `act_code`
   were **also** NULL on exactly those rows (100% correlated), while `rate` and `vouch_time` were
   present.
4. That exact column set (`act_name` present; `txn_code`/`vouch_code`/`act_code`/`agent_code`/
   `agent_name` absent) matched a **saved Query Executor preset**
   ("Current Year Sales (FY 2026-2027) [Exact ERP Match]", in the `saved_queries` table) almost
   column-for-column — that preset selects `ACT.act_name` but never `ACT.act_code`,
   `HD.agent_code`, `AG.Agent_Name`, `TXN.vouch_code`, or a `txn_code`-equivalent.
5. **Root cause**: FY 2026-27's data had been bulk-loaded into `mssql_sales_records` via the
   Query Executor's manual "Import Selected" path using that trimmed preset — not via
   `invoflow_agent.py`'s own full sync. This explains both the missing agent data *and* why the
   automated agent had been stuck since 11:04 that morning retrying-and-failing with the
   `txn_code` 409 error above, sleeping until its next scheduled slot (next day, 02:00).

**Fix**: `python invoflow_agent.py bootstrap --yes`, run from `local_bridge/`. This rebuilt all
three configured financial years (24-25, 25-26, 26-27 — 203,066 rows total) via the full query,
against both the `local` and `cloud` targets, and cleared the block. Verified afterward:
`txn_code`/`agent_code`/`act_code` NULL counts all dropped to 0, and AKOLA's agent breakdown
showed real named agents again.

**Lesson — do not repeat this**: never use the Query Executor's ad-hoc "Import Selected" feature
to (re)populate a full financial year of `mssql_sales_records`. It's meant for one-off
investigative queries and small manual imports, not as a substitute for the sync agent's own
`sync`/`bootstrap` commands, because it will not include the full column set the rest of the app
depends on (`agent_code`, `act_code`, `txn_code`, ...) and will silently jam incremental sync for
every other financial year until someone notices and runs `bootstrap`.

If you see `(No Agent)`/blank-party symptoms again, check in this order:
1. Is it every bill from one date onward across all branches (→ almost certainly this pattern —
   check `txn_code`/`act_code` nullness correlation, then check `saved_queries` for a preset that
   was likely used to import it), or
2. Is it scattered/partial (→ more likely a genuine Busy-side data-entry gap — nothing to fix in
   this app; a Busy voucher-configuration question for whoever owns the ERP).

## The two agent-resolution paths don't unify

Even after the above fix, remember these are genuinely two different data sources for "who is the
agent":

- **`ReportController::salesDrilldown()`'s `agent` level** — raw `mssql_sales_records.agent_name`
  per voucher. Blank whenever Busy itself has no agent tagged on that specific bill (a real,
  occasional occurrence — the code already labels this case `(No Agent)` / treats ERP's `NIL`
  placeholder the same way, see `drillDisplayLabel()`).
- **Collection Report / agent targets** — resolves agent via `getPartyMasterMap()`, i.e. the
  Algebra ERP PartyMaster API's per-customer default agent, keyed by `act_code`. Stable
  regardless of what a given voucher happened to record.

A genuinely-blank Busy `agent_code` (not the bulk-import-gap bug above) will still show
`(No Agent)` in Sales Report drilldown while Collection Report shows the party's real default
agent for the same customer — **this is not itself a bug**, but if it's ever worth fixing, the
fix is adding a `getPartyMasterMap()` fallback (keyed by `act_code`) to the drilldown's `agent`
level for rows where `agent_name` is blank *and* `act_code` is present. (Not implemented as of
2026-09-15 — deliberately not done as part of the incident above, since that incident's rows had
`act_code` missing too, making this fallback ineffective for that specific case.)

## Known hardcoded default secrets

Every token in this system has a hardcoded fallback default if the corresponding `AppSetting` was
never set: `invoflow_bridge_key_2026` (bridge), `invoflow_mssql_sync_secret_2026` (sales sync),
`invoflow_backup_key_2026` (cron backup). The bridge one is also printed in
`local_bridge/AGENT_README.md`. Before relying on token auth for anything sensitive, confirm the
production `AppSetting` values have actually been rotated away from these defaults — see
[08-known-issues.md](08-known-issues.md).
