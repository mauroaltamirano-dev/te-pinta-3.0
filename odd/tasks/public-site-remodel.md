# Public site remodel

Objective: close the public site behind a "coming soon" screen while the storefront is remodeled (pizzas, dine-in, vegetarian leaf, real photos). Branch `feat/public-coming-soon` chained on `feat/brand-logos` (`6c0b572`); later tasks on their own branches. Delivery: local branches only; push, merge and deploy only with the user's OK. Test runners: PHPUnit in `sail-8.5/app` against the private Postgres `tepinta-print-e2e-pg` (127.0.0.1:55520), Playwright chromium/mobile-chromium.

## Specs

- S1 — "De momento, pongamos como que próximamente se va a poder hacer pedidos y se viene la web etc etc, o sea, que la gente entre, pero que todavía no pueda ver la web tal cual la estamos haciendo, sino que sea una sola pantalla de "EN construcción" con algún que otro detallito". The public site shows one coming-soon screen; menu, cart, product pages and checkout endpoints are closed to the public (no orders can be placed).
- S2 — "hasta que termines de hacer todo, dejemos la web como en construcción, para el público, pero nosotros podemos seguir en desarrollo todo esto". The closure is a switch: closed in production by default, open in development and tests, reopened with configuration at launch.
- S3 — "aprovecharemos la propiedad del producto de Vegetariano, los que lo tengan, le pondremos una hojita verde." Products tagged `vegetariana` show a green leaf on the public storefront.
- S4 — "habría que cambiar el slogan de Te pinta una empanada, ya que ahora no son solo emapnadas, también pizzas". New slogan covering empanadas and pizzas. Assumption: wording proposed to the user before shipping.
- S5 — "no hagamos tanto énfasis en la oferta cruda, solamente que quede si nos preguntan y nada más, no ublicitarlo tanto". The raw option stays available but is not promoted on the public pages.
- S6 — "mostrar que puede comer en el lugar, también el lugar ofrecemos servicio a mesa, bebidas, postre. Esta es la ubicación:  https://maps.app.goo.gl/CoZHPYbs9VHNMH9L6". The public site presents dine-in with table service, drinks and desserts, and links the location.
- S8 — "en el precio por docena, poner la típica de $20.000 (al lado el precio de docena original tachado y en grís) % descuento, o sea, hacer la promo visible de cuánto sale la docena de una variedad con su descuento respectivo (siendo el precio de 12 unidades sin el precio fijo de docena el "anterior" o de no ahorro)". Public dozen price shows D, next to it 12 × U struck through in gray, and the discount percentage.
- S7 — "sobreotdo porque quiero sacar y usar fotos reales, y todavía no las tengo". Real photos replace placeholders when the user provides them (external dependency).

## Tasks

- T1 — S1, S2: coming-soon screen and public-site switch, PHPUnit + e2e, docs. Route: inline. Status: in progress.
- T2 — S3: vegetarian leaf on public product cards and detail. Route: inline. Status: pending.
- T3 — S4, S5, S6: home remodel (slogan, dine-in, table service, drinks, desserts, location; raw option de-emphasized). Route: inline. Status: pending (slogan wording to confirm).
- T4 — S7: real photos. Status: waiting for the user's photos.
- T5 — S8: dozen promo price (D, struck 12 × U, % off) on public menu cards and detail. Route: inline. Status: pending.

## Log

- L1 (2026-10-24) — User request: "Te voy agregando unas cosas mientras para la web: De momento, pongamos como que próximamente se va a poder hacer pedidos y se viene la web etc etc, o sea, que la gente entre, pero que todavía no pueda ver la web tal cual la estamos haciendo, sino que sea una sola pantalla de "EN construcción" con algún que otro detallito, sobreotdo porque quiero sacar y usar fotos reales, y todavía no las tengo y faltan algunos detalles. Luego, aprovecharemos la propiedad del producto de Vegetariano, los que lo tengan, le pondremos una hojita verde. TAmbién habría que cambiar el slogan de Te pinta una empanada, ya que ahora no son solo emapnadas, también pizzas, también no hagamos tanto énfasis en la oferta cruda, solamente que quede si nos preguntan y nada más, no ublicitarlo tanto, también mostrar que puede comer en el lugar, también el lugar ofrecemos servicio a mesa, bebidas, postre. Esta es la ubicación:  https://maps.app.goo.gl/CoZHPYbs9VHNMH9L6, hagamos un pequeño remodelo entonces de la web pública, así que hasta que termines de hacer todo, dejemos la web como en construcción, para el público, pero nosotros podemos seguir en desarrollo todo esto"
- L2 — Classified large: T4 waits on the user's photos. All tasks inline (no Writer trigger). Vegetarian is the `vegetariana` tag (migration `2026_08_11_000500` replaced `is_vegetarian`). Switch design: `config('public_site.open')` from `PUBLIC_SITE_OPEN`, defaulting to closed only when `APP_ENV=production`, so production closes without editing its env file and launch sets `PUBLIC_SITE_OPEN=true`.
- L3 (2026-10-24) — User addition: "Tambié, en el precio por docena, poner la típica de $20.000 (al lado el precio de docena original tachado y en grís) % descuento, o sea, hacer la promo visible de cuánto sale la docena de una variedad con su descuento respectivo (siendo el precio de 12 unidades sin el precio fijo de docena el "anterior" o de no ahorro)" → S8/T5.
- L4 — T1 RED: 4 of 6 `PublicSiteComingSoonTest` failed (missing `config/public_site.php`, home still `public/home`, storefront routes open). GREEN after the config, `EnsurePublicSiteIsOpen` on every public route but `/`, and `public/coming-soon` (no PublicLayout). Screenshots desktop 1366 and mobile 390 with `PUBLIC_SITE_OPEN=false`: no horizontal scroll; `/menu` → 302 `/`.
