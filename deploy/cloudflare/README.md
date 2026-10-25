# Public storefront gateway

This directory contains the local boundary for the public storefront at
`https://tepinta.com.ar`. It is **not** an administrative reverse proxy and does
not contain a Cloudflare connector token, certificate, or account configuration.

The gateway runs with Linux host networking but listens only on
`127.0.0.1:8083`; it proxies only to the isolated public application at
`127.0.0.1:8084`. The existing private Tailscale application remains on
`127.0.0.1:8082` and is never a public fallback. The public application is a
separate, clean-source Laravel release image that shares the existing database
but does not replace the private runtime. Its release and image rollback are
documented in [public-app/README.md](public-app/README.md).

The gateway trusts `CF-Connecting-IP` only from its loopback direct peer and uses
that validated address for the upstream `X-Forwarded-For` and `X-Real-IP` headers.
This assumes that only the local Cloudflare connector can reach `127.0.0.1:8083`;
do not publish the gateway port or place another proxy in front of it.

Only public storefront pages, catalog, cart, checkout endpoints, product images,
and required public assets are allowed. Admin, authentication, API, health,
Visitors that reach Cloudflare over plain HTTP (`CF-Visitor` scheme `http`) get a 301 to
`https://tepinta.com.ar` before the origin is contacted, and every response carries
`Strict-Transport-Security: max-age=31536000`: an `http://` page cannot run the `https://`
module scripts, so phones and in-app browsers that open `http://` saw a blank page.

Instagram media, generic storage, and the existing administrative PWA manifest are
denied; storefront pages do not link the manifest. Do not change the operational `APP_URL`: it remains the private Tailscale
WebAuthn/passkey origin.

Run the disposable regression harness before any local gateway start:

```bash
tests/deploy/public-gateway/test.sh
```

After a passing test, a local operator can start or stop the reversible public
application and gateway with:

```bash
docker compose -f deploy/cloudflare/public-app/compose.yaml up -d --no-build --pull never public-app
docker compose -f deploy/cloudflare/compose.yaml up -d
docker compose -f deploy/cloudflare/compose.yaml down
docker compose -f deploy/cloudflare/public-app/compose.yaml down
```

Those commands do not publish a hostname. Only after both local services are
ready should the operator start or restart the verified scoped named-tunnel
connector. It is a local user systemd service named
`tepinta-public-cloudflared.service` and serves only the exact apex hostname
`tepinta.com.ar`. Its configuration, credentials, and isolated HOME remain outside
this repository. Operators may inspect or control that local service with:

```bash
systemctl --user status tepinta-public-cloudflared.service
systemctl --user restart tepinta-public-cloudflared.service
```

## Safe rollback

Never repoint the public hostname or gateway to private `127.0.0.1:8082`. To
withdraw the public storefront, stop and confirm the connector is down first,
then stop the gateway, then stop the public application:

```bash
systemctl --user stop tepinta-public-cloudflared.service
systemctl --user is-active tepinta-public-cloudflared.service # expect: inactive
docker compose -f deploy/cloudflare/compose.yaml down
docker compose -f deploy/cloudflare/public-app/compose.yaml down
```

The resulting temporary outage on `tepinta.com.ar` is intentional. It preserves
the private Tailscale administration runtime and prevents accidental public access
to it.
