# 02 — Architecture

## Routing (`routes/web.php`, ~315 lines)

Everything is in one file plus `routes/auth.php` (Breeze) and `routes/console.php` (scheduled
artisan commands). Rough shape, top to bottom:

1. `/` and `/dashboard` — desktop dashboard, redirects mobile-interface users to
   `mobile.dashboard`.
2. `Route::middleware(['auth', 'interface:desktop'])` group — the bulk of the desktop app:
   products, recipes, production, adjustments, users, planning, indent, **costing**, reports,
   query-executor, settings/branches. Most are `Route::resource(...)`.
3. A small `['auth']`-only group for `settings/branches/reorder` — shared by both interfaces.
4. `indent-api/*` (`['auth']` only, no interface restriction) — indent show/destroy/clone/update,
   used by *both* desktop and mobile JS.
5. `Route::prefix('mobile')->middleware(['auth', 'interface:mobile'])` — the mobile PWA, almost
   entirely routed to `MobileController` methods. Costing sub-pages are named
   `mobile.costing`, `mobile.costing.boms`, `mobile.costing.pro`, `mobile.costing.purchase`,
   `mobile.costing.pricelist`, `mobile.costing.pricelist-update`.
6. `['auth']`-only System Management group (`/system/*`) — backup/restore/update/cache, gated
   *inside the controller* by `Auth::user()->role !== 'admin'` (not by a route-level check).
7. `/cron/database-backup` — **no auth middleware**, protected only by a `?token=` query param
   compared against `AppSetting::get('backup_cron_token', 'invoflow_backup_key_2026')`. Meant for
   Hostinger's hPanel cron. See [08-known-issues.md](08-known-issues.md) about the comparison
   operator used here.
8. `/api/sync/mssql-sales/*` — the **sales-sync agent's** API (`MssqlSyncController`). No Laravel
   auth; a shared-secret header/query/body token instead. CSRF is explicitly excluded for `api/*`
   and `cron/*` in `bootstrap/app.php`.
9. `/api/v1/bridge/*` — the **query-executor bridge's** API (`BridgeApiController`). Same
   token-auth pattern, **completely separate agent/process** from #8 — see
   [05-sales-sync-bridge.md](05-sales-sync-bridge.md).

## Middleware

- `interface:desktop` / `interface:mobile` → `App\Http\Middleware\EnforceInterface`, aliased in
  `bootstrap/app.php`. Redirects based on `$user->interface_type`; a no-op for guests (auth
  middleware runs first anyway on protected routes).
- CSRF is disabled for `api/*` and `cron/*` (`bootstrap/app.php` → `validateCsrfTokens(except:
  [...])`) since those are hit by non-browser clients (the Python bridge agents, Hostinger cron).

## Scheduler (`bootstrap/app.php` + `routes/console.php`)

Two places define scheduled jobs — check both when debugging "why didn't X run":

- `bootstrap/app.php` → `withSchedule(...)`:
  - Daily 01:00 — `ProductController::syncFromApiRaw()` (product master sync from Algebra ERP).
  - Purchase-register sync — daily/weekly, **only if** `AppSetting('purchase_sync_auto') ===
    'enabled'`, time/day itself also read from `AppSetting`.
  - Sales-report sync — same pattern, `AppSetting('sales_sync_auto')`.
  - Pricelist sync — same pattern, `AppSetting('pricelist_sync_auto')`.
  - All three are try/caught and logged, never throw into the scheduler.
- `routes/console.php`:
  - `app:backup-database --mail` daily at 23:50 (`BackupDatabaseCommand`).

None of the above touch `mssql_sales_records` — that table is fed exclusively by the external
Python bridge agent hitting the HTTP API, not by Laravel's own scheduler. See
[05-sales-sync-bridge.md](05-sales-sync-bridge.md).

## Controllers (`app/Http/Controllers`)

Sizes as of 2026-09-15 (a rough proxy for "how much logic lives here" / refactor candidates):

