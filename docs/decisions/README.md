# Registros de decisiones de arquitectura

Use este directorio para las decisiones duraderas cuyas compensaciones no deberían tener que reconstruir quienes mantengan el proyecto en el futuro. Los detalles rutinarios de implementación no requieren un ADR.

## Cuándo registrar una decisión

Cree un ADR cuando se modifique un límite importante, una convención del modelo de datos, una política de seguridad, un enfoque de runtime o deployment, o una dependencia significativa.

## Nombres

```text
NNNN-short-decision-title.md
```

Los números son secuenciales y los registros nunca se renumeran. Los registros reemplazados permanecen en Git y enlazan con su reemplazo.

## Plantilla

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

Las decisiones fundacionales actuales se resumen en `docs/ARCHITECTURE.md`. Cree ADR individuales cuando esas decisiones cambien o necesiten una justificación más profunda.

## Registros aceptados

- [0001 — El Inicio administrativo es el pedido rápido](0001-admin-home-is-quick-order.md)
- [0002 — Edición administrativa segura de pedidos](0002-safe-administrative-order-editing.md)
- [0003 — Descuentos y medios de pago de pedidos en el backend](0003-backend-order-discounts-and-payment-methods.md)
- [0004 — Eliminación administrativa recuperable](0004-recoverable-administrative-deletion.md)
- [0005 — Marketing e Instagram son dominios separados](0005-marketing-and-instagram-are-separate-domains.md)
- [0006 — Ledger inmutable para Gestión](0006-immutable-management-ledger.md)
- [0007 — Egresos semánticos separados del ledger de Gestión](0007-semantic-management-outflows.md)
- [0008 — Distribuciones de Gestión versionadas e inmutables](0008-versioned-immutable-management-distributions.md)
- [0009 — Pagos de Orders como orígenes financieros idempotentes](0009-idempotent-order-payments-in-management.md)
- [0010 — Reconciliación financiera derivada y de solo lectura](0010-derived-read-only-management-financial-reconciliation.md)
- [0011 — Comprobantes documentales privados de Management](0011-private-management-document-attachments.md)
