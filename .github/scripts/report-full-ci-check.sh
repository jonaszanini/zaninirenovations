#!/usr/bin/env bash
# Create or update a GitHub check run named "full-ci" on HEAD_SHA.
#
# Env:
#   GH_TOKEN      required
#   REPO          owner/name
#   HEAD_SHA      commit to attach the check to
#   STATUS        queued | in_progress | completed (default completed)
#   CONCLUSION    required when STATUS=completed (success|failure|cancelled|neutral|skipped|timed_out)
#   CHECK_NAME    default full-ci
#   TITLE         output title
#   SUMMARY       output summary
#   DETAILS_URL   optional link to the workflow run
set -euo pipefail

REPO="${REPO:?REPO is required}"
HEAD_SHA="${HEAD_SHA:?HEAD_SHA is required}"
STATUS="${STATUS:-completed}"
CHECK_NAME="${CHECK_NAME:-full-ci}"
TITLE="${TITLE:-Full CI}"
SUMMARY="${SUMMARY:-}"
DETAILS_URL="${DETAILS_URL:-}"

if [[ "$STATUS" == "completed" && -z "${CONCLUSION:-}" ]]; then
  echo "CONCLUSION is required when STATUS=completed" >&2
  exit 1
fi

existing="$(gh api "repos/${REPO}/commits/${HEAD_SHA}/check-runs" --jq \
  "[.check_runs[] | select(.name==\"${CHECK_NAME}\") | .id] | first")"

payload="$(jq -n \
  --arg name "$CHECK_NAME" \
  --arg sha "$HEAD_SHA" \
  --arg status "$STATUS" \
  --arg conclusion "${CONCLUSION:-}" \
  --arg title "$TITLE" \
  --arg summary "$SUMMARY" \
  --arg url "$DETAILS_URL" \
  '{
      name: $name,
      head_sha: $sha,
      status: $status,
      output: {title: $title, summary: $summary}
    }
    + (if $status == "completed" then {conclusion: $conclusion} else {} end)
    + (if $url != "" then {details_url: $url} else {} end)')"

tmp="$(mktemp)"
trap 'rm -f "$tmp"' EXIT
printf '%s\n' "$payload" > "$tmp"

if [[ -n "$existing" && "$existing" != "null" ]]; then
  jq 'del(.name, .head_sha)' "$tmp" > "${tmp}.patch"
  gh api --method PATCH "repos/${REPO}/check-runs/${existing}" --input "${tmp}.patch"
  rm -f "${tmp}.patch"
else
  gh api --method POST "repos/${REPO}/check-runs" --input "$tmp"
fi
