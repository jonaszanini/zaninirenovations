#!/usr/bin/env bash
# Validate report-full-ci-check.sh argument handling without calling GitHub.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SCRIPT="$ROOT/.github/scripts/report-full-ci-check.sh"
fail=0

expect_fail() {
  local name="$1"
  shift
  local out rc
  set +e
  out="$("$@" 2>&1)"
  rc=$?
  set -e
  if [[ "$rc" -eq 0 ]]; then
    echo "FAIL $name: expected non-zero"
    echo "$out"
    fail=1
    return
  fi
  echo "PASS $name"
}

expect_fail "missing REPO" env -u REPO HEAD_SHA=abc STATUS=in_progress bash "$SCRIPT"
expect_fail "missing HEAD_SHA" env REPO=o/r -u HEAD_SHA STATUS=in_progress bash "$SCRIPT"
expect_fail "completed without CONCLUSION" env REPO=o/r HEAD_SHA=abc STATUS=completed bash "$SCRIPT"

if [[ "$fail" -ne 0 ]]; then
  echo "report-full-ci-check tests failed"
  exit 1
fi
echo "report-full-ci-check tests passed"
