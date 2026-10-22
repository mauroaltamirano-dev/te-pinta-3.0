# Purchase and consumption forms

Objective: make "Registrar compra" and "Consumo físico" readable (presentation only) and filter the recipe list by active/retired. Branch `feat/purchase-and-consumption-forms` from `main` at `7214e93`, worktree `~/workspaces/tepinta3.0-worktrees/purchase-and-consumption-forms`. Delivery: local branch only; no deploy, push or merge without the user's OK. Test runners: `node --test tests/frontend`, PHPUnit against a private throwaway Postgres (`tepinta-purchase-forms-pg`, 127.0.0.1:55496, config in `../.purchase-forms-test.env`), Playwright (chromium, mobile-chromium).

## Specs

Scope: "solo presentación, sin cambiar qué se guarda ni las reglas del backend" (except S9).

- S1 — Registrar compra: "Billetera, fecha y proveedor en una fila en desktop."
- S2 — "Líneas como tabla compacta en desktop (insumo, presentación, cantidad, tipo de precio, precio, subtotal, quitar) y tarjetas compactas en mobile."
- S3 — "Subtotal por línea según el tipo de precio." (display mirrors `CreatePurchase`: total → price; unit → quantity × price.)
- S4 — "Resumen siempre visible junto a "Confirmar compra": insumos + envío = total."
- S5 — "Textos simples en español con tildes y voseo, sin jerga ("capas de costo", "pesos enteros"), una sola explicación arriba."
- S6 — Consumo físico: "Clasificación en español (semi_finished, raw_material → "Semielaborado", "Materia prima"), "Insumo" en vez de "Item", unidades en español."
- S7 — "Dejar claro que la cantidad es por 1 unidad vendida, con un ejemplo vivo: "Una docena descuenta 12 × 60 g = 720 g"."
- S8 — "Componentes como filas compactas, igual que las líneas de compra."
- S9 — "/admin/inventory/recipes lista hoy todas las recetas sin filtro (RecipeController::index, línea 34), incluidas las retiradas como "Criolla Salada". Mostrá por defecto solo las activas, con pestañas "Activas" y "Retiradas" igual que las versiones en la página de la receta (?view=retired). Es el único cambio de backend permitido en esta feature: un filtro de lectura en index, con test de que una retirada no aparece en "Activas" y sí en "Retiradas"."
- Cross-cutting: "Reusá los helpers de etiquetas de unidades que agregó la rama de recetas. Tests e2e en desktop y mobile, screenshots antes/después."

## Tasks

- T1 — S1–S5: purchase form layout, line subtotal helper, summary. Route: inline. Status: done (commit 2).
- T2 — S6–S8: consumption page rows, kind labels, live example. Route: inline. Status: done (commit 2).
- T3 — S9: recipe index filter + tabs. Route: inline. Status: done (commit 1).
- T4 — e2e desktop/mobile, screenshots, docs, full checks. Route: inline. Status: done (commits 1–2).

## Log

- L1 (2026-10-22) — User request: "Pedido del usuario (2026-10-22): sobre "Registrar compra": "está todo muy confuso, no se entiende bien y algunas cosas se pierden". Sobre "Consumo físico": "tampoco se entiende mucho, hay variables, y no sé si debo cargar el valor por docena, unidad, etc". Alcance: solo presentación, sin cambiar qué se guarda ni las reglas del backend." Items (1)–(8) as quoted in S1–S8; "Nada de deploy, push ni merge sin mi ok."
- L2 (2026-10-22) — User addition: item (9), quoted in S9.
- L3 — Before screenshots (chromium, mobile-chromium) captured with a throwaway spec, not committed. Route inline for all tasks: no Writer trigger (units are small and share the e2e specs).
- L4 — RED: `node --test` (missing `features/purchases/presentation.ts`), `RecipeLifecycleTest` new test ("Property [view] does not exist"). GREEN: frontend 30/30, `RecipeLifecycleTest` 15/15. `test_retiring_a_recipe_keeps_its_history_and_blocks_new_use` pinned the old unfiltered list (retired recipe on `/admin/inventory/recipes`); moved to `?view=retired` because S9 changes exactly that.
- L5 — E2E: existing specs moved to the per-line labels ("Insumo 1", "Cantidad 1", "Precio 1", "Envío") and Spanish classification. First run of the new purchase spec failed on purpose-relevant evidence: the backend rejects the same item twice ("El campo insumo contiene un valor duplicado.", shown inline), so the spec uses two items; and on mobile the fixed bottom navigation covered the sticky summary. Fix: summary `bottom-24 md:bottom-0` (layout reserves `pb-24`); the hit-test assertion fails with `bottom-0` (RED) and passes with the fix. New/affected specs 6/6 (chromium, mobile-chromium).
- L6 — Checks: Pint, PHPStan, ESLint, Prettier, tsc, build clean. PHPUnit 1011/1017 + 6 `GD extension is not installed` (host has no gd; same 6 as the previous feature). Full Playwright: 223 passed, 52 skipped, 19 failed; 17 are environment-only (Instagram fixture and `ManagementOverviewSeeder` fail in `php artisan` with "GD extension is not installed"), 2 were the new purchase spec, fixed and rerun green. Screenshots before/after in the session scratchpad (not committed). Engram mirror: pending.
- L7 (2026-10-22) — Pre-merge check, one full Playwright run with the committed spec: 225 passed, 52 skipped, 17 failed. All 17 fail in fixture setup (`InstagramPlaywrightFixture` via `php artisan tinker`, `ManagementOverviewSeeder` via `php artisan db:seed`) with "GD extension is not installed"; host `php -m` has no gd. The purchase/consumption spec passed 4/4 inside the suite (chromium, mobile-chromium), so the earlier failure did not reproduce in suite order. Engram mirror saved under `odd/purchase-and-consumption-forms/tasks`; `tepinta-purchase-forms-pg` stopped.
