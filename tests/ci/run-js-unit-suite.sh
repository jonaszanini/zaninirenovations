#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

source "$ROOT/tests/ci/expand-test-paths.sh"

list="$ROOT/tests/ci/js-unit-paths.txt"
while IFS= read -r path; do
  [[ -z "$path" ]] && continue
  echo "→ $path"
  if [[ "$path" == *.mjs ]]; then
    node "$path"
  else
    node "$path"
  fi
done < <(expand_list "$list" | sort -u)
