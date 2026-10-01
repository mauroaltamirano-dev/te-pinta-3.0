# Publish the public storefront without exposing administration

## Authorized outcome

Serve the public website, catalog, cart and customer order submission on
`https://tepinta.com.ar`. Keep the administrative panel exclusively on its existing
private Tailscale origin. Both entry points use the existing order storage; no
separate forwarding into a Tailscale API is needed.

The user explicitly authorized a NEW user-initiated Cloudflare browser session to
create this tunnel and configure this domain. Do not discover or reuse ambient
Cloudflare, SSH, Tailscale or other authenticated access. Do not change unrelated
DNS records, domains, tunnels, account policies or private Tailscale routing.
No remote Git operations, push, PR, database migrations or real customer/test-order
creation are authorized by this deployment task.

## Design constraints

- Use the separately derived public application on loopback (`127.0.0.1:8084`),
  not the private Tailscale runtime on `8082` or mutable Sail/Vite port 8000.
- Put a default-deny public boundary in front of that runtime. Only public routes
  and their required assets may reach the origin. Administrative, authentication,
  debug, source, private-media and internal API paths must remain inaccessible,
  including encoded and normalized path variants.
- Preserve private-origin sessions, WebAuthn/passkeys, CSRF protection and current
  admin behavior. Public-host URLs must remain HTTPS and same-origin.
- Keep all generated credentials outside the repository, with restrictive file
  permissions. Never print certificates, tunnel secrets or tokens.
- Use the official Cloudflare release for a user-space connector, pin the version
  and verify its SHA-256 against the release asset metadata before executing it.
  Do not alter system-wide packages or existing services merely to log in.
- Retain a reversible local shutdown path. Do not switch DNS or activate the
  public connector before the public-only routing tests pass.

## Execution

| Item | Setting |
| --- | --- |
| Route | Delegated direct; 4+ file deployment/security mapping and 2+ non-trivial writer trigger |
| Primary / worker | User-confirmed Sol/High; native Terra/High lane verified |
| TDD | Strict for new local routing/security behavior; RED before implementation |
| Test runner | `bash tests/deploy/public-gateway/test.sh`; disposable Docker/mock origin, no real DB |
| RDD | On by default; respect candidate assessment and separate consent |
| Delivery | Local work-unit commits only; no push/PR; user selected `feature-branch-chain` for the current public UI release |
| Current base | `feat/public-site-identity`, `60c6e5c1fc6b4139d6d10d1f3a2b49129e83d369` |
| Shared changes | Existing dirty/staged work must not be overwritten, included in commits or normalized incidentally |
| Forecast | Approximately 355–450 source lines initially; actual CFT-1 is368 authored lines. Keep local chained slices; task-document additions belong to a later unit. |

## Tasks

- [x] **CFT-10 — Publish only the reviewed PM-4 public view.** User authorized
  native review of the mixed committed range and updating `tepinta.com.ar`.
  Native lineage `review-c2b7cf2595211868` approved and acknowledged target
  `sha256:aff35d77b0377f423f9cd803b4b5fa2b2682bf65d82411825e98495d6b6a2e9c`.
  Build from the currently deployed clean source `3cceb12` plus only the
  `fb1bb62` public layout/menu source diff, not from the dirty checkout or the
  full branch with unrelated Marketing/Admin changes. Preserve the active image
  under a new rollback tag before replacing only loopback public-app 8084;
  leave gateway 8083, private app 8082 and Cloudflare connector untouched.
  Verify image source identity, build, staged runtime and browser cart/divider
  behavior, then live HTTPS public paths/assets and private-path denials without
  submitting a live order. Pin the new image in Compose and document rollback.
  **Route:** delegated direct; read-only deployment mapping completed, root owns
  local cutover, bounded writer owns Compose/README. **TDD:** existing setting
  unresolved; ordinary checks, no invented RED/GREEN. **Forecast:** under 100
  authored repo lines for release pin/docs; existing PM-4 source/tests are
  already committed. **Checks:** scoped Compose config, gateway harness, clean
  build, local and public HTTP/browser checks, formatting/diffcheck, rollback
  readiness and native assessment of any new work-unit commit. No push/PR, DB
  migration, private login, Cloudflare credential access or fake order.

