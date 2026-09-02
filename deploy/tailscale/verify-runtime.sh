#!/usr/bin/env bash

set -euo pipefail

backend="${TEPINTA3_BACKEND:-http://127.0.0.1:8082}"
deployment="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
environment="$deployment/.env"
compose=(docker compose --env-file "$environment" --file "$deployment/compose.yaml")

for service in app scheduler; do
    container_id="$("${compose[@]}" ps --quiet "$service")"
    [[ -n "$container_id" ]] || {
        printf 'Operational %s container is missing.\n' "$service" >&2
        exit 1
    }
    [[ "$(docker inspect --format '{{.State.Status}}' "$container_id")" == "running" ]] || {
        printf 'Operational %s container is not running.\n' "$service" >&2
        exit 1
    }
done

scheduler_id="$("${compose[@]}" ps --quiet scheduler)"
docker top "$scheduler_id" -eo pid,args | grep -q '[a]rtisan schedule:work' || {
    printf 'Laravel scheduler worker is not running.\n' >&2
    exit 1
}

html="$(curl --fail --silent --show-error "$backend/")"

curl --fail --silent --show-error "$backend/up" >/dev/null

if grep -Eq 'localhost:5173|/@vite/client' <<<"$html"; then
    printf 'Runtime still references the Vite development server.\n' >&2
    exit 1
fi

if [[ "$backend" == https://* ]] && grep -Eq '(href|src)="http://' <<<"$html"; then
    printf 'HTTPS runtime HTML still references insecure HTTP assets.\n' >&2
    exit 1
fi

if ! grep -Fq 'rel="manifest" href="/manifest.webmanifest"' <<<"$html"; then
    printf 'Runtime HTML does not link the web app manifest.\n' >&2
    exit 1
fi

curl --fail --silent --show-error "$backend/manifest.webmanifest" >/dev/null

image_path="$(find storage/app/public/products -maxdepth 1 -type f 2>/dev/null | head -n 1 || true)"
if [[ -n "$image_path" ]]; then
    curl --fail --silent --show-error "$backend/storage/products/$(basename "$image_path")" >/dev/null
fi

printf 'Runtime is healthy at %s and uses built assets.\n' "$backend"