| Controller | ~Lines | Notes |
|---|---|---|
| `ReportController` | 3170 | Stock ledger, live stock, purchase/sales/collection reports, sales drilldown, agent/team targets, query executor's SQL-string helpers live elsewhere (see below). |
| `MobileController` | 3020 | Mirrors most desktop features for the mobile PWA; **delegates** some (e.g. sales drilldown) to the desktop controller rather than re-implementing. |
| `QueryExecutorController` | 584 | Admin-only ad-hoc SQL against the local MSSQL ERP via the bridge (dispatch/poll/import). |
| `Api\MssqlSyncController` | 518 | Sales-sync agent's ingest/status/checksum/prune/health endpoints. |
| `CostingController` | 1354 | Cost calculator, purchase register, pricelist sync/push. |
| `SystemController` | 728 | Backup/restore/update/cache; admin-only, checked in-method. |
| `UserController` | 514 | Users + the permission matrix with granular feature flags (including `branch_lock`). |
| `CostingBomController` | 495 | BOM Master CRUD (multi-step wizard backend). |
| `ProductionController` | ~580 | Finished goods batch creation, stock deductions, Logic ERP stock push (`SaveReceiptStock`, `SaveIssueStock`), single retry (`retryErpPush`), and bulk retry (`bulkRetryErpPush`). |
| `IndentController` | 413 | |
| `Api\BridgeApiController` | 338 | Query-executor bridge's poll/submit/push-sync/heartbeat. |
| `PlanningController` | ~240 | MRP calculator (Packaging BOM + Chemical Formulation toggle, Excel export). |
| `RecipeController` | 202 | |
| `AdjustmentController` | ~380 | Manual stock adjustments across all item types (FG, RM, PM). Dual-mode: Stock Receipt (+) & Stock Issue (-), branch locking to assigned user branch, packing size display, available stock validation, Logic ERP sync (`SaveReceiptStock`/`SaveIssueStock`), single & bulk retry, revert/delete. |

Everything else (Auth\*, Profile, ProductType/Group/Attribute) is small and standard.

## Services (`app/Services`)

- `BomResolverService` (`app/Services/BomResolverService.php`):
  Unified resolver that merges **Packaging BOM** (`Recipe` / `CostingBomPackingMaterial`) and **Chemical Formulation** (`CostingBom`).
  - Resolves exact material explosion per batch or planning quantity.
  - Automatically scales chemical formulations using `quantity_boxes * unit_box * weight_multiplier`.
  - Distinguishes packaging items (`[ 📦 Pack ]`) from chemical bulk items (`[ 🧪 Chem ]`).
  - Used by `PlanningController` (MRP explosion & Excel export) and `ProductionController` (live stock validation & batch stock deduction).

## Models (`app/Models`)

Notable relationships:

- `Product` → `group()` (ProductGroup), `type()` (ProductType), `recipes()`,
  `costingBoms()`. Has a computed `weight_multiplier` attribute normalizing GM/ML/KG/LTR into a
  KG/LTR multiplier — used across costing and planning maths.
- `CostingBom` → `items()` (`CostingBomItem`, the bulk ingredients), `packingMaterials()`
  (`CostingBomPackingMaterial`, size-wise packing config linked to a `Pricelist` row).
- `User` → `permissions()` (`UserPermission`, one row per `page_key`), `branches()` /
  `productTypes()` / `permittedAttributes()` (pivot-scoped access — see
  [06-permissions.md](06-permissions.md)).
- No Eloquent model wraps `mssql_sales_records` — it's queried via the `DB::table(...)` query
  builder throughout (`ReportController`, `MssqlSyncController`), and its schema is created/
  migrated *at runtime* by `MssqlSyncController::ensureSchema()`, not by a Laravel migration file.
  Same for `saved_queries` / `query_jobs` (Query Executor).

## Views (`resources/views`)

- `costing/index.blade.php` — Cost Manager dashboard (Tab 1: ingredient breakdown; size-wise
  packed cost cards).
- `costing/bom/index.blade.php` — the multi-step Alpine.js BOM wizard (Steps: header → ingredients
  → technical/purity → packing/size-wise → review). This is the single most business-rule-dense
  view in the app — cross-reference [04-costing-module.md](04-costing-module.md) before editing.
- `reports/sales_report.blade.php` — desktop sales report + the interactive drill-down UI
  (branch → agent → category → series → party → bill → item), driven by
  `ReportController::salesDrilldown()` over AJAX.
- `reports/query_executor.blade.php` — the SQL console + bridge status badge (reads
  `bridge_agent_last_seen`, unrelated to the sales-sync agent's own health key).
- `mobile/*.blade.php` — mobile-PWA equivalents; `mobile/sales_report.blade.php` and
  `mobile/collection_report.blade.php` render the same drilldown JSON.
- `mobile/sales_360.blade.php` — 360° Sales Explorer with multi-select branch scoping, guided dual
  selection (Category-wise vs Agent-wise), contextual drilldown bottom sheets, and interactive breadcrumbs.
- `layouts/app.blade.php` — desktop shell; has the Fullscreen API toggle + `localStorage`-based
  resume-toast (see `PROJECT_MEMORY.md` §5 for why it's implemented that way).
