# Inventory

`Inventory` owns physical facts: what is in stock, what it cost, and how it moved. `Management` owns money ([FINANCE.md](FINANCE.md)); `Products` owns the sellable catalog and prices. Inventory never creates ledger entries; waste is not an expense and historical COGS is not cashflow.

Code: `app/Modules/Inventory/`. All routes are under `/admin/inventory` and require the `administrator` gate; `use-pos` and `operate-cashier` grant nothing here.

| Surface | Route |
| --- | --- |
| Items, categories, presentations, stock, history, valuation | `/admin/inventory` |
| Recipes | `/admin/inventory/recipes` |
| Production | `/admin/inventory/production` |
| Adjustments | `/admin/inventory/adjustments` |
| Product consumption definition | `/admin/inventory/product-consumption/{product}` (linked from `/admin/settings/products`) |
| Costing policies / current costing | `/admin/inventory/costing-policies`, `/admin/inventory/costing` |
| Rich purchases (money + stock) | `/admin/management/purchases` (owned by Management) |

## Items, units and categories

- **Categories** (`inventory_categories`) organize items; an item's category is mandatory (`ON DELETE RESTRICT`). Categories infer nothing.
- **Items** (`inventory_items`) have an optional classification `kind` (`raw_material`, `semi_finished`, `finished`, `packaging`, `consumable`, `other`; null for unclassified imports, required by the admin form) and independent capability flags: `tracks_stock`, `recipe_input_enabled`, `production_output_enabled`, `order_consumption_enabled`. Classification never implies a capability.
- **Units** (`inventory_units`) are seven immutable system units: `g`, `kg` (weight, base `g`); `ml`, `L` (volume, base `ml`); `unit`, `half_dozen`, `dozen` (count, base `unit`). Stock is always kept in the item's base unit.
- An item's measurement (unit family, base unit, precision) becomes immutable once physical history or dependent presentations exist. Inputs exceeding the item's precision are rejected, never rounded.
- Optional `reference_mass_grams_per_unit` on count items helps compare mass (six eggs at 60 g = 360 g) but never changes count stock.
- **Purchase presentations** (`inventory_purchase_presentations`) describe how an item is bought (for example a 25 kg bag) with exact contents in a compatible unit.

## Stock movements and cost layers

| Table | Role |
| --- | --- |
| `inventory_transactions` | Header: type, source type/ID, effective date, actor, optional `reversal_of_id` |
| `inventory_stock_movements` | Signed, non-zero base-unit quantity per item |
| `inventory_cost_layers` | Signed integer-peso cost linked to a movement; optional `valuation_warning` |

Transaction types: `purchase_receipt`, `adjustment`, `production`, `production_consumption`, `sale_consumption`, `reversal`. Source types: `purchase`, `production`, `order`, `adjustment`, `reversal`.

- Stock is derived as `SUM(movements.quantity)`; there is no editable balance. Negative stock is technically allowed and is shown as unreliable valuation.
- History is immutable (triggers reject `UPDATE`/`DELETE`); a deferred trigger rejects transactions without movements; inserting a movement locks the parent item, serializing direct SQL with stockability and unit changes.
- Cost is integer pesos. A known free receipt has cost `0`; unknown cost is `null` and never becomes zero.

### Valuation

All consumption uses one allocator, `Support/MovingWeightedAverageCostAllocator.php` (perpetual moving weighted average over cost layers).

| Coverage | Condition | Value |
| --- | --- | --- |
| zero | Zero stock or non-stock item | 0 |
| fully valued | Positive stock equals cost-covered quantity | Signed cost total (known zero included) |
| partial | Positive stock differs from cost-covered quantity | Unknown |
| unknown | Positive stock with no cost-covered quantity | Unknown |
| negative | Negative stock | Unreliable |

Layers carrying warning quantities (from uncertain production or fallbacks) keep stock out of the confident total. Fallback codes recorded on consumption: `no_cost_history`, `partial_valuation`, `insufficient_stock`, `unreliable_input`. Current valuation (`Queries/InventoryValuationReadModel.php`) is not a point-in-time historical valuation.

## Recipes and production

- A **recipe** has a name and sealed **versions**. Creating a recipe creates its first version atomically; a new formula creates a new version and retires the previous one. Versions are never edited or hard-deleted; retired versions stay readable.
- Inputs must be active stock items with `recipe_input_enabled`; the output must have `production_output_enabled`. Expected output is declared explicitly, never summed from ingredients. PostgreSQL prevents recipe cycles and validates unit-family compatibility.
- `Queries/BuildRecipeCurrentCost.php` estimates a version's cost with **current** valuation. Example: 1 kg onion 2,150 + 1 kg beef 14,500 = 16,650 pesos for 2 kg expected → 8,325 pesos/kg. It is not the historical cost of past production.
- A **production batch** consumes the selected current version once (inputs are not scaled to the actual output) and records the actual output. Input cost moves to the output: 16,650 pesos for 1.85 kg actual → 9,000 pesos/kg historical unit cost. Allocation records freeze basis, quantities, cost, method and fallback; later price changes never alter them.
- Batch creation is idempotent by lowercase UUID plus a payload HMAC; equal retries return the original, conflicting reuse is rejected.
- **Reversal** appends a linked transaction with exactly opposite movements, layers and allocations (including null costs) without consulting current prices; a second reversal is rejected.

