#!/usr/bin/env bash
# Builds and deploys immutable Te Pinta releases.
#
#   build <commit>    Export the commit with git archive (never the working tree)
#                     and build the image tepinta3:<short-sha>.
#   deploy <sha>      Backup -> migrate with the new image -> switch the
#                     operational app, scheduler, public app and gateway
#                     (nginx.conf from the release directory, never a
#                     checkout) -> wait healthy -> smoke test. A failed smoke
#                     test switches the application back to the previous release.
#   rollback          Switch the application back to the previous release.
#                     Database migrations are not reverted. It swaps current
#                     and previous: running it twice returns to the newer one.
#   status            Show the current and previous releases and running images.
#
# deploy and rollback require RELEASE_CONFIRM=yes.
#
# Environment (defaults target production; override all of them to rehearse):
#   RELEASE_ENV_FILE        runtime environment file (required for deploy/rollback)
#   RELEASE_HOME            release directories and state      (~/tepinta-releases)
#   APP_STORAGE_ROOT        absolute path of the persistent storage directory
#   OPERATIONAL_PROJECT     Compose project of app + scheduler (tailscale)
#   PUBLIC_PROJECT          Compose project of the public app  (public-app)
#   GATEWAY_PROJECT         Compose project of the gateway     (cloudflare)
#   APP_PORT / PUBLIC_APP_PORT / GATEWAY_PORT  loopback ports  (8082 / 8084 / 8083)
#   DATABASE_DOCKER_NETWORK database network                    (tepinta-production)
#   BACKUP_COMMAND          run before migrating   (~/tepinta-ops/backup-db.sh)
#   SAIL_IMAGE              build base image                    (sail-8.5/app)
set -euo pipefail

release_home=${RELEASE_HOME:-$HOME/tepinta-releases}
operational_project=${OPERATIONAL_PROJECT:-tailscale}
public_project=${PUBLIC_PROJECT:-public-app}
gateway_project=${GATEWAY_PROJECT:-cloudflare}
export APP_PORT=${APP_PORT:-8082}
export PUBLIC_APP_PORT=${PUBLIC_APP_PORT:-8084}
export GATEWAY_PORT=${GATEWAY_PORT:-8083}
export DATABASE_DOCKER_NETWORK=${DATABASE_DOCKER_NETWORK:-tepinta-production}
backup_command=${BACKUP_COMMAND:-$HOME/tepinta-ops/backup-db.sh}
state_file="$release_home/state"
log_file="$release_home/releases.log"

usage() {
    sed -n '2,28p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
}

log() {
    printf '%s %s\n' "$(date -u +%FT%TZ)" "$*" | tee -a "$log_file" >&2
}

require_confirmation() {
    [[ ${RELEASE_CONFIRM:-} == yes ]] || {
        echo "Refusing to change the runtime without RELEASE_CONFIRM=yes." >&2
        exit 1
    }
}

