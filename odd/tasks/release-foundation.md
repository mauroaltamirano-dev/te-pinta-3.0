# Release foundation (Phase 2)

Objective: every production release is an immutable image built from a clean commit, deployed with backup → migrate → switch → smoke, and rolled back with one command.
Branch: `reset/baseline` (stacked on Phase 1). Delivery: `auto-chain`. Test runner: PHPUnit on PostgreSQL 18; release scripts rehearsed on a staging stack against database copies only.
Roadmap items: I-02, I-03 (`~/tepinta-handoffs/global-architecture-audit-2026-10/ROADMAP_BACKLOG.md`).

## Specs

- S1 — One package manager: production images install frontend dependencies with npm from `package-lock.json` (as CI does); `pnpm-lock.yaml` is removed.
- S2 — One immutable image per commit, built from `git archive` of that commit (never the working tree), tagged `tepinta3:<short-sha>`, used by the operational app, the scheduler and the public app.
- S3 — The web runtime serves concurrent requests (several PHP workers), so one slow request does not block others.
- S4 — The health check fails when the database is unreachable.
- S5 — `deploy/release.sh` with `build <commit>`, `deploy <tag>` (backup → migrate with the new image → switch → wait healthy → smoke → automatic rollback of the application image on smoke failure), `rollback` and `status`; every mutation requires explicit confirmation; release history is recorded outside the repo.
- S6 — Production PostgreSQL defined by a deploy-owned Compose file that reuses the existing data volume, so the development Sail stack can no longer stop or delete it. Applied only at cutover.
- S7 — Everything rehearsed on a staging stack (separate project name, ports and database copy); production containers untouched until the owner authorizes the cutover.

## Tasks

| ID | Specs | Route | Status | Evidence |
|---|---|---|---|---|
| T1 | S1, S3 | inline | done | `b4fdc64`: npm ci in Sail image verified; staging image runs 1 master + 8 `php -S` workers (production today: 1). |
| T2 | S4 | inline | done | `8e9aad1`: RED (200 with DB unreachable) → GREEN; suite 741 pass. |
| T3 | S2, S5 | inline | done | `9afa3c2`, `4f90d75`: shellcheck clean; staging: confirmation refusal, deploy live, broken release auto-rolled back by smoke test, manual rollback, release log/state correct. |
| T4 | S6 | inline | done | `81150b1` + runbook: deploy-owned compose started on a copied existing volume, alias `tepinta-db` served 143 orders; dev service renamed `pgsql-dev` with its own volume. |
| T5 | S7 | inline | done | Staging projects `tepinta-staging-*` on 8092/8094 with DB copy and temp storage; legacy import into running staging reconciled; production containers verified untouched (uptimes 2–5 weeks); staging cleaned up. |

## Log

- L1 (2026-10-16, owner): "vamos con eso" — authorizes Phase 2 items 1 and 2 (image + release script) prepared and tested on copies only; the production cutover needs a separate explicit go.
- L2: Found the production Dockerfile installs with `pnpm --frozen-lockfile` from a `pnpm-lock.yaml` last updated 2026-09-02, while CI and development use npm with `package-lock.json`.
- L3 (lead decision): keep `php artisan serve` with `PHP_CLI_SERVER_WORKERS` instead of introducing FrankenPHP/nginx now: removes the single-request bottleneck without a new base image; revisit when traffic or hardening requires it.
- L4: Rehearsal caught a real defect: the public app had no healthcheck, so `--wait` returned before the server listened and the smoke test saw 000. Fixed in `4f90d75` (healthcheck + smoke retries).
- L5: Cutover hazards found: the Sail `pgsql` service *is* production (volume `sail-pgsql`), so any change to the dev compose recreated it; n8n reaches the database through the Sail network. Production now gets `deploy/database/compose.yaml` on network `tepinta-production` (cutover step 4–5).
- L6: Image size 3.7 GB (Sail base). Acceptable on the notebook; slimming is a later optimization.
