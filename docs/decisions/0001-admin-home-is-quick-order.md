# 0001: Admin Home is quick order

Status: accepted
Date: 2026-08-13

## Context

The administrative root currently points to a placeholder dashboard. The primary daily task is creating an order quickly, while metrics are already available in Orders and are less valuable as the initial destination.

## Decision

`/admin` will become a product-first quick-order POS. It will use the existing sidebar, a product-card catalog, a current-order column, and a second-stage right panel for customer and fulfillment data. Dashboard analytics will become a secondary section.

The feature will reuse the existing Orders pricing, preview, validation, creation, and snapshot boundaries. The payment UI in the visual template will not be implemented.

## Consequences

- Administrators reach the highest-frequency task immediately.
- The current admin create form must be decomposed into reusable draft/form content rather than duplicated.
- `/admin/orders/create` remains available as a full-page fallback and direct link.
- The new Home is a substantial UI/navigation slice and must be implemented after the Orders worktree is consolidated.

## Alternatives considered

- **Dashboard as Home:** rejected because it optimizes observation over the primary operational task.
- **Persist an order on every product click:** rejected because incomplete drafts must not become business records.
- **Copy the Figma payment popup:** rejected because Te Pinta does not process online payments and already has a complete customer/fulfillment workflow.