- [x] **CFT-1 — Public-only boundary and local security proof** (delegated).
  Map public routes, deploy/proxy configuration and host/session behavior. Add the
  smallest deploy-native public boundary and a regression harness with a mock
  origin. Prove that customer paths work and private paths, methods/headers/path
  variants cannot bypass the allowlist. Preserve the private runtime and routing.
  Checks: observed RED/GREEN, config validation, local HTTP positive/negative
  controls, formatting/lint applicable to the changed files, `git diff --check`.
- [x] **CFT-2 — New login, scoped tunnel and lifecycle** (delegated local files;
  primary owns the authenticated operation and user handoff).
  Install a verified connector locally, start a fresh browser login in isolated
  credential storage and wait for the user to authenticate. Create a scoped named
  tunnel and root hostname route only after CFT-1 verification. Add durable start,
  status and stop instructions/config with no secrets in the checkout. A hostname
  conflict must be surfaced, not removed with a force/overwrite flag.
  Checks: connector checksum/version, successful new login, ingress validation,
  tunnel health, exact DNS route and restart/stop behavior. Record pending user
  interaction and any unavailable runtime checks honestly.
- [ ] **CFT-3 — Verify public ordering and private administration** (delegated).
  Validate HTTPS pages, built assets, images, price preview, cart/CSRF behavior and
  rejection of administrative/auth/internal paths on the public hostname. Use
  test storage for order-persistence proof; do not create fake orders in the live
  database. Confirm the private panel is unchanged without inspecting or reusing
  an unauthorized Tailscale session. Document any manual authenticated check that
  still requires the user. Update deployment/security docs with actual results.

- [x] **CFT-9 — Pin and document the revised public UI release** (delegated direct).
  Record the isolated PM-3 source commit and verified live public image as the
  Compose default; update the local rollback image and operational instructions.
  Keep gateway8083, private8082 and tunnel service unchanged. Checks: default
  and rollback Compose config, scoped docs formatting, `git diff --check`,
  exact scoped commit, and native committed-only RDD assessment. Route:
  delegated direct because the Compose, README and tracker updates are three
  non-trivial files. Delivery: `feature-branch-chain`, no push or PR; this
  documentation/pin commit is one local work-unit slice. Do not mark CFT-3
  complete without authenticated private-order confirmation.

- [x] **CFT-8A — Complete the immutable public source cut** (delegated direct).
  User confirmed publishing only completed/verified public-site changes now;
  derived pricing and quick-create remain deferred. Include the existing missing
  menu background PNG as one bounded tracked asset and add a regression checking
  the actual asset response, not only its DOM URL. Do not stage any other dirty
  artwork or source. Observe the focused test and existing public checks; commit
  the asset and test together. Forecast: under100 authored text lines plus binary.
- [x] **CFT-8B — Build and stage the clean recent public image** (delegated direct).
  Use a Git-archived immutable commit, not the 179-path mutable checkout or the
  old overlay-only Dockerfile. Build a separate full image from the existing
  operational Dockerfile and locked dependencies, with no environment secrets in
  the build context. Verify the source manifest and resulting image contents,
  run affected/full quality checks, and stage via a separate loopback-only public
  app instance before cutover. Preserve the current image for rollback. The live
  DB already has both nullable Product metadata columns and migration row (read-
  only Laravel checks on 2026-09-30), so no migration is needed or authorized.
  Forecast: about100–250 authored lines if a release harness is necessary.
- [x] **CFT-8C — Cut over and verify customer-only release** (primary operator).
  Change only the public app image after staged checks and applicable native
  review/consent, keeping gateway8083 and private admin8082 unchanged. Verify
  HTTPS pages, asset/image 200, cart/CSRF/invalid preview, public denials,
  connector health and fast rollback to the saved prior image. Never create a
  fake live order; genuine-order admin confirmation remains CFT-3. Record the
  final source/image IDs and observed checks. Forecast: docs/tracker only.

