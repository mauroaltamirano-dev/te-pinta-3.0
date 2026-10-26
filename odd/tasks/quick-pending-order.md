# Quick pending order

Objective: let staff create an order from Vender with only its products, as a same-day pickup awaiting confirmation, and complete the customer data later. Branch `feat/quick-pending-order` from `main` at `38155af`. Delivery: ask-on-risk; local branch only, no push or PR without the user's OK. Test runner: PHPUnit through Sail (`php artisan test`, `DB_HOST=pgsql-dev`), Playwright for e2e.

## Specs

- S1 — Quick order without data: "crear pedido rápido sin que nos pida nombre, teléfono, etc etc, porque a veces tomamos un pedido manualmente y queresmoc rearlo rápido sin tener que añadir todos los datos como ya hacemos con las mesas"; "queremos selccionar el producto y crear, así ya vemos el total y nos queda como comanda rápida". Vender's quick order offers it; it sends products, condition, add-ons and optional notes, payment and discount, but no customer, address or schedule.
- S2 — Same day, pending: "por defecto sería para el mismo día, luego nosotros podemos modificar esos datos, inclsuive, si lo creamos rápido, lo dejamos como Pendiente, no como confirmado, luego lo confirmamos una vez que complemteos datos de nombre, teléfono, etc". The server stores delivery date = business today, delivery type pickup, no period, status `pending_confirmation`, source `admin`.
- S3 — Kitchen ticket on creation: "si, imprime apenas lo creamos". Confirming later never prints it again (same dedupe key).
- S4 — Confirmation requires the data: leaving `pending_confirmation` for any status except `cancelled` requires a customer name and a delivery period, by the status control and by the full edit (which already requires them). Error: "Completá el nombre del cliente y la franja horaria antes de confirmar el pedido."
- S5 — Database: `orders_context_valid` accepts a non-dine-in order without customer or period only when it is an `admin` pickup that is `pending_confirmation` or `cancelled` and has a delivery date. High-risk item 1 (constraint on persisted data): own commit, independent verifier.
- S6 — The new web order alert stays limited to `public_checkout` orders (a quick order is not a web order).

Assumptions:
- A1 — The quick order takes no customer fields at all (`customer` and `customer_id` are rejected); names are added by editing.
- A2 — Editing a quick order with the full form requires the name and schedule like any pickup, even while it stays pending.

## Tasks

| ID | Specs | Route | Status | Evidence |
| --- | --- | --- | --- | --- |
| T1 | S2, S4, S5 | inline | done | RED 7/8 failing → GREEN 9/9; Orders suite 248 passed; own commit |
| T2 | S1, S3, S6 | inline | done | RED 6/6 failing → GREEN 6/6; full suite 1094 passed; e2e admin-quick-order 5/5 |

Route evidence: one unit of behavior with shared code (no writer trigger); T1 holds the high-risk migration and gets its own commit.

## Log

- L1 (2026-10-26, user): "también falta lo de crear pedido rápido sin que nos pida nombre, teléfono, etc etc, porque a veces tomamos un pedido manualmente y queresmoc rearlo rápido sin tener que añadir todos los datos como ya hacemos con las mesas, esa opción no está disponible en el punto de Vender de la web"
- Verified: Vender already offers "Mostrador" (`dine_in`, no customer); pickup and delivery require name, date and period in validation and in `orders_context_valid` (`2026_10_05_000000_prepare_order_context.php`).
- L2 (2026-10-26, user): "Claro, o sea, sería sobretodo para anotar rápido el pedido que nos hagan, por defecto sería para el mismo día, luego nosotros podemos modificar esos datos, inclsuive, si lo creamos rápido, lo dejamos como Pendiente, no como confirmado, luego lo confirmamos una vez que complemteos datos de nombre, teléfono, etc, es por ejemplo que alguien nos venga, nos pida en el mostrador pero no se siente a comer, y yo quiero hacer el pedido rápido, cargar su pedido y listo, o mismo cuando nos mandan un mensaje para pedirnos, estamos cargando rápido, queremos ver el total y que se quede creado el pedido, si estamos apurados no nos da tiempo a cargar el nombre, teléfono, dirección, etc, queremos selccionar el producto y crear, así ya vemos el total y nos queda como comanda rápida"
- L3 (2026-10-26, user, kitchen ticket on creation or on confirmation): "si, imprime apenas lo creamos"
- T1 committed as `73c7ef7` (migration + status guard; RED 7 of 8 failing, GREEN 9/9 with the added name-without-customer case).
- T2: `quick` flag (admin store only; public checkout and edit reject it), `OrderCheckoutData::$quick` (server sets today's business date), `CreateOrder` status pending, `ResolveOrderCustomer` creates no customer, draft panel button "Pedido rápido", result panel shows the pending state. Playwright needed a local `testing_playwright` database on `tepinta-dev-pgsql` (created 2026-10-26).

- Independent verifier (S2, S5 pass; S4 blocker): a cancelled quick order reopened from the status control hit `orders_context_valid` (HTTP 500) because the guard keyed on the previous status. Fix: the guard keys on the target status for every non-dine-in order; RED 1 failing → GREEN; Orders suite 282 passed.
- Native review (risk + reliability lenses, base `38155af` → `8e5ddeb`): consent granted, approved and acknowledged. Non-blocking advisories: R3-001 (`quick-order-draft-panel.tsx:168-185`, warning) and R3-002 (`UpdateOrderStatus.php:27-31`, suggestion). Feature closed on the local branch; push and PR are pending the user's decision.
