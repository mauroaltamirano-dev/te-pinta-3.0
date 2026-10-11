# Inventory adjustments (AOB-4)

Administrators operate `/admin/inventory/adjustments` to preview and record physical changes, inspect immutable historical costs and reverse exact original movements. These operations do not create financial expenses, Management entries or sale COGS.

## Quick path

1. Choose an active stock-tracked item and compatible unit.
2. Select the semantic type. **Count correction** receives an observed non-negative physical count; losses receive a positive quantity; manual adjustment receives a signed quantity.
3. Preview stock, resulting stock, base-unit delta, estimated economic impact and valuation coverage/warnings.
4. Confirm. The backend locks the InventoryItem and recalculates stock/delta/cost in a transaction. A preview is never persistence authority.
5. Open the history/detail to inspect actor, effective/recording timestamps, quantity, original transaction, historical basis and coverage. Reverse with an explicit reason if needed.

| Type                | Meaning                                                                                                                                                             |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `count_correction`  | Physical count minus currently locked system stock. Two concurrent counts of 8 against 10 cannot each apply -2. The second sees 8 and rejects a no-change movement. |
| `waste`             | Merma; physical loss, not automatically a financial expense.                                                                                                        |
| `damage`            | Rotura / daño; physical loss.                                                                                                                                       |
| `expiry`            | Vencimiento; physical loss.                                                                                                                                         |
| `manual_adjustment` | Explicit signed stock change.                                                                                                                                       |

An unchanged count has a valid no-change preview but cannot create a zero movement. Count input is converted exactly into the item's existing base unit; base quantity and precision rules are preserved. UI formatting removes insignificant decimal zeros without changing decimal persistence.

## Cost and history

Negative deltas use the single AOB-1 `MovingWeightedAverageCostAllocator`. Snapshot fields store signed integer-peso cost, basis quantity/total, method and fallback. Unknown history or partial physical cost coverage stays null, not zero. Negative-stock latest-positive fallback and inherited AOB-3 uncertainty remain visible as unreliable; insufficient physical stock cannot silently become confident just because monetary basis is positive.

Positive deltas accept an optional explicit non-negative **total** in integer pesos (zero is a known free input). Without it, only positive, fully covered, non-negative-cost, reliable current stock supplies the weighted estimate. Otherwise cost is unknown/null with no invented cost layer. Signed layer warning provenance is shared with AOB-3 valuation; no parallel costing subsystem is introduced.

A historical 2 kg loss with -8,000 pesos never changes when tomorrow's price becomes 6,000 pesos/kg. A reversal creates a separate linked adjustment/transaction and the exact opposite movement and optional layer, preserving null cost, original layer linkage and warning provenance. It never consults current valuation. Reversal-of-reversal and duplicate reversal are rejected. Count reversal compensates the original delta; it does not perform a new physical count.

Canonical lowercase UUID plus normalized request HMAC protects creation retries and rejects conflicting reuse. Preview returns transient data only. The client invalidates previews on input changes and ignores responses for earlier revisions; confirmation still recomputes server-side.

## PostgreSQL boundary

The incremental `2026_10_11_000000_create_inventory_adjustments.php` adds the adjustment aggregate without modifying integrated migrations. INSERT locks the parent item before calculating/verifying stock snapshots, conversions and monetary basis/fallback. Deferred constraints require exact transaction metadata, single movement, optional layer, matching item and single historical reversal. Full transaction XID binds completion/child appends; committed history cannot be edited, deleted or appended.

The signed cost-layer gate is extended only for original semantic adjustments with their reserved `inventory-adjustment:<id>` source. AOB-1 primitive fixtures/legacy adjustments remain distinct; generic consumption or sale workflows are not opened. The rest of the AOB-3 validator is unchanged. Rollback requires an empty semantic adjustment aggregate, restores the exact AOB-3 validator and never deletes history or disables guards to force rollback. A private up → tests → empty rollback → up cycle must preserve purchases, recipes/production, POS tables and seven system units.

## Verification and scope

Strict TDD covers counts/no-change, losses/manual signs, explicit/reliable/unknown cost, stale preview, precision, cash isolation, historical reversal, HTTP administrator/staff authorization and PostgreSQL rejection. Independent PHP processes observe real PostgreSQL Lock waits before release, then verify resumed identical counts, canonical UUID retries and competing reversals. Browser coverage exercises desktop and mobile count/loss preview, stale-preview invalidation, creation, reversal and stock/history links.

No AOB-5 Order Consumption, AOB-6 Product Costing, AOB-7 Management, global UX normalization or POS-3 is included. Functional checks and primary self-review are not native or independent approval. Review and commit require their own candidate-scoped authorization.
