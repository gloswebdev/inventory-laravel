# 03 — Database

Connection: MySQL, `DB_DATABASE=inventory_laravel_db` locally (see `.env`). Production uses a
separate Hostinger MySQL database — same schema, different data/state, kept in sync only where
explicit sync code exists (mainly `mssql_sales_records`, via the bridge — see
[05-sales-sync-bridge.md](05-sales-sync-bridge.md)).

## All tables (as of 2026-09-15)

```
adjustments                       migrations                        products
agent_targets                     mssql_sales_records                product_attributes
app_settings                      password_reset_tokens              product_attribute_user
branches                          personal_access_tokens             product_groups
branch_user                       pricelists                         product_prices
cache                             pricelist_push_logs                product_sync_logs
cache_locks                       pricelist_push_log_items           product_types
costing_boms                      productions                        product_type_user
costing_bom_items                 production_items                   purchase_registers
costing_bom_packing_materials     production_records                 query_jobs
failed_jobs                       recipes                            recipe_items
indents                           sales_registers                    saved_queries
indent_items                      sessions                           stock_ledger
jobs                              teams                               team_targets
job_batches                       users                              user_permissions
```

## Tables with no Eloquent model / no migration file

These are managed by raw `DB::table(...)` + runtime `Schema::` calls inside their owning
controller, **not** by `database/migrations/*`:

- `mssql_sales_records` — created/altered by `MssqlSyncController::ensureSchema()` on first use.
  Column list, types, and the unique-index rule are defined there in PHP, not in a migration.
  See [05-sales-sync-bridge.md](05-sales-sync-bridge.md) for the full column list and the
  `txn_code`-must-be-present rule that guards `upsert` mode.
- `saved_queries`, `query_jobs` — Query Executor presets and dispatched-job records
  (`QueryExecutorController`, `Api\BridgeApiController`).

If you're hunting for a column definition and can't find a migration for it, check the owning
controller for a `Schema::hasColumn(...)` / `Schema::create(...)` block before assuming it
doesn't exist.

## `app_settings` — the app's runtime config table

A generic key/value store (`AppSetting::get($key, $default)` / `::set($key, $value)`) used
instead of `.env`/`config/*` for anything an admin should be able to change from the UI, or that
a background process needs to read/write at runtime. Keys seen in use, grouped by owner:

| Key(s) | Written by | Read by |
|---|---|---|
| `bridge_secret_token` | Bridge Setup UI | `BridgeApiController::authenticateToken()` |
| `bridge_agent_last_seen`, `bridge_agent_version`, `bridge_agent_db_name` | `BridgeApiController::poll()` (query-executor bridge, every 2-3s) | `reports/query_executor.blade.php` "online" badge |
| `mssql_sync_token` | Settings UI | `MssqlSyncController::authorised()` (sales-sync agent) |
| `sync_agent_health` (JSON blob) | `MssqlSyncController::agentHealth()` (sales-sync agent) | not currently surfaced in any view — see [08-known-issues.md](08-known-issues.md) |
| `last_mssql_sales_sync` | `MssqlSyncController::ingestSales()` | — |
| `backup_cron_token`, `backup_auto_enabled` | Settings UI | `SystemController::cronBackup()` |
| `purchase_sync_auto/frequency/time/day`, `sales_sync_auto/...`, `pricelist_sync_auto/...` | Costing / Reports settings UI | `bootstrap/app.php` scheduler |
| `erp_api_base_url`, `erp_api_key` | Settings UI | `ReportController::getPartyMasterMap()` and friends (Algebra ERP) |
| `partymaster_api_branch/actcode/agentcode/txntype` | Settings UI | `getPartyMasterMap()` request params |
| `erp_push_enabled`, `erp_push_base_url`, `erp_push_username`, `erp_push_password` | Settings UI / DB seed | `ProductionController::pushProductionToErp()` (Logic ERP Stock Push) |
| `erp_receipt_doc_prefix`, `erp_receipt_godown_name`, `erp_receipt_received_from`, `erp_receipt_issue_to` | Settings UI / DB seed | `ProductionController::pushProductionToErp()` (SaveReceiptStock) |
| `erp_issue_doc_prefix`, `erp_issue_godown_name`, `erp_issue_issue_to` | Settings UI / DB seed | `ProductionController::pushProductionToErp()` (SaveIssueStock) |