- [x] **CFT-4 — Same-origin public product images** (delegated; two non-trivial serializers plus tests trigger).
  User explicitly authorized fixing image links. Return safe request-relative
  product image URLs for catalog and featured product, preserving null images,
  public payload shape and private APP_URL. Add isolated tests (do not touch
  existing staged integration tests) for private-config/public-host independence
  and unsafe path handling. No image upload changes or private runtime mutation.
  Strict TDD: observed RED, GREEN, refactor; Sail Pest runner per docs/TESTING.md.
  Checks: focused and affected Products/Marketing suites, scoped Pint, PHPStan,
  git diff --check. Frontend checks N/A unless frontend files change.
  Forecast: approximately 80–160 authored lines, separate local chained slice
  after reviewed1295785; source commit excludes task tracker and unrelated work.
  Runtime publication remains pending: existing baked image needs a narrowly
  verified immutable deployment, not a full dirty-source rebuild.

- [x] **CFT-5 — Narrow immutable public runtime** (delegated; deployment/test/patch multi-file trigger).
  Derive a separate public image from verified local operational RepoDigest
  `tepinta3-operational@sha256:e2c78c01075a5a774cb1164a8bc93736a68994dc3b6991f959df603aada2a519`.
  Apply checksum-gated minimal edits to the two baked serializers and include the
  reviewed Products helper; never copy newer full source or rebuild dirty checkout.
  Verify autoload even under optimized/classmap settings. No new dependencies.
  Run public app only on loopback8084, existing DB network via existing local
  operational env-file reference without printing secrets. Read-only public-media
  mount and separate logs; no private-media mounts, scheduler, migrations, or
  private-runtime/Tailscale changes. Gateway8083 routes to8084 only after proof.
  Strict TDD for overlay/path/autoload guard: observed RED then GREEN.
  Checks: checksum rejection, derived image PHP/autoload/url proof without live DB,
  Compose validators, shell syntax, image and lifecycle smoke tests, public allowlist
  harness. Root owns start/stop/live HTTP handoff, no live test orders.
  Forecast250–400 authored lines as separate local chained unit; no push/PR.
  Native review assessment before publication; earlier image medium slice174 lines
  still pending since last reviewed boundary1295785.

- [x] **CFT-6 — Public runtime restart and safe rollback** (delegated).
  Add automatic restart for the separate public app and a focused Compose assertion.
  Document rollback order: disable the public connector/tunnel first, then stop
  gateway and public app. Never repoint the public hostname to private8082.
  No Cloudflare operation or service start in this task. Observe RED/GREEN for
  the config check; rerun overlay/gateway harness, Compose validation, diffcheck.
  Commit separately, assess from last acknowledged boundary f154719.
  Forecast20–60 authored lines; cached local chain, no push/PR.

- [x] **CFT-7 — Gateway accepts actual public response headers** (delegated).
  Local 8083→8084 smoke found GET `/` and `/cart` return 502 although direct
  public app returns 200. Gateway error is upstream response header too large:
  root/card headers are ~4.1/4.6 KB. Set a bounded proxy header buffer sized for
  current Laravel CSRF/session/Link headers without weakening default-deny rules.
  Add a failing large-header regression to the disposable gateway harness before
  production config change, then GREEN and repeat real local smoke. No tunnel/DNS,
  no live orders. Checks: root/cart/menu/catalog/images 200, private routes denied,
  no unintended origin access, nginx config/harness/Compose validation, diffcheck.
  Commit separately, assess from last acknowledged boundary f154719.
  Forecast30–90 authored lines; cached local chain, no push/PR.

## Progress and verification

