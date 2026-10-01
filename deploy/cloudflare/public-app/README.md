# Public application release

The public Compose service is pinned to the locally built, tested storefront
release `tepinta3-public-app:release-9a4dac2` (ImageID
`sha256:9b5675ca64de92b0c94a1a0cff04b0b0f5aa428dc243f734720e9f7304be6894`).
Its source is clean commit `9a4dac270136c6100d5dad13105d87a648613c0d`:
the previously deployed `3cceb12` release plus only the floating-cart and
full-width menu category changes from `fb1bb62`. It does not include the mutable
working tree or unrelated changes on the feature branch. The previous
checksum-gated image overlay and its Dockerfile remain for historical
verification; **do not run `docker compose build` from this checkout** to
prepare a new release. Compose has no `build` directive and will never pull an
image from a registry.

## Prepare and start

Build future releases from a clean detached worktree at an approved commit,
using `deploy/tailscale/Dockerfile` and the verified local Sail base ImageID.
Before starting, verify that the expected image is present:

```bash
docker image inspect tepinta3-public-app:release-9a4dac2 --format '{{.Id}}'
# Expected: sha256:9b5675ca64de92b0c94a1a0cff04b0b0f5aa428dc243f734720e9f7304be6894
docker compose -f deploy/cloudflare/public-app/compose.yaml config --quiet
docker compose -f deploy/cloudflare/public-app/compose.yaml up -d --no-build --pull never public-app
docker compose -f deploy/cloudflare/public-app/compose.yaml ps public-app
```

The service listens only on `127.0.0.1:8084`, joins the existing database
network, mounts public media read-only, and stores logs in `public-app-logs`.
It uses the existing operational environment file by path without embedding it
in the image or Compose file. `restart: unless-stopped` keeps the public app
running after a daemon or host restart. It does not run a scheduler or database
migrations and does not mount private media. The gateway remains a separate
default-deny service on loopback `8083`; the private Tailscale app on `8082` is
not involved.

## Roll back the application image

The immediately preceding public image is tagged locally as
`tepinta3-public-app:rollback-before-pm4-20261001` (ImageID
`sha256:37648c04428295c4fc7fe1be07e70832123fe0687c196748a7ae9efb48fe9069`).
Retain that tag while this release is active. To restore it, override only the
public app image; do not change the gateway or point it at private `8082`:

```bash
docker image inspect tepinta3-public-app:rollback-before-pm4-20261001 --format '{{.Id}}'
# Expected: sha256:37648c04428295c4fc7fe1be07e70832123fe0687c196748a7ae9efb48fe9069
PUBLIC_APP_IMAGE=tepinta3-public-app:rollback-before-pm4-20261001 \
  docker compose -f deploy/cloudflare/public-app/compose.yaml up -d --no-build --pull never public-app
```

The image override is per command. Repeat it for later Compose operations while
the rollback is active; an unqualified `up` selects the release image again.
The older `tepinta3-public-app:rollback-before-ui-20260930` tag (ImageID
`sha256:66fce021afda0b48bee4bcb354a4b4eba047d4d59c3eebf1deabb8737fd83250`)
remains a separate historical rollback point.
Recreating the public app may briefly interrupt storefront requests, but cannot
expose the private panel through the unchanged gateway.

## Withdraw public access

If the public service must be withdrawn, first stop the scoped public connector
and confirm that it is inactive; then stop the gateway and public app:

```bash
systemctl --user stop tepinta-public-cloudflared.service
systemctl --user is-active tepinta-public-cloudflared.service # expect: inactive
docker compose -f deploy/cloudflare/compose.yaml stop gateway
docker compose -f deploy/cloudflare/public-app/compose.yaml stop public-app
```

This intentionally takes the public domain offline instead of falling back to
the private Tailscale runtime. Tunnel operation remains separately authorized;
these commands are instructions only, not an automatic cutover.
