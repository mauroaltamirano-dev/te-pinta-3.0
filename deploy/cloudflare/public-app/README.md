# Narrow public application image

This image derives from the verified local operational ImageID
`sha256:e2c78c01075a5a774cb1164a8bc93736a68994dc3b6991f959df603aada2a519`.
It checks the two frozen baked serializer checksums and the reviewed image helper
checksum before applying only the same-origin product-image URL overlay. It does
not copy the working tree's serializers, storage, environment file, Composer
dependencies, or any private application files.

The local Docker daemon currently resolves that content-addressed base with the
legacy builder only. Build with the exact local base identity after running the
disposable proof:

```bash
bash tests/deploy/public-app/test.sh
DOCKER_BUILDKIT=0 docker compose -f deploy/cloudflare/public-app/compose.yaml build public-app
```

`compose.yaml` references the existing operational environment file by path; it
does not contain or print its values. The public app publishes only
`127.0.0.1:8084:80`, joins the existing database network, mounts public media
read-only, and writes logs only to `public-app-logs`. Its inherited entrypoint is
the application server; this Compose service adds no scheduler, migration command,
or private-media mount.

After separate approval to start the new local service, use only these lifecycle
commands:

```bash
docker compose -f deploy/cloudflare/public-app/compose.yaml up -d --no-build public-app
docker compose -f deploy/cloudflare/public-app/compose.yaml ps public-app
docker compose -f deploy/cloudflare/public-app/compose.yaml stop public-app
docker compose -f deploy/cloudflare/public-app/compose.yaml down
```

## Safe rollback

Never repoint the public gateway or hostname to private `127.0.0.1:8082`. If the
public service must be withdrawn, first disable the public connector/tunnel and
confirm that it is down. Then stop the gateway, then stop the public app:

```bash
docker compose -f deploy/cloudflare/compose.yaml stop gateway
docker compose -f deploy/cloudflare/public-app/compose.yaml stop public-app
```

The temporary public-domain outage is intentional: it prevents a public fallback
to the private Tailscale application. This rollback never changes
`tailscale-app-1`; it affects only the public connector, gateway, and public app.
Tunnel operations remain separately authorized and are not performed by these
commands.