require_runtime_settings() {
    [[ -n ${RELEASE_ENV_FILE:-} && -f $RELEASE_ENV_FILE ]] || {
        echo "RELEASE_ENV_FILE must point to the runtime environment file." >&2
        exit 1
    }
    [[ -n ${APP_STORAGE_ROOT:-} && $APP_STORAGE_ROOT == /* && -d $APP_STORAGE_ROOT/app ]] || {
        echo "APP_STORAGE_ROOT must be the absolute path of the persistent storage directory." >&2
        exit 1
    }
    export RUNTIME_ENV_FILE=$RELEASE_ENV_FILE PUBLIC_APP_ENV_FILE=$RELEASE_ENV_FILE APP_STORAGE_ROOT
}

state() {
    [[ -f $state_file ]] && sed -n "s/^$1=//p" "$state_file" || true
}

write_state() {
    printf 'current=%s\nprevious=%s\n' "$1" "$2" > "$state_file.tmp"
    mv "$state_file.tmp" "$state_file"
}

image() {
    printf 'tepinta3:%s' "$1"
}

require_release() {
    [[ $1 =~ ^[0-9a-f]{12}$ ]] || { echo "Release ids are 12-character commit hashes." >&2; exit 1; }
    [[ -d $release_home/$1 ]] || { echo "Release $1 was not built here." >&2; exit 1; }
    docker image inspect "$(image "$1")" > /dev/null 2>&1 || { echo "Image $(image "$1") is missing." >&2; exit 1; }
}

compose() {
    local project=$1 file=$2
    shift 2
    docker compose --project-name "$project" --file "$file" "$@"
}

switch_to() {
    local release=$1 directory="$release_home/$1"
    export APP_IMAGE PUBLIC_APP_IMAGE
    APP_IMAGE=$(image "$release")
    PUBLIC_APP_IMAGE=$APP_IMAGE

    # Called as an `if` condition, where `set -e` does not apply: check each step.
    compose "$operational_project" "$directory/deploy/tailscale/compose.yaml" \
        up --detach --no-build --pull never --wait --wait-timeout 90 app scheduler || return 1
    compose "$public_project" "$directory/deploy/cloudflare/public-app/compose.yaml" \
        up --detach --no-build --pull never --wait --wait-timeout 90 public-app || return 1
    # The gateway reads deploy/cloudflare/nginx.conf next to its compose file: the release copy.
    compose "$gateway_project" "$directory/deploy/cloudflare/compose.yaml" \
        up --detach --no-build --pull never --wait --wait-timeout 30 gateway || return 1
}

expect_status() {
    local url=$1 expected=$2 status attempt
    for attempt in 1 2 3 4 5; do
        status=$(curl --silent --output /dev/null --max-time 10 --write-out '%{http_code}' "$url" || true)
        [[ $status =~ ^($expected)$ ]] && return 0
        sleep "$attempt"
    done
    if [[ ! $status =~ ^($expected)$ ]]; then
        echo "smoke: $url answered $status, expected $expected" >&2
        return 1
    fi
}

# The gateway answers the storefront and still denies the private paths. Every release
# satisfies this, so the same check guards deploys and rollbacks.
expect_gateway() {
    expect_status "http://127.0.0.1:$GATEWAY_PORT/" 200 || return 1
    expect_status "http://127.0.0.1:$GATEWAY_PORT/admin" 404
}

smoke_test() {
    local ok=0
    expect_status "http://127.0.0.1:$APP_PORT/up" 200 || ok=1
    expect_status "http://127.0.0.1:$APP_PORT/login" 200 || ok=1
    expect_status "http://127.0.0.1:$PUBLIC_APP_PORT/up" 200 || ok=1
    expect_status "http://127.0.0.1:$PUBLIC_APP_PORT/" 200 || ok=1
    # 302: the coming-soon switch (PUBLIC_SITE_OPEN) sends the menu back home.
    expect_status "http://127.0.0.1:$PUBLIC_APP_PORT/menu" "200|302" || ok=1
    expect_gateway || ok=1
    return $ok
}

build() {
    local repository commit sha directory
    # Only build reads the repository: deploy, rollback and status also run from a release directory.
    repository=$(git -C "$(dirname "${BASH_SOURCE[0]}")" rev-parse --show-toplevel)
    commit=$(git -C "$repository" rev-parse --verify "${1:?usage: build <commit>}^{commit}")
    sha=${commit:0:12}
    directory="$release_home/$sha"
    mkdir -p "$release_home"

    if [[ ! -d $directory ]]; then
        rm -rf "$directory.partial"
        mkdir "$directory.partial"
        git -C "$repository" archive "$commit" | tar -x -C "$directory.partial"
        mv "$directory.partial" "$directory"
    fi

    docker build \
        --file "$directory/deploy/tailscale/Dockerfile" \
        --build-arg "SAIL_IMAGE=${SAIL_IMAGE:-sail-8.5/app}" \
        --label "org.opencontainers.image.revision=$commit" \
        --tag "$(image "$sha")" \
        "$directory" >&2
    docker run --rm --entrypoint test "$(image "$sha")" -f /var/www/html/public/build/manifest.json
    log "built $(image "$sha") from $commit"
    echo "$sha"
}

deploy() {
    local release=${1:?usage: deploy <sha>} current
    require_confirmation
    require_runtime_settings
    require_release "$release"
    current=$(state current)
    if [[ $release == "$current" ]]; then
        echo "Release $release is already current." >&2
        exit 1
    fi
    exec 9> "$release_home/.lock"
    flock --nonblock 9 || { echo "Another release operation is running." >&2; exit 1; }

    log "deploy $release (current: ${current:-none}): backup"
    "$backup_command"

    log "deploy $release: migrate"
    docker run --rm --env-file "$RELEASE_ENV_FILE" --network "$DATABASE_DOCKER_NETWORK" \
        --entrypoint php "$(image "$release")" /var/www/html/artisan migrate --force

    log "deploy $release: switch"
    if switch_to "$release" && smoke_test; then
        write_state "$release" "$current"
        log "deploy $release: live"
        return 0
    fi

    if [[ -n $current ]]; then
        log "deploy $release: switch or smoke test failed, switching back to $current"
        switch_to "$current" || log "rollback to $current did not become healthy"
    else
        log "deploy $release: switch or smoke test failed and there is no previous release to switch back to"
    fi
    exit 1
}

rollback() {
    local current previous
    require_confirmation
    require_runtime_settings
    current=$(state current)
    previous=$(state previous)
    [[ -n $previous ]] || { echo "There is no previous release." >&2; exit 1; }
    require_release "$previous"

    exec 9> "$release_home/.lock"
    flock --nonblock 9 || { echo "Another release operation is running." >&2; exit 1; }

    log "rollback $current -> $previous (database migrations are not reverted)"
    switch_to "$previous" || { log "rollback: $previous did not become healthy; the stack may be partly switched"; exit 1; }
    smoke_test || { log "rollback: smoke test failed on $previous"; exit 1; }
    write_state "$previous" "$current"
    log "rollback: $previous live"
}

status() {
    echo "current:  $(state current)"
    echo "previous: $(state previous)"
    for project in "$operational_project" "$public_project" "$gateway_project"; do
        docker ps --filter "label=com.docker.compose.project=$project" --format '{{.Names}}\t{{.Image}}\t{{.Status}}'
    done
}

mkdir -p "$release_home"
case "${1:-}" in
    build) build "${2:-}" ;;
    deploy) deploy "${2:-}" ;;
    rollback) rollback ;;
    status) status ;;
    *) usage; exit 2 ;;
esac
