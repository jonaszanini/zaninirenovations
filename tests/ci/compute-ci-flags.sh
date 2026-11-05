#!/usr/bin/env bash
# Select CI job flags from path-filter outputs and change classification.
# Writes multiline output to GITHUB_OUTPUT when set; always prints a summary to stdout.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"

truthy() {
  case "${1:-}" in
    true|True|TRUE|1|yes|Yes) return 0 ;;
    *) return 1 ;;
  esac
}

is_main=false
if [[ "${GITHUB_REF:-}" == "refs/heads/main" ]]; then
  is_main=true
fi

slow_input=false
if [[ "${GITHUB_EVENT_NAME:-}" == "workflow_dispatch" ]] && truthy "${SLOW_INPUT:-false}"; then
  slow_input=true
fi

docs_only=false
if truthy "${DOCS_ONLY:-false}"; then
  docs_only=true
fi

has_code_changes=false
if truthy "${HAS_CODE_CHANGES:-false}"; then
  has_code_changes=true
fi

broad_run=false
if [[ -n "${CHANGED_FILES_FILE:-}" && -f "$CHANGED_FILES_FILE" ]]; then
  while IFS= read -r path || [[ -n "$path" ]]; do
    [[ -z "$path" ]] && continue
    case "$path" in
      *.md|docs/*|lang/*) continue ;;
    esac
    matched=false
    while IFS= read -r pattern || [[ -n "$pattern" ]]; do
      [[ -z "$pattern" || "$pattern" == \#* ]] && continue
      if bash "$ROOT/tests/ci/path-matches-pattern.sh" "$path" "$pattern"; then
        matched=true
        break
      fi
    done < "$ROOT/tests/ci/change-coverage-patterns.txt"
    if [[ "$matched" == false ]]; then
      broad_run=true
      break
    fi
  done < "$CHANGED_FILES_FILE"
fi

global_deps=false
if truthy "${FILTER_GLOBAL:-false}" || [[ "$broad_run" == true ]]; then
  global_deps=true
fi

run_all_heavy=false
if [[ "$is_main" == true ]] || [[ "$slow_input" == true ]] || [[ "$global_deps" == true ]]; then
  run_all_heavy=true
fi

run_database=false
if [[ "$is_main" == true ]] || [[ "${GITHUB_EVENT_NAME:-}" == "workflow_dispatch" ]]; then
  run_database=true
fi
if [[ "${GITHUB_EVENT_NAME:-}" == "pull_request" ]] && [[ "$docs_only" != true ]] && [[ "$has_code_changes" == true ]]; then
  run_database=true
fi

run_shared=false
if [[ "$slow_input" == true ]] || [[ "$run_all_heavy" == true ]] || truthy "${FILTER_SP:-false}"; then
  run_shared=true
fi

run_ra=false
if [[ "$slow_input" == true ]] || [[ "$run_all_heavy" == true ]] || truthy "${FILTER_RA:-false}"; then
  run_ra=true
fi

run_bl=false
if [[ "$slow_input" == true ]] || [[ "$run_all_heavy" == true ]] || truthy "${FILTER_BL:-false}"; then
  run_bl=true
fi

run_mf=false
if [[ "$slow_input" == true ]] || [[ "$run_all_heavy" == true ]] || truthy "${FILTER_MF:-false}"; then
  run_mf=true
fi

browser_js_admin=false
browser_js_openplan3d=false
browser_js_quotation=false
browser_js_client=false
if truthy "${FILTER_BROWSER_JS:-false}" && [[ -n "${CHANGED_FILES_FILE:-}" && -f "$CHANGED_FILES_FILE" ]]; then
  while IFS= read -r path || [[ -n "$path" ]]; do
    [[ -z "$path" ]] && continue
    case "$path" in
      tests/js/admin-*|tests/js/**/*admin*browser*) browser_js_admin=true ;;
      tests/js/openplan3d-*|tests/js/room-area-*browser*) browser_js_openplan3d=true ;;
      tests/js/quotation-*|tests/js/*quotation*browser*) browser_js_quotation=true ;;
      tests/js/client-projects-*) browser_js_client=true ;;
    esac
  done < "$CHANGED_FILES_FILE"
  if [[ "$browser_js_admin" == false && "$browser_js_openplan3d" == false && "$browser_js_quotation" == false && "$browser_js_client" == false ]]; then
    browser_js_admin=true
    browser_js_openplan3d=true
    browser_js_quotation=true
    browser_js_client=true
  fi
fi

run_browser_admin=false
if [[ "$run_all_heavy" == true ]] || truthy "${FILTER_BROWSER_ADMIN:-false}" || [[ "$browser_js_admin" == true ]]; then
  run_browser_admin=true
fi

run_browser_openplan3d=false
if [[ "$run_all_heavy" == true ]] || truthy "${FILTER_BROWSER_OPENPLAN3D:-false}" || truthy "${FILTER_RA:-false}" || truthy "${FILTER_SP:-false}" || [[ "$browser_js_openplan3d" == true ]]; then
  run_browser_openplan3d=true
fi

run_browser_quotation=false
if [[ "$run_all_heavy" == true ]] || truthy "${FILTER_BROWSER_QUOTATION:-false}" || truthy "${FILTER_ROUTES:-false}" || [[ "$browser_js_quotation" == true ]]; then
  run_browser_quotation=true
fi

run_browser_client=false
if [[ "$run_all_heavy" == true ]] || truthy "${FILTER_BROWSER_CLIENT:-false}" || [[ "$browser_js_client" == true ]]; then
  run_browser_client=true
fi

run_browser=false
if [[ "$run_browser_admin" == true ]] || [[ "$run_browser_openplan3d" == true ]] || [[ "$run_browser_quotation" == true ]] || [[ "$run_browser_client" == true ]]; then
  run_browser=true
fi

run_mysql_node=false
if [[ "$run_ra" == true ]] || [[ "$run_bl" == true ]]; then
  run_mysql_node=true
fi

# MySQL supplemental filter outputs (for select-database-paths.sh) — true when domain touched or broad.
filter_sp_out=false
if [[ "$run_all_heavy" == true ]] || truthy "${FILTER_SP:-false}"; then filter_sp_out=true; fi
filter_ra_out=false
if [[ "$run_all_heavy" == true ]] || truthy "${FILTER_RA:-false}"; then filter_ra_out=true; fi
filter_bl_out=false
if [[ "$run_all_heavy" == true ]] || truthy "${FILTER_BL:-false}"; then filter_bl_out=true; fi
filter_seo_out=false
if [[ "$run_all_heavy" == true ]] || truthy "${FILTER_SEO:-false}"; then filter_seo_out=true; fi
filter_mf_out=false
if [[ "$run_all_heavy" == true ]] || truthy "${FILTER_MF:-false}"; then filter_mf_out=true; fi
filter_routes_out=false
if [[ "$run_all_heavy" == true ]] || truthy "${FILTER_ROUTES:-false}"; then filter_routes_out=true; fi

emit() {
  local key=$1
  local val=$2
  echo "$key=$val"
  if [[ -n "${GITHUB_OUTPUT:-}" ]]; then
    echo "$key=$val" >> "$GITHUB_OUTPUT"
  fi
}

emit run_database "$run_database"
emit run_browser "$run_browser"
emit run_browser_admin "$run_browser_admin"
emit run_browser_openplan3d "$run_browser_openplan3d"
emit run_browser_quotation "$run_browser_quotation"
emit run_browser_client "$run_browser_client"
emit run_mysql_node "$run_mysql_node"
emit run_shared_prices_geometry "$run_shared"
emit run_room_area_live "$run_ra"
emit run_blog_live "$run_bl"
emit run_millwork_fuzz "$run_mf"
emit filter_shared_prices "$filter_sp_out"
emit filter_room_area "$filter_ra_out"
emit filter_blog "$filter_bl_out"
emit filter_seo "$filter_seo_out"
emit filter_millwork "$filter_mf_out"
emit filter_routes_smoke "$filter_routes_out"
emit broad_run "$broad_run"
emit global_test_deps "$global_deps"

echo "CI flags: database=$run_database browser=$run_browser (admin=$run_browser_admin openplan3d=$run_browser_openplan3d quotation=$run_browser_quotation client=$run_browser_client) mysql_node=$run_mysql_node broad=$broad_run global=$global_deps"
