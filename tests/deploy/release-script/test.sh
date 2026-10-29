#!/usr/bin/env bash
# deploy/release.sh must run from a release directory (a git archive without .git):
# only `build` needs the repository.
set -euo pipefail

root=$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)
scratch=$(mktemp -d)
trap 'rm -rf "$scratch"' EXIT

mkdir -p "$scratch/release/deploy" "$scratch/home"
cp "$root/deploy/release.sh" "$scratch/release/deploy/release.sh"
printf 'current=aaaaaaaaaaaa\nprevious=bbbbbbbbbbbb\n' > "$scratch/home/state"

fail() { echo "FAIL: $*" >&2; exit 1; }

output=$(cd "$scratch" && RELEASE_HOME="$scratch/home" OPERATIONAL_PROJECT=release-script-test-none \
    PUBLIC_PROJECT=release-script-test-none GATEWAY_PROJECT=release-script-test-none \
    bash release/deploy/release.sh status 2>&1) || fail "status outside a git repository exited $?: $output"
grep -q '^current:  aaaaaaaaaaaa$' <<< "$output" || fail "status did not print the current release: $output"

set +e
output=$(cd "$scratch" && RELEASE_HOME="$scratch/home" bash release/deploy/release.sh 2>&1)
code=$?
set -e
[[ $code -eq 2 ]] || fail "usage exited $code, expected 2: $output"
grep -q 'build <commit>' <<< "$output" || fail "usage did not list the commands: $output"

set +e
output=$(cd "$scratch" && RELEASE_HOME="$scratch/home" bash release/deploy/release.sh build HEAD 2>&1)
code=$?
set -e
[[ $code -ne 0 ]] || fail "build outside a git repository succeeded"
grep -q 'not a git repository' <<< "$output" || fail "build outside a git repository gave an unexpected error: $output"

echo "release script tests passed"
