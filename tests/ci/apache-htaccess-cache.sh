#!/usr/bin/env bash
# Smoke-test public/.htaccess under Apache 2.4 (AllowOverride All): no 500,
# and Cache-Control for versioned, immutable-chunk, and unversioned JS.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
PUBLIC="$ROOT/public"
PORT="${APACHE_HTACCESS_TEST_PORT:-18765}"
CONTAINER="${APACHE_HTACCESS_TEST_CONTAINER:-apache-htaccess-ci}"
IMAGE="${APACHE_HTACCESS_IMAGE:-httpd:2.4-alpine}"
IMMUTABLE_CHUNK="vendor/openplan3d/_app/immutable/chunks/DXEQVQnt.js"

if ! command -v docker >/dev/null 2>&1; then
  echo "apache-htaccess-cache: skip (docker not available)"
  exit 0
fi

TMPDIR="$(mktemp -d)"
trap 'rm -rf "$TMPDIR"; docker rm -f "$CONTAINER" >/dev/null 2>&1 || true' EXIT

cat >"$TMPDIR/httpd.conf" <<EOF
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

docker pull "$IMAGE" >/dev/null
docker rm -f "$CONTAINER" >/dev/null 2>&1 || true
docker run -d --name "$CONTAINER" \
  -v "$PUBLIC:/usr/local/apache2/htdocs:ro" \
  -v "$TMPDIR/httpd.conf:/usr/local/apache2/conf/httpd.conf:ro" \
  -p "127.0.0.1:${PORT}:80" \
  "$IMAGE" >/dev/null

for i in $(seq 1 30); do
  if curl -sf -o /dev/null "http://127.0.0.1:${PORT}/css/admin.css"; then
    break
  fi
  sleep 0.5
  if [[ "$i" -eq 30 ]]; then
    echo "apache-htaccess-cache: Apache did not become ready" >&2
    docker logs "$CONTAINER" >&2 || true
    exit 1
  fi
done

docker exec "$CONTAINER" httpd -t >/dev/null

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

assert_status "http://127.0.0.1:${PORT}/css/admin.css" "200"
assert_status "http://127.0.0.1:${PORT}/js/openplan3d-editor-bridge.js" "200"
assert_status "http://127.0.0.1:${PORT}/js/openplan3d-editor-bridge.js?v=ci-test" "200"
assert_status "http://127.0.0.1:${PORT}/${IMMUTABLE_CHUNK}" "200"

assert_header "http://127.0.0.1:${PORT}/js/openplan3d-editor-bridge.js?v=ci-test" "cache-control:.*immutable" "versioned JS"
assert_header "http://127.0.0.1:${PORT}/${IMMUTABLE_CHUNK}" "cache-control:.*immutable" "immutable chunk"
assert_header "http://127.0.0.1:${PORT}/js/openplan3d-editor-bridge.js" "cache-control:.*must-revalidate" "unversioned JS"
if curl -sI "http://127.0.0.1:${PORT}/js/openplan3d-editor-bridge.js" | grep -qi 'cache-control:.*immutable'; then
  echo "apache-htaccess-cache: unversioned JS must not be immutable" >&2
  exit 1
fi

echo "apache-htaccess-cache: ok"
