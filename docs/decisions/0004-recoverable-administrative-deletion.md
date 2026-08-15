# 0004: Recoverable administrative deletion

Status: accepted
Date: 2026-08-14

## Context

Administrators need to remove test or mistaken records from operational lists, but Orders contain immutable public IDs, snapshots, revisions, and public-checkout idempotency data. Customer records may be linked to real historical orders. Physical cascade deletion would destroy business history and audit evidence.

## Decision

Normal Order and Customer deletion remains Eloquent soft deletion. Administrator-only trash pages list deleted records and restore the original rows. No force-delete route or customer-to-orders deletion cascade is exposed.

Restoring an Order preserves its public ID, snapshots, lines, compositions, toppings, and revisions. Deleting or restoring a Customer never mutates linked Orders. If a deleted customer's normalized phone was reused by another active customer, restore is rejected with an actionable validation error; neither record is silently changed or merged.

Permanent deletion is not a routine product capability. Any future legal/data-retention purge requires a separate design with strong confirmation and audit storage outside the rows being destroyed.

## Consequences

- Accidental deletion is reversible without reconstructing history.
- Operational lists stay clean while reports can deliberately choose whether to include trashed rows.
- Phone reuse after deletion can block restore until the active conflict is resolved explicitly.
- A one-time, backed-up development-data purge does not establish a general force-delete feature.

## Alternatives considered

- **Immediate permanent delete:** rejected because it removes revisions, snapshots, and idempotency protection.
- **Delete a customer and cascade all orders:** rejected because customer lifecycle is not order-history lifecycle.
- **Restore by clearing the phone or merging automatically:** rejected because it silently changes identity data.
