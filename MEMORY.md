# MEMORY — Running Project Context

This file is different from `docs/` (which explains how the system is *built*). This file tracks
**what's currently true, in-flight, or unresolved** — the stuff that goes stale and needs
updating, as opposed to `docs/`'s architecture facts that stay true for months. Read this
*after* `docs/README.md`, and **update it as you go** — add a dated line when you fix something,
resolve an open item, or discover a new one. Don't let it just grow forever: when an "Open
Question" gets answered, move the answer to the right `docs/` file and delete it from here.

---

## Open questions (need the project owner's input)

- **Deployment state unclear (opened 2026-09-15)**: local working tree has ~30 files of
  uncommitted changes vs. last commit `3f5de920`, including near-full-file diffs in
  `ReportController.php`, `sales_report.blade.php`, `MssqlSyncController.php`. Unknown whether
  this is already live on production (uploaded but never `git commit`-ed) or still pending. See
  [docs/07-deployment.md](docs/07-deployment.md). **Ask before deploying anything or assuming
  what's live.**
- Are the default fallback secret tokens (`invoflow_bridge_key_2026`,
  `invoflow_mssql_sync_secret_2026`, `invoflow_backup_key_2026`) still in use on production, or
  have they been rotated? Not verified. See [docs/08-known-issues.md](docs/08-known-issues.md).

## Recent incidents / fixes (newest first)

- **2026-09-22 — Indent Manager & Pricelist Module Support for "100% SOLUBLE IN WATER".**
  1. **Indent Manager Dynamic Item Type Filter**:
     - Converted the static "Finished Good" badge in `resources/views/indent/index.blade.php` to an interactive `<select id="item_type_filter">` supporting: *All Types*, *Finished Good* (default selected), and *100% SOLUBLE IN WATER*.
     - Synchronized with `productSearchInput` so search text and item type filtering work simultaneously.
     - Updated `IndentController::index()` and `getBulkStock()` to query both `Finished Good` (type 6) and `100% SOLUBLE IN WATER` (type 9) so live stock and rows populate accurately for both categories.
  2. **Pricelist Master & Pricelist Update Modules**:
     - Products of type `100% SOLUBLE IN WATER` are synced from ERP with `group5 = 'FERTILIZER GOODS'` and `group1 = '100% SOLUBLE IN WATER'` in `pricelists` table.
     - Updated `CostingController::pricelist()`, `pricelistUpdate()`, and `pro()` to query `whereIn('group5', ['FINISHED GOODS', 'FERTILIZER GOODS'])->orWhere('group1', '100% SOLUBLE IN WATER')`.
     - Updated `MobileController::costingPricelist()`, `pricelistUpdateQuery()`, and `costingPricelistUpdate()`.
     - Updated `CostingBomController::index()` for consistency in FG pack linking.
     - Enabled "100% SOLUBLE IN WATER" in the Group 1 (Category) filter dropdown across Desktop and Mobile pricelist modules.

- **2026-09-22 — Stock Adjustment Module Revamp & Granular Access Control.**
  1. **Dual Action Mode (Receipt & Issue)**: Revamped Desktop (`/adjustments`) and Mobile (`/mobile/adjustments`) with dual-action modes: **Stock Receipt (+ Inward)** and **Stock Issue (- Outward)** supporting **all product types** (Finished Goods, Raw Materials, Packing Materials, Semi-Finished Goods, General items).
  2. **Logic ERP Push Integration**: Added `pushAdjustment()` in `ErpStockPushService.php` pushing `SaveReceiptStock` (Prefix `ADJ-REC`) and `SaveIssueStock` (Prefix `ADJ-ISS`) to Logic ERP with real-time response capture, single retry, and bulk retry.
  3. **Granular Access Control & Branch Lock**: Added 11 granular feature permissions in `UserController.php` for `adjustments` and `mobile_adjustments` (`management`, `create_receipt`, `create_issue`, `history`, `type_filter`, `branch_select`, `branch_lock`, `reason_select`, `erp_push`, `erp_bulk_retry`, `delete`). With `branch_lock` active, adjustment entry, dropdowns, and history are strictly locked to the user's assigned branch.
  4. **Item Packing Size Visibility**: Enhanced product dropdowns, search filters, selected product cards, and history rows across Desktop & Mobile to prominently display `pack_name` (e.g. `ABAWIN • 250 ML [SWA001386] (ML)`), eliminating ambiguity between different pack sizes of the same product.
  5. **Live Database Requirement**: Live database (`u293228258_invoflowsagar`) **requires** running `ALTER TABLE adjustments` to add: `user_id`, `branch_code`, `branch_name`, `erp_push_status`, `erp_doc_no`, `erp_response` to avoid `SQLSTATE[42S22]` errors upon deployment.

