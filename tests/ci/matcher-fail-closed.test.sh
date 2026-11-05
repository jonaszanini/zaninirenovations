#!/usr/bin/env bash
# Fail-closed: matcher errors must force broad_run + full browser (global deps).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
MATCHER_JS="${MATCH_WORKFLOW_FILTERS_JS:-$ROOT/tests/ci/match-workflow-filters.js}"
COMPUTE="$ROOT/tests/ci/compute-ci-flags.sh"

tmp=$(mktemp)
echo 'public/js/admin-hotkey-save.js' > "$tmp"

# Broken matcher → must fail closed (not silently skip browser).
export GITHUB_REF=refs/pull/1/merge
export GITHUB_EVENT_NAME=pull_request
export DOCS_ONLY=false
export HAS_CODE_CHANGES=true
export CHANGED_FILES_FILE="$tmp"
export FILTER_BROWSER_ADMIN=false
export FILTER_BROWSER_QUOTATION=false
export FILTER_GLOBAL=false
export MATCH_WORKFLOW_FILTERS_JS="$ROOT/tests/ci/fixtures/broken-matcher.js"

set +e
out=$(bash "$COMPUTE" 2>&1)
rc=$?
set -e

rm -f "$tmp"

if [[ $rc -ne 0 ]]; then
  echo "compute exited $rc (unexpected): $out"
  exit 1
fi

if ! grep -q 'global_test_deps=true' <<< "$out"; then
  echo "expected global_test_deps=true on matcher failure"
  echo "$out"
  exit 1
fi
if ! grep -q 'broad_run=true' <<< "$out"; then
  echo "expected broad_run=true on matcher failure"
  exit 1
fi
if ! grep -q 'run_browser=true' <<< "$out"; then
  echo "expected run_browser=true on matcher failure"
  exit 1
fi

echo 'matcher-fail-closed.test.sh: ok'
