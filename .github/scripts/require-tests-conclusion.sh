#!/usr/bin/env bash
# Terminal Tests conclusion for the already-pinned SHA. Only "success" may
# deploy. Red, cancelled, timed_out, missing, or unknown conclusions fail closed.
#
# Env:
#   TESTS_CONCLUSION  latest completed Tests run conclusion for the pinned SHA
set -euo pipefail

CONCLUSION="${TESTS_CONCLUSION:-}"
case "$CONCLUSION" in
  success)
    echo "Tests succeeded"
    exit 0
    ;;
  failure|cancelled|timed_out)
    echo "Tests ${CONCLUSION}; refusing deploy" >&2
    exit 1
    ;;
  "")
    echo "Tests conclusion missing; refusing deploy" >&2
    exit 1
    ;;
  *)
    echo "Tests ${CONCLUSION}; refusing deploy" >&2
    exit 1
    ;;
esac
