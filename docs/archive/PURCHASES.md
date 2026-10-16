# Operational rich purchases

Rich purchases coordinate a financial fact with optional physical receipts. They do not replace historical simple Management outflows.

## Operation

1. Configure active Inventory items and compatible purchase presentations.
2. Open `/admin/management/purchases/create`, select an active wallet and enter lines.
3. Choose either an input unit or an item-specific presentation. Quantity is an exact decimal string. Price is integer pesos, either a line total or per entered unit/presentation.
4. Confirm: the wallet receives the complete negative total; stockable lines receive exact base-unit stock and known cost layers. Non-stockable lines produce no Inventory transaction. Shipping is separate and never capitalized into item cost.
5. Review immutable detail, its physical-history link and optional private attachments.
6. Reverse through the Purchase detail. Money, quantities and original cost layers are compensated atomically; the original remains intact. Negative stock remains technically allowed.

The list supports date, wallet, supplier/search and confirmed/reversed filters before pagination. The canonical family is `/admin/management/purchases`; no parallel `/admin/purchases` route is introduced. Legacy simple purchases remain available in existing Management history, without invented item lines.

## Contracts and invariants

- Administrator-only routes and policies. Waiter/cashier access is unchanged.
- One request UUID plus normalized payload fingerprint identifies a creation. Matching retries return the same aggregate; conflicting reuse is rejected. The validated UUID is canonicalized to lowercase before every identity use, matching PostgreSQL UUID equality for advisory locks, idempotency lookup and physical source references. Case-variant retries share one identity. An advisory transaction lock serializes the key. Wallets, presentations and items are locked while creating historical snapshots.
- Historical lines preserve item/category names, stockability, entered quantity, conversion factors, presentation contents, normalized base quantity and applied money. Catalog edits never recalculate them.
- PostgreSQL validates exact conversion/price snapshots, line subtotal, one matching ledger entry, physical movement/cost cardinality, source relationships and all reversal links. Deferred checks run at the outer commit, not merely when a nested action returns.
- Late append attempts cannot change a committed purchase's lines, ledger impact or physical receipt. An immutable PostgreSQL-assigned full `xid8` creation identity allows line insertion only during that same top-level transaction, including nested savepoints. This avoids 32-bit `xmin` wraparound and transaction-status retention ambiguity.
- A purchase reversal has exactly one compensating financial entry and, when stock exists, exactly one opposite movement and original-linked opposite cost layer per stockable line. Known free lines retain zero cost; zero is not unknown.
- Generic financial reversal rejects rich purchases. PostgreSQL also refuses an uncoordinated financial or Inventory reversal of a Purchase source.
- Existing attachment MIME/size validation, private storage, authorization and immutable/tombstone retention apply unchanged through the financial transaction's attachment endpoints.

## Migration boundary

`2026_09_03_000000_create_management_purchases` adds three aggregate tables and exact signed `reversal_of_id` cost-layer compensation. It extends the financial type allowlist without changing integrated POS or Management migrations. Foundation quantities, money units and parent-item concurrency protection remain unchanged.

The isolated migration check applies, rolls back only the new migration on an empty Purchase boundary, and reapplies it, retaining POS tables, pricing families and seven Inventory units. Rollback after real purchase/reversal history is not a data-erasure workflow: existing negative layers or `purchase` financial facts may prevent restoration of the old constraints; migrate forward instead. Never use development `migrate:fresh`.

## Verification and remaining reconstruction

Recovery tests cover normalization, shipping, stockable/non-stockable lines, immutable snapshots, idempotency, atomic rollback, coordinated reversal, generic-reversal rejection, direct SQL reconciliation and administrator HTTP access. Independent connections verify creation/reversal lock contention and reject appending lines to committed history. Browser checks exercise create, stock/history detail and reversal on desktop/mobile.

This unit does not implement Recipes, Production, Adjustments, Order consumption or Costing. Existing operating-outflow totals and the Purchase movement filter include rich receipts and their reversals alongside legacy outflows. Expanded periods/metrics/navigation remain in AOB-7; wallet balances derive from the authoritative ledger. Global UX normalization and POS-3 remain excluded.
