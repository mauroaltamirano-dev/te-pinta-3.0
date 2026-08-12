# Logging and observability

Use stable event names and structured context. Logs identify a business event without serializing private payloads.

## Implemented events

| Event                                   | Context                                               |
| --------------------------------------- | ----------------------------------------------------- |
| `[Customers] customer.created`          | `module`, internal `customer_id`, `admin_id`          |
| `[Customers] customer.updated`          | `module`, internal `customer_id`, `admin_id`          |
| `[Customers] customer.deleted`          | `module`, internal `customer_id`, `admin_id`          |
| `[Orders] order.created`                | `module`, public `order_id`, `admin_id`               |
| `[Orders] public_order.created`         | `module`, public `order_id`; `admin_id` is null       |
| `[Orders] order.status_changed`         | `module`, public `order_id`, `admin_id`, `from`, `to` |
| `[Orders] order.cancelled`              | same transition context when target is cancelled      |
| `[Orders] order.payment_status_changed` | `module`, public `order_id`, `admin_id`, `from`, `to` |
| `[Orders] order.deleted`                | `module`, public `order_id`, `admin_id`               |

Administrative requests also pass through `admin.request.log`, which supplies the existing request/correlation logging boundary. Reuse its correlation context rather than inventing a second request-ID mechanism.

## PII rule

Never log:

- full phone numbers or delivery/customer addresses;
- customer notes, order notes, or formatted WhatsApp messages;
- checkout payloads, idempotency keys, cookies, tokens, or secrets;
- serialized Customer, Order, Request, or exception objects without reviewing fields.

Use internal customer ID or public Order ID for correlation. If phone-based operational correlation is ever necessary, add an explicitly reviewed masked/hash representation; do not add the raw value.

## Event shape

```php
Log::info('[Orders] order.status_changed', [
    'module' => 'Orders',
    'order_id' => $order->public_id,
    'admin_id' => $adminId,
    'from' => $previous->value,
    'to' => $status->value,
]);
```

## Levels

| Level     | Use                                                 |
| --------- | --------------------------------------------------- |
| `INFO`    | Completed meaningful business action                |
| `WARNING` | Recoverable abnormal condition that needs attention |
| `ERROR`   | Failure requiring diagnosis/intervention            |

Do not duplicate Laravel's exception reporting without adding safe, actionable context. Channels are configured in `config/logging.php`; local files under `storage/logs/` are never committed.

## Review checklist

- [ ] Stable event name and useful identifiers.
- [ ] Correct level.
- [ ] No PII, secret, raw payload, or idempotency token.
- [ ] High-frequency preview endpoints do not create routine noise.
- [ ] Tests inspect logs without depending on private values.