- **CFT-10 public view published and verified** (2026-10-01): The existing
  deployed source `3cceb12` and only the two PM-4 runtime source hunks from
  `fb1bb62` became clean release commit `9a4dac270136c6100d5dad13105d87a648613c0d`
  on `release/public-pm4`; the release diff hash matched the original PM-4
  source diff. The pinned local Sail base built
  `tepinta3-public-app:release-9a4dac2` (ImageID
  `sha256:9b5675ca64de92b0c94a1a0cff04b0b0f5aa428dc243f734720e9f7304be6894`).
  Staged HTTP root/menu/cart/catalog/assets were 200, source hashes inside
  the image matched the release commit, gateway allowlist harness and Compose
  config passed, and staged Chromium verified the full-width category divider,
  56 px floating cart, 1683/390/320 px bounds, cart navigation and no overflow.
  The original production image was tagged
  `tepinta3-public-app:rollback-before-pm4-20261001` with matching ImageID
  `sha256:37648c04428295c4fc7fe1be07e70832123fe0687c196748a7ae9efb48fe9069`.
  Only public-app on loopback 8084 was recreated; gateway/private container IDs
  and active Cloudflare connector were unchanged. Live HTTPS root/menu/cart,
  catalog and menu artwork returned 200; admin/login/up/api returned 404.
  Live Chromium verified 43 built asset responses, the full-width divider and
  floating cart at 1683/390/320 px, navigation to cart, no overflow and zero
  JS/network errors. Manifest URL returned 404 at the public boundary while
  actual hashed assets loaded successfully. Private loopback `/up` remained
  200. No order, migration, credential access, push or PR occurred. The staged
  container was removed; both release and immediate rollback image tags remain.
  Compose default pin and README were updated in work-unit commit
  `043842f` (`chore(deploy): pin floating-cart public release`); scoped Compose,
  README Prettier and `git diff --check` passed. The original mixed committed
  range received native approval and exact acknowledgement. The CFT-10
  pin/docs commit assesses MEDIUM, 102 authored lines, `under_budget`; its
  separately derived release-source commit `9a4dac2` assesses MEDIUM, 89
  authored lines, `under_budget`. Neither assessment is due for a new review.
  Post-cutover image `npm run lint:check` passed. Image `npm run types:check`
  failed with seven shared Admin/auth TypeScript errors, including an unresolved
  `@inertiajs/core` type; the previous live `release-3cceb12` image produced
  identical errors. This is a pre-existing image baseline failure, not a PM-4
  regression. No unrelated type fixes were added to the public release.

- **CFT-9 public UI pin verified** (2026-09-30): User selected
  `feature-branch-chain`; this is a local work-unit slice, without push or PR.
  Commit `d466f1f8327924da614f8fa4ee575125e8498ae5` pins Compose and
  operational README to `tepinta3-public-app:release-3cceb12` (ImageID
  `sha256:37648c04428295c4fc7fe1be07e70832123fe0687c196748a7ae9efb48fe9069`).
  Source is isolated PM-3 commit `3cceb12b48e1b6bc9969b0d119399ee68a24e92e`
  on clean base `de09382`; the prior image is retained as
  `tepinta3-public-app:rollback-before-ui-20260930` (ImageID
  `sha256:66fce021afda0b48bee4bcb354a4b4eba047d4d59c3eebf1deabb8737fd83250`).
  Image build used frozen Composer/pnpm dependencies and passed Vite build;
  candidate pre-cutover HTTP `/`, `/menu`, `/cart`, artwork and manifest returned
  200. Gateway regression passed. After cutover, local gateway and public HTTPS
  `/`, `/menu`, `/cart` and artwork returned 200; public `/admin`, `/login`,
  `/up` and `/api` returned 404. Live Chromium at 1440px, 390px and 320px
  showed a full-width hero, loaded artwork, 10 cards, zero horizontal overflow
  and no JavaScript errors. Tunnel remained active; gateway8083 and private8082
  were unchanged. No order was submitted. Focused checkout E2E remains blocked
  by unresolved `pgsql`, so CFT-3 is not complete. Both default and rollback
  Compose configs and scoped README Prettier passed; tracker-wide Prettier fails
  on its pre-existing table/list formatting and was not rewritten wholesale.
  `git diff --check` passed. Native committed-only assessment from reviewed
  boundary `a1c815f` returned MEDIUM, 1248 authored lines across the accumulated
  slice, `slice_budget_reached`; review is due and requires candidate consent.
  No native review or approval is claimed. The original unrelated staged patch
  was preserved byte-for-byte (its diff against prior HEAD `c7d1bb5` retained
  SHA-256 `7f2d5791bf74fbafff58632b54e3431be6903c9fca96db7fbf0d1e0fc25927d8`).