- **2026-09-21 — Production Logic ERP Credentials Configuration & Connectivity Verification.**
  1. **Live Logic ERP Endpoints & Basic Auth**: Configured live production endpoints (`http://logic.gloswebdev.in/SaveReceiptStock` and `http://logic.gloswebdev.in/SaveIssueStock`) with user `SALapi` and password `SAL@api@123`.
  2. **Database & Fallback Persistence**: Saved to `app_settings` table (`erp_push_base_url`, `erp_push_username`, `erp_push_password`, and `erp_push_enabled = '1'`). Updated default fallbacks in `ErpStockPushService.php` and `SettingController::testErpPushConnection()`.
  3. **Live Connectivity Verification**: Verified live connection via test script; both endpoints responded with `HTTP 200 OK` and expected Logic ERP JSON payloads (`"Item Details not Present...Please Check"`).
  4. **UI & Documentation Sync**: Updated `resources/views/settings/branches.blade.php` Section 6 with `PRODUCTION CONFIGURED` badge, live preview URLs, and documented SQL statements in `docs/07-deployment.md` and `walkthrough.md`.

- **2026-09-20 — Unified BOM Integration, ERP Stock Push & Retry Engine, and Planning Filter Fix.**
  1. **Unified BOM (`BomResolverService`)**: Merged Packaging BOM (`Recipe` / `CostingBomPackingMaterial`) and Chemical Formulation (`CostingBom`) with dynamic batch scaling (`quantity_boxes * unit_box * weight_multiplier`). Added Chemical Formulation toggle (default OFF) in Planning and Production modals.
  2. **Production ERP Push & Retry Engine**: Isolated `pushProductionToErp()` in `ProductionController`. Pushes `SaveReceiptStock` (finished goods) and `SaveIssueStock` (raw materials read from local `StockLedger` deduction history). Added single retry button `[ 🔄 Retry ]` / `[ 🚀 Push ]` on table rows and batch detail drawer, plus bulk retry `[ 🔄 Sync to ERP (X) ]` in top filter bar for `pending`, `skipped`, and `failed` batches.
  3. **UI Localization**: Converted all Hinglish helper and confirmation texts in `production/index.blade.php` to clean professional English.
  4. **Planning Filter & Bulk Modal Fix**: Resolved empty dropdown and blank bulk add modal when selecting `100% SOLUBLE IN WATER` (ProductType 9). Updated DB rows with category `100% SOLUBLE IN WATER` to `product_type_id = 9`, made frontend filters match both ID and category string, and preserved mapping in `ProductController::syncFromApiRaw()`.
  5. **Live DB Schema Fix**: Documented and resolved `SQLSTATE[42S22]: Unknown column 'erp_push_status'` on production (`u293228258_invoflowsagar`) by adding required migration SQL in `docs/07-deployment.md`.
  6. **Mobile Production Synchronization & Product Filter Fix**: Synchronized `MobileController::production()` and `submitProduction()` with `BomResolverService`, ERP stock push, and `StockLedger` reversals. Removed the hardcoded `Product::whereHas('recipes')` restriction in `MobileController::production()` and `planning()`, fixing the issue where only 6 products (legacy recipe items) were available in the search drawer instead of all 449 Finished Goods and producible items.
  7. **Mobile Preview Slip Card Navbar Collision Fix**: Fixed floating "Total Yield / Preview Slip" card colliding with the bottom navigation bar and the center floating `+` button in `mobile/production.blade.php`. Changed bottom offset from `bottom-24` (96px) to `bottom-[7.5rem]` (120px) and added `pb-36` to the history container for full scroll clearance.

- **2026-09-16 — Mobile 360° Sales Explorer Guided Multi-Level Drilldown & UX Overhaul.**
  Redesigned `resources/views/mobile/sales_360.blade.php` into a guided step-by-step decision flow:
  1. **Step 1 (Branch Multi-Select at top)**: Clickable multi-select chips for all branches with
     an "All Branches Active" toggle so users can filter by one or multiple branches immediately.
  2. **Step 2 (View Selection)**: Big interactive selector for initial view: Category-wise vs
     Agent-wise (with secondary options for Product-wise, Party-wise, and Branch Summary).
  3. **Step 3 (Guided Drill-down Action Sheet)**: Clicking any breakdown card opens an action sheet
     prompting for the next dimension to explore (e.g. Category -> Agent / Party / Product;
     Agent -> Category / Product / Party), dynamically filtering and switching views via AJAX.
  4. **Breadcrumb Path & Reset**: Added an interactive breadcrumb trail showing current drilldown
     path with individual `×` removals and a quick reset button.
  5. **Bug Fix**: Fixed route exception by changing broken `route('mobile.sales')` back link to
     `route('mobile.sales-report')`.

