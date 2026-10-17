# 0013: Code-defined roles and permissions per restaurant membership

Status: accepted
Date: 2026-10-17

## Context

Access was a single `users.is_admin` flag plus fixed `staff_profile` values (`waiter`, `cashier`) that granted no HTTP access. The owner needs staff accounts with different reach, for example an accountant who can read income, expenses and purchases but cannot change anything, and a waiter who can take orders. The schema is tenant-ready ([ADR 0012](0012-tenant-ready-schema.md)), so access must belong to a restaurant, not to the user globally.

## Decision

- Access is a membership: table `restaurant_user` (`restaurant_id`, `user_id`, `role`, `is_active`), unique per restaurant and user, with `restaurant_id` defaulting from the session setting like every business row.
- Six roles and fifteen permissions are defined in code (`Identity\Enums\Role`, `Identity\Enums\Permission`). `Role::permissions()` is the only role → permission mapping. There is no runtime role editor and no per-user override.
- Every permission is a Gate ability with the same name. Every `/admin` route requires `restaurant.member` (an active membership in the current restaurant) and its own `can:<permission>` middleware; a test fails when an admin route has none. Policies and Form Requests check the same permissions.
- Login (Fortify `authenticateUsing`) accepts only users with an active membership in the current restaurant; anyone else gets the wrong-password failure.
- The migration converts `is_admin` users to `owner` and `staff_profile` users to `waiter`/`cashier` in restaurant 1. Both legacy columns stay for one release as a rollback path; no code reads them, and every membership save mirrors them (active owner → `is_admin`, active waiter/cashier → `staff_profile`, anything else → none) so a rollback keeps deactivated members out and post-deploy owners in. Managers, kitchen and accountants have no legacy equivalent and lose access on rollback (fail closed).
- Membership changes run in a transaction that locks the target row and the active owner rows in one ordered statement. Nobody changes their own role or active state, and the last active owner cannot be demoted or deactivated.

## Consequences

- Read-only roles are enforced by the backend: a mutating request answers 403 even if the UI shows the control.
- Adding a permission or changing a role is a code change with a test (`RolePermissionTableTest`), reviewed like any other.
- Rolling back after deploy drops the memberships; users created through user management after the deploy lose access, while converted users fall back to their legacy columns.
- Deferred: mandatory 2FA, PIN login, per-user overrides and restaurant switching.

## Alternatives considered

- A runtime role/permission editor (for example a permissions package with database tables): rejected; six fixed roles cover the need, and editable permissions make the security model harder to test and reason about.
- Keeping `is_admin` and adding flags per capability: rejected; it does not scale to read-only roles and is not restaurant-scoped.
