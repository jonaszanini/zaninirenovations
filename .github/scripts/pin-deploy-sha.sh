#!/usr/bin/env bash
# Resolve the exact commit that may be deployed. The candidate must equal
# current origin/main for both workflow_run and workflow_dispatch. Never fall
# back from a Tests SHA to github.sha (the default-branch tip).
#
# Env:
#   EVENT_NAME          workflow_run | workflow_dispatch (optional when PINNED_SHA is set)
#   WORKFLOW_RUN_SHA    github.event.workflow_run.head_sha
#   DISPATCH_SHA        github.sha for workflow_dispatch
#   PINNED_SHA          already-pinned SHA (late freshness recheck)
#   MAIN_SHA            current origin/main (required)
#   GITHUB_OUTPUT       optional; sha= is appended when set
set -euo pipefail

EVENT_NAME="${EVENT_NAME:-}"
WORKFLOW_RUN_SHA="${WORKFLOW_RUN_SHA:-}"
DISPATCH_SHA="${DISPATCH_SHA:-}"
PINNED_SHA="${PINNED_SHA:-}"
MAIN_SHA="${MAIN_SHA:-}"

if [ -n "$PINNED_SHA" ]; then
  SHA="$PINNED_SHA"
elif [ "$EVENT_NAME" = "workflow_run" ]; then
  SHA="$WORKFLOW_RUN_SHA"
  if [ -z "$SHA" ]; then
    echo "workflow_run.head_sha is empty; refusing deploy (no fallback to github.sha)" >&2
    exit 1
  fi
elif [ "$EVENT_NAME" = "workflow_dispatch" ]; then
  SHA="$DISPATCH_SHA"
  if [ -z "$SHA" ]; then
    echo "github.sha is empty; refusing deploy" >&2
    exit 1
  fi
else
  echo "Unsupported event ${EVENT_NAME:-unset}; refusing deploy" >&2
  exit 1
fi

if [ -z "$SHA" ]; then
  echo "deploy SHA is empty; refusing deploy (no fallback to github.sha)" >&2
  exit 1
fi
if [ -z "$MAIN_SHA" ]; then
  echo "current main SHA is empty; refusing deploy" >&2
  exit 1
fi
if [ "$SHA" != "$MAIN_SHA" ]; then
  echo "Tested SHA ${SHA} is not current main ${MAIN_SHA}; refusing so an untested or stale tip cannot ship" >&2
  exit 1
fi

if [ -n "${GITHUB_OUTPUT:-}" ]; then
  echo "sha=${SHA}" >> "$GITHUB_OUTPUT"
fi
echo "sha=${SHA}"
echo "Pinned deploy SHA ${SHA}"
