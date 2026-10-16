# Finance

`Management` (UI label **Gestión**, `/admin/management`) records money: wallets, an immutable ledger and the semantic facts that explain each movement. `Dashboard` (`/admin/dashboard`) reports operational sales from order snapshots. Neither changes orders or stock.

Code: `app/Modules/Management/`, `app/Modules/Dashboard/`. ADRs: [0006](decisions/0006-immutable-management-ledger.md), [0007](decisions/0007-semantic-management-outflows.md), [0008](decisions/0008-versioned-immutable-management-distributions.md), [0009](decisions/0009-idempotent-order-payments-in-management.md), [0010](decisions/0010-derived-read-only-management-financial-reconciliation.md), [0011](decisions/0011-private-management-document-attachments.md).

## Model

The semantic entity explains **what happened**; the ledger records **how money moved**.

| Table | Role |
| --- | --- |
| `wallets` | Name, sort order, `archived_at`, `is_order_payment_default`. **No balance column.** |
| `financial_transactions` | Immutable header: type, optional description, `effective_date` (business date), creator, optional `reversal_of_id` |
| `ledger_entries` | Signed non-zero integer-peso amount per wallet; unique per (transaction, wallet) |
| `management_outflow_categories` | Seeded catalog per outflow type with stable `(type, code)`; name, active flag, order (no CRUD UI) |
| `management_outflows` | Purchase, expense or personal withdrawal: category, wallet, description, optional counterparty, amount |
| `management_transfers` | Source wallet, destination wallet, amount |
| `management_distribution_rules` / `_rule_versions` / `_rule_allocations` | Logical rule (one active per restaurant), immutable numbered versions, allocations in basis points |
| `management_distributions` / `_distribution_allocations` | Applied distribution with source wallet, version and relational snapshot of each destination |
| `management_order_payments` | One payment per order (`order_id` unique) into the default wallet |
| `purchases`, `purchase_lines`, `purchase_reversals` | Rich purchases coordinating money and stock |
| `management_attachments` | Private document metadata and tombstone, attached to a financial transaction |

Every semantic row links 1:1 to its `financial_transactions` row through a unique foreign key. Deferred triggers check the matching entries at commit; immutability triggers reject `UPDATE`/`DELETE`.

### Transaction types

| Type | Entries |
| --- | --- |
| `opening`, `adjustment_increase`, `manual_income` | One positive entry |
| `adjustment_decrease`, `manual_expense` | One negative entry |
| `management_outflow` | One negative entry matching the outflow |
| `transfer` | `-amount` at source, `+amount` at destination (sums to zero) |
| `distribution` | `-amount` at source, one positive entry per allocation (sums to zero) |
| `order_payment` | One positive entry in the default wallet |
| `purchase` | One negative entry for subtotal + shipping |
| `reversal` | Exact opposite entries of the original |

Users always enter positive amounts; the backend sets the sign. Negative balances are allowed and shown.

## Operations

| Operation | Route | Notes |
| --- | --- | --- |
| Wallets | `POST/PATCH /admin/management/wallets`, `.../archive`, `.../restore` | Archived wallets keep identity and history; ordinary operations reject them; the default payment wallet cannot be archived |
| Manual transaction | `POST /admin/management/transactions` | Opening, adjustments, manual income/expense |
| Outflow | `POST /admin/management/outflows` | Active category of the same type required |
| Transfer | `POST /admin/management/transfers` | Distinct active wallets, locked in ascending ID order; no sufficiency check |
| Distribution rule / version / status | `POST .../distribution-rules`, `POST .../{rule}/versions`, `PATCH .../{rule}/status` | Activating one rule deactivates the previous |
| Manual distribution | `POST /admin/management/distributions` | Source wallet cannot be a destination |
| Retry payment distribution | `POST /admin/management/order-payments/{payment}/distribution` | For payments recorded without an active rule |
| Reverse | `POST /admin/management/transactions/{transaction}/reverse` | Once per original; never a reversal of a reversal |
| Rich purchases | `/admin/management/purchases` (list, create, detail, `POST .../{purchase}/reverse`) | See below |
| Attachments | `GET/POST /admin/management/transactions/{t}/attachments`, `GET/DELETE .../{attachment}` | See below |
| Audit | `GET /admin/management/audit` | Read-only |

Outflow types: `purchase` (Compra), `expense` (Gasto), `personal_withdrawal` (Retiro personal). New purchases in the UI use the rich purchase flow; legacy `purchase` outflows remain readable history.

### Distributions

- Allocations are positive integer basis points per wallet, unique per version, summing exactly 10000 (deferred check). Versions and allocations are immutable; a new composition is a new version.
- Calculation: each part is `floor(amount × bps / 10000)` in canonical order `(sort_order, wallet_id)`; the whole remainder goes to the last allocation; any zero part rejects the operation. React previews the same math; the backend recomputes.
- Archiving a wallet later never breaks reading or reversing a historical distribution.

### Order payments

