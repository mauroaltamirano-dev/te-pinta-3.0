# Users, roles and permissions (Phase 3, A-01)

Objective: staff accounts with code-defined roles and permissions per restaurant, replacing the single `is_admin` flag.
Branch: `feat/rbac` (from `main` = production release `365091a`). Delivery: `auto-chain`. Test runner: PHPUnit on PostgreSQL (`testing_rbac`).

## Specs

- S1 — Roles, code-defined (no runtime role editor): `owner` (Dueño), `manager` (Encargado), `cashier` (Cajero), `waiter` (Mozo), `kitchen` (Cocina), `accountant` (Contador).
- S2 — Permissions, code-defined, one place mapping role → permissions:

  | Permission | owner | manager | cashier | waiter | kitchen | accountant |
  |---|---|---|---|---|---|---|
  | `dashboard.view` | ✓ | ✓ | ✓ | | | ✓ |
  | `orders.view` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
  | `orders.manage` (create, edit, status, payment) | ✓ | ✓ | ✓ | ✓ | | |
  | `customers.view` | ✓ | ✓ | ✓ | ✓ | | ✓ |
  | `customers.manage` | ✓ | ✓ | ✓ | | | |
  | `catalog.view` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
  | `catalog.manage` (products, tags, ordering settings) | ✓ | ✓ | | | | |
  | `inventory.view` | ✓ | ✓ | | | ✓ | ✓ |
  | `inventory.manage` | ✓ | ✓ | | | | |
  | `finance.view` (management overview, audit, purchases) | ✓ | ✓ | | | | ✓ |
  | `finance.manage` | ✓ | ✓ | | | | |
  | `marketing.manage` (featured, Instagram) | ✓ | ✓ | | | | |
  | `floor.manage` (restaurant floor) | ✓ | ✓ | | | | |
  | `settings.manage` | ✓ | | | | | |
  | `users.manage` | ✓ | | | | | |

- S3 — Membership per restaurant: table `restaurant_user` (`restaurant_id`, `user_id`, `role`, `is_active`, timestamps; unique per restaurant and user; restaurant-scoped like every business table). The migration gives every `is_admin` user an `owner` membership in restaurant 1 and every `staff_profile` user a `waiter`/`cashier` membership. `is_admin` and `staff_profile` columns stay for one release (rollback safety) but no code reads them anymore.
- S4 — Login: a user can log in only with an active membership in the current restaurant; anyone else gets the same generic failure as a wrong password.
- S5 — Every `/admin` route is authorized by a permission (route middleware `can:<permission>` and/or policies that check permissions). A test enumerates all registered admin routes and fails when one has no permission check. Policies stop checking `is_admin`.
- S6 — Read-only roles are enforced by the backend. Owner's example, verbatim: "le doy un usuario a un encargado, pero que tenga permisos para ver, no para editar ni modificar datos, suponiendo que sea por ejemplo un contador que quiera ver ingresos, egresos, gastos, etc." The `accountant` can open finance, dashboard, inventory and order pages; every mutating request of those modules returns 403. A role × route matrix test proves it for representative GET and mutating routes of each module.
- S7 — User management (permission `users.manage`): list members of the restaurant; create a user (name, email, temporary password, role); change role; deactivate and reactivate. Nobody can deactivate or demote themselves, and the last active owner cannot be removed or demoted. All validated in the backend. UI copy in Spanish like the rest of the admin. Inertia shares the current user's permissions; the sidebar shows only modules the user can open.
- S8 — `artisan admin:create` creates the user with an `owner` membership.
- S9 — Waiter access: owner's request, verbatim: "y ese mozo tenga la app nuestra para poder cargar las mesas y su pedido". In this task a waiter can log in and reach order creation and the restaurant floor in read mode; the dedicated waiter screens come with the POS phase.
- S10 — Docs: `docs/SECURITY.md` and `docs/ARCHITECTURE.md` updated; new ADR `docs/decisions/0013-code-defined-roles-and-permissions.md`.

Out of scope: mandatory 2FA, PIN login, per-user permission overrides, multi-restaurant switching UI, theme changes (parallel task `feat/light-theme`).

## Tasks

| ID | Specs | Route | Status | Evidence |
|---|---|---|---|---|
| T1 | S1–S10 | delegated (parallel writer; light theme runs at the same time in its own worktree) | pending | |

## Log

- L1 (2026-10-15, owner): "también necesitaría un gestor de usuarios, para que por ejemplo le doy un usuario a un encargado, pero que tenga permisos para ver, no para editar ni modificar datos, suponiendo que sea por ejemplo un contador que quiera ver ingresos, egresos, gastos, etc. Y también mismo genera un mozo por ejemplo, y ese mozo tenga la app nuestra para poder cargar las mesas y su pedido, etc etc."
- L2 (2026-10-16, owner): "perfecto, sigue tus recomendaciones" — Phase 3 (RBAC + light theme) authorized.
- L3 (lead decisions): code-defined roles/permissions (no editor); email + password login for every role (PIN login deferred to POS); `is_admin`/`staff_profile` kept one release for rollback, unused.
