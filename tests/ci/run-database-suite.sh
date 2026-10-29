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
run_batch() {
  local -n batch=$1
  local label=$2
  if [[ ${#batch[@]} -eq 0 ]]; then
    return 0
  fi
  local ci_xml="$ROOT/phpunit.ci-mysql.xml"
  {
    echo '<?xml version="1.0" encoding="UTF-8"?>'
    echo '<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
    echo '         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"'
    echo '         bootstrap="vendor/autoload.php"'
    echo '         colors="true">'
    echo '    <testsuites><testsuite name="CI-MySQL">'
    for f in "${batch[@]}"; do
      echo "            <file>${f}</file>"
    done
    echo '    </testsuite></testsuites>'
    awk '/<php>/,/<\/php>/' phpunit.xml
    echo '</phpunit>'
  } > "$ci_xml"
  if ! php artisan test --configuration phpunit.ci-mysql.xml --log-junit storage/logs/ci-mysql-junit.xml; then
    failed+=("$label batch")
  fi
  rm -f "$ci_xml"
}

run_batch core_files "core"

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
