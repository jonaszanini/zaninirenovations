#!/usr/bin/env bash
# Resolve the exact commit that may be deployed. Never fall back from a
# workflow_run Tests SHA to github.sha (the default-branch tip), which can
# already be a newer untested commit.
#
# Env:
#   EVENT_NAME          workflow_run | workflow_dispatch
#   WORKFLOW_RUN_SHA    github.event.workflow_run.head_sha
#   DISPATCH_SHA        github.sha for workflow_dispatch
#   MAIN_SHA            current origin/main (required for workflow_run)
#   GITHUB_OUTPUT       optional; sha= is appended when set
set -euo pipefail

EVENT_NAME="${EVENT_NAME:?EVENT_NAME is required}"
WORKFLOW_RUN_SHA="${WORKFLOW_RUN_SHA:-}"
DISPATCH_SHA="${DISPATCH_SHA:-}"
MAIN_SHA="${MAIN_SHA:-}"

if [ "$EVENT_NAME" = "workflow_run" ]; then
  SHA="$WORKFLOW_RUN_SHA"
  if [ -z "$SHA" ]; then
    echo "workflow_run.head_sha is empty; refusing deploy (no fallback to github.sha)" >&2
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
elif [ "$EVENT_NAME" = "workflow_dispatch" ]; then
  SHA="$DISPATCH_SHA"
  if [ -z "$SHA" ]; then
    echo "github.sha is empty; refusing deploy" >&2
    exit 1
  fi
else
  echo "Unsupported event ${EVENT_NAME}; refusing deploy" >&2
  exit 1
fi

if [ -n "${GITHUB_OUTPUT:-}" ]; then
  echo "sha=${SHA}" >> "$GITHUB_OUTPUT"
fi
echo "sha=${SHA}"
echo "Pinned deploy SHA ${SHA}"
