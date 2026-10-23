# Costing simplification

Objective: current product costing falls back to the active recipe estimate for semi-finished components without reliable valuation, and `/admin/inventory/costing` plus its packaging editor speak plain Spanish. Branch `feat/costing-simplification` from `main` at `153fafe`, worktree `~/workspaces/tepinta3.0-worktrees/costing-simplification`. Delivery: local branch only; no push, merge or deploy. Test runners: PHPUnit against a private Postgres (`tepinta-costing-simpl-pg`, 127.0.0.1:55499, config in `../.costing-simplification-test.env`), full suite in the `sail-8.5/app` image; Playwright (chromium, mobile-chromium) on database `testing_costing` in the same container, port 8093.

## Specs

- S1 — Recipe fallback: "si un componente semielaborado no tiene valuación confiable (desconocida, parcial, negativa o con advertencias), el costo actual del producto usa el costo estimado actual de su receta activa (Queries/BuildRecipeCurrentCost), marcado \"estimado por receta\". Si la receta tampoco tiene costo completo, sigue sin número. Los insumos de compra y el packaging no tienen ese respaldo. Nunca tratar lo faltante como cero. Sigue siendo una estimación de solo lectura: no escribe movimientos, capas ni asientos."
- S2 — Costing screen: "por producto, una tabla Unidad / Media docena / Docena (o Unidad para productos por unidad) con Precio, Costo, Ganancia y %. Debajo, solo lo que falta, como acción concreta con enlace (por ejemplo \"Registrá una compra de Caja Cartón 1 doc\" o \"Registrá una producción de Relleno Jamón y Queso\"). Sacá la jerga: \"política\", \"cobertura\", \"valoración\", \"COGS\". Los basis points se muestran y se cargan como %."
- S3 — Packaging editor: "el editor de políticas pasa a preguntar en lenguaje simple qué packaging lleva cada presentación (\"¿Qué caja lleva la docena?\") en vez de \"Presentación 1/2/3\" con \"Unidades comerciales\". El modelo de datos no cambia salvo que sea imprescindible; si cambia, explicá por qué."
- S4 — "Todo en español, con voseo, consistente con el resto del admin."
- S5 — Tests: "PHPUnit para el respaldo por receta: semielaborado sin producción, con stock de costo desconocido, receta incompleta, e insumo comprado sin respaldo. Specs e2e en desktop y mobile, capturas antes/después en tu scratchpad." Full PHPUnit in `sail-8.5/app`. Update `docs/INVENTORY.md`.

## Tasks

- [x] T1 — S1, S5: recipe fallback in `BuildProductUnitEconomics` + `next_steps` + PHPUnit. Route: inline. Commit `b7a588d`.
- [x] T2 — S2, S4: costing page rewrite. Route: inline. Commit `29ab9c8`.
- [x] T3 — S3, S4: packaging editor in plain language, overhead as %. Route: inline. Commit `29ab9c8`.
- [x] T4 — S5: e2e desktop/mobile, screenshots, docs, full checks. Route: inline. Commits `b7a588d`, `29ab9c8`.

## Log

- L1 (2026-10-23) — User request (verbatim, from the coordination chat): "ya tengo los precios cargados, pero no me muestra el costo por docena, unidad, etc, también pasa lo mismo que la otra sección, es muy confuso todo, muchos datos e info, para un cliente no lo va a entender y va a ser tedioso". Scope agreed in S1–S5.
- L2 — Route inline for every task: no Writer trigger (one coherent change, no parallel independent units). Before screenshots captured with a throwaway spec using `tests/e2e/support/costing-fixture.ts` (filling with recipe but no production, dozen box never bought): every presentation shows "No disponible" and "Cobertura directa: 50 %".
- L3 — T1: RED observed (9 tests: `incomplete` instead of `complete`, missing `cost_source`/`next_steps`), then GREEN 9/9. The costing read runs in `REPEATABLE READ READ ONLY`, where PostgreSQL rejects the `FOR SHARE` in `BuildRecipeCurrentCost`; added `buildMany(..., lockItems: false)`. The read-only test was proven to fail with the lock on ("read-only transaction"). Fallback scope: kind `semi_finished`, warning unknown/zero/partial/negative/warning layers, exactly one active recipe, complete recipe; one level only; insufficient stock of a fully valued item stays unreliable (not a valuation problem).
- L4 — T2/T3: "Política" is now "Packaging y gastos" in the UI and backend messages; routes, tables and payload keys unchanged (no data model change). Overhead input is a percentage ("12,5" → 1250 bp) with `percentToBasisPoints`. `?edit={id}` opens the editor from the costing page and from next steps.
- L5 — Checks: PHPUnit full suite in `sail-8.5/app` 1026 passed (8245 assertions). Pint, PHPStan, ESLint, Prettier, tsc, `node --test tests/frontend/*.test.mjs` (33 pass), build clean. Costing e2e specs 6/6 (chromium, mobile-chromium). Before/after screenshots in the session scratchpad (`screens/`), not committed.
- L6 — Full Playwright: 227 passed, 52 skipped, 17 failed. All 17 fail in fixture setup on the host PHP without GD (`InstagramPlaywrightFixture` → "Call to undefined function imagecreatetruecolor()", `ManagementOverviewSeeder`), the same 17 as previous features; costing specs passed inside the suite. Final after screenshots captured. Container `tepinta-costing-simpl-pg` left running; empty `.env` left in the worktree for the sail run. Next step: user review of screenshots, then merge/deploy decision.