**Two health/"online" signals share the naming pattern but are unrelated** —
`bridge_agent_last_seen` (query-executor bridge) vs `sync_agent_health` (sales-sync agent). Don't
assume one tells you anything about the other. See
[05-sales-sync-bridge.md](05-sales-sync-bridge.md).

## Key model relationships

- **Product** (`products`) — `group()`→ProductGroup, `type()`→ProductType, `recipes()`,
  `costingBoms()`. Computed `weight_multiplier` normalizes GM/ML/KG/LTR (see `Product.php`).
  *Note on ProductType id: 9 (`100% SOLUBLE IN WATER`)*: Synced products with category `100% SOLUBLE IN WATER`
  or `group_id = 7` are mapped to ProductType 9 to ensure correct filtering in Production Planning.
- **Recipe** (`recipes` + `recipe_items`) — manufacturing packaging BOM for `Production`.
- **CostingBom** (`costing_boms` + `costing_bom_items` + `costing_bom_packing_materials`) —
  bulk costing recipe and chemical formulation BOM. `packingMaterials()` links to `pricelists` (a size/Finished-Good config)
  and to a `products` row representing the packing material, with `is_container` triggering the
  CF1 division rule (see [04-costing-module.md](04-costing-module.md)).
- **Production** (`productions` + `production_items`) — finished goods batch execution.
  Has `erp_push_status` (`pending`, `pushed`, `skipped`, `failed`), `erp_issue_response` (JSON),
  and `erp_receipt_response` (JSON). Raw materials deducted on creation or retry are tracked in `stock_ledger`
  (`transaction_type = 'production_deduct'`).
- **User** (`users`) — `role` enum(`admin`,`user`); `interface_type` enum(`desktop`,`mobile`);
  `permissions()`→`UserPermission` (one row per `page_key`); `branches()` / `productTypes()` /
  `permittedAttributes()` are `belongsToMany` scoping pivots. See
  [06-permissions.md](06-permissions.md).
- **Indent** (`indents` + `indent_items`) — bulk requisition; has a separate "process" workflow
  (`IndentController::process`) distinct from create/edit.
- **Adjustment** (`adjustments`) — manual inventory receipts (+ inward) and issues (- outward) across all product types (FG, RM, PM, etc.).
  Columns added via migration `2026_09_22_100000_add_revamp_fields_to_adjustments_table`: `user_id` (logged by user), `branch_code`, `branch_name`, `erp_push_status` (`pending`, `success`, `failed`, `skipped`), `erp_doc_no`, and `erp_response`.
  Effects recorded in `stock_ledger` (`transaction_type = 'adjustment_add'` or `'adjustment_deduct'`, with revert recorded as `'adjustment_revert'`).
- **AgentSalesTarget / TeamTarget** — monthly target rows keyed by `agent_name`/`target_month`
  (string agent name, not a foreign key — matched by trimmed/uppercased string against whatever
  `agent_name` the sales data or PartyMaster API produced. Fragile to spelling drift between the
  two sources — see [05-sales-sync-bridge.md](05-sales-sync-bridge.md)).

## Cross-database caveat

Because production and local XAMPP are **separate MySQL instances**, any diagnostic query you run
against the local `inventory_laravel_db` only tells you about local data. The Python bridge
agents in `local_bridge/` are both configured (via `agent_config.json` / `bridge_config.json`)
to talk to the **production** base URL (`https://invoflow.gloswebdev.in`) by default — so their
heartbeats and synced rows land in the *production* database, not the local one, unless someone
has deliberately pointed a given run at `--target local`. Always check which target you're
looking at before concluding something is "stale" or "broken".
