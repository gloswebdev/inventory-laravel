# 06 — Permissions & Access Scoping

## Role gate

`users.role` is `enum('admin', 'user')`. `User::hasPermission()` and `User::hasFeature()` both
short-circuit to `true` for `role === 'admin'` — admins bypass the entire permission matrix below.
A handful of controllers (`SystemController`, `QueryExecutorController`) check
`Auth::user()->role !== 'admin'` directly and `abort(403)` — these pages have **no** granular
permission key, they're admin-only, full stop.

## Page-level permission matrix (`user_permissions`)

One row per `(user_id, page_key)`, with boolean columns: `can_view`, `can_create`, `can_edit`,
`can_delete`, `can_print`, `can_export_excel`, `can_export_pdf`, plus a `features` JSON column for
finer-grained per-page feature flags.

```php
$user->hasPermission('costing_bom', 'edit');   // → looks up page_key='costing_bom', returns can_edit
$user->hasFeature('costing_bom', 'bulk_delete'); // → looks up features['bulk_delete']
```

`hasPermission()`'s `$permissionType` values map 1:1 to the boolean columns: `view`, `create`,
`edit`, `delete`, `print`, `excel`, `pdf`. Anything else returns `false`.

## Costing's sub-module split (the pattern to copy if you add more sub-modules)

Costing doesn't use one `costing` key for everything — it was deliberately split into:

| `page_key` | Controls |
|---|---|
| `costing` | Parent/general costing access; the sidebar dropdown shows if the user has `view` on **any** of the keys below. |
| `costing_bom` | BOM Master (`CostingBomController`, all methods). |
| `costing_pro` | Cost Calculator (`CostingController@pro/@calculate/@show/@export`). |
| `costing_purchase` | Purchase Register (`CostingController@purchaseRegister/@syncPurchaseRegister/@saveSyncSettings`). |
| `costing_pricelist` | Pricelist (`CostingController@pricelist/@syncPricelist/@savePricelistSyncSettings`). |
| `mobile_costing` | Mobile PWA costing interface as a whole. |

If you add a new sub-page anywhere in the app that needs independent access control, follow this
pattern (own `page_key`, own row in `UserController`'s `$modules`/`$moduleFeatures` arrays, own
`@if(hasPermission(...))` around the sidebar link) rather than overloading an existing key.

## Scoping pivots (beyond view/edit/delete)

Some data is additionally scoped per-user via `belongsToMany` pivots, independent of the
page-level permission matrix:

- `branches()` / `getPermittedBranchCodes()` — which branches' data a non-admin user can see.
  Admins implicitly get every branch (`Branch::pluck('code')`).
- `productTypes()` / `getPermittedProductTypeIds()` — same pattern for product types.
- `permittedAttributes()` / `getPermittedRMTypes()` — same pattern, filtered to
  `ProductAttribute::where('type', 'rm_type')`.

Any report or list that should respect branch/product-type scoping needs to explicitly call these
— it is **not** applied automatically by a global query scope.

## Interface gating (desktop vs. mobile)

`users.interface_type` (`enum('desktop','mobile')`) plus the `interface:{desktop|mobile}`
middleware decides which entire route group a user can reach — see
[02-architecture.md](02-architecture.md). This is orthogonal to the permission matrix above: a
user can be `interface_type=mobile` and still have full/no permissions on `mobile_costing`, etc.

## Production & Mobile Production Feature Flags

Both Desktop (`production`) and Mobile (`mobile_production`) support identical granular feature toggles defined in `UserController::$moduleFeatures`:

| Feature Key | Label | Purpose |
|---|---|---|
| `management` | Record New Production Entry | Entry form & modal access to create new batches. |
| `history` | View Production History Table / Cards | Displays batch history logs. |
| `type_filter` | Product Type Filter | Allows filtering products by type in the search drawer / modal. |
| `packaging_toggle` | Packaging Material Issue Toggle (ON/OFF) | Allows toggling packaging issue ON/OFF (Default: ON if permission absent). |
| `formulation_toggle` | Chemical Formulation Issue Toggle (ON/OFF) | Allows toggling chemical BOM issue ON/OFF (Default: OFF if permission absent). |
| `erp_push` | ERP Push / Sync Single Batch | Shows single retry / push to ERP buttons. |
| `erp_bulk_retry` | ERP Bulk Retry Failed Batches | Shows the "Sync to ERP (X)" bulk retry button. |
| `view_details` | View Batch Details Drawer / Modal | Allows clicking batch rows/cards to inspect full BOM and ERP documents. |

## Stock Adjustments & Mobile Adjustments Feature Flags

Both Desktop (`adjustments`) and Mobile (`mobile_adjustments`) support comprehensive granular feature toggles:

| Feature Key | Label | Purpose |
|---|---|---|
| `view` | View Adjustments Screen | (Mobile only) Page access permission. |
| `management` | Record New Stock Adjustment Form | Controls access to the New Adjustment modal / form. |
| `create_receipt` | Allow Stock Receipt (+ Inward / Add Stock) | Controls ability to select and record Stock Receipts (+). |
| `create_issue` | Allow Stock Issue (- Outward / Deduct Stock) | Controls ability to select and record Stock Issues (-). Validates available stock. |
| `history` | View Stock Adjustment History Table / Cards | Displays adjustment history table / cards. |
| `type_filter` | Product Type Filter (FG / RM / PM) | Displays Category / Product Type filter pills and dropdowns. |
| `branch_select` | Branch Selection | Allows selecting / switching branch or site for adjustments. |
| `branch_lock` | Branch Lock | Locks adjustment entry and history strictly to user's assigned branch. |
| `reason_select` | Reason & Remarks Field | Displays preset quick reason chips and custom remark notes. |
| `erp_push` | ERP Push / Retry Single Adjustment | Displays single retry / push button to post vouchers to Logic ERP. |
| `erp_bulk_retry` | ERP Bulk Retry Failed Adjustments | Displays "Sync to ERP (X)" button to bulk retry unpushed adjustments. |
| `delete` | Revert / Delete Adjustment | Allows reverting an adjustment (reversing the stock effect in `products` and `stock_ledger`). |

## Where the matrix is edited

`UserController` — the User Manager's Permission Matrix UI (`resources/views/users/index.blade.php` and `resources/views/mobile/users.blade.php`),
one row of checkboxes per `page_key`, per user. `$modules` and `$moduleFeatures` arrays in
`UserController.php` are the definitive list of what pages exist and what feature-flags each one
supports — check there when adding a new page rather than guessing a `page_key` string.
