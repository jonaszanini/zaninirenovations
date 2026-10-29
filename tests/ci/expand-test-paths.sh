#!/usr/bin/env bash
# Expand tests/ci list entries (files, directories) to concrete *Test.php paths.
set -euo pipefail

expand_list() {
  local list_file=$1
  while IFS= read -r line || [ -n "$line" ]; do
    line="${line%%#*}"
    line="$(echo "$line" | xargs 2>/dev/null || true)"
    [[ -z "$line" ]] && continue
    if [[ -d "$line" ]]; then
      while IFS= read -r f; do
        printf '%s\n' "$f"
      done < <(find "$line" -type f -name '*Test.php' | sort)
    elif [[ -f "$line" ]]; then
      printf '%s\n' "$line"
    else
      echo "Missing test path: $line" >&2
      exit 1
    fi
  done < "$list_file"
}

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
  expand_list "$1"
fi
