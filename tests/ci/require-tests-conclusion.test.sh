#!/usr/bin/env bash
# Terminal Tests conclusion gate: only success may deploy.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SCRIPT="$ROOT/.github/scripts/require-tests-conclusion.sh"
fail=0

run_conclusion() {
  local out rc
  set +e
  out="$(TESTS_CONCLUSION="${1:-}" bash "$SCRIPT" 2>&1)"
  rc=$?
  set -e
  printf '%s\n' "$out"
  return "$rc"
}

expect_ok() {
  local name="$1" conclusion="$2"
  local out rc
  set +e
  out="$(run_conclusion "$conclusion")"
  rc=$?
  set -e
  if [ "$rc" -ne 0 ]; then
    echo "FAIL $name: expected success, got rc=$rc"
    echo "$out"
    fail=1
    return
  fi
  echo "PASS $name"
}

expect_fail() {
  local name="$1" conclusion="$2" needle="$3"
  local out rc
  set +e
  out="$(run_conclusion "$conclusion")"
  rc=$?
  set -e
  if [ "$rc" -eq 0 ]; then
    echo "FAIL $name: expected refusal, got success"
    echo "$out"
    fail=1
    return
  fi
  if ! grep -q "$needle" <<< "$out"; then
    echo "FAIL $name: expected message containing '$needle'"
    echo "$out"
    fail=1
    return
  fi
  echo "PASS $name"
}

expect_ok "green Tests conclusion may deploy" success
expect_fail "red Tests conclusion is refused" failure "Tests failure"
expect_fail "cancelled Tests conclusion is refused" cancelled "Tests cancelled"
expect_fail "timed_out Tests conclusion is refused" timed_out "Tests timed_out"
expect_fail "missing Tests conclusion is refused" "" "Tests conclusion missing"
expect_fail "unknown Tests conclusion is refused" skipped "Tests skipped"

if [ "$fail" -ne 0 ]; then
  echo "require-tests-conclusion tests failed"
  exit 1
fi

echo "require-tests-conclusion tests passed"
