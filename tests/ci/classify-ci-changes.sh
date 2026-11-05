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
append_changed_path() {
  local p=$1
  [[ -z "$p" ]] && return
  local existing
  for existing in "${files[@]}"; do
    [[ "$existing" == "$p" ]] && return
  done
  files+=("$p")
}

if [[ "${GITHUB_EVENT_NAME:-}" == "pull_request" ]]; then
  base="${PR_BASE:?}"
  head="${PR_HEAD:?}"
  while IFS=$'\t' read -r status col2 col3 || [[ -n "$status" ]]; do
    [[ -z "$status" ]] && continue
    case "$status" in
      R*|C*)
        append_changed_path "$col2"
        append_changed_path "$col3"
        ;;
      *)
        append_changed_path "$col2"
        ;;
    esac
  done < <(git diff --name-status --find-renames=50% "$base" "$head")
elif [[ "${GITHUB_EVENT_NAME:-}" == "push" ]]; then
  before="${GITHUB_EVENT_BEFORE:-}"
  head="${GITHUB_SHA:?}"
  if [[ -n "$before" && "$before" != "0000000000000000000000000000000000000000" ]]; then
    while IFS=$'\t' read -r status col2 col3 || [[ -n "$status" ]]; do
      [[ -z "$status" ]] && continue
      case "$status" in
        R*|C*)
          append_changed_path "$col2"
          append_changed_path "$col3"
          ;;
        *)
          append_changed_path "$col2"
          ;;
      esac
    done < <(git diff --name-status --find-renames=50% "$before" "$head")
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
