# P0-A: derived pricing foundation — not activated

**Review this foundation before P0-B.** The shared `OrderPricingEngine`, checkout,
Quick Order, order persistence and costing are unchanged. There is no quick-quote
screen yet. New configuration is editable, but the new combination rule has no
production caller. Do not deploy this as a complete pricing cutover.

## Rules and optimization

The new pure `DerivedTierAllocator` receives effective tariffs for one condition.
It allocates only `empanada` products into `floor(N/12)` dozens, at most one six
when `N % 12 >= 6`, and `N % 6` units. For example, 18 is dozen + six, not three
sixes; 30 is two dozens + six. It does not optimize arbitrary package counts.

- A dozen uses each member's `D/12`, without surcharge.
- A homogeneous six uses its existing `H = D/2 + R_product`.
- A mixed six requires at least two flavors and uses `sum(q*D/12) + R_combined`.
- `combined_half_dozen_surcharge` is stored in Settings, initially 1500. There is
  no monetary default hidden in the allocator.

The search compares homogeneous six candidates with a separate mixed-six dynamic
program. Mixed states retain flavor identity: otherwise a six of one flavor could
incorrectly receive the mixed surcharge. All charges participate in optimization.
Equal costs retain homogeneous-first/product-ID iteration order deterministically.
Dozen blocks are partitioned in sorted product-ID order; this affects composition,
not their weighted price. There is no loss or duplication of physical units.
An independent exhaustive oracle enumerates assignments directly, with independent
homogeneous/mixed cost evaluation. The calculator filters `unit` products before
allocation; they cannot fill packages or receive empanada surcharges/discounts.

## Product surcharge: one stored source, no catalog rewrite

`products.half_dozen_price` remains the one canonical stored H. All mapped existing
readers — provider, product/public resources, Marketing, order options and AOB-6 —
continue reading it. R is derived exactly as `(2H-D)/2`; no surcharge column or
backfill is needed. Existing seeder examples derive R=1000, NOT the 2000 reference
for new configuration. Neither the migration nor a GET reprices products.

Administration supports either direct H (compatible existing API) OR explicit R,
never both in one request. The R editor previews H through an authorized Laravel
endpoint, with abort + request-identity + input-key protection against stale replies.
Saving R explicitly changes H. Decimal `.5` surcharges support legacy odd D while
keeping stored H in integer pesos. An unrepresentable proposed H is rejected, not
rounded. The 2000 new-editor reference is visible, editable and never backfilled.

Changed cooked homogeneous/dozen/mixed configuration must save against normal units;
unchanged legacy tariffs do not prevent a metadata correction. A quote independently
checks every participating tier, so direct/imported or concurrently changed prices
cannot bypass the primitive's commercial invariants. Invalid tiers can be avoided
only if another valid assignment exists within the required block counts; the
calculator never silently falls back to a different package decomposition.

## Savings and exact money

All pricing arithmetic uses integer numerators over 12. Percentage discounts use
scale 1200; shipping is not discountable. `ExactOrderTotals` retains exact fractions
through products, extras, administrative discount and shipping, then HALF-UP rounds
once to the nearest **peso**, not 100 pesos. Adding an integer unit-product price or
shipping charge commutes with this rounding; neither is rounded to hundreds.

The product quote retains each exact line numerator and the exact final adjustment.
Whole-peso display projections use largest remainder, stable line order for ties,
and sum to the projected subtotal; they never feed pricing. Unit-family lines have
zero remainder and retain their exact integer amount. The final order primitive
also exposes the integer reconciliation adjustment for independently projected
subtotal/discount values. Integration of these primitives is P0-B work.

Tier eligibility is strict, in the selected condition:

- dozen: `D < 12U`;
- homogeneous six: `H < 6U`, with nonnegative derived surcharge;
- each mixed-six flavor: `D + 2R_combined < 12U`.

After HALF-UP, the grouped basket must still cost less than its equivalent unit
baseline. A six with only 0.5 peso exact savings could round back to equality;
that quote is rejected explicitly. No cap, replacement price, round-down or silent
configuration correction is introduced. Inputs/aggregates have monetary bounds;
there are no authoritative floats.

## Raw compatibility: diagnose, do not replace

Existing discounts stay authoritative:

```
U_raw = U_cooked - discount_unit
D_raw = D_cooked - discount_dozen
H_raw = H_cooked - discount_half
R_raw = R_cooked + discount_dozen/2 - discount_half
```

