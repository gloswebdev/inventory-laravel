# 📋 InvoFlow — Session Handoff Document
**Date:** 2026-09-22  
**Session Focus:** Stock Adjustment Revamp (Receipt/Issue, All Product Types, Pack Size Visibility & Branch Lock)  
**Repository Working Dir:** [`c:/xampp/htdocs/inventorymanager`](file:///c:/xampp/htdocs/inventorymanager)  
**Active Application Root:** [`inventory-laravel/`](file:///c:/xampp/htdocs/inventorymanager/inventory-laravel)  
**Branch:** `main` (Head commit: `3f5de920`)

---

## 1. Executive Summary

In this session, we revamped the **Stock Adjustment Module** across Desktop (`/adjustments`) and Mobile (`/mobile/adjustments`), delivering:
1. **Dual Action Mode**: Support for both **Stock Receipt (+ Inward / Add Stock)** and **Stock Issue (- Outward / Deduct Stock)** for **all product types** (Finished Goods, Raw Materials, Packing Materials, Semi-Finished Goods, General items).
2. **Item Packing Size Visibility**: Fixed the issue where different pack sizes of the same product had identical labels by showing `pack_name` / `weight_unit` in product select dropdowns, search filters, summary cards, and history rows/cards.
3. **Branch Lock (`branch_lock`)**: Added a granular permission toggle in User Manager allowing administrators to freeze/lock a user strictly to their assigned branch for stock adjustments.
4. **Logic ERP Push Integration**: Added real-time stock sync via `ErpStockPushService.php` (`SaveReceiptStock` / `SaveIssueStock`), with single and bulk retry engines.
5. **Database & Deployment Documentation**: Created migration `2026_09_22_100000_add_revamp_fields_to_adjustments_table.php`, executed on local DB, and documented required `ALTER TABLE adjustments` query for Hostinger live database in `docs/07-deployment.md`.

---

## 2. Key Accomplishments & Deliverables

### A. Stock Adjustment Module Revamp & Branch Lock
*Files modified:*
- [`inventory-laravel/app/Http/Controllers/UserController.php`](file:///c:/xampp/htdocs/inventorymanager/inventory-laravel/app/Http/Controllers/UserController.php)
- [`inventory-laravel/app/Http/Controllers/AdjustmentController.php`](file:///c:/xampp/htdocs/inventorymanager/inventory-laravel/app/Http/Controllers/AdjustmentController.php)
- [`inventory-laravel/app/Http/Controllers/MobileController.php`](file:///c:/xampp/htdocs/inventorymanager/inventory-laravel/app/Http/Controllers/MobileController.php)
- [`inventory-laravel/resources/views/adjustments/index.blade.php`](file:///c:/xampp/htdocs/inventorymanager/inventory-laravel/resources/views/adjustments/index.blade.php)
- [`inventory-laravel/resources/views/mobile/adjustments.blade.php`](file:///c:/xampp/htdocs/inventorymanager/inventory-laravel/resources/views/mobile/adjustments.blade.php)
- [`inventory-laravel/docs/06-permissions.md`](file:///c:/xampp/htdocs/inventorymanager/inventory-laravel/docs/06-permissions.md)
- [`inventory-laravel/docs/07-deployment.md`](file:///c:/xampp/htdocs/inventorymanager/inventory-laravel/docs/07-deployment.md)
- [`inventory-laravel/docs/02-architecture.md`](file:///c:/xampp/htdocs/inventorymanager/inventory-laravel/docs/02-architecture.md)
- [`inventory-laravel/MEMORY.md`](file:///c:/xampp/htdocs/inventorymanager/inventory-laravel/MEMORY.md)
- [`CHANGELOG.md`](file:///c:/xampp/htdocs/inventorymanager/CHANGELOG.md)

---

### B. Mobile 360° Sales Explorer Revamp (Prior Session)
*File modified:* [`inventory-laravel/resources/views/mobile/sales_360.blade.php`](file:///c:/xampp/htdocs/inventorymanager/inventory-laravel/resources/views/mobile/sales_360.blade.php)

1. **Step 1: Branch Multi-Select Badges & Toggle**
   - Clickable multi-select chips at the top for all available branches (AKOLA, INDORE, PUNE, GHAZIABAD, etc.).
   - Added an **"All Branches Active"** toggle button allowing one-tap switching between viewing all branches or scoping down to specific subsets.
   - Live visual badging indicating selected vs unselected state.

2. **Step 2: View Mode Decision (Primary & Secondary Dimensions)**
   - Clear visual selection cards for the primary exploration paths:
     - **Category-wise** (Explore by product categories / segments)
     - **Agent-wise** (Explore by sales representatives / field agents)
   - Secondary tabs for **Product-wise**, **Party-wise**, and **Branch Summary** breakdowns.

3. **Step 3: Context-Aware Drilldown Action Sheet (Bottom Drawer)**
   - Tapping any row or breakdown card automatically triggers a mobile action sheet.
   - The drawer suggests context-aware next dimensions based on the current view:
     - From **Category**: drill down into *Agent-wise*, *Party-wise*, or *Product-wise*.
     - From **Agent**: drill down into *Category-wise*, *Product-wise*, or *Party-wise*.
     - From **Product**: drill down into *Party-wise* or *Agent-wise*.
     - From **Party**: drill down into *Product-wise* or *Category-wise*.
   - Selecting a drilldown option automatically locks the selected item as an active filter (e.g. `category=INSECTICIDE`) and re-queries the server via Alpine.js AJAX (`fetchData()`).

4. **Step 4: Interactive Breadcrumb Trail & Quick Reset**
   - Renders a live visual breadcrumb path (e.g. `Path: 🏢 Branches ➔ 🧪 INSECTICIDE ➔ 👤 TUSHAR PISE`).
   - Every breadcrumb badge includes a direct remove button (`×`) to pop that specific filter layer.
   - Added a **"Reset Trail"** button to instantly clear all drilldown filters and restore the top-level view.

5. **Weight & Metric Accuracy (KG/LTR vs Packs)**
   - Retained and verified the Busy Item Master `weight_per_unit` calculation: automatically shows `Total Qty (KG/LTR)` when available, falling back to pack count only when missing.

6. **Bug Fix: Route Back Link**
   - Fixed the back button navigation error: changed `route('mobile.sales')` (which threw a `RouteNotDefinedException`) to the valid route `route('mobile.sales-report')`.

---

## 3. Architecture & Code Map for Mobile 360°

| Component | Path | Responsibility |
|---|---|---|
| **View (UI / Alpine.js)** | [`inventory-laravel/resources/views/mobile/sales_360.blade.php`](file:///c:/xampp/htdocs/inventorymanager/inventory-laravel/resources/views/mobile/sales_360.blade.php) | Step 1/2/3/4 guided layout, Alpine.js state (`sales360Report()`), action sheet, breadcrumbs, AJAX reloads |
| **Data Aggregator** | [`inventory-laravel/app/Http/Controllers/ReportController.php`](file:///c:/xampp/htdocs/inventorymanager/inventory-laravel/app/Http/Controllers/ReportController.php) | `buildSales360Payload()` executes simultaneous multi-dimensional aggregation (Branch, Category, Agent, Product, Party) with AND filtering |
| **JSON Endpoints** | [`inventory-laravel/routes/web.php`](file:///c:/xampp/htdocs/inventorymanager/inventory-laravel/routes/web.php) | `mobile.sales-360` (view), `mobile.sales-360.data` (AJAX stats), `mobile.sales-360.products`, `mobile.sales-360.parties` (typeahead) |
| **Permissions** | [`inventory-laravel/app/Http/Controllers/UserController.php`](file:///c:/xampp/htdocs/inventorymanager/inventory-laravel/app/Http/Controllers/UserController.php) | Permission key `mobile_sales_360` checked before rendering view and endpoints |

---

## 4. Documentation & Memory Synchronization

All project status and knowledge files have been updated to reflect the latest changes:

- [`TASKS.md`](file:///c:/xampp/htdocs/inventorymanager/TASKS.md): Checked off all items under "Mobile 360° Sales Explorer (Guided Multi-Level Drilldown)".
- [`CHANGELOG.md`](file:///c:/xampp/htdocs/inventorymanager/CHANGELOG.md): Logged version **[1.6.0] - 2026-09-16** detailing the guided drilldown, branch bar, action sheet, and route fix.
- [`inventory-laravel/MEMORY.md`](file:///c:/xampp/htdocs/inventorymanager/inventory-laravel/MEMORY.md): Updated the "Recent incidents / fixes" section with today's 2026-09-16 entry.
- [`inventory-laravel/docs/`](file:///c:/xampp/htdocs/inventorymanager/inventory-laravel/docs/): Complete 8-document reference covering overview, architecture, database, costing, sales sync bridge, permissions, deployment, and known issues.

---

## 5. Current Environment & Git Status

### A. Git Working Tree (in `inventory-laravel/`)
- **Current Branch:** `main`
- **Last Commit:** `3f5de920` (*Fix: Align sales drilldown series exclusion filter with main sales report summary to match outer card values*)
- **Working Tree State:** ~32 modified or untracked files exist locally.
  - **Important:** As documented in [`docs/07-deployment.md`](file:///c:/xampp/htdocs/inventorymanager/inventory-laravel/docs/07-deployment.md) and [`inventory-laravel/MEMORY.md`](file:///c:/xampp/htdocs/inventorymanager/inventory-laravel/MEMORY.md), production is hosted on Hostinger (`https://invoflow.gloswebdev.in`) and deployment is done via manual file/zip upload.
  - **Note:** Always ask the project owner before assuming whether local uncommitted files are already deployed or pending.

### B. Background Services Running on this Host
1. **Sales Sync Agent (`invoflow_agent.py`):**
   - Runs as a Windows background service from [`inventory-laravel/local_bridge/`](file:///c:/xampp/htdocs/inventorymanager/inventory-laravel/local_bridge/).
   - Syncs on-prem Busy ERP MS SQL Server data directly to production cloud (`https://invoflow.gloswebdev.in`).
   - Check status via `local_bridge/logs/agent-YYYY-MM-DD.log`.
2. **Query Executor Bridge (`invoflow_bridge.py`):**
   - Flask server listening on `127.0.0.1:5000` for ad-hoc queries from the desktop Query Executor UI.

---

## 6. Critical Standing Rules & Gotchas for Next Developer

1. **Never use Query Executor manual presets to import whole fiscal years** into `mssql_sales_records` (this breaks agent sync and omits `txn_code`/`agent_code`). Always use `python invoflow_agent.py sync` or `bootstrap`.
2. **Local MySQL vs Cloud:** Local MySQL is `inventory_laravel_db` on `127.0.0.1:3306`. Production is on Hostinger. The local bridge agent writes to the cloud target by default.
3. **Desktop vs Mobile Controllers:** Mobile views live under `resources/views/mobile/*` and routes under `/mobile/*`. Where possible, mobile methods reuse or delegate to desktop controllers (e.g. `ReportController`). Keep common logic unified.
4. **Costing Formulas:** Any changes to costing or BOM math must be logged in [`PROJECT_MEMORY.md`](file:///c:/xampp/htdocs/inventorymanager/PROJECT_MEMORY.md) and [`CHANGELOG.md`](file:///c:/xampp/htdocs/inventorymanager/CHANGELOG.md).

---

## 7. Next Up / Outstanding Tasks

- [ ] **Cross-Device Testing:** Perform field testing of the Mobile 360° guided action sheet across various smartphone screen resolutions.
- [ ] **Costing Export:** Implement custom Excel/PDF export of size-wise packed cost breakdowns (Item in `TASKS.md`).
- [ ] **Deployment Alignment:** Clarify with the project owner which uncommitted local files should be packaged and deployed to production.
