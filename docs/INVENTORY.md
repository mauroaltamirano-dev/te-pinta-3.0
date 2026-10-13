# Inventory foundation

Inventory owns physical facts. Management owns money; Products owns the sellable catalog and backend pricing. Configuring Inventory never creates a financial entry or changes an Order.

## Administrative operation

`/admin/inventory` now supports category/item/purchase-presentation creation and editing, derived stock, immutable movement history (20 per page), transaction-specific history links and current valuation. All routes require administrator access. Waiter and cashier permissions do not include these routes.

1. Create an active category to organize items.
2. Define the item's classification, physical measure and explicit capabilities.
3. Define purchase presentations using exact decimal strings and compatible units.
4. Review physical stock and current valuation. Purchases, production and semantic adjustments are operated through their owning AOB-2/3/4 workflows, not an unrestricted stock-edit endpoint.

Categories do not infer pricing or capabilities. Classification may be null for pending/imported items; the administrative form requires an explicit classification. Allowed kinds are raw material, semi-finished, finished, packaging, consumable and other. Recipe input, production output and sale consumption are independent flags. Recipe/Production integration is now recovered in AOB-3; Order consumption is recovered in AOB-5.

Category is mandatory (`inventory_items.inventory_category_id NOT NULL`, foreign key to `inventory_categories` with `ON DELETE RESTRICT`); `InventoryCategory` has no soft-delete or global filter that could hide the required relationship. Nullable classification means `kind = NULL`, not `category = NULL`. An unclassified but sale-eligible imported item retains its category and remains configurable. The AOB-5 HTTP/SQL regression verifies this supported shape and rejects a null category without changing the item.

The existing status-log contract is preserved: cancellation logs `[Orders] order.cancelled`; other status updates log `[Orders] order.status_changed`, with the same module, public order ID, actor, previous status and next status context. Physical fulfillment/reversal remains atomic and unchanged by that message selection.

## Quantities and money

- Weight uses g as base, volume ml, and count unit. Seeded conversion factors also support kg, L, half-dozen and dozen. System units cannot be mutated.
- Quantities remain NUMERIC(20,6) / decimal strings; conversion factors use NUMERIC(20,9). Inputs incompatible with item precision are rejected rather than silently rounded.
- Measurements become immutable after physical history or dependent presentations exist. Default input units may still change compatibly.
- Optional reference mass is positive grams per count unit only; it does not replace the stock unit.
- Cost totals are integer Argentine pesos. Never divide them by 100. A known free receipt has cost zero; missing cost is null/unknown.
- The UI formats quantities without conversion to JavaScript Number, preserving all significant digits.

## Physical integrity

A transaction atomically creates nonzero signed base-unit movements and optional positive-receipt cost layers. Stock is derived from movements, not an editable cached balance. Negative stock is technically allowed. Cost layers must match their movement, item, transaction and effective date. Historical transactions, movements and layers cannot be updated/deleted. Movement validation locks the parent item row inside PostgreSQL, serializing direct SQL inserts with stockability, unit and precision changes. A deferred PostgreSQL trigger rejects orphan transactions. Provenance and reversal shape/uniqueness are enforced by constraints.

AOB-1 installs positive quantity/nonnegative cost layers. AOB-2 adds only exact original-linked signed compensation for coordinated Purchase reversals. AOB-3 adds signed production consumption and exact linked compensation; AOB-4 recovers semantic adjustment consumption/reversal; AOB-5 adds exact Order consumption/reversal. The generic transaction primitive alone is not a business reversal workflow.

## Current valuation

Current stock and accumulated cost quantity are compared exactly:

| Coverage | Meaning | Reliable value |
| --- | --- | --- |
| zero | Zero stock or non-stockable item | 0 |
| fully valued | Positive stock equals cost-covered quantity | Signed cost total, including known zero |
| partial | Positive stock and nonzero cost quantity differ | Unknown |
| unknown | Positive stock has no cost-covered quantity | Unknown |
| negative | Physical stock is negative | Unreliable/unknown |

The confident total includes only fully valued items. It is not historical point-in-time valuation, COGS or a wallet balance. History is never recalculated or overwritten from current item configuration.

## Migration and verification boundary

The foundation migration, classification migration and reference-mass migration are independent of later aggregate tables. Recipe identity/capability relationship guards are introduced incrementally by AOB-3 when its tables exist. Consumption guards remain deferred; no future-domain tables are imported prematurely.

Private migration verification applies existing POS migrations first, then all three AOB-1 migrations, rolls back only those three, confirms POS tables/pricing survive, and reapplies them. Never run `migrate:fresh` against development. Backend feature tests cover actions, HTTP/authorization, exact arithmetic, valuation and direct PostgreSQL constraints. Browser coverage exercises real desktop/mobile item and presentation administration.

