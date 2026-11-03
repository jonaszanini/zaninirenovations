#!/usr/bin/env bash
# Smoke-test public/.htaccess under Apache 2.4 (AllowOverride All): no 500,
# Cache-Control for versioned/unversioned assets. Runs public docroot and
# repo-root docroot (production root .htaccess -> /public/).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
PUBLIC="$ROOT/public"
IMAGE="${APACHE_HTACCESS_IMAGE:-httpd:2.4-alpine}"
BASE_PORT="${APACHE_HTACCESS_TEST_PORT:-18765}"

if ! command -v docker >/dev/null 2>&1; then
  echo "apache-htaccess-cache: docker is required for this check" >&2
  exit 1
fi

IMMUTABLE_REL="$(find "$PUBLIC/vendor/openplan3d-h1/_app/immutable" -type f -name '*.js' -print -quit | sed "s|^$PUBLIC/||")"
if [[ -z "$IMMUTABLE_REL" ]]; then
  echo "apache-htaccess-cache: no immutable JS chunk found under vendor/openplan3d-h1" >&2
  exit 1
fi

FONT_REL="$(find "$PUBLIC" -type f \( -name '*.woff2' -o -name '*.woff' \) -print -quit | sed "s|^$PUBLIC/||")"
IMAGE_REL="$(find "$PUBLIC" -type f \( -name '*.png' -o -name '*.svg' -o -name '*.webp' \) -print -quit | sed "s|^$PUBLIC/||")"
ENV_REL="vendor/openplan3d-h1/_app/env.js"
EDITOR_INDEX_REL="vendor/openplan3d-h1/editor/index.html"

for f in "$PUBLIC/$ENV_REL" "$PUBLIC/$IMMUTABLE_REL" "$PUBLIC/$EDITOR_INDEX_REL"; do
  if [[ ! -f "$f" ]]; then
    echo "apache-htaccess-cache: missing fixture file $f" >&2
    exit 1
  fi
done

write_httpd_conf() {
  local conf="$1"
  cat >"$conf" <<'EOF'
ServerRoot "/usr/local/apache2"
Listen 80
ServerName localhost
LoadModule mpm_event_module modules/mod_mpm_event.so
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule headers_module modules/mod_headers.so
LoadModule setenvif_module modules/mod_setenvif.so
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule dir_module modules/mod_dir.so
LoadModule mime_module modules/mod_mime.so
LoadModule expires_module modules/mod_expires.so
LoadModule deflate_module modules/mod_deflate.so
LoadModule filter_module modules/mod_filter.so
LoadModule unixd_module modules/mod_unixd.so
LoadModule log_config_module modules/mod_log_config.so

User daemon
Group daemon

DocumentRoot "/usr/local/apache2/htdocs"
<Directory "/usr/local/apache2/htdocs">
    AllowOverride All
    Require all granted
    Options -Indexes +FollowSymLinks
</Directory>

ErrorLog /proc/self/fd/2
CustomLog /proc/self/fd/1 combined
EOF
}

assert_status() {
  local url="$1"
  local want="$2"
  local got
  got="$(curl -s -o /dev/null -w '%{http_code}' "$url")"
  if [[ "$got" != "$want" ]]; then
    echo "apache-htaccess-cache: expected HTTP $want for $url, got $got" >&2
    exit 1
  fi
}

assert_header() {
  local url="$1"
  local pattern="$2"
  local label="$3"
  local headers
  headers="$(curl -sI "$url")"
  if ! echo "$headers" | grep -qi "$pattern"; then
    echo "apache-htaccess-cache: $label — missing header match /$pattern/ for $url" >&2
    echo "$headers" >&2
    exit 1
  fi
}

assert_no_header() {
  local url="$1"
  local pattern="$2"
  local label="$3"
  if curl -sI "$url" | grep -qi "$pattern"; then
    echo "apache-htaccess-cache: $label — must not match /$pattern/ for $url" >&2
    exit 1
  fi
}

run_suite() {
  local label="$1"
  local docroot="$2"
  local port="$3"
  local container="apache-htaccess-ci-${label}"
  local tmp
  tmp="$(mktemp -d)"

  write_httpd_conf "$tmp/httpd.conf"

  docker pull "$IMAGE" >/dev/null
  docker rm -f "$container" >/dev/null 2>&1 || true
  docker run -d --name "$container" \
    -v "$docroot:/usr/local/apache2/htdocs:ro" \
    -v "$tmp/httpd.conf:/usr/local/apache2/conf/httpd.conf:ro" \
    -p "127.0.0.1:${port}:80" \
    "$IMAGE" >/dev/null

  local ready=0
  for _ in $(seq 1 30); do
    if curl -sf -o /dev/null "http://127.0.0.1:${port}/css/admin.css"; then
      ready=1
      break
    fi
    sleep 0.5
  done
  if [[ "$ready" -ne 1 ]]; then
    echo "apache-htaccess-cache [$label]: Apache did not become ready" >&2
    docker logs "$container" >&2 || true
    docker rm -f "$container" >/dev/null 2>&1 || true
    rm -rf "$tmp"
    exit 1
  fi

  docker exec "$container" httpd -t >/dev/null

  local base="http://127.0.0.1:${port}"
  assert_status "${base}/css/admin.css" "200"
  assert_status "${base}/js/openplan3d-editor-bridge.js" "200"
  assert_status "${base}/js/openplan3d-editor-bridge.js?v=ci-test" "200"
  assert_status "${base}/js/openplan3d-editor-bridge.js?ver=ci-test" "200"
  assert_status "${base}/${IMMUTABLE_REL}" "200"
  assert_status "${base}/${ENV_REL}" "200"

  assert_header "${base}/js/openplan3d-editor-bridge.js?v=ci-test" "cache-control:.*immutable" "${label} ?v= JS"
  assert_header "${base}/js/openplan3d-editor-bridge.js?ver=ci-test" "cache-control:.*immutable" "${label} ?ver= JS"
  assert_header "${base}/${IMMUTABLE_REL}" "cache-control:.*immutable" "${label} immutable chunk"
  assert_header "${base}/js/openplan3d-editor-bridge.js" "cache-control:.*must-revalidate" "${label} unversioned JS"
  assert_no_header "${base}/js/openplan3d-editor-bridge.js" "cache-control:.*immutable" "${label} unversioned JS not immutable"
  assert_status "${base}/${EDITOR_INDEX_REL}" "200"
  assert_header "${base}/${EDITOR_INDEX_REL}" "cache-control:.*must-revalidate" "${label} hosted editor index.html"
  assert_no_header "${base}/${EDITOR_INDEX_REL}" "cache-control:.*immutable" "${label} editor index not immutable-cached"

  if [[ -n "$FONT_REL" ]]; then
    assert_status "${base}/${FONT_REL}" "200"
    assert_header "${base}/${FONT_REL}" "cache-control:.*immutable" "${label} font"
  fi
  if [[ -n "$IMAGE_REL" ]]; then
    assert_status "${base}/${IMAGE_REL}" "200"
    assert_header "${base}/${IMAGE_REL}" "cache-control:.*immutable" "${label} image"
  fi

  docker rm -f "$container" >/dev/null 2>&1 || true
  rm -rf "$tmp"
  echo "apache-htaccess-cache [$label]: ok"
}

run_suite "public-docroot" "$PUBLIC" "$BASE_PORT"
run_suite "root-docroot" "$ROOT" "$((BASE_PORT + 1))"

echo "apache-htaccess-cache: ok"