- **CFT-8C published and smoke-verified** (2026-09-30): Deployment configuration/work-unit commit `04ccc97e8529d58d79cca0e7f0f5ebe1a08594a3` pins `tepinta3-public-app:release-de09382` and prevents Compose rebuilding or pulling mutable checkout content. Default and rollback override Compose rendering, public-app harness, gateway allowlist harness, Prettier and diffcheck passed. Native committed-only assessment from `a1c815f`: MEDIUM,227 authored lines, `under_budget`, no review due. Tagged prior ImageID as `tepinta3-public-app:rollback-372050c8`, then recreated only `public-app-public-app-1` on loopback8084. New running ImageID is `sha256:66fce021afda0b48bee4bcb354a4b4eba047d4d59c3eebf1deabb8737fd83250`, restart `unless-stopped`; gateway8083 and private8082 were not reconfigured. Live HTTPS `/`, `/menu`, `/cart`, catalog, options, menu artwork, built CSS/JS/fonts and sampled product PNG returned200; `/admin`, `/login`, `/up`, `/api` returned404. A real-browser mobile cart added a Fatay dozen, loaded artwork, received successful raw-price preview with no JS errors and **zero order submissions**; a CSRF-authenticated empty preview returned expected422. Connector active/enabled, `Linger=yes`; private `/up` and `/login` remained200, unauthenticated `/admin` redirected302. No reboot, real customer order, or authenticated Tailnet admin proof was performed; CFT-3 remains pending. Rollback image and command are documented in `deploy/cloudflare/public-app/README.md`.

- **CFT-8A completed**: Clean source cut includes existing menu artwork and HTTP response regression in commit `6a84243b2eeb687b0bfedd5dfb6f8c289401c8af`; public card CSS nested under the admin shell was fixed in `de09382654918831bc61e99a209d7714c57dd0cf`. Both commits used a temporary index, preserving unrelated staged/dirty changes. Focused artwork Playwright test passed against the final staged gateway (1/1); final browser at 390px loaded the artwork and correct card colors.
- **CFT-8B staged and verified**: Clean detached worktree at `de09382` built full image `tepinta3-public-app:release-de09382` (`sha256:66fce021afda0b48bee4bcb354a4b4eba047d4d59c3eebf1deabb8737fd83250`) from the pinned local base. No `.env` or Vite hot marker is baked; manifest, artwork hash and image helper are present. Final app/gateway staged only on loopback8087/8088. Root, menu, cart, catalog, checkout options and artwork all returned200; admin/login/up returned404. Clean-source PHP tests:81 passed/733 assertions; PHPStan passed; Prettier, TypeScript and ESLint passed; full image build passed. No live order submitted. Native committed-only assessment from passive boundary `a1c815f`: MEDIUM,111 lines, `under_budget`, no review due. At staging time the older public image still ran on8084; private8082 was unchanged.


- **Continuous-uptime and CFT-8 scope update**: User wants an always-available public site and a newer release. Local `loginctl enable-linger` readback showed `Linger=yes`; connector remains active/enabled and HTTPS root 200. The older public image must not be rebuilt from the 179-path dirty checkout. Tracker update commit `70fbbe712c9937cc678579f3df01e073c93caf26`; native committed-only assessment from passive boundary `a1c815f` is MEDIUM, `under_budget` (73 authored lines including previous test work), no review due yet. No reboot or new image cutover was attempted.

- **CFT-2 local work-unit commit** `0b2195f4f1b6fde0f43d98ece017213998c262a0` (`docs(deploy): record scoped public tunnel activation`), 2 paths, 335 authored lines. Gateway and public-app harnesses, both Compose validators and diff check passed. Native committed-only assessment from reviewed boundary `0fbf525` returned PASSIVE (`non_executable_only`), review_due=false; no review ceremony required. This documentation commit records operational state; live Cloudflare tunnel/DNS are external state, not Git changes.