1. Orders emits `OrderPaymentConfirmed` synchronously when an order becomes `paid` ([ORDERS_AND_PRICING.md](ORDERS_AND_PRICING.md#payments-current-model)).
2. `CreateManagementOrderPayment` writes an `order_payment` transaction with one positive entry in the single active `is_order_payment_default` wallet, dated with the confirmation business date. Without that wallet, the whole `paid` transition fails.
3. `ApplyDistributionToOrderPayment` applies the latest version of the active rule from that same wallet, same amount and date. With no active rule the payment stays **pending distribution** and can be retried; a technical failure after a rule is selected rolls back the payment and the `paid` transition.
4. `order_id` is unique, so repeated events or concurrent requests never create a second payment; a payment has zero or one linked distribution (unique nullable FK).
5. Reversal order: reverse the distribution first, then the payment; the reverse order is refused. Reversing does not change `payment_status` and does not allow a second payment for the same order.

### Rich purchases

- One request UUID (canonicalized to lowercase) plus a normalized payload fingerprint identifies a creation; an advisory lock serializes it; matching retries return the same purchase, conflicting reuse is rejected.
- The selected active wallet receives one negative entry for subtotal + shipping. Stock effects are described in [INVENTORY.md](INVENTORY.md#purchases-stock-side).
- Reversal (`POST .../purchases/{purchase}/reverse`) compensates money and stock atomically; generic financial reversal of a purchase is rejected.
- The list filters by date, wallet, supplier/search and confirmed/reversed before pagination.

### Attachments

- PDF, JPG/JPEG, PNG, WEBP up to 10 MB; the real MIME must match the extension.
- Stored on the private `local` disk at `management/attachments/{year}/{month}/{uuid}`; the original name is metadata only. Rows keep MIME, size and SHA-256 checksum (integrity, not deduplication).
- Uploads are immutable. Deletion requires a 3–240 character reason and creates a one-time tombstone; the row and file are kept and PostgreSQL blocks physical deletion. There is no purge or retention policy yet.
- Attachments never change amounts, entries or balances, and their presence plays no role in the audit.

## Balances and reporting

- Wallet balance = `SUM(ledger_entries.amount)`. Total balance in the financial summary (`Queries/BuildManagementFinancialSummary.php`) sums **all** wallets, archived included, across all effective dates. Period opening = sum before `from`; closing = sum through `to`.
- Reporting uses `financial_transactions.effective_date` and today's business date (`ORDERS_TIMEZONE`).
- Periods: `today`, `7_days`, `30_days`, `this_month`, `previous_month`, `custom`. Summary defaults to the last 30 days, movements to the last 7 days; legacy `week`/`month` map to 7/30 days. Section is selected by URL (`?section=panorama|operations|wallets|movements`).
- Operating result = income − purchases − operating expenses (including manual expenses). Personal withdrawals are reported separately and excluded. Transfers and distributions are internal and globally neutral. Purchases include shipping, shown separately.
- Reconciliation compares the period ledger change with income, outflows and signed openings/adjustments.
- Not financial facts: inventory waste, historical COGS, costing estimates. A legacy `paid` order without a `ManagementOrderPayment` contributes no income.

## Audit and reconciliation

`/admin/management/audit` derives findings at read time; nothing is persisted and nothing is repaired automatically.

| Finding | Severity |
| --- | --- |
| `pending_distribution` | info |
| `reversed_payment`, `reversed_distribution` | warning |
| `missing_active_distribution_rule`, `missing_default_income_wallet` | warning |
| `missing_semantic_source`, `amount_mismatch`, `wallet_mismatch`, `date_mismatch`, `ledger_structure_mismatch` | critical |
| `multiple_active_distribution_rules`, `multiple_default_income_wallets`, `archived_default_income_wallet` | critical (defensive; PostgreSQL normally prevents them) |

Filters: `from`/`to`, `wallet_id`, `severity`, `status` (`ok`, `pending`, `reversed`, `requires_review`) and `type` (operation type, `configuration`, or a finding code). The summary and wallet statuses are computed over the period and wallet before visual filters. **OK** means no warning or critical finding for that wallet in the audited period, not an accounting certification.

## Dashboard KPIs

`/admin/dashboard` reads persisted orders and snapshots only. Period membership uses `orders.delivery_date` in the business time zone, never `created_at`. Periods: `today`, `week` (ISO Monday–Sunday), `month` (calendar), `all` (first to last active delivery date).

| Metric | Includes |
| --- | --- |
| Sales | Sum of `orders.total` with status `waiting`, `prepared`, `delivered` |
| Collected | Same statuses and `payment_status = paid` |
| Orders | Count of `pending_confirmation`, `waiting`, `prepared`, `delivered` |
| Uncollected | Confirmed orders with positive total not paid |
| Today's status counts | Today only, per status |

Cancelled and soft-deleted orders never count. Dashboard sales are order-based, not ledger-based: they can differ from Management income.

- **Urgent today** (always today, whatever the period): `pending_confirmation`, `waiting`, `prepared`, ordered by status priority, delivery period, exact time (nulls last), ID. **Today's orders** are ordered chronologically.
- Product units come from snapshots: direct lines use `units_count`, combined dozens count each composition part once. Raw/cooked splits units; delivery/pickup splits orders. Evolution returns every bucket in calendar order with zeros filled by the backend.
- The Marketing card shows Marketing's own summary ([STOREFRONT_AND_MARKETING.md](STOREFRONT_AND_MARKETING.md#dashboard-summary)).

## Not implemented

Category CRUD, suppliers as a domain, multiple or partial payments per order, automatic reversal on cancel/unpay, structured payment correction after re-pricing, profitability or accounting reports, attachment retention policy.