The seeder's discounts (200 / 1000 / 2000) make `R_raw = R_cooked` exactly, so the
existing homogeneous benefit is not discounted twice. Other settings need this
explicit equivalence check; a changed effective R is not silently reset.
Mixed-six pricing uses effective raw D plus the configured combined surcharge once.
It does not subtract the half-dozen discount again.

Fixtures, NOT a live-catalog audit:

| Fixture                | Cooked U/H/D     | Effective raw U/H/D | Result with mixed R=1500                                         |
| ---------------------- | ---------------- | ------------------- | ---------------------------------------------------------------- |
| Owner Matambre example | 2200/13000/22000 | 2000/12000/20000    | Raw homogeneous six equals 6U: invalid; raw mixed share is valid |
| Owner economic example | 1800/10500/18000 | 1600/9500/16000     | Both six presentations remain valid                              |
| Existing seed Caprese  | 1600/9000/16000  | 1400/8000/14000     | Raw mixed share is 1416 2/3 > 1400: invalid                      |
| Existing seed Humita   | 1700/9500/17000  | 1500/8500/15000     | Raw mixed share equals 1500: invalid                             |

Before raw activation in P0-B, the owner must choose explicit corrections to the
affected unit/dozen prices, half discount or surcharges, or retain those invalid
presentations as unavailable with clear errors. For seed Caprese, unchanged raw
U/D require a mixed surcharge strictly below 1400; 1500 is not valid. P0-A neither
changes these configurations nor activates new cooked/raw pricing.

## Snapshots and Inventory/COGS

The additive migration reserves v2 exact numerator columns plus line surcharge
snapshots, with default v1/nulls and shape constraints. It changes no existing
Product, order amount or composition. The modern base has no v2 rows/columns;
this is NOT an import of the historical branch's nearest-100 activation migration.
Existing aggregate/composition/discount constraints remain in force; P0-B must
adapt their version-aware reconciliation before enabling a v2 writer.

A new PostgreSQL guard extends AOB-5 protection to **every new order economic
field**, including version and rounding adjustment, after any consumption evidence,
including no-inventory delivery. AOB-5's existing all-write line guards already
protect every new line field and composition. Tests attempt each new field and
require SQLSTATE 55000, not just a later shape-check failure. Rollback refuses if
any versioned history exists. PostgreSQL `migrate:fresh` leaves functions behind,
so the new function uses `CREATE OR REPLACE`, like the existing AOB migrations.

Physical consumption remains derived from real units/compositions. No fulfillment,
COGS, reversal, valuation or packaging rule is changed. Existing costing still
reads canonical H/U/D; there is no new combined-basket margin. Mixed costing and
its packaging estimate are explicitly deferred; no fictitious margin is exposed.

## Verification and handoff

See `odd/tasks/p0-derived-pricing.md` for observed RED/GREEN, commands and results.
All fixtures and migrations run only in private `tepinta-p0a-pg`; no commercial or
shared development database is read, migrated or reset. There are no new production
dependencies. No commit, push, PR, merge or deploy is authorized.

P0-B must wire this one derived primitive into the existing shared engine, assemble
cooked/raw snapshots, validate version-aware PostgreSQL economics and surcharges,
prove preview/persist equality and idempotency/concurrency, adapt economic edits,
and regress Inventory/COGS/current costing before activation. Native review of
P0-A does not approve that integration. P0-C owns `/admin/quick-quote`, basket HTTP
request ordering and mobile quote interaction; the new config-preview test is NOT
proof of a future basket-quote endpoint.

## Worked examples — explicit fixtures only

These are acceptance fixtures, not queried live prices. Matambre U/H/D is
2200/13000/22000; Caprese 1800/10500/18000; Salteña 2100/12000/20000;
Pollo 2000/11500/19000; Humita uses 1800/10500/18000 in the four-flavor fixture.
Combined surcharge is 1500.

| Basket                                      |              Peso total |
| ------------------------------------------- | ----------------------: |
| 6 Matambre                                  |                   13000 |
| 6 Caprese                                   |                   10500 |
| 3 Matambre + 3 Caprese                      |                   11500 |
| 3 Matambre + 2 Salteña + 1 Caprese          | 11833 (exact 142000/12) |
| 2 Matambre + 2 Humita + 2 Pollo + 2 Salteña |                   15267 |
| 12 Matambre                                 |                   22000 |
| 3 Matambre + 3 Salteña + 6 Caprese          |                   19500 |
| 12 Matambre + 6 Caprese                     |                   32500 |
| 8 Matambre + 8 Salteña + 8 Caprese          |                   40000 |