- **CFT-2 published and verified** (2026-09-29). Fresh isolated browser login
  succeeded using only the new HOME and pinned, checksum-verified cloudflared
  2026.9.3. Created named tunnel `tepinta-public-storefront` with UUID
  `c099574d-81fc-4157-bbe1-1c7d0c8fe3ec`. Mode-600 local ingress matches only
  `tepinta.com.ar` to `127.0.0.1:8083`; all other hostnames return 404.
  User systemd service `tepinta-public-cloudflared.service` is enabled, active,
  and restart-on-failure; tunnel info showed a registered connector. Apex DNS
  CNAME route was added without overwrite; authoritative and public resolvers
  returned Cloudflare edge addresses. Stop/start lifecycle was observed and live
  HTTPS recovered to 200 after a brief edge convergence 502. On 2026-09-30,
  user lingering was enabled (`Linger=yes` read back), so the enabled connector
  user service starts before desktop login after reboot. No reboot was performed.
  Credentials/config are outside the repository. No private Tailscale routing
  or unrelated DNS/tunnels were changed. Local router/system DNS still gave an
  empty/negative answer at immediate test time; edge checks used public DNS
  address with the correct HTTPS SNI/Host (`curl --resolve`).
- **CFT-3 partial live HTTPS proof**: Direct public DNS and valid HTTPS now
  resolve without override. Root, menu, cart, catalog, checkout options,
  sampled CSS/JS/font assets, and product PNG returned 200. `/admin`, `/login`,
  `/up`, `/api`, private media and encoded traversal returned 404. TLS verified
  and checkout cookies were Secure. A fresh cart session sent a CSRF-authenticated
  invalid price preview and received expected 422; no live order was submitted.
  Local private loopback8082 still served `/up` and `/login` (200) and redirected
  unauthenticated `/admin` to login (302); no Tailscale session was inspected.
- **CFT-3 local test work-unit** `b33741e9bef75a264fe7bb80589e775727b153a4` (`test(orders): prove public checkout reaches admin view`), 2 paths, 39 authored lines. Native committed-only assessment from passive boundary `a1c815f` returned MEDIUM, `under_budget`; no review due yet.
- **CFT-3 isolated order-persistence proof**: Existing checkout suite passed
  17 tests/170 assertions. A new cross-boundary regression now creates a public
  order in guarded test storage, then confirms the same public ID in an
  authenticated admin Orders index, selection and detail. Parent reran full
  OrderCheckoutHttpTest:18 tests/195 assertions; scoped Pint and PHPStan passed.
  Manual private-host authentication and a genuine customer order appearing in
  the live panel remain unverified; do not create a fake live order.


- **CFT-6 locally verified**, commit `76c71bc5b7f4c8e9d5a685deb38dda8552c1c369`
  (`fix(deploy): restart and safely withdraw the public app`),3 files,+28/-4.
  Strict RED: rendered Compose lacked restart policy; GREEN: `unless-stopped`
  asserted from rendered config. README rollback disables/confirms public connector
  down before gateway and public app; no fallback to private8082. Parent reran app
  and gateway harnesses, Compose validators, Bash syntax and diffcheck: PASS.
- **CFT-7 locally verified**, commit `0fbf52508aac4f28a5caae8afabf9ef42a99a110`
  (`fix(deploy): accept public checkout response headers`),3 files,+23/-0.
  Real loopback smoke initially found GET `/` and `/cart` gateway502 despite direct
  app200; nginx said upstream response header too big. Mock 5KB header observed
  RED502 then GREEN200 with bounded16k proxy/busy buffers. Parent reran both
  harnesses, Compose/syntax/diff checks: PASS. Root retested local gateway:
  `/`, `/menu`, `/cart`, catalog, options all200;10 catalog images relative and
  sampled PNG200; CSS/JS/woff2 200; `/admin`, `/login`, `/up`, manifest404;
  invalid CSRF-authenticated price preview422 (no order created). Both services
  stopped after smoke; private8082 unchanged, no tunnel/DNS activated.
