#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

ci_xml="$ROOT/phpunit.ci-unit.xml"
"$ROOT/tests/ci/build-phpunit-suite.sh" "$ci_xml" "$ROOT/tests/ci/unit-paths.txt"

php artisan test --configuration phpunit.ci-unit.xml
rm -f "$ci_xml"
