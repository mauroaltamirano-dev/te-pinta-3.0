#!/usr/bin/env bash

set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
compose=(docker compose --project-name tepinta-public-gateway-test --file "$root/tests/deploy/public-gateway/compose.yaml")
gateway='http://127.0.0.1:18083'

cleanup() {
    "${compose[@]}" down --volumes --remove-orphans >/dev/null 2>&1 || true
}
trap cleanup EXIT

request_count() {
    "${compose[@]}" logs --no-log-prefix mock 2>/dev/null | grep -c 'request=' || true
}

expect_reaches_origin() {
    local method="$1"
    local path="$2"
    local body

    body="$(curl --path-as-is --fail --silent --show-error --max-time 5 -X "$method" "$gateway$path")"
    [[ "$body" == *"$method request=$path"* ]] || {
        printf 'Expected %s %s to reach the mock origin.\n' "$method" "$path" >&2
        exit 1
    }
}

expect_blocked() {
    local method="$1"
    local path="$2"
    local before after status

    before="$(request_count)"
    status="$(curl --path-as-is --silent --show-error --output /dev/null --write-out '%{http_code}' --max-time 5 -X "$method" "$gateway$path")"
    after="$(request_count)"

    [[ "$status" != 2* && "$status" != 3* ]] || {
        printf 'Expected %s %s to be blocked, got HTTP %s.\n' "$method" "$path" "$status" >&2
        exit 1
    }
    [[ "$before" == "$after" ]] || {
        printf 'Blocked %s %s reached the mock origin.\n' "$method" "$path" >&2
        exit 1
    }
}

expect_client_address() {
    local address="$1"
    local body

    body="$(curl --path-as-is --fail --silent --show-error --max-time 5 \
        -H "CF-Connecting-IP: $address" \
        -H 'X-Forwarded-For: 203.0.113.7' \
        "$gateway/menu")"
    [[ "$body" == *"xff=$address"* && "$body" == *"realip=$address"* ]] || {
        printf 'Gateway did not preserve trusted CF client address %s: %s\n' "$address" "$body" >&2
        exit 1
    }
}

"${compose[@]}" up --detach

for _ in {1..30}; do
    if curl --silent --fail --max-time 1 "$gateway/" >/dev/null 2>&1; then
        break
    fi
    sleep 1
done
curl --silent --fail --max-time 1 "$gateway/" >/dev/null

expect_reaches_origin GET '/'
expect_reaches_origin GET '/menu'
expect_reaches_origin GET '/cart'
expect_reaches_origin GET '/menu/catalog'
expect_reaches_origin GET '/menu/fatay'
expect_reaches_origin GET '/checkout/options'
expect_reaches_origin POST '/checkout/price-preview'
expect_reaches_origin POST '/checkout/orders'
expect_reaches_origin GET '/build/assets/app-ABC123.js'
expect_reaches_origin GET '/build/assets/fonts-ABC123.woff2'
expect_reaches_origin GET '/images/brand/hero.png'
expect_reaches_origin GET '/images/brand/logo-completo-blanco.png'
expect_reaches_origin GET '/images/brand/logo-blanco.png'
expect_reaches_origin GET '/images/brand/bg-empanada.png'
expect_reaches_origin GET '/icons/te-pinta-logo.svg'
expect_reaches_origin GET '/icons/te-pinta-logo-192.png'
expect_reaches_origin GET '/storage/products/product.png'
expect_reaches_origin GET '/menu?campaign=%2Fspring'

for blocked in \
    'GET /admin' 'GET /ADMIN' 'GET /admin/' 'GET /api/automation/instagram/pending' \
    'GET /up' 'GET /login' 'POST /login' 'GET /user/passkeys/options' \
    'GET /media/instagram/1/story.jpg' 'GET /manifest.webmanifest' \
    'GET /storage/private/secret.pdf' 'PUT /storage/products/product.png' \
    'POST /menu' 'GET /checkout/orders' 'OPTIONS /checkout/orders' \
    'GET //admin' 'GET /%2fadmin' 'GET /%252fadmin' 'GET /%2e%2e/admin' \
    'GET /menu%2f..%2fadmin' 'GET /admin/../menu' 'GET /menu\admin' \
    'GET /%00admin' 'GET /menu%00admin' 'GET /.env'; do
    method="${blocked%% *}"
    path="${blocked#* }"
    expect_blocked "$method" "$path"
done

headers="$(curl --path-as-is --fail --silent --show-error --max-time 5 \
    -H 'Host: attacker.invalid' \
    -H 'X-Forwarded-Host: attacker.invalid' \
    -H 'X-Forwarded-Proto: http' \
    -H 'X-Forwarded-Port: 444' \
    -H 'X-Forwarded-Ssl: on' \
    -H 'X-Forwarded-Prefix: /admin' \
    -H 'X-Original-URL: /admin' \
    -H 'X-Rewrite-URL: /admin' \
    -H 'Forwarded: host=attacker.invalid;proto=http' \
    -H 'X-Forwarded-For: 203.0.113.7' \
    "$gateway/menu")"

[[ "$headers" == *'host=tepinta.com.ar'* && "$headers" == *'xfh=tepinta.com.ar'* && "$headers" == *'xfp=https'* && "$headers" == *'xfport=443'* && "$headers" == *'xfssl='* ]] || {
    printf 'Gateway did not replace client-controlled origin headers: %s\n' "$headers" >&2
    exit 1
}
[[ "$headers" != *'attacker.invalid'* && "$headers" != *'xfpfx=/admin'* && "$headers" != *'forwarded=host='* && "$headers" != *'203.0.113.7'* && "$headers" != *'original=/admin'* && "$headers" != *'rewrite=/admin'* && "$headers" != *'xfport=444'* && "$headers" != *'xfssl=on'* ]] || {
    printf 'Gateway forwarded a client-controlled proxy header: %s\n' "$headers" >&2
    exit 1
}

expect_client_address '198.51.100.24'
expect_client_address '2001:db8::24'

malformed_client="$(curl --path-as-is --fail --silent --show-error --max-time 5 \
    -H 'CF-Connecting-IP: not-an-ip' \
    "$gateway/menu")"
[[ "$malformed_client" == *'xff=127.0.0.1'* && "$malformed_client" == *'realip=127.0.0.1'* ]] || {
    printf 'Gateway accepted a malformed CF client address: %s\n' "$malformed_client" >&2
    exit 1
}

printf 'Public gateway allowlist and default-deny regression checks passed.\n'
