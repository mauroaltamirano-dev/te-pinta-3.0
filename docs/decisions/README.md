# Architecture decision records

Record a decision here when it changes a module boundary, a data-model convention, a security policy, the runtime or deployment approach, or a significant dependency, and future maintainers should not have to reconstruct its trade-offs. Routine implementation details do not need an ADR.

Records are numbered sequentially and never renumbered. A superseded record stays in place and links to its replacement. ADRs 0001–0011 were written in Spanish and are kept verbatim; new records are written in English.

## Template

File name: `NNNN-short-decision-title.md`.

```markdown
# NNNN: Decision title

Status: proposed | accepted | superseded
Date: YYYY-MM-DD

## Context

What problem or constraint requires a decision?

## Decision

What will the project do?

## Consequences

What becomes easier, harder, or intentionally deferred?

## Alternatives considered

Which credible alternative was rejected, and why?
```

## Accepted records

| ADR | Decision |
| --- | --- |
| [0001](0001-admin-home-is-quick-order.md) | The admin home (`/admin`) is the quick-order screen |
| [0002](0002-safe-administrative-order-editing.md) | Safe administrative order editing (optimistic versions, closed-order reason, revisions) |
| [0003](0003-backend-order-discounts-and-payment-methods.md) | Order discounts and payment methods are computed and enforced by the backend |
| [0004](0004-recoverable-administrative-deletion.md) | Administrative deletion is recoverable (soft delete, trash, restore) |
| [0005](0005-marketing-and-instagram-are-separate-domains.md) | Marketing and Instagram are separate sibling domains |
| [0006](0006-immutable-management-ledger.md) | Immutable financial ledger; balances are derived |
| [0007](0007-semantic-management-outflows.md) | Semantic outflows (purchase, expense, withdrawal) are separate from the ledger |
| [0008](0008-versioned-immutable-management-distributions.md) | Versioned, immutable distribution rules and applications |
| [0009](0009-idempotent-order-payments-in-management.md) | Order payments become idempotent financial facts in Management |
| [0010](0010-derived-read-only-management-financial-reconciliation.md) | Financial reconciliation is derived at read time, never persisted |
| [0011](0011-private-management-document-attachments.md) | Private, immutable document attachments on financial transactions |
| [0012](0012-tenant-ready-schema.md) | Tenant-ready schema: `restaurant_id` from a session setting; RLS deferred |
| [0013](0013-code-defined-roles-and-permissions.md) | Code-defined roles and permissions on a per-restaurant membership |
