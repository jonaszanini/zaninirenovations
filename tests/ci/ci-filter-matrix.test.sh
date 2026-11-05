#!/usr/bin/env bash
# Regression matrix for tests/ci/compute-ci-flags.sh (no GitHub Actions required).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
COMPUTE="$ROOT/tests/ci/compute-ci-flags.sh"

fail() {
  echo "FAIL: $*" >&2
  exit 1
}

run_flags() {
  local changed_file=$1
  shift
  local out
  out="$(mktemp)"
  env GITHUB_REF=refs/pull/1/merge GITHUB_EVENT_NAME=pull_request \
    DOCS_ONLY=false HAS_CODE_CHANGES=true CHANGED_FILES_FILE="$changed_file" \
    "$@" \
    bash "$COMPUTE" > "$out" 2>&1 || true
  cat "$out"
  rm -f "$out"
}

assert_contains() {
  local haystack=$1 needle=$2
  [[ "$haystack" == *"$needle"* ]] || fail "expected output to contain '$needle', got:$haystack"
}

assert_not_contains() {
  local haystack=$1 needle=$2
  [[ "$haystack" != *"$needle"* ]] || fail "expected output NOT to contain '$needle', got:$haystack"
}

tmp="$(mktemp)"
trap 'rm -f "$tmp"' EXIT

printf '%s\n' 'public/js/admin-hotkey-save.js' > "$tmp"
out="$(run_flags "$tmp" FILTER_BROWSER_ADMIN=true)"
assert_contains "$out" 'run_browser_admin=true'
assert_contains "$out" 'run_mysql_node=false'
assert_not_contains "$out" 'run_browser_openplan3d=true'

printf '%s\n' 'public/js/openplan3d-host.js' > "$tmp"
out="$(run_flags "$tmp" FILTER_BROWSER_OPENPLAN3D=true)"
assert_contains "$out" 'run_browser_openplan3d=true'
assert_not_contains "$out" 'run_browser_admin=true'

printf '%s\n' 'tests/ci/compute-ci-flags.sh' > "$tmp"
out="$(run_flags "$tmp" FILTER_GLOBAL=true)"
assert_contains "$out" 'global_test_deps=true'
assert_contains "$out" 'run_browser_openplan3d=true'
assert_contains "$out" 'run_browser_quotation=true'

printf '%s\n' 'scripts/patch-openplan3d.mjs' > "$tmp"
out="$(run_flags "$tmp" FILTER_RA=true FILTER_BROWSER_OPENPLAN3D=true)"
assert_contains "$out" 'run_mysql_node=true'
assert_contains "$out" 'run_room_area_live=true'
assert_contains "$out" 'filter_room_area=true'

printf '%s\n' 'scripts/openplan3d-h1.json' > "$tmp"
out="$(run_flags "$tmp" FILTER_RA=true)"
assert_contains "$out" 'filter_room_area=true'
assert_contains "$out" 'run_browser_openplan3d=true'
assert_not_contains "$out" 'run_browser_admin=true'

printf '%s\n' 'public/vendor/openplan3d-h1/_app/immutable/chunks/1VjVcrjm-h4.js' > "$tmp"
out="$(run_flags "$tmp" FILTER_RA=true)"
assert_contains "$out" 'filter_room_area=true'

printf '%s\n' 'README.md' > "$tmp"
out="$(env GITHUB_REF=refs/pull/1/merge GITHUB_EVENT_NAME=pull_request DOCS_ONLY=true HAS_CODE_CHANGES=false CHANGED_FILES_FILE="$tmp" bash "$COMPUTE" 2>&1 | grep run_database || true)"
assert_contains "$out" 'run_database=false'

echo "ci-filter-matrix: ok"
