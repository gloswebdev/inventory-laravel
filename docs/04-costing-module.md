# 04 — Costing Module

**Authoritative source for formulas/schema/UI rules: [`../PROJECT_MEMORY.md`](../PROJECT_MEMORY.md)
and [`../CHANGELOG.md`](../CHANGELOG.md) — read those in full before changing anything in
`CostingController`, `CostingBomController`, or `costing/bom/index.blade.php`.** This file is
just an index/summary so you know those documents exist and roughly what's in them; it
deliberately does not restate the formulas to avoid the two drifting out of sync.

## What's covered in `PROJECT_MEMORY.md`

1. **Auto-calculation formulas**:
   - Technical ingredient auto-qty = `Batch Qty × Formulation % ÷ Purity %`, with a
     purchase-register → cached `ProductPrice` → manual-entry fallback chain for purity.
   - Container packing material price division by the Finished Good's `CF1`.
   - Everything rounds to exactly 2 decimals.
   - Step 3 "Next" is locked until remaining batch quantity is exactly 0.
   - Manual raw-material rate entry, with purchase-register rates always overriding manual ones
     on merge (`array_merge($localPrices, $prPrices)`).
   - Transportation cost per ingredient (default ₹5.00), and the per-unit/grand-total costing
     formulas built on top of it.
2. **Permission sub-module keys**: `costing`, `costing_bom`, `costing_pro`, `costing_purchase`,
   `costing_pricelist`, `mobile_costing`, and which controller method each gates — see
   [06-permissions.md](06-permissions.md) for how the permission check itself works.
3. **Schema**: `costing_boms` → `costing_bom_items` (ingredients) and
   `costing_bom_packing_materials` (size-wise packing, linked to `pricelists`, with
   `is_container`).
4. **UI structure**: the multi-step BOM wizard in `costing/bom/index.blade.php`, and the Cost
   Manager dashboard in `costing/index.blade.php`.
5. **Fullscreen persistence** (`layouts/app.blade.php`) — unrelated to costing math but documented
   there because it shipped in the same release; a `localStorage` resume-toast pattern to work
   around browsers requiring a user gesture to re-enter fullscreen after reload.
6. **Composition/product-name normalization** — the regex routine used to match Semi-Finished
   Goods to Finished-Good size configs despite inconsistent spacing/punctuation
   (`1.9 %` vs `1.9%`, `WG.` vs `WG`).

## Pricelist Master & Pricelist Update (`costing/pricelist` & `costing/pricelist-update`)

- **Scope of Products**:
  - Initially, the pricelist modules only queried `where('group5', 'FINISHED GOODS')`.
  - As of 2026-09-22, the query is expanded to:
    ```php
    where(function($q) {
        $q->whereIn('group5', ['FINISHED GOODS', 'FERTILIZER GOODS'])
          ->orWhere('group1', '100% SOLUBLE IN WATER');
    })
    ```
  - This brings in all fertilizer and water-soluble products (which carry `group5 = 'FERTILIZER GOODS'` and `group1 = '100% SOLUBLE IN WATER'`).
  - The **Group 1 (Category)** filter dropdown dynamically includes `"100% SOLUBLE IN WATER"`.
  - Both Desktop (`CostingController`) and Mobile PWA (`MobileController`) share this logic.

## What's *not* in PROJECT_MEMORY.md (check the code)

- `CostingController::syncPurchaseRegister` / `syncPricelist` and their `*Raw()` counterparts
  (called both from the HTTP route and from the scheduler in `bootstrap/app.php`) — the
  purchase-register and pricelist ERP sync/push logic itself.
- `ErpPricelistPushService` / `ErpStockPushService` (`app/Library/`) — pushing InvoFlow data back
  out to the ERP side, separate from pulling data in.

## When you change a formula or a schema field here

Update `PROJECT_MEMORY.md` (formula/schema) and `CHANGELOG.md` (dated entry) in the same change —
that's the existing convention in this repo, not something introduced by this docs folder.
