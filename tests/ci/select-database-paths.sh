#!/usr/bin/env bash
# Print the MySQL PHPUnit paths for this CI run (one path per line, sorted unique).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

source "$ROOT/tests/ci/expand-test-paths.sh"

lists=("$ROOT/tests/ci/database-core.txt")

if [[ "${CI_MYSQL_SUITE:-pr}" == "full" ]]; then
  lists+=("$ROOT/tests/ci/database-extended.txt")
  # Every filter list must still run in the full suite (union, not extended-only).
  for filter_list in "$ROOT/tests/ci"/database-filter-*.txt; do
    lists+=("$filter_list")
  done
else
  [[ "${CI_MYSQL_FILTER_SHARED_PRICES:-false}" == "true" ]] && lists+=("$ROOT/tests/ci/database-filter-shared-prices.txt")
  [[ "${CI_MYSQL_FILTER_ROOM_AREA:-false}" == "true" ]] && lists+=("$ROOT/tests/ci/database-filter-room-area.txt")
  [[ "${CI_MYSQL_FILTER_BLOG:-false}" == "true" ]] && lists+=("$ROOT/tests/ci/database-filter-blog.txt")
  [[ "${CI_MYSQL_FILTER_SEO:-false}" == "true" ]] && lists+=("$ROOT/tests/ci/database-filter-seo.txt")
  [[ "${CI_MYSQL_FILTER_MILLWORK:-false}" == "true" ]] && lists+=("$ROOT/tests/ci/database-filter-millwork.txt")
  [[ "${CI_MYSQL_FILTER_QUOTATION:-false}" == "true" ]] && lists+=("$ROOT/tests/ci/database-filter-quotation.txt")
  [[ "${CI_MYSQL_FILTER_ROUTES_SMOKE:-false}" == "true" ]] && lists+=("$ROOT/tests/ci/database-filter-routes-smoke.txt")
fi

tmp="$(mktemp)"
trap 'rm -f "$tmp"' EXIT
for list in "${lists[@]}"; do
  expand_list "$list" >> "$tmp"
done
sort -u "$tmp"
