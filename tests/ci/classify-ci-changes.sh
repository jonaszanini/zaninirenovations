#!/usr/bin/env bash
# Classify the git diff for this workflow event (docs-only vs code changes).
set -euo pipefail

is_documentation_path() {
  local path=$1
  case "$path" in
    *.md) return 0 ;;
    docs/*) return 0 ;;
    lang/*) return 0 ;;
  esac
  return 1
}

files=()
if [[ "${GITHUB_EVENT_NAME:-}" == "pull_request" ]]; then
  base="${PR_BASE:?}"
  head="${PR_HEAD:?}"
  while IFS= read -r line; do
    [[ -n "$line" ]] && files+=("$line")
  done < <(git diff --name-only "$base" "$head")
elif [[ "${GITHUB_EVENT_NAME:-}" == "push" ]]; then
  before="${GITHUB_EVENT_BEFORE:-}"
  head="${GITHUB_SHA:?}"
  if [[ -n "$before" && "$before" != "0000000000000000000000000000000000000000" ]]; then
    while IFS= read -r line; do
      [[ -n "$line" ]] && files+=("$line")
    done < <(git diff --name-only "$before" "$head")
  fi
fi

docs_only=false
has_code_changes=false

if [[ ${#files[@]} -gt 0 ]]; then
  docs_only=true
  for path in "${files[@]}"; do
    if is_documentation_path "$path"; then
      continue
    fi
    docs_only=false
    has_code_changes=true
  done
  if [[ "$docs_only" == true ]]; then
    has_code_changes=false
  fi
fi

changed_files_path=""
if [[ ${#files[@]} -gt 0 ]]; then
  changed_files_path="$(mktemp)"
  printf '%s\n' "${files[@]}" > "$changed_files_path"
fi

{
  echo "docs_only=$docs_only"
  echo "has_code_changes=$has_code_changes"
  echo "changed_file_count=${#files[@]}"
  echo "changed_files_path=$changed_files_path"
} >> "${GITHUB_OUTPUT:?}"

echo "Changed files: ${#files[@]} (docs_only=$docs_only, has_code_changes=$has_code_changes)"
