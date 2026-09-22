# 01 — Overview

## What InvoFlow is

A manufacturing inventory + costing management system for an agro-chemical manufacturer with
multiple branches (AKOLA, INDORE, PUNE, GHAZIABAD, LUCKNOW, Factory/HO, ...). It covers:

- Product master, recipes (BOMs) and production entries.
- Stock adjustments and a stock ledger.
- Indent (bulk purchase requisition) manager with a process/approve workflow.
- MRP-style production planning.
- A **Costing** module: bulk-recipe BOM costing with per-ingredient technical/purity
  auto-calculation, size-wise packing cost, purchase-register & pricelist ERP sync/push.
- Reports: stock ledger, live stock, purchase, sales (with a drill-down explorer), collection,
  agent/team sales targets, an ad-hoc "Query Executor" against the on-prem MSSQL ERP.
- User management with a granular, page-level permission matrix.
- System administration: DB backup/restore/update, cache clear.

## Stack

- **Backend**: Laravel 12, PHP 8.2, MySQL. `barryvdh/laravel-dompdf` for PDFs,
  `maatwebsite/excel` for Excel import/export.
- **Frontend**: Blade templates, Alpine.js for interactivity, Tailwind CSS, Vite build.
- **No SPA framework, no API-first design** — Blade + Alpine + a handful of JSON endpoints
  (mainly the sales drilldown and the query executor) is the pattern throughout.

## Environments

| | Local dev | Production |
|---|---|---|
| Host | XAMPP on this Windows machine | Hostinger shared hosting |
| App root | `C:\xampp\htdocs\inventorymanager\inventory-laravel` | `https://invoflow.gloswebdev.in` |
| DB | MySQL `inventory_laravel_db` on `127.0.0.1:3306` (XAMPP) | Separate MySQL on Hostinger |
| Deploy | — | Manual file upload, no git remote — see [07-deployment.md](07-deployment.md) |

This same machine also runs the **local Python bridge agents** (`local_bridge/`) that connect a
Busy ERP instance (via MS SQL Server, ODBC) to the *production* site — not to this local XAMPP
copy, by default. See [05-sales-sync-bridge.md](05-sales-sync-bridge.md).

## The two "faces" of the app

Every user has `interface_type` = `desktop` or `mobile` (on the `users` table). The
`interface:{desktop|mobile}` middleware (`App\Http\Middleware\EnforceInterface`) redirects a user
away from the other interface's routes. This means:

- Desktop routes live directly under `routes/web.php`'s main group, views in `resources/views/*`.
- Mobile routes live under `Route::prefix('mobile')`, all handled by one giant
  `MobileController` (~3000 lines), views in `resources/views/mobile/*`.
- **Much of the business logic is duplicated** between a desktop controller and the equivalent
  `MobileController` method, *except* where mobile explicitly delegates back to the desktop
  controller (e.g. `MobileController::salesDrilldown()` just calls
  `ReportController::salesDrilldown()` — always check for this kind of delegation before assuming
  mobile has its own copy of some logic).

## External integrations (the two ERPs)

InvoFlow talks to **two unrelated external systems**, both referred to loosely as "the ERP" in
conversation — keep them straight:

1. **Algebra ERP `PartyMaster` HTTP API** (`erp_api_base_url` setting, default
   `https://logicapi.algebraerp.com/API/SYNWOOD`) — a master-data API returning, per customer
   (`ActCode`), the party's name, **assigned agent/salesman**, town, branch, group. Used by
   Product sync, Collection Report, Party Master report, agent targets. See
   `ReportController::getPartyMasterMap()`.
2. **On-premise Busy ERP via MS SQL Server** — reached only through a Python bridge running on a
   PC that has ODBC access to it (`local_bridge/`). This is where transactional sales data
   (`mssql_sales_records`) comes from, per-voucher, including whatever agent code Busy itself
   recorded on that voucher. See [05-sales-sync-bridge.md](05-sales-sync-bridge.md) — this is the
   single most trap-laden part of the system and deserves its own read before you touch it.

**Important distinction**: "the agent on a sale" can mean two different things depending on
which of the above supplied it — a party's *default* agent (Algebra PartyMaster, stable) vs. the
agent actually *tagged on that specific voucher* in Busy (`mssql_sales_records.agent_name`,
can be blank for all sorts of reasons). Sales Report drilldown uses the second; Collection Report
uses the first. They can legitimately disagree.

## Terminology quick-reference

| Term | Meaning |
|---|---|
| BOM | Bill of Materials — both the manufacturing `Recipe` and the `CostingBom` (bulk costing recipe) are BOM-shaped but are separate models/tables. |
| Technical ingredient | A costing BOM ingredient whose quantity is auto-derived from batch qty × formulation % ÷ purity %, rather than typed in. |
| CF1 | A pack-size conversion factor on a Pricelist/Finished-Good row, used to divide a packing material's rate when it's flagged "Container". |
| Indent | A bulk purchase requisition, distinct from a purchase order in the ERP sense. |
| Agent (in Sales Report context) | A salesman/broker code on a Busy sales voucher (`Sl_Head.agent_code` → `Agents_Brokers`), **not** a software agent. |
