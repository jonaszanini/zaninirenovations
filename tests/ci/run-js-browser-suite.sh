#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

source "$ROOT/tests/ci/expand-test-paths.sh"

lists=()
truthy() {
  case "${1:-}" in
    true|True|TRUE|1|yes|Yes) return 0 ;;
    *) return 1 ;;
  esac
}

if truthy "${CI_JS_BROWSER_ALL:-false}"; then
  lists+=(
    "$ROOT/tests/ci/js-browser-admin.txt"
    "$ROOT/tests/ci/js-browser-openplan3d.txt"
    "$ROOT/tests/ci/js-browser-quotation.txt"
    "$ROOT/tests/ci/js-browser-client-projects.txt"
  )
else
  truthy "${CI_JS_BROWSER_ADMIN:-false}" && lists+=("$ROOT/tests/ci/js-browser-admin.txt")
  truthy "${CI_JS_BROWSER_OPENPLAN3D:-false}" && lists+=("$ROOT/tests/ci/js-browser-openplan3d.txt")
  truthy "${CI_JS_BROWSER_QUOTATION:-false}" && lists+=("$ROOT/tests/ci/js-browser-quotation.txt")
  truthy "${CI_JS_BROWSER_CLIENT:-false}" && lists+=("$ROOT/tests/ci/js-browser-client-projects.txt")
fi

if [[ ${#lists[@]} -eq 0 ]]; then
  echo "No browser JS paths selected (CI_JS_BROWSER_* all false)." >&2
  exit 1
fi

tmp="$(mktemp)"
trap 'rm -f "$tmp"' EXIT
for list in "${lists[@]}"; do
  expand_list "$list" >> "$tmp"
done

while IFS= read -r path; do
  [[ -z "$path" ]] && continue
  echo "→ $path"
  node "$path"
done < <(sort -u "$tmp")