- Native assessment since last acknowledged boundary f154719 for CFT-6+CFT-7:
  HIGH,6 paths,55 lines. Preflight and relay bound immutable target
  sha256:ca26fefbaa979de0c25bb74857b53aa1d2fa007be48d6e9de01260ab6401fe94
  and returned consent_required. User granted this exact review. Four compiled
  lenses returned zero findings; native review terminal APPROVED, lineage
  review-2d0d74895f326d93, exact acknowledgement consumed. Last reviewed
  boundary advanced to0fbf525. Reviewer inspection did not run commands;
  local functional evidence is recorded separately above.

- **CFT-5 prepared and locally verified**, commit
  `f154719ebf5a49dc4830d318934f07682b77a698`
  (`feat(deploy): prepare an isolated public application image`),8 files,
  359 additions +1 deletion (360 authored lines). Derived local image
  `sha256:372050c8d42d0ab5aa97a8de2abe3fe7928ecdb613e4c8bea816f19949a476fa`
  built offline from exact legacy-builder ImageID, not a mutable tag or dirty rebuild.
  No service started; port8084 not listening; private8082 and Tailscale unchanged.
- CFT-5 strict behavioral RED: inert overlay accepted invalid checksums and
  authoritative Composer classmap failed to load new helper. BuildKit local
  RepoDigest lookup failure was separate environment compatibility, not RED;
  scoped DOCKER_BUILDKIT=0 FROM exact ImageID resolved it without network.
  Root inspected eight-file diff and reran overlay harness, unchanged-layer audit,
  fixture no-network URL/autoload proof, gateway allowlist harness, PHP/Bash syntax,
  both Compose validators and diffcheck: PASS. No actual DB/order or real-domain
  test, no published service. Frontend lint/types/build N/A: no frontend edit;
  Laravel test suites were run on CFT-4 source slice, not on container deployment.
- Native committed-only assessment from reviewed gateway1295785 through CFT-4+CFT-5:
  HIGH,15 files,534 authored lines, review_due=true. Preflight fixed unrelated
  untracked exclusion; relay returned consent_required for immutable target
  sha256:0c9be9b35028a4991b1f7ca1d645356b54a20899b446a63d398d4bc2a66d086c.
  Earlier user consent covered gateway-only target, not this new candidate.
  User granted exact new review; native lineage review-df0f2e4646de5b2b
  completed four compiled lenses and reached terminal APPROVED; exact
  acknowledgement consumed. Reviewed boundary is f154719. Two informational
  resilience findings (missing restart and unsafe rollback order) are separate
  CFT-6 work, not a reopened native receipt. Publication waits for CFT-6 and
  live checks. Exact unrelated staged entries preserved by custom index commit.


- **CFT-4 complete in source**, commit `474ffdc492ba4263b10e92d220a95c6318e2b6cc`
  (`fix(public): serve product images from the current origin`),7 files,
  166 additions +8 deletions (174 authored lines), separate local chained slice.
  Added one tiny Products-owned safe-path helper shared by public catalog/detail
  and featured-product serializers. Only canonical jpg/jpeg/png/webp product
  basenames emit relative URLs; null/unsafe inputs return null. Private APP_URL,
  storage configuration, admin serializers and baked runtime remain unchanged.
- CFT-4 strict RED: worker observed13 tests11 failures before implementation.
  Final focused16 tests21 assertions pass. Parent inspected actual source/docs/tests
  and reran Products+Marketing:65 passed589 assertions; scoped Pint6files and full
  PHPStan passed; diffcheck passed. Frontend lint/types/build/E2E not run: PHP-only
  behavior, no frontend changes. Real-domain/live-image checks pending deployment.
- Existing staged MarketingIntegrationTest contained unrelated editorial tests.
  Root custom-index commit included only HEAD-existing image expectation/import
  changes; updated the staged-new expectation locally, preserving all unrelated
  index entries byte-for-byte. Evidence outside repo image-commit-cca_ugkj.
- CFT-4 native assessment first required untracked declaration; current status
  inventory and explicit exclusion resolved it. Actual result MEDIUM,174 lines,
  review_due=false,reason under_budget. No new receipt claimed; reviewed boundary
  remains1295785 until the medium slice reaches a later review boundary.
  Running feature authored total542; cached local feature-branch-chain keeps
  gateway and image-link commits as separate slices, no push/PR.

