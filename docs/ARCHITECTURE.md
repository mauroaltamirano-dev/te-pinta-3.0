# Arquitectura

Te Pinta 3.0 es un monolito modular en Laravel con un frontend Inertia/React. PostgreSQL garantiza la integridad transaccional; Laravel controla la autorización, la validación, los precios y los cambios de estado; React compone el flujo de trabajo y muestra los resultados del backend.

## Flujos de solicitudes

```text
Administrative browser
  -> auth + verified + admin + admin.request.log
  -> module controller / Form Request / policy
  -> action or pricing service
  -> PostgreSQL transaction
  -> Inertia page or JSON preview

Public checkout
  -> public slug input + Laravel validation + rate limit
  -> internal slug resolution
  -> shared pricing engine
  -> atomic customer match/create + order snapshots
  -> backend summary + WhatsApp link

Confirmed administrative payment
  -> Orders persists the operational transition to paid
  -> synchronous OrderPaymentConfirmed application event
  -> Management records one idempotent ManagementOrderPayment
  -> FinancialTransaction(order_payment) + one positive LedgerEntry
```

El pedido público se confirma antes de generar la URL de WhatsApp. Abrir WhatsApp es una transferencia al cliente y no demuestra que este haya enviado el mensaje.

## Límites del backend

```text
app/
  Core/       stable application-wide contracts and value data
  Shared/     narrowly named reusable infrastructure
  Modules/    business capabilities and their delivery/application code
```

- `Products` es responsable de la disponibilidad del catálogo, los precios base de productos cocinados y los descuentos configurables para productos crudos, que son la alternativa de menor precio.
- `Orders` es responsable de la agrupación comercial, los totales finales, los snapshots, el ciclo de vida, los toppings, la entrega, los resúmenes, la idempotencia del checkout y de anunciar una transición confirmada a `paid` sin escribir el ledger.
- `Customers` es responsable de los perfiles, la normalización de teléfonos, la búsqueda administrativa y el historial de clientes.
- `Settings` proporciona el límite administrativo de escritura para la configuración de pedidos, los descuentos de productos crudos y los toppings existentes.
- `PublicSite` es responsable de las rutas de páginas públicas; sus endpoints de checkout delegan en `Orders`.
- `Marketing` es responsable del historial, la programación y la elegibilidad pública de Featured Product, de su modelo de vista seguro para uso público y del resumen compacto del Dashboard.
- `Instagram` es responsable de los assets de Story, las programaciones, el estado de la cola de publicaciones, el límite de automatización con n8n y la entrega temporal de medios. n8n coordina las llamadas a Meta, pero nunca lee PostgreSQL.
- `Management` es responsable de billeteras, hechos financieros, entries inmutables, saldos derivados y reversiones. Consume la confirmación de pago de Orders mediante un contrato de aplicación acotado y conserva su propio origen financiero idempotente; no administra el ciclo de vida del pedido ni stock.
- `Core\Contracts\ProductPriceProvider` es la interfaz acotada entre Products y Orders. Orders no debe reproducir consultas de precios del catálogo.

Los Controllers coordinan HTTP. Los Form Requests autorizan y validan. Las Actions ejecutan casos de uso. Los Models persisten relaciones. No introduzca repositories genéricos, services base ni carpetas sin una función concreta.

## Límites del frontend

```text
resources/js/
  layouts/             public, admin, authentication, and Settings shells
  pages/               thin Inertia route entry points
  features/            feature-owned components and types
  components/ui/       shadcn primitives
  components/shared/   Te Pinta reusable components
  lib/                 focused infrastructure helpers
```

Las vistas previas de precios del frontend llaman a Laravel. Son únicamente informativas: la creación del pedido vuelve a ejecutar el mismo motor del backend y persiste su resultado.

## Dirección de las dependencias

1. La capa de entrega HTTP llama a Actions de aplicación o a services específicos.
2. Orders consume Products mediante `ProductPriceProvider`.
3. Orders emite `OrderPaymentConfirmed`; el listener síncrono de Management invoca su propia Action dentro de la misma transacción, sin que Orders conozca modelos, tablas ni servicios internos de Gestión.
4. Core nunca depende de un módulo de negocio.
5. Un módulo nunca llama al Controller ni a la clase Request de otro módulo.
6. Las Pages pueden componer UI de features o shared; la UI shared no debe importar código de features.
7. PublicSite y Dashboard consumen modelos de lectura de Marketing; no reproducen las reglas de ventanas temporales de Marketing ni de elegibilidad de Products.

## Decisiones duraderas

| Decisión                                | Consecuencia                                                                                                                                                                                |
| --------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Monolito modular                        | Customers, Products, Orders, Settings y PublicSite conservan su responsabilidad sin transacciones distribuidas.                                                                             |
| Secuencia PostgreSQL para IDs de Order  | La asignación de `TP-000001` es segura ante concurrencia; los rollbacks pueden dejar huecos inofensivos.                                                                                    |
| Un único motor de precios en el backend | El checkout administrativo y el público no pueden divergir en agrupaciones ni totales.                                                                                                      |
| Snapshots de pedidos inmutables         | Los cambios en el catálogo, las direcciones, los toppings y la configuración nunca modifican ni recalculan el historial.                                                                    |
| Unicidad parcial del teléfono activo    | La coincidencia es determinista entre clientes activos, mientras que puede reutilizarse el teléfono de un cliente eliminado mediante soft delete.                                           |
| Slugs públicos, IDs internos            | El checkout público selecciona entradas del catálogo sin exponer IDs de la base de datos.                                                                                                   |
| Idempotencia con hash + fingerprint     | Los reintentos exactos devuelven el mismo pedido; se rechaza reutilizar una clave con otro payload.                                                                                         |
| Soft deletion                           | Customers y Orders se pueden recuperar y las claves foráneas históricas permanecen intactas.                                                                                                |
| Marketing e Instagram son pares         | Marketing define la presentación comercial; Instagram controla la publicación en el canal. Una integración futura requiere un contrato explícito entre módulos.                             |
| Cola de Instagram controlada por el POS | Laravel controla las programaciones y el estado atómico de publicación; n8n reclama un elemento mediante una API protegida por bearer y contiene todas las solicitudes específicas de Meta. |
| Ledger inmutable de Management          | El saldo se deriva de entries firmadas; las correcciones crean reversiones o ajustes nuevos y nunca mutan historia financiera.                                                              |
| Pago de Order como origen financiero    | `paid` sigue siendo estado operativo mutable; `ManagementOrderPayment` es el hecho financiero durable, único por pedido y creado síncronamente en la transición confirmada.                 |

## Aún fuera de alcance

No están implementados los gateways de pago, la automatización de WhatsApp, las promociones, cupones o combos de Marketing, el inventario/stock, la distribución automática de cobros de Orders, la reversión automática al cancelar o desmarcar un pago, la reconciliación histórica, Analytics ni otros tipos de combos de pedidos. Featured Product es únicamente de presentación y no modifica precios.
