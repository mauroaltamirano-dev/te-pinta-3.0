# Índice de documentación

Comience por el documento más acotado que sea responsable del cambio.

| Necesidad                                                                   | Documento                                                  |
| --------------------------------------------------------------------------- | ---------------------------------------------------------- |
| Comprender los límites y la dirección de las dependencias                   | [ARCHITECTURE.md](ARCHITECTURE.md)                         |
| Encontrar al responsable exacto de una ruta, regla, página o configuración  | [PROJECT_MAP.md](PROJECT_MAP.md)                           |
| Agregar o modificar una capacidad del backend                               | [MODULES.md](MODULES.md)                                   |
| Modificar la agrupación o los totales de pedidos                            | [ORDER_PRICING.md](ORDER_PRICING.md)                       |
| Modificar estados, pagos o integración financiera de pedidos                | [ORDERS.md](ORDERS.md)                                     |
| Modificar tablas, restricciones, identificadores o ciclos de vida           | [DATABASE.md](DATABASE.md)                                 |
| Modificar autorización, checkout público o manejo de datos privados         | [SECURITY.md](SECURITY.md)                                 |
| Modificar métricas, períodos o reglas de alertas operativas del Dashboard   | [DASHBOARD.md](DASHBOARD.md)                               |
| Modificar billeteras, ledger, egresos, saldos o auditoría de Gestión        | [MANAGEMENT.md](MANAGEMENT.md)                             |
| Modificar la programación de Featured Product o Marketing público           | [MARKETING.md](MARKETING.md)                               |
| Operar o ampliar la automatización de Instagram Story                       | [instagram-automation.md](instagram-automation.md)         |
| Modificar tokens, layouts o UI reutilizable                                 | [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md)                       |
| Ejecutar comandos habituales locales, de Docker, calidad u operación        | [COMMANDS.md](COMMANDS.md)                                 |
| Configurar, migrar, ejecutar o exponer la aplicación                        | [DEVELOPMENT.md](DEVELOPMENT.md)                           |
| Agregar un evento o diagnosticar una falla                                  | [LOGGING.md](LOGGING.md)                                   |
| Agregar o ejecutar verificaciones automatizadas                             | [TESTING.md](TESTING.md)                                   |
| Comprender las compensaciones registradas                                   | [decisions/README.md](decisions/README.md)                 |
| Ver qué está completo y qué se pospuso intencionalmente                     | [CURRENT_STATE.md](CURRENT_STATE.md)                       |
| Implementar el próximo slice de Inicio para pedidos rápidos administrativos | [design/ADMIN_QUICK_ORDER.md](design/ADMIN_QUICK_ORDER.md) |

Inventory foundation: [INVENTORY.md](INVENTORY.md) documents physical quantities, integer-peso cost, current valuation and the staged reconstruction boundary.

## Lista de verificación para cambios

1. Identifique el módulo responsable y lea su guía específica.
2. Mantenga la autorización, la validación, los precios y los totales en el backend.
3. Preserve los snapshots de pedidos; nunca recalcule el historial a partir del catálogo o la configuración actuales.
4. Ejecute las verificaciones específicas y luego el control de calidad completo que corresponda.
5. Actualice esta documentación cuando cambie un límite o una regla duradera.

Para la instalación móvil y el runtime de Tailscale, comience por [DEVELOPMENT.md](DEVELOPMENT.md#operational-runtime-and-mobile-installation). El servicio operativo se ejecuta en el puerto loopback `8082`; use `deploy/tailscale/route.sh status` en lugar de inferir el estado de la ruta a partir de un contenedor en ejecución.