- **2026-09-15 — New feature: Mobile 360° Sales Explorer.** Added `mobile.sales-360` (+`.data`
  +`.products` +`.parties` AJAX/typeahead routes), backed by `ReportController::buildSales360Payload()` —
  branch/category/agent/product/**party** breakdowns with every filter combinable simultaneously
  (AND), unlike `salesDrilldown()`'s fixed single-path chain. New view:
  `resources/views/mobile/sales_360.blade.php`. New permission key `mobile_sales_360`
  (`UserController.php`). Entry points: a "360°" button on the existing mobile sales report
  header, and a dashboard tile. Verified against real data: for every dimension, top-8 rows +
  "Others" bucket sum exactly to the grand total; combining two filters (e.g. branch=AKOLA +
  agent=TUSHAR PISE) correctly narrows both the totals and every breakdown. Nothing in
  `salesReport()`/`salesDrilldown()`/`DRILL_LEVELS` was touched. See
  [docs/02-architecture.md](docs/02-architecture.md) for the reusable-helpers list this reused.
  **Added same day**: sale quantity also shown in KG/LTR, not just raw pack/unit count. Busy's
  own Item Master already carries `weight_per_unit` normalized to KG/LTR per pack (verified on
  real data: a "500 ML" pack row has `weight_per_unit=0.5`, a "250 GM" row has `0.25`, etc.), and
  this column is already synced onto every `mssql_sales_records` line — so
  `SUM(tot_qty * weight_per_unit)` is the correct KG/LTR total with no separate product-master
  join needed. Exposed as `totals.total_qty_kg` / each row's `total_qty_kg`, gated by
  `totals.qty_kg_available` (false on the `sales_registers` fallback, which lacks the column).

- **2026-09-15 — "No Agent" in Sales Report drilldown, all branches, since 2026-04-11.**
  Root cause: FY2026-27 data (~21k rows) had been bulk-imported into `mssql_sales_records` via
  the Query Executor's "Current Year Sales (FY 2026-2027) [Exact ERP Match]" saved preset, which
  omits `txn_code`/`act_code`/`agent_code`/`agent_name`. This also jammed the sales-sync agent's
  incremental upserts (HTTP 409, "legacy rows without txn_code") since ~11:04 that morning.
  Fixed by `python invoflow_agent.py bootstrap --yes` from `local_bridge/` — rebuilt all 3
  financial years (203,066 rows) against both cloud and local targets. Verified: NULL counts on
  `txn_code`/`agent_code`/`act_code` dropped from 20,953 to 0; AKOLA's agent breakdown showed
  real names again. Full write-up: [docs/05-sales-sync-bridge.md](docs/05-sales-sync-bridge.md).
  **Standing lesson**: never use Query Executor's manual import to (re)populate a full year of
  `mssql_sales_records` — always use the agent's own `sync`/`bootstrap`.
- **2026-09-15 — `docs/` folder created.** This session also wrote the whole `docs/` folder
  (architecture, database, permissions, deployment, known-issues) from scratch based on a full
  codebase read — see `docs/README.md`. If it's ever out of date, trust the code over the docs and
  fix the docs.

## Environment quirks worth remembering right now

- This machine (XAMPP, `C:\xampp\htdocs\inventorymanager\inventory-laravel`) is *also* the host
  for the `local_bridge/` Python agents that sync the **production** site
  (`https://invoflow.gloswebdev.in`) — not this local copy — with the on-prem Busy ERP. Don't
  confuse "local MySQL" with "the database the bridge agents actually write to" (default target:
  both, but `cloud` is production).
- The sales-sync agent (`invoflow_agent.py`) runs as a long-lived `service` process on this
  machine (confirmed running via `Get-Process python*` during the 2026-09-15 investigation) — if
  it looks "offline", check `local_bridge/logs/agent-YYYY-MM-DD.log` for why it's sleeping
  (usually a failed run pushed `next_run` out to the next scheduled slot), don't assume it
  crashed.
- Query Executor's green "Local Bridge Connected" badge and the sales-sync agent's health are
  **unrelated signals** — see [docs/05-sales-sync-bridge.md](docs/05-sales-sync-bridge.md) before
  drawing conclusions from one about the other.

## How the project owner likes to work (observed, not asserted)

- Communicates in Hinglish; prefers direct, confident answers over hedged ones once something is
  verified — but wants the verification (DB queries, log reads) shown, not just asserted.
- Wants root cause nailed down with real data/logs before accepting a fix, especially before any
  production-impacting action (e.g. asked for a `bootstrap` confirmation before it was run, even
  after already agreeing to a general direction).
- Fine with an agent running read/investigate commands freely; wants an explicit go-ahead before
  something that touches production data (the `bootstrap` run) or uploads/publishes anything.
- Cares about this repo being self-explanatory to *any* agent, not just this one session — hence
  this file and `docs/`.
