# 0002: Safe administrative order editing

Status: accepted
Date: 2026-08-13

## Context

Administrators need to correct complete orders without inventing a second form or pricing path. Orders are historical snapshots, closed orders require stronger intent, current catalog rules may differ from the original sale, and concurrent admin surfaces must not overwrite each other silently.

## Decision

`/admin/orders/{public_id}/edit` hydrates the shared admin draft from structured order items, combined-dozen compositions, toppings, and order snapshots. Create and edit share `OrderCheckoutData`, customer resolution, `OrderPricingEngine`, `PersistOrderQuote`, preview, fields, and draft state. `UpdateOrder` remains a distinct transactional use case.

An economic edit reprices the complete order with current rules and atomically replaces snapshots for that order only. A non-economic edit keeps the persisted quote. Historical unavailable selections may remain only when the complete economic selection is unchanged; current-rule repricing requires removing or replacing them.

Every mutation advances `orders.edit_version`. Full editing locks the row and requires the version observed at hydration. Delivered/cancelled orders additionally require a brief reason and a single-use authenticated-session grant bound to the order and version; granting permission does not transition status.

Each successful full edit writes one Orders-specific `order_revisions` record containing identity, admin, timestamp, status, before/after totals, changed field names, economic-change flag, and the closed-edit reason when applicable. It does not store complete phone, address, or note values.

## Consequences

- The backend remains the sole price and sellability authority; React only previews and compares totals.
- Existing creation and Quick Order behavior remain on the same boundaries.
- Economic corrections intentionally adopt current configuration for the whole edited order rather than mixing historical and current rules.
- Closed-order permission cannot be reused after a successful edit or after the order version changes.
- Revision persistence and snapshot replacement succeed or roll back together.
- Historical unavailable selections cannot participate in a newly calculated quote.

## Alternatives considered

- **A separate edit pricing engine or form:** rejected because it would duplicate validation, grouping, and UI behavior.
- **Preserve original prices for unchanged lines during economic edits:** rejected because it mixes rule generations within one commercial closure.
- **Allow repricing unavailable historical products from snapshots:** rejected because snapshots do not establish current sellability or a complete current-rule quote.
- **Last write wins:** rejected because status, payment, and full-edit surfaces can otherwise lose accepted admin changes.
- **Generic application-wide auditing:** rejected because Orders needs a narrow structured revision record and no other module currently demonstrates that abstraction need.
- **Reopen closed orders by changing status:** rejected because permission to correct data is not an operational state transition.