## Adjustments

`/admin/inventory/adjustments` previews and records physical changes, then allows exact reversal.

| Type | Input | Effect |
| --- | --- | --- |
| `count_correction` | Observed non-negative count | Delta = count − locked system stock (a second concurrent count of the same value becomes a rejected no-change) |
| `waste`, `damage`, `expiry` | Positive quantity | Physical loss (not a financial expense) |
| `manual_adjustment` | Signed quantity | Explicit stock change |

- Preview is transient; confirmation locks the item and recomputes stock, delta and cost on the server.
- Negative deltas are costed by the shared allocator. Positive deltas accept an optional explicit total cost; otherwise only reliable, fully covered current stock supplies an estimate, else cost is unknown.
- Creation is idempotent (lowercase UUID + request HMAC). Reversal creates a separate linked adjustment with the exact opposite movement and layer; reversal of a reversal and duplicate reversals are rejected.
- Default form dates use the business date (`ORDERS_TIMEZONE`), not UTC.

## Purchases (stock side)

Rich purchases are created in Management (`/admin/management/purchases/create`) and coordinate one ledger entry with optional physical receipts.

- Each line uses an input unit or an item presentation; quantity is an exact decimal string; price is integer pesos per line total or per entered unit.
- Stock-tracked lines create a `purchase_receipt` transaction with exact base-unit movements and known cost layers; non-stock lines create no inventory transaction. Shipping is recorded separately and never capitalized into item cost.
- Lines snapshot item and category names, stockability, entered quantity, conversion factors, presentation contents, base quantity and money; catalog edits never change them.
- A purchase reversal compensates money and, per stock line, exactly one opposite movement and original-linked layer. Generic inventory or financial reversal of a purchase is refused by PostgreSQL.

Money side: [FINANCE.md](FINANCE.md#rich-purchases).

## Order consumption

Each product has a consumption definition (`inventory_product_consumption_definitions`, versions and components):

| State | Meaning | Effect on delivery |
| --- | --- | --- |
| missing (no definition) | Not configured; not a known zero | **Delivery is blocked** with a configuration error |
| `no_inventory` | Explicitly no tracked physical consumption | Known-zero semantic record, no stock movement |
| `consume` | Sealed current formula of positive components | Stock movements and historical COGS |

When it happens: on the first transition of an order to `delivered`, `OrderDelivered` runs `Actions/ConsumeDeliveredOrder.php` synchronously inside the order transaction; any failure rolls back the status change and the payment listener effects.

- Commercial units come from persisted `order_items.units_count` or combined-dozen composition quantities, never from pricing tiers. Recipes are not expanded: a produced filling is consumed directly because its ingredients were consumed at production.
- Components must be active stock items with `order_consumption_enabled`. A `consume` formula changes only by creating a new version; the mode chosen at creation is immutable.
- Locks: order, then sorted products and definitions, then sorted items. Cost is allocated per item with the shared allocator and split across components conserving the total. Coverage is `complete`, `unreliable`, `unknown` or `partial`; snapshots keep product, item and unit names, formula IDs, quantities, basis and fallback.
- After any consumption evidence (including `no_inventory`), economic edits of the order are rejected by the application and by PostgreSQL.
- `delivered → cancelled` emits `OrderDeliveryCancelled`, which writes exact opposite movements and original-linked layers (never current valuation). Duplicate cancellation is idempotent; redelivery after reversal is rejected.
- Known limitation: cancelling an order of food already served returns stock it may not physically recover. Preparation cancellation, refund, waste and real returns are not distinguished yet. A planned rework makes consumption non-blocking (roadmap O-07).

## Current product costing

`/admin/inventory/costing` estimates what a product would cost **today**. It is not historical COGS, profit, cashflow or an order snapshot, and it writes nothing (no movements, consumption, transactions or ledger entries).

| Component | Source |
| --- | --- |
| Physical formula | Active `consume` version of the product's consumption definition |
| Direct cost | Current balance and weighted cost layers of each component |
| Packaging | Costing policy presentation (`none` or explicit item components) per commercial quantity 1–120, valued at current cost |
| Overhead | Policy basis points, 0–10000 |
| Selling price | Live `OrderPricingEngine` cooked quote, pickup, no add-ons or discount |

```text
overhead            = round_half_up((direct + packaging) × basis_points / 10000)
estimated total     = direct + packaging + overhead
estimated margin    = selling price − estimated total
margin percentage   = round_half_up(margin × 10000 / selling price)   (basis points; unavailable at price 0)
```

- Policies (`inventory_costing_policies`, presentations, packaging components) are explicit and assigned per product (`inventory_product_costing_policies`); none is inferred from category. An inactive assigned policy shows but yields no total.
- Empanada products estimate unit, half-dozen (six units at the stored unit price) and dozen; the selling price is the quoted line before the order-level ARS 100 rounding. Unit products show one unit (or a requested quantity 1–120). Mixed-basket costing is not implemented.
- Missing configuration, missing packaging presentation, unknown, partial, negative or warning-bearing valuation, or insufficient combined stock make the estimate Incomplete or Unreliable; total and margin are then unavailable. The UI never treats missing as zero.
