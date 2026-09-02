# Registro y observabilidad

Usar nombres de eventos estables y contexto estructurado. Los registros identifican un evento de negocio sin serializar payloads privados.

## Eventos implementados

| Evento                                            | Contexto                                                                                                                                      |
| ------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------- |
| `[Customers] customer.created`                    | `module`, `customer_id` interno, `admin_id`                                                                                                   |
| `[Customers] customer.updated`                    | `module`, `customer_id` interno, `admin_id`                                                                                                   |
| `[Customers] customer.deleted`                    | `module`, `customer_id` interno, `admin_id`                                                                                                   |
| `[Customers] customer.restored`                   | `module`, `customer_id` interno, `admin_id`                                                                                                   |
| `[Orders] order.created`                          | `module`, `order_id` público, `admin_id`                                                                                                      |
| `[Orders] public_order.created`                   | `module`, `order_id` público; `admin_id` es nulo                                                                                              |
| `[Orders] order.status_changed`                   | `module`, `order_id` público, `admin_id`, `from`, `to`                                                                                        |
| `[Orders] order.cancelled`                        | El mismo contexto de transición cuando el destino es `cancelled`                                                                              |
| `[Orders] order.payment_status_changed`           | `module`, `order_id` público, `admin_id`, `from`, `to`                                                                                        |
| `[Orders] order.updated`                          | `module`, `order_id` público, `admin_id`, `status`, `total_before`, `total_after`                                                             |
| `[Orders] closed_order_edit_enabled`              | `module`, `order_id` público, `admin_id`, `status`, `version`                                                                                 |
| `[Orders] order.deleted`                          | `module`, `order_id` público, `admin_id`                                                                                                      |
| `[Orders] order.restored`                         | `module`, `order_id` público, `admin_id`                                                                                                      |
| `[Marketing] featured_product.created`            | `module`, `featured_product_id` interno, `product_id` interno, `admin_id`                                                                     |
| `[Marketing] featured_product.updated`            | `module`, `featured_product_id` interno, `product_id` interno, `admin_id`                                                                     |
| `[Marketing] featured_product.disabled`           | `module`, `featured_product_id` interno, `product_id` interno, `admin_id`                                                                     |
| `[Management] wallet.created`                     | `module`, `wallet_id` interno, `admin_id`                                                                                                     |
| `[Management] wallet.updated`                     | `module`, `wallet_id` interno, `admin_id`                                                                                                     |
| `[Management] wallet.archived`                    | `module`, `wallet_id` interno, `admin_id`                                                                                                     |
| `[Management] wallet.restored`                    | `module`, `wallet_id` interno, `admin_id`                                                                                                     |
| `[Management] transaction.created`                | `module`, `financial_transaction_id`, `wallet_id`, `type`, `admin_id`                                                                         |
| `[Management] transaction.reversed`               | `module`, `financial_transaction_id`, `reversal_of_id`, `admin_id`                                                                            |
| `[Management] outflow.created`                    | `module`, `management_outflow_id`, `financial_transaction_id`, `wallet_id`, `admin_id`, `type`, `category_id`                                 |
| `[Management] outflow.reversed`                   | `module`, `management_outflow_id`, `financial_transaction_id`, `reversal_of_id`, `wallet_id`, `admin_id`, `type`, `category_id`               |
| `[Management] transfer.created`                   | `module`, `management_transfer_id`, `financial_transaction_id`, `source_wallet_id`, `destination_wallet_id`, `admin_id`                       |
| `[Management] transfer.reversed`                  | `module`, `management_transfer_id`, `financial_transaction_id`, `reversal_of_id`, `source_wallet_id`, `destination_wallet_id`, `admin_id`     |
| `[Management] distribution.rule_created`          | `module`, `distribution_rule_id`, `rule_version_id`, `admin_id`                                                                               |
| `[Management] distribution.rule_version_created`  | `module`, `distribution_rule_id`, `rule_version_id`, `version_number`, `admin_id`                                                             |
| `[Management] distribution.rule_status_changed`   | `module`, `distribution_rule_id`, `active`, `admin_id`                                                                                        |
| `[Management] distribution.created`               | `module`, `management_distribution_id`, `distribution_rule_id`, `rule_version_id`, `financial_transaction_id`, `admin_id`                     |
| `[Management] distribution.reversed`              | `module`, `management_distribution_id`, `financial_transaction_id`, `reversal_of_id`, `rule_version_id`, `admin_id`                           |
| `[Management] order_payment.created`              | `module`, `management_order_payment_id`, `order_id`, `financial_transaction_id`, `wallet_id`, `admin_id`                                      |
| `[Management] order_payment.reversed`             | `module`, `management_order_payment_id`, `order_id`, `financial_transaction_id`, `reversal_of_id`, `wallet_id`, `admin_id`                    |
| `[Management] order_payment.distribution_created` | `module`, `management_order_payment_id`, `order_id`, `management_distribution_id`, `financial_transaction_id`, `rule_version_id`, `wallet_id` |
| `[Management] order_payment.distribution_skipped` | `module`, `management_order_payment_id`, `order_id`, `wallet_id`, `reason`                                                                    |
| `[Management] order_payment.distribution_failed`  | `module`, `management_order_payment_id`, `order_id`, `rule_version_id`, `wallet_id`, clase de excepción revisada                              |
| `[Management] attachment.created`                 | `module`, `management_attachment_id`, `financial_transaction_id`, `admin_id`                                                                  |
| `[Management] attachment.deleted`                 | `module`, `management_attachment_id`, `financial_transaction_id`, `admin_id`                                                                  |
| `[Management] attachment.file_missing`            | `module`, `management_attachment_id`, `financial_transaction_id`                                                                              |
| `[Management] attachment.file_corrupt`            | `module`, `management_attachment_id`, `financial_transaction_id`                                                                              |

