# Public storefront gateway

This directory contains the local boundary for the public storefront at
`https://tepinta.com.ar`. It is **not** an administrative reverse proxy and does
not contain a Cloudflare connector token, certificate, or account configuration.

The gateway runs with Linux host networking but listens only on
`127.0.0.1:8083`; it proxies only to the existing loopback operational runtime at
`127.0.0.1:8082`. A user-space Cloudflare connector is started separately by the
operator after the local isolation checks pass. The gateway and the private
Tailscale origin use the same Laravel application and database.

The gateway trusts `CF-Connecting-IP` only from its loopback direct peer and uses
that validated address for the upstream `X-Forwarded-For` and `X-Real-IP` headers.
This assumes that only the local Cloudflare connector can reach `127.0.0.1:8083`;
do not publish the gateway port or place another proxy in front of it.

Only public storefront pages, catalog, cart, checkout endpoints, product images,
and required public assets are allowed. Admin, authentication, API, health,
Instagram media, generic storage, and the existing administrative PWA manifest are
denied. Do not change the operational `APP_URL`: it remains the private Tailscale
WebAuthn/passkey origin.

Run the disposable regression harness before any local gateway start:

```bash
tests/deploy/public-gateway/test.sh
```

After a passing test, a local operator can start or stop only the reversible
gateway container with:

```bash
docker compose -f deploy/cloudflare/compose.yaml up -d
docker compose -f deploy/cloudflare/compose.yaml down
```

Those commands do not publish a hostname. The Cloudflare tunnel lifecycle and its
credentials stay outside this repository and require the separately authorized
operator action.