The concurrency regression uses two independent PostgreSQL sessions and committed fixtures. Both insertion-first and identity-update-first schedules must block the conflicting statement, then reject it against committed history or the new identity. Stockability, base unit/family and quantity precision are covered; bounded `lock_timeout` assertions avoid scheduling sleeps.

## Recipe and Production operation

See [RECIPES_AND_PRODUCTION.md](RECIPES_AND_PRODUCTION.md). Production uncertainty is carried by signed layer warning quantities: even fully cost-covered stock is excluded from confident valuation while it contains positive unreliable cost quantity. Administrator sidebar/Inventory links expose Recipes and Production without changing global navigation or staff permissions.

## Adjustment operation

See [INVENTORY_ADJUSTMENTS.md](INVENTORY_ADJUSTMENTS.md). AOB-4 introduces semantic count/loss/manual adjustments and exact reversals; reserved-source adjustments can consume signed cost layers without opening generic sale consumption. Merma changes physical Inventory only, not financial expenses or COGS. Existing AOB-1/2/3 histories and costing remain authoritative.


## AOB-5 — Order physical fulfillment

Products define what is sold, Inventory components define what is physically consumed, and Orders own the commercial state. No recursive Recipe expansion occurs when delivering a sale: produced filling is consumed directly, while its ingredients were consumed during production.

Configuration lives at `/admin/inventory/product-consumption/{product-id}`, linked from canonical `/admin/settings/products`. Both current and inspected historical Settings navigation use that canonical Products route; the alternative `/admin/products` is not a route in this stack. No duplicate alias or global catalog redesign is introduced. Configuration GET/POST is administrator-only; waiter/cashier access and POS-1 permissions are unchanged.

| State | Meaning | Delivery |
| --- | --- | --- |
| missing_config | No physical definition; not known zero | Atomically blocked with a configuration error |
| no_inventory | Explicit deliberate absence of tracked physical consumption | Known zero semantic snapshot; no stock transaction |
| consume | Sealed current formula with positive exact sale-eligible components | Physical movements and allocated historical COGS |

The historical explicit mode is immutable after identity creation. A consume formula changes only by creating its next version, retiring the previous one and updating the current pointer atomically. No hard delete, destructive historical edit or automatic missing-to-zero conversion exists. Components require active stock-tracked items with `order_consumption_enabled`; classification and pricing family never infer eligibility. Quantity inputs are strings converted to existing base units; configured dozen and base unit snapshots remain distinct. Reference mass never changes count stock.

The first transition to `delivered` emits a synchronous scalar OrderDelivered event inside the Order transaction; failure rolls back status, financial listener effects and physical facts together. Full Order edits and status-only edits share this integration. Commercial quantities use direct line `units_count` or combined composition quantities, not tier presentation counts. The Order lock plus unique consumption/order key serialize duplicate events, retries and reversal. Missing Product identity or an empty commercial order blocks rather than pretending it is free. All admin/public_checkout/pos and delivery/pickup/dine_in contexts follow the same physical contract; consumption does not require a Customer, address or schedule, while existing contextual Order constraints remain intact.

Fulfillment locks Order, then sorted Product identities, definitions/versions, then sorted Inventory items. Identity locks include missing definitions to serialize with configuration. The existing AOB-1 allocator operates once per aggregated item quantity; integer costs are deterministically partitioned across its sold components conserving total cost. Full historical Product name, Inventory item name, configured/base unit codes, formula/component IDs, commercial/base quantities, movement/layer IDs, weighted basis, allocation method/fallback, actor/date and per-product explicit modes persist. Later formula/Product/price changes do not change these facts. Nullable unknown/partial cost is deliberately reconciled with clean AOB-3/4 policy instead of dirty historical zero; unknown movements have no invented cost layer. Negative stock fallback and signed valuation warning provenance remain visible. Coverage is complete, unreliable, unknown or partial; this is historical COGS, not current estimated Product costing or a financial expense.

Economic line/product/quantity/pricing changes are blocked after any fulfillment evidence, including explicit no_inventory. Notes, real customer context and non-economic corrections remain subject to existing Order policy. PostgreSQL additionally locks the Order for child mutations, rejects economic snapshot updates, enforces unique definition versions/components/active version, exact conversions and eligibility, complete semantic/transaction reconciliation, original-source-only signed layers and single exact reversal. Full-XID guards disallow committed history mutation or late child append. Allocation basis is checked when the physical transaction is completed; deferred structural reconciliation never reevaluates historical cost against later replenishments in the same surrounding transaction.

`delivered → cancelled` retains the historical compatibility behavior: exact opposite physical movements and original-linked cost layers/warnings, never current valuation. Duplicate cancellation/reversal is idempotent; redelivery after reversal is rejected and requires a new Order. Explicit no_inventory has a semantic reversal without physical movements. The incremental migration refuses populated rollback, and empty rollback restores the exact AOB-4 layer validator.

### Deferred POS/fulfillment limitation

