#!/usr/bin/env bash
# Write a ephemeral PHPUnit config with one <file> entry per path (supports directories).
set -euo pipefail

if [[ $# -lt 2 ]]; then
  echo "Usage: build-phpunit-suite.sh <output.xml> <list.txt> [list2.txt ...]" >&2
  exit 1
fi

output=$1
shift
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
source "$ROOT/tests/ci/expand-test-paths.sh"

{
  echo '<?xml version="1.0" encoding="UTF-8"?>'
  echo '<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
  echo '         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"'
  echo '         bootstrap="vendor/autoload.php"'
  echo '         colors="true">'
  echo '    <testsuites>'
  echo '        <testsuite name="CI">'
  for list in "$@"; do
    while IFS= read -r f; do
      echo "            <file>${f}</file>"
    done < <(expand_list "$list" | sort -u)
  done
  echo '        </testsuite>'
  echo '    </testsuites>'
  awk '/<php>/,/<\/php>/' "$ROOT/phpunit.xml"
  echo '</phpunit>'
} > "$output"

file_count=$(grep -c '<file>' "$output" || true)
echo "Generated ${output} with ${file_count} test file(s)"
