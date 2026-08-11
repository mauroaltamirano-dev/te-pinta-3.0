# Logging and observability

Use Laravel logging and Monolog. Logs should explain meaningful application events without exposing private data or flooding the output.

## Event shape

Use stable event names and structured context:

```php
Log::info('order.created', [
    'request_id' => $requestId,
    'module' => 'Orders',
    'action' => 'create',
    'order_id' => $order->public_id,
    'admin_id' => $admin->getKey(),
    'status' => $order->status,
    'duration_ms' => $duration,
]);
```

Prefer searchable fields over interpolated prose. Include only fields that help operate or diagnose the event.

## Levels

| Level     | Use                                                                                                 |
| --------- | --------------------------------------------------------------------------------------------------- |
| `INFO`    | Relevant completed actions, such as an order created or status changed                              |
| `WARNING` | Abnormal but recoverable situations, such as a rejected transition or degraded optional integration |
| `ERROR`   | Real failures requiring diagnosis or intervention                                                   |

Debug-level output is for local diagnosis and must not become noisy production logging.

## Context

When relevant, include:

- request/correlation ID;
- module and action;
- authenticated administrator ID;
- public order ID;
- resulting status;
- duration;
- safe exception/error context.

Do not log passwords, tokens, cookies, secrets, full card data, or unnecessary complete addresses/phone numbers. Do not serialize full request payloads, user models, or exception context without reviewing their fields.

## Storage and access

Channels are configured in `config/logging.php`. Local file logs live under `storage/logs/` and are not versioned. Production retention, forwarding, access control, and redaction are deployment responsibilities and must match data-protection requirements.

## Review checklist

- [ ] Event name is stable and describes a meaningful action.
- [ ] Level matches operational severity.
- [ ] Context supports correlation without private payloads.
- [ ] Failure logs do not expose secrets or duplicate framework reporting unnecessarily.
- [ ] High-frequency paths will not create avoidable noise or cost.
