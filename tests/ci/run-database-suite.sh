#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

mapfile -t all_files < <("$ROOT/tests/ci/select-database-paths.sh")
if [[ ${#all_files[@]} -eq 0 ]]; then
  echo "No database test paths selected." >&2
  exit 1
fi

mapfile -t core_files < <("$ROOT/tests/ci/expand-test-paths.sh" "$ROOT/tests/ci/database-core.txt" | sort -u)

declare -A is_core=()
for f in "${core_files[@]}"; do
  is_core["$f"]=1
done

extras=()
for f in "${all_files[@]}"; do
  if [[ -z "${is_core[$f]:-}" ]]; then
    extras+=("$f")
  fi
done

echo "MySQL suite=${CI_MYSQL_SUITE:-pr}: ${#core_files[@]} core file(s), ${#extras[@]} supplemental file(s)"

failed=()
if [[ ${#core_files[@]} -gt 0 ]]; then
  ci_xml="$ROOT/phpunit.ci-mysql.xml"
  tmp_list="$(mktemp)"
  printf '%s\n' "${core_files[@]}" > "$tmp_list"
  "$ROOT/tests/ci/build-phpunit-suite.sh" "$ci_xml" "$tmp_list"
  rm -f "$tmp_list"
  if ! php artisan test --configuration phpunit.ci-mysql.xml --log-junit storage/logs/ci-mysql-junit.xml; then
    failed+=("core batch")
  fi
  rm -f "$ci_xml"
fi

for f in "${extras[@]}"; do
  if ! php artisan test "$f"; then
    failed+=("$f")
  fi
done

if [[ ${#failed[@]} -ne 0 ]]; then
  printf 'Failed MySQL paths:\n' >&2
  printf '  %s\n' "${failed[@]}" >&2
  exit 1
fi
