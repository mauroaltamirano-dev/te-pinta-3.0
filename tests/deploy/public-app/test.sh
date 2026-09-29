#!/usr/bin/env bash

set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
base_image='sha256:e2c78c01075a5a774cb1164a8bc93736a68994dc3b6991f959df603aada2a519'
base_image_id='sha256:e2c78c01075a5a774cb1164a8bc93736a68994dc3b6991f959df603aada2a519'
test_image='tepinta3-public-app:verification'
dockerfile="$root/deploy/cloudflare/public-app/Dockerfile"
fixture="$root/tests/deploy/public-app/verify-overlay.php"

if [[ "$(docker image inspect --format '{{.Id}}' "$base_image")" != "$base_image_id" ]]; then
    printf 'The local operational base image does not match the verified immutable ImageID.\n' >&2
    exit 1
fi

build_image() {
    DOCKER_BUILDKIT=0 docker build --network=none --pull=false --tag "$test_image" --file "$dockerfile" \
        --build-arg PRODUCT_RESOURCE_SHA256=1fdb088e06bb6b58f8fcabb5e980dcc91fb951e1048baaac30333e1a04f90ce8 \
        --build-arg FEATURED_VIEW_SHA256=0d34ea4ed8e9499bed525cc6c4d8b5842a03058a620ee3b76accb74cc233d46e \
        --build-arg HELPER_SHA256=7d048d7505ea16676351e859c2386f13d39b4bab738583df8f67f129e090e9b5 \
        "$root"
}

failures=0

if DOCKER_BUILDKIT=0 docker build --network=none --pull=false --tag "$test_image" --file "$dockerfile" \
    --build-arg PRODUCT_RESOURCE_SHA256=invalid \
    --build-arg FEATURED_VIEW_SHA256=invalid \
    --build-arg HELPER_SHA256=invalid \
    "$root"; then
    printf 'Bad overlay checksums were accepted.\n' >&2
    failures=$((failures + 1))
fi

build_image

if ! docker run --rm --network=none --entrypoint sh "$test_image" -c \
    "! grep -q 'Storage::disk' app/Modules/Products/Http/Resources/PublicProductResource.php && ! grep -q 'Storage::disk' app/Modules/Marketing/Queries/BuildFeaturedProductPublicView.php"; then
    printf 'The baked serializers still use Storage URL generation.\n' >&2
    failures=$((failures + 1))
fi

if ! docker run --rm --network=none \
    -v "$fixture:/verify-overlay.php:ro" \
    --entrypoint php "$test_image" /verify-overlay.php; then
    printf 'The image URL helper/autoload proof failed.\n' >&2
    failures=$((failures + 1))
fi

if (( failures > 0 )); then
    exit 1
fi

base_hashes="$(docker run --rm --network=none --entrypoint sha256sum "$base_image" \
    config/filesystems.php app/Modules/Orders/Models/Order.php)"
derived_hashes="$(docker run --rm --network=none --entrypoint sha256sum "$test_image" \
    config/filesystems.php app/Modules/Orders/Models/Order.php)"

if [[ "$base_hashes" != "$derived_hashes" ]]; then
    printf 'The derived image changed files outside the permitted overlay.\n' >&2
    exit 1
fi

changed_app_and_classmap_paths="$(comm -3 \
    <(docker run --rm --network=none --entrypoint sh "$base_image" -c 'find app vendor/composer -type f -exec sha256sum {} + | awk "{print \$2, \$1}" | sort') \
    <(docker run --rm --network=none --entrypoint sh "$test_image" -c 'find app vendor/composer -type f -exec sha256sum {} + | awk "{print \$2, \$1}" | sort') \
    | awk '{print $1}' | sort -u)"
expected_changed_app_and_classmap_paths="$(cat <<'PATHS'
app/Modules/Marketing/Queries/BuildFeaturedProductPublicView.php
app/Modules/Products/Http/Resources/PublicProductResource.php
app/Modules/Products/Support/PublicProductImageUrl.php
vendor/composer/autoload_classmap.php
vendor/composer/autoload_static.php
PATHS
)"

if [[ "$changed_app_and_classmap_paths" != "$expected_changed_app_and_classmap_paths" ]]; then
    printf 'The derived app or Composer classmap changed outside the five-file overlay.\n' >&2
    exit 1
fi

PUBLIC_APP_ENV_FILE="$root/tests/deploy/public-app/fixture.env" \
    docker compose -f "$root/deploy/cloudflare/public-app/compose.yaml" config --quiet

printf 'Public app overlay checks passed.\n'
