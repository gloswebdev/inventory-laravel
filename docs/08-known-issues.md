# 08 — Known Issues & Traps

Things discovered while working in this codebase that aren't bugs to silently "fix" on sight
(some are intentional trade-offs, some need the project owner's input first) but are worth
knowing before you go looking for the cause of something and rediscover them the hard way.

## Security / hardening

- **Hardcoded default secret tokens** — every token-auth endpoint falls back to a hardcoded
  default if its `AppSetting` was never explicitly set:
  - `bridge_secret_token` → `invoflow_bridge_key_2026` (also printed in
    `local_bridge/AGENT_README.md`, effectively public)
  - `mssql_sync_token` → `invoflow_mssql_sync_secret_2026`
  - `backup_cron_token` → `invoflow_backup_key_2026`

  Verify the production `app_settings` values have actually been rotated away from these before
  treating these endpoints as secured. If not, anyone knowing the default could push fake sales
  data, trigger a backup, or drive the query-executor bridge.
- **`SystemController::cronBackup()` compares the token with `!==`** rather than `hash_equals()`,
  unlike every other token check in the codebase (`BridgeApiController`, `MssqlSyncController`
  both use `hash_equals`). Low real-world risk (a network-timing attack over HTTP is hard to pull
  off in practice), but inconsistent with the rest of the codebase's own pattern — worth aligning
  if that file is touched anyway.
- Repo root has stray artifacts that shouldn't be near a web root or in version control:
  SQL dumps (`invoflow_backup_*.sql`, `u293228258_*.sql`), and standalone debug scripts
  (`debug.php`, `recover.php`, `recover2.php`, `check_*.php`, `api_test.php`). If any of these are
  reachable at a public URL on production, that's a live data-exposure risk, not just clutter —
  worth confirming with the project owner rather than assuming they're already excluded.

## Two "bridge" health signals, unrelated

Covered in full in [05-sales-sync-bridge.md](05-sales-sync-bridge.md) — the Query Executor's
"Local Bridge Connected" badge (`bridge_agent_last_seen`) says nothing about the sales-sync
agent's health (`sync_agent_health`). Don't diagnose one from the other.

## `mssql_sales_records` is one bad write away from jamming everything

Any row lacking `txn_code` — from any source, any financial year — blocks `upsert` mode
table-wide with a 409 until someone runs `bootstrap`. See the 2026-09-15 incident write-up in
[05-sales-sync-bridge.md](05-sales-sync-bridge.md) for how this actually happened (a manual
Query Executor import using an incomplete column list). The guard itself is working as designed;
the trap is that the Query Executor makes it easy to write incomplete rows into that table in the
first place.

## Two agent-resolution paths for "who is the agent on this sale"

Sales Report drilldown reads the per-voucher `mssql_sales_records.agent_name` (from Busy);
Collection Report resolves via the Algebra ERP PartyMaster API per customer. They can legitimately
disagree when a voucher genuinely has no agent tagged in Busy but the customer has a default agent
in the PartyMaster master data. See the end of
[05-sales-sync-bridge.md](05-sales-sync-bridge.md) for the (currently unimplemented) fallback
idea, and why it wouldn't have fixed the 2026-09-15 incident specifically.

## Architecture-level

- **Large controllers**: `ReportController` (~3170 lines), `MobileController` (~3020),
  `CostingController` (~1354). A lot of near-duplicate logic between desktop controllers and
  `MobileController`, except where mobile explicitly delegates back (e.g. sales drilldown). Adding
  a new report/feature to only one side is an easy way to introduce a desktop/mobile
  inconsistency — check both.
- **Minimal automated test coverage** — `tests/` only has Laravel Breeze's default auth tests
  (`AuthenticationTest`, `RegistrationTest`, etc.). No coverage for costing formulas, permission
  checks, or the sales-sync/bridge logic. Changes to any of those are effectively verified
  manually — be correspondingly careful, and prefer verifying against real data (as the
  2026-09-15 incident investigation did) over trusting the code reading alone.
- **No git remote, ambiguous deploy state** — see [07-deployment.md](07-deployment.md).

## Data-quality footguns worth remembering

- `agent_targets`/`team_targets` are matched to sales data by **trimmed, uppercased string name**
  (`agent_name`), not a foreign key. A spelling/casing drift between what Busy calls an agent and
  what a target row was entered as will silently produce a 0%-achievement or "no target" result
  rather than an error.
- `Product::weight_multiplier` has heuristics for detecting mislabeled units (a value ≥100 stored
  under a KG/LTR label is assumed to actually be grams/ml). If a genuinely large KG/LTR quantity
  ever needs to be entered, this heuristic will silently mis-convert it — worth knowing if a
  costing/planning number looks off by 1000×.
- **`productions.erp_push_status` column missing on live DB (2026-09-20)**:
  Uploading code that writes `erp_push_status` before running the migration SQL on Hostinger MySQL
  triggers `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'erp_push_status' in 'SET'`.
  Always run the migration SQL documented in [07-deployment.md](07-deployment.md) in phpMyAdmin.
- **`100% SOLUBLE IN WATER` ProductType vs Category mismatch (2026-09-20)**:
  `ProductType` id: 9 was created for `100% SOLUBLE IN WATER`, but existing products were synced
  with `product_type_id = 8` (`General`) despite having `category = '100% SOLUBLE IN WATER'` and
  `group_id = 7`. This caused the Planning filter and Bulk Add modal to find 0 products.
  Fixed by updating product rows to `product_type_id = 9`, enhancing the UI to match both type ID
  and category name, and mapping `100% SOLUBLE IN WATER` in `ProductController::syncFromApiRaw()`.
- **ERP Retry Push must read from `StockLedger`, not dynamic BOM (2026-09-20)**:
  When retrying a failed ERP stock push (`pushProductionToErp`), do not dynamically recalculate the
  recipe or formulation BOM because the user may have toggled chemical formulation on/off or
  edited BOM masters after batch creation. `pushProductionToErp()` reads exact deducted items from
  `stock_ledger` (`transaction_type = 'production_deduct'`) to guarantee that what was physically
  deducted locally matches what is issued in ERP.
