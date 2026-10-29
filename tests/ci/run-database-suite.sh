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

php artisan test --parallel --processes=4 --recreate-databases --log-junit storage/logs/ci-mysql-junit.xml -- "${files[@]}"
