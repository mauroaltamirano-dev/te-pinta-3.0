# Recipes and production (AOB-3)

Inventory owns recipes, physical production and historical material cost. This unit does not implement adjustments, order consumption, product costing, broader Management or POS-3.

## Operating a recipe

Only administrators can access `/admin/inventory/recipes` and `/admin/inventory/production`. Waiter and cashier access is unchanged and forbidden here.

1. Configure active stock-tracked input items with `recipe_input_enabled` and an output item with `production_output_enabled`. Classification does not infer capabilities.
2. Create a named recipe with its initial formula. The HTTP operation creates the identity and first sealed version atomically.
3. Declare expected output explicitly; it is never the sum of ingredients. The output is an InventoryItem, including semi-finished items.
4. A new formula creates a new version and retires the previous one. `Actual` and `Retiradas` are filtered in SQL **before** pagination. Retired sealed formulas remain readable and are not hard deleted.
5. Search eligible inputs by name, category or classification. Unit-family compatibility and exact positive quantities are enforced on the backend and in PostgreSQL.

The formula and its relationships are immutable once sealed. PostgreSQL serializes recipe graph changes, prevents cycles and guards referenced item measurement identities even before physical movements exist. Active formulas require their input/output capabilities; deactivation can stop future operation without rewriting formulas or past batches.

Optional grams-per-count reference mass helps compare ingredient mass. Six eggs at 60 g each can have a 360 g reference mass while stock and consumption remain **six units**. Reference mass never infers expected output.

## Current estimate, not historical cost

`BuildRecipeCurrentCost` reuses the foundation's moving-weighted allocator and current physical valuation. The ingredient rows are immutable; current price data is read under sorted shared item locks in a transaction. Known components are counted, unknown cost is `null`, and partial/negative/propagated unreliable valuation is visible rather than converted to zero. A known free receipt remains a known zero cost.

Money is integer Argentine pesos. There is no cents conversion:

- 1 kg onion: 2,150 pesos.
- 1 kg beef: 14,500 pesos.
- Recipe: **16,650 pesos**, expected output **2 kg**, current estimate **8,325 pesos/kg**.

A retired version's estimate also uses **current prices of that old formula**. It is not the historical cost of a recipe or past production. Formatting strips insignificant decimal zeros without changing persisted decimal strings.

## One production batch

A batch consumes the selected current formula once. Inputs are not scaled from the declared actual output. PostgreSQL transactions lock the recipe/version and all item rows in deterministic ID order. The physical movement trigger also locks the parent item, protecting direct SQL writers.

Inputs leave Inventory; actual output enters Inventory. Consumption uses `MovingWeightedAverageCostAllocator`, not a second costing service. Allocation records freeze its exact basis, quantities, integer costs, method and fallback. Database validation checks that recorded basis and fallback against the locked ledger, verifies physical/valuation cardinalities, reconciles warning coverage and enforces total-cost transfer. Batch creation, source transaction, movements, cost layers and allocations commit atomically.

For 16,650 pesos of inputs and 1.85 kg actual output (2 kg expected), the batch keeps **16,650 pesos** total and **9,000 pesos/kg** historical actual unit cost. Updating beef prices tomorrow does not change this batch.

Negative stock remains allowed. The historical latest-positive-replenishment fallback is retained with warnings and unreliable coverage. Missing cost history or incomplete physical valuation does not invent a free input or output: allocations/batch total may be null and no authoritative zero output layer is created. Known input amounts remain in allocations. If earlier unknown consumption leaves a positive monetary basis but insufficient physical stock, the same allocator basis is retained with an explicit `insufficient_stock` warning; the batch/output is not classified as confident. Signed cost-layer warning quantities propagate uncertain production output through subsequent valuation and consumption; exact reversal cancels those warning quantities too.

Idempotency uses a validated canonical lowercase UUID, normalized exact quantity/date and payload HMAC. Equal retries return the original batch; conflicting payloads are rejected. Full PostgreSQL transaction identity (`xid8`) binds original and reversal child creation; no late append to committed production history is allowed.

## Exact reversal

A reversal locks the original batch and affected items, then appends a separate linked transaction/source. Movements, optional layers and allocations are exact signed opposites, including basis/method/fallback and null unknown cost. Current prices are not consulted. Original history is neither edited nor deleted. A unique batch reversal and transaction/layer/allocation links prevent double reversal; a second attempt is rejected rather than silently duplicating effects.

## Migration and checks

`2026_10_07_000000_create_inventory_recipes_and_production.php` incrementally adds the recipe and production aggregate plus the limited signed-production layer/uncertainty extension. Existing AOB-1/AOB-2 migrations are untouched. Purchase positive receipts and exact signed purchase reversals retain their contracts.

Rollback is allowed only with **empty Recipe/Production aggregates**. It never disables history triggers to delete production facts. It restores the AOB-2 cost-layer contract and leaves purchases, units, POS tables and pricing families intact. Verify up → tests → empty-boundary rollback → up in a private migration database; never use development.

Focused tests cover exact money/quantities, eligibility, formula/version history, current/retired pagination, current coverage, actual output/cost transfer, null versus free costs, immutable provenance, exact reversal, backend authorization, private separate-connection contention/retry and real desktop/mobile operation. Aborted or failing harness attempts are not passing evidence. Candidate review and local commit require their own consent; prior AOB exceptions do not transfer.
