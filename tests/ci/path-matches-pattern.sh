#!/usr/bin/env bash
# Return 0 when $1 matches CI change-coverage pattern $2.
set -euo pipefail

path=$1
pattern=$2

if [[ "$pattern" == *'/**' ]]; then
  prefix="${pattern%/**}"
  [[ "$path" == "$prefix"/* ]] && exit 0
  [[ "$path" == "$prefix" ]] && exit 0
  exit 1
fi

if [[ "$pattern" == *'*'* ]]; then
  case "$path" in
    $pattern) exit 0 ;;
  esac
  exit 1
fi

[[ "$path" == "$pattern" ]] && exit 0
exit 1
