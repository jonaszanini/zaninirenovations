#!/usr/bin/env bash
# Decision table for .github/scripts/pin-deploy-sha.sh (Nov 6 Tests#128 / #130 race).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SCRIPT="$ROOT/.github/scripts/pin-deploy-sha.sh"
fail=0

run_pin() {
  local out rc
  set +e
  out="$(EVENT_NAME="${1:-}" WORKFLOW_RUN_SHA="${2:-}" DISPATCH_SHA="${3:-}" MAIN_SHA="${4:-}" PINNED_SHA="${5:-}" bash "$SCRIPT" 2>&1)"
  rc=$?
  set -e
  printf '%s\n' "$out"
  return "$rc"
}

expect_ok() {
  local name="$1" event="$2" run_sha="$3" dispatch_sha="$4" main_sha="$5" want="$6"
  local pinned_sha="${7:-}"
  local out rc
  set +e
  out="$(run_pin "$event" "$run_sha" "$dispatch_sha" "$main_sha" "$pinned_sha")"
  rc=$?
  set -e
  if [ "$rc" -ne 0 ]; then
    echo "FAIL $name: expected success, got rc=$rc"
    echo "$out"
    fail=1
    return
  fi
  if ! grep -qx "sha=${want}" <<< "$out"; then
    echo "FAIL $name: expected sha=${want}"
    echo "$out"
    fail=1
    return
  fi
  echo "PASS $name"
}

expect_fail() {
  local name="$1" event="$2" run_sha="$3" dispatch_sha="$4" main_sha="$5" needle="$6"
  local pinned_sha="${7:-}"
  local out rc
  set +e
  out="$(run_pin "$event" "$run_sha" "$dispatch_sha" "$main_sha" "$pinned_sha")"
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

expect_ok "workflow_run pins tested SHA when it is still main" \
  workflow_run aaa aaa aaa aaa

expect_fail "workflow_run refuses empty head_sha instead of github.sha fallback" \
  workflow_run "" bbb bbb "no fallback to github.sha"

expect_fail "workflow_run refuses Tests SHA when main already moved (Nov 6 race)" \
  workflow_run 128sha 130sha 130sha "is not current main"

expect_fail "workflow_run refuses when current main SHA is missing" \
  workflow_run aaa aaa "" "current main SHA is empty"

expect_ok "workflow_dispatch pins github.sha only when it is current main" \
  workflow_dispatch ignored mainsha mainsha mainsha

expect_fail "workflow_dispatch refuses older or non-main github.sha (D1)" \
  workflow_dispatch ignored dispatchsha mainsha "is not current main"

expect_fail "workflow_dispatch refuses empty github.sha" \
  workflow_dispatch ignored "" mainsha "github.sha is empty"

expect_fail "workflow_dispatch refuses when current main SHA is missing" \
  workflow_dispatch ignored mainsha "" "current main SHA is empty"

expect_ok "late recheck passes when pinned SHA is still main" \
  "" ignored ignored mainsha mainsha mainsha

expect_fail "late recheck refuses when main moved after the pin (N1)" \
  "" ignored ignored newmain "is not current main" oldpin

expect_fail "unsupported event is refused" \
  push ignored ignored ignored "Unsupported event"

if [ "$fail" -ne 0 ]; then
  echo "pin-deploy-sha tests failed"
  exit 1
fi

echo "pin-deploy-sha tests passed"
