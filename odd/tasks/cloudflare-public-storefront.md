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

- Use the documented production-like loopback runtime (`127.0.0.1:8082`), not the
  mutable Sail/Vite development server on port 8000.
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
| Delivery | Local work-unit commits only; no push/PR; retain the user's current chained work-unit preference |
| Current base | `feat/public-site-identity`, `60c6e5c1fc6b4139d6d10d1f3a2b49129e83d369` |
| Shared changes | Existing dirty/staged work must not be overwritten, included in commits or normalized incidentally |
| Forecast | Approximately 355–450 source lines initially; actual CFT-1 is368 authored lines. Keep local chained slices; task-document additions belong to a later unit. |

## Tasks

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

- **CFT-2 published and verified** (2026-09-29). Fresh isolated browser login
  succeeded using only the new HOME and pinned, checksum-verified cloudflared
  2026.9.3. Created named tunnel `tepinta-public-storefront` with UUID
  `c099574d-81fc-4157-bbe1-1c7d0c8fe3ec`. Mode-600 local ingress matches only
  `tepinta.com.ar` to `127.0.0.1:8083`; all other hostnames return 404.
  User systemd service `tepinta-public-cloudflared.service` is enabled, active,
  and restart-on-failure; tunnel info showed a registered connector. Apex DNS
  CNAME route was added without overwrite; authoritative and public resolvers
  returned Cloudflare edge addresses. Stop/start lifecycle was observed and live
  HTTPS recovered to 200 after a brief edge convergence 502. `Linger=no`, so
  after a machine reboot the user service may not start until user login; this
  uptime limitation remains to resolve if unattended pre-login service is needed.
  Credentials/config are outside the repository. No private Tailscale routing
  or unrelated DNS/tunnels were changed. Local router/system DNS still gave an
  empty/negative answer at immediate test time; edge checks used public DNS
  address with the correct HTTPS SNI/Host (`curl --resolve`).
- **CFT-3 partial live HTTPS proof**: Root, menu, cart, catalog and checkout
  options returned 200; catalog product PNG returned 200. `/admin`, `/login`,
  `/up`, `/api`, private media and encoded traversal returned 404. TLS verified
  and checkout cookies were Secure. No live order submitted; test-storage
  order-persistence and manual private admin confirmation remain pending.


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
Continue CFT-3: verify order persistence against test storage without creating a
fake live order, and obtain any needed user-side confirmation that the private
Tailscale admin still shows genuine customer orders. Resolve unattended startup
only if pre-login availability after reboot is required; current user service is
enabled but `Linger=no`. Keep the public gateway pointed only to loopback8084.
