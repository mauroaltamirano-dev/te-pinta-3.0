# Inventory foundation

Inventory owns physical facts. Management owns money; Products owns the sellable catalog and backend pricing. Configuring Inventory never creates a financial entry or changes an Order.

## Administrative operation

`/admin/inventory` now supports category/item/purchase-presentation creation and editing, derived stock, immutable movement history (20 per page), transaction-specific history links and current valuation. All routes require administrator access. Waiter and cashier permissions do not include these routes.

1. Create an active category to organize items.
2. Define the item's classification, physical measure and explicit capabilities.
3. Define purchase presentations using exact decimal strings and compatible units.
4. Review physical stock and current valuation. Purchases/production/adjustment entry is recovered in the subsequent owning units, not through an unrestricted stock-edit endpoint.

Categories do not infer pricing or capabilities. Classification may be null for pending/imported items; the administrative form requires an explicit classification. Allowed kinds are raw material, semi-finished, finished, packaging, consumable and other. Recipe input, production output and sale consumption are independent flags. Recipe/Production integration is now recovered in AOB-3; Order consumption remains deferred.

## Quantities and money

- Weight uses g as base, volume ml, and count unit. Seeded conversion factors also support kg, L, half-dozen and dozen. System units cannot be mutated.
- Quantities remain NUMERIC(20,6) / decimal strings; conversion factors use NUMERIC(20,9). Inputs incompatible with item precision are rejected rather than silently rounded.
- Measurements become immutable after physical history or dependent presentations exist. Default input units may still change compatibly.
- Optional reference mass is positive grams per count unit only; it does not replace the stock unit.
- Cost totals are integer Argentine pesos. Never divide them by 100. A known free receipt has cost zero; missing cost is null/unknown.
- The UI formats quantities without conversion to JavaScript Number, preserving all significant digits.

## Physical integrity

A transaction atomically creates nonzero signed base-unit movements and optional positive-receipt cost layers. Stock is derived from movements, not an editable cached balance. Negative stock is technically allowed. Cost layers must match their movement, item, transaction and effective date. Historical transactions, movements and layers cannot be updated/deleted. Movement validation locks the parent item row inside PostgreSQL, serializing direct SQL inserts with stockability, unit and precision changes. A deferred PostgreSQL trigger rejects orphan transactions. Provenance and reversal shape/uniqueness are enforced by constraints.

AOB-1 installs positive quantity/nonnegative cost layers. AOB-2 adds only exact original-linked signed compensation for coordinated Purchase reversals. AOB-3 adds signed production consumption and exact linked compensation; adjustment/Order reversal workflows remain deferred. The generic transaction primitive alone is not a business reversal workflow.

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