- **CFT-1 implemented and verified**, local commit
  `1295785fee9fc3e2efb4a36de4d478090a105088`
  (`feat(deploy): isolate the public storefront gateway`),6 new files,368 additions.
  Root inspected all6 files and reran the final harness successfully. The original
  pre-existing staged patch is byte-for-byte unchanged after the scoped commit.
- Strict RED1: permissive stub let GET/admin reach the origin (HTTP200).
  Strict RED2: spoofed forwarded port/SSL reached the mock. GREEN after both fixes.
- Root checks PASS: gateway mock harness, both Compose config validators,
  Bash syntax and git diff --check. Worker additionally validated nginx -t in an
  isolated non-root/read-only/network-none container.
- The gateway is host-networked but binds only127.0.0.1:8083; origin remains8082.
  Only loopback cloudflared is trusted for validated CF-Connecting-IP; spoofed
  host/proto/port/forwarding headers are stripped or replaced. Tests cover distinct
  IPv4/IPv6 visitors, malformedIP fallback, positivepublic routes/assets, denied
  private paths/methods/normalization variants and no origin requests for denials.
- Production gateway is NOT started. No tunnel created, no DNS changed.
  Administrative PWA manifest is denied. The existing baked runtime still returns
  private APP_URL image URLs; correcting/deploying only reviewed public changes
  without importing unrelated dirty work remains pending.
- Official connector2026.9.3 installed outside repository and SHA-256 verified:
  `77e26d8d900e0b8469f416239d14b5f296525fdf79fee6f511ef55609e3fbac2`.
  The previous isolated login process10318 expired with exit1; no new certificate
  exists. Refresh browser login only after the current local gate is resolved.
- RDD is ON/default. The committed-only6-file candidate was assessed HIGH
  (`executable_mode`, `process_boundary`, `shell_source`),368 changed lines.
  Assessment initially required explicit exclusion of unrelated untracked files;
  preflight status plus inventory-bound exclusion resolved it. Status does not
  accept --json; corrected invocation has --contract/--next-transition.
- Native preflight returned review.start with consent=relay; executed exactly and
  received **consent_required**, not a review or approval. Target
  `sha256:5369df0d77ba74a3f6427a506dd80aa5705734c5de00be7030060ad58e725f86`,
  lineage `review-809c3b6bb9f89fc7`, boundary remains the original base until
  reviewed/acknowledged. Six committed gateway paths only; existing dirty work and
  this task document are excluded from that immutable candidate.
- Native review status saved outside the repo at
  `~/.local/state/tepinta-cloudflare/gateway-review-status.json`. Recheck current
  untracked inventory before submitting granted/declined consent. Never change
  review mode or infer approval from functional tests/a checkbox.
- Full Laravel/PHPStan/Pint/frontend build/E2E gates have not run for this deployment
  feature; CFT-1 adds only deploy configs and shell/mock tests. Application image
  fixes and real-domain/CSRF/order-persistence/private-panel verification are pending.
- Runtime supplied registered session identity after compaction; memory writes now
  use the exact authoritative binding. Earlier manual-save mirrors remain valid.
- User granted gateway review. Native start created lineage review-809c3b6bb9f89fc7
  in reviewing state. all four selected lenses
  completed through compiled native Codex capture. Terminal approved and exact
  acknowledgement consumed; reviewed boundary is commit1295785. Reviewers inspected
  patches only (functional harness was run separately by root). Two nonblocking
  informational advisories remain for later work: doubled-leading public slash
  guard and mock Docker log-count timing. They do not reopen this native receipt.

## Next step

CFT-2 is published at `https://tepinta.com.ar` through the scoped, active tunnel.
CFT-3 has isolated same-order test proof; still confirm a genuine customer order
in the private admin without creating a fake live order. The CFT-10 PM-4 public
view is live; its source and immediate rollback image are pinned locally.
PM-4 isolated public E2E passed 30/30 on a guarded test database. Observe
unattended startup
at a future reboot; the user service is enabled with `Linger=yes`. Keep the public
gateway pointed only to loopback8084.
