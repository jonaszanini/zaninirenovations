#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

mapfile -t files < <("$ROOT/tests/ci/select-database-paths.sh")
if [[ ${#files[@]} -eq 0 ]]; then
  echo "No database test paths selected." >&2
  exit 1
fi

echo "Running ${#files[@]} MySQL integration test file(s) (suite=${CI_MYSQL_SUITE:-pr})"

php artisan migrate --force --no-interaction
php artisan schema:dump --database=mysql --no-interaction

ci_xml="$ROOT/phpunit.ci-mysql.xml"
{
  echo '<?xml version="1.0" encoding="UTF-8"?>'
  echo '<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
  echo '         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"'
  echo '         bootstrap="vendor/autoload.php"'
  echo '         colors="true">'
  echo '    <testsuites>'
  echo '        <testsuite name="CI-MySQL">'
  for f in "${files[@]}"; do
    echo "            <file>${f}</file>"
  done
  echo '        </testsuite>'
  echo '    </testsuites>'
  awk '/<php>/,/<\/php>/' phpunit.xml
  echo '</phpunit>'
} > "$ci_xml"

php artisan test \
  --configuration phpunit.ci-mysql.xml \
  --log-junit storage/logs/ci-mysql-junit.xml

rm -f "$ci_xml"