`AssignRequestId` se ejecuta en el stack de middleware web antes de las acciones administrativas y agrega `request_id` al contexto del logger de Laravel. Por eso los eventos de Orders anteriores heredan el mismo ID de correlación aunque cada array de evento no repita esa clave. Las solicitudes administrativas también pasan por `admin.request.log`, que emite el evento de finalización de ruta/acción en el mismo contexto. Reutilizar este límite en lugar de inventar un segundo mecanismo de ID de solicitud.

Los detalles completos de edición se encuentran en el registro estructurado `order_revisions`. Los logs omiten intencionalmente los valores modificados y el motivo de edición de un pedido cerrado para no copiar teléfonos, direcciones ni notas a los flujos de registro operativos. Los logs de cambios de pago pueden incluir transiciones del enum del método; no contienen datos de tarjeta, cuenta ni token porque la aplicación solo almacena la categoría del método.

Los logs de mutaciones de Marketing identifican registros y administradores, pero omiten el titular, el texto breve, los payloads de inicio/fin y los modelos Product/FeaturedProduct serializados. El registro persistido y sus timestamps son el historial administrativo de v1; los logs son eventos de correlación, no un segundo almacén de revisiones.

Los logs de Management identifican el hecho, las billeteras cuando corresponde, el tipo, la categoría, la regla/versión y el administrador. Para un cobro y su distribución incluyen sólo IDs de Order, orígenes financieros, cabeceras, billetera y versión; omiten cliente, importe, allocations, snapshot y payload del evento. `distribution_skipped` hace observable la ausencia de configuración sin tratarla como error; `distribution_failed` registra la falla técnica antes de propagarla y provocar rollback. Las tablas semánticas, `financial_transactions` y `ledger_entries`, no los logs, constituyen la evidencia financiera. Una reversión emite `[Management] transaction.reversed` y, cuando existe un origen semántico específico, su evento correlacionado; no reemplaza el historial persistido.

Los eventos de comprobantes registran sólo los IDs del attachment, la cabecera financiera y, para mutaciones, el administrador. No incluyen nombre original, MIME, tamaño, checksum, disk, path, contenido ni motivo de eliminación. La metadata, el tombstone y el blob privado retenido constituyen la evidencia documental; los logs no duplican esos datos. Una descarga o preview exitoso no genera un evento de negocio rutinario, mientras que un blob ausente o cuyo tamaño/checksum ya no coincide emite respectivamente `attachment.file_missing` o `attachment.file_corrupt` en nivel `WARNING` y responde `404` sin revelar storage interno.

Abrir o filtrar la auditoría financiera no genera un evento por finding ni serializa sus resultados. Los findings son una proyección derivada y una lectura frecuente no debe producir ruido ni duplicar evidencia. Si en el futuro se incorpora una acción persistida de revisión, sólo esa mutación administrativa podrá emitir un evento estable como `management.audit.reviewed` o `management.audit.resolved`, con identificadores y sin importes, notas privadas ni payloads completos.

## Regla sobre PII

Nunca registrar:

- números de teléfono completos ni direcciones de entrega o de clientes;
- notas de clientes, notas de pedidos ni mensajes de WhatsApp formateados;
- payloads de checkout, claves de idempotencia, cookies, tokens ni secretos;
- objetos Customer, Order, Request o excepciones serializados sin revisar sus campos.

Usar el ID interno del cliente o el ID público de Order para correlación. Si alguna vez se necesita correlación operativa por teléfono, agregar una representación enmascarada o con hash revisada explícitamente; no agregar el valor directo.

## Forma del evento

```php
Log::info('[Orders] order.status_changed', [
    'module' => 'Orders',
    'order_id' => $order->public_id,
    'admin_id' => $adminId,
    'from' => $previous->value,
    'to' => $status->value,
]);
```

## Niveles

| Nivel     | Uso                                                 |
| --------- | --------------------------------------------------- |
| `INFO`    | Acción de negocio significativa completada          |
| `WARNING` | Condición anormal recuperable que requiere atención |
| `ERROR`   | Falla que requiere diagnóstico o intervención       |

No duplicar el reporte de excepciones de Laravel sin agregar contexto seguro y accionable. Los canales se configuran en `config/logging.php`; los archivos locales de `storage/logs/` nunca se incluyen en commits.

## Lista de revisión

- [ ] Nombre de evento estable e identificadores útiles.
- [ ] Nivel correcto.
- [ ] Sin PII, secretos, payloads directos ni tokens de idempotencia.
- [ ] Los endpoints de preview de alta frecuencia no generan ruido rutinario.
- [ ] Las pruebas inspeccionan los logs sin depender de valores privados.