Cancelling/refunding already served food does **not** necessarily recover physical stock. This reconstruction does not introduce rounds, tickets, table sessions, preparation-time consumption or dine-in special cases. Future POS/fulfillment must distinguish preparation cancellation, financial refund, waste and genuinely recoverable physical return. AOB-6 Product Costing, AOB-7 Management and UX normalization remain outside this candidate. AOB-4 R3-stale-preview-body remains an unrelated documented follow-up.

## Current product costing (AOB-6)

`/admin/inventory/costing` estimates what a sellable product would cost **today**.
It is not historical COGS, accounting profit, cashflow or an Order cost snapshot.
Only administrators may read costing, configure policies or assign/remove them.
The canonical catalog link remains `/admin/settings/products`; no Inventory
Products alias is introduced.

### Sources and calculation

| Component | Authority |
| --- | --- |
| Physical formula | Active, sealed ConsumptionDefinition version; never expand Recipes |
| Direct component value | Current physical balance and current weighted cost layers, including valuation warnings |
| Packaging | Explicit policy presentation, InventoryItem and exact quantity/unit; current valuation only |
| Overhead | Policy basis points, 0–10000 inclusive |
| Selling price | Existing POS-0 `OrderPricingEngine` cooked quote, pickup, no additions/discounts |

Category organizes the catalog; pricing family chooses commercial semantics;
ConsumptionDefinition describes physical inputs; CostingPolicy describes current
estimates. No policy is inferred from category or from the first policy in storage.
Assignment is explicit per Product and may be replaced or removed. An assigned
inactive policy remains visible but cannot supply a total or margin. Policy
configuration is current mutable state, not an immutable historical snapshot.

Empanada estimates unit, half-dozen and dozen with the real independently priced
backend tiers. Unit products show one unit by default, not empanada tiers; an
explicit quantity 1–120 uses actual unit-family backend pricing. Mixed empanada
cart/quote costing is a follow-up: the existing combined pricing engine is neither
copied nor changed. Unavailable products retain physical cost information but have
no current sellable price or margin. Current estimates use cooked pricing, matching
the historical costing contract; future raw/cart/topping scenarios are not guessed.

```text
overhead = round_half_up((direct cost + packaging cost) × basis_points / 10000)
estimated total = direct cost + packaging cost + overhead
estimated margin = selling price − estimated total
margin percentage bp = round_half_up(estimated margin × 10000 / selling price)
```

This preserves the historical overhead **cost base**, not a percentage of sales.
Money outputs are integer pesos. Quantities remain exact decimal strings and use
existing base-unit conversions; reference mass never reinterprets physical stock.
Each presentation scales exact physical requirements before costing. Direct and
packaging totals each sum PostgreSQL NUMERIC ratios and round once, half-up; they
never multiply a pre-rounded unit cost. Individually displayed rounded component
estimates may differ from the rounded aggregate by a peso. Overhead then rounds
once on the displayed direct+packaging totals; margin percentage is unavailable
for a zero selling price. Standalone reads use a repeatable-read read-only snapshot;
callers already inside a transaction own its isolation.

### Coverage and configuration

- `missing_config`: direct cost unavailable, never zero. Reasons link to physical
  consumption configuration and policy assignment.
- `no_inventory`: explicit known direct zero. A known free inventory valuation
  is also zero; unknown valuation is not.
- `consume`: only current active components; multiple inputs propagate coverage.
- Packaging `none`: explicitly known zero for that presentation. A missing policy
  or missing presentation is **unconfigured**, not “no packaging required”.
- Known positive stock with full valuation is reliable. Zero/no current valuation
  is incomplete; negative, partial, warning-bearing valuation or insufficient stock
  is unreliable. Ineligible packaging is incomplete. Partial known subtotals are
  diagnostic lower bounds, not totals used for margin.
- Stock sufficiency uses the combined exact base-unit demand per InventoryItem
  across direct consumption and presentation packaging. Shared insufficient stock
  makes both affected sources unreliable; this read never deducts physical stock.
- Total and exact margin are unavailable unless all required costs are reliable.
  The UI exposes Complete, Incomplete and Unreliable with actionable reasons; it
  never calculates price minus an invented zero or calls the estimate net profit.

### Explicit side-effect limits

**Packaging estimate is not physical packaging consumption.** No StockMovement,
OrderInventoryConsumption, COGS update, InventoryTransaction, FinancialTransaction
or ledger entry is created by costing or overhead. Physical fulfillment packaging
belongs to future fulfillment/POS work. Historical Order COGS, delivery/reversal
facts and ConsumptionDefinition history remain unchanged when current item costs
or policies change. Overhead is not a recorded expense; estimated margin is not
net profit and does not necessarily include taxes, rent, actual payroll, utilities,
aggregate waste or commissions. AOB-7 and global Admin UX Normalization remain
outside AOB-6.
