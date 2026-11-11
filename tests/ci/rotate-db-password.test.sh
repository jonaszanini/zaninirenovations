#!/usr/bin/env bash
# Local checks for the Hostinger DB password rotate helpers (no SSH, no real secret).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
REWRITE="$ROOT/.github/scripts/rewrite-env-db-password.php"
REMOTE="$ROOT/.github/scripts/remote-rotate-db-password.sh"
fail=0

expect_eq() {
  local name="$1" got="$2" want="$3"
  if [ "$got" != "$want" ]; then
    echo "FAIL $name"
    echo "  got:  $got"
    echo "  want: $want"
    fail=1
    return
  fi
  echo "PASS $name"
}

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

envfile="$tmp/.env"
printf 'APP_KEY=base64:old\nDB_PASSWORD=old-secret\nMAIL_HOST=smtp.gmail.com\n' > "$envfile"

printf '%s' 'plain' | php "$REWRITE" "$envfile"
got="$(grep '^DB_PASSWORD=' "$envfile")"
expect_eq "rewrites existing DB_PASSWORD" "$got" 'DB_PASSWORD="plain"'

if grep -q 'APP_KEY=base64:old' "$envfile" && grep -q 'MAIL_HOST=smtp.gmail.com' "$envfile"; then
  echo "PASS preserves other env keys"
else
  echo "FAIL preserves other env keys"
  cat "$envfile"
  fail=1
fi

printf '%s' 'p"ass$word\x' | php "$REWRITE" "$envfile"
got="$(grep '^DB_PASSWORD=' "$envfile")"
expect_eq "escapes quotes dollars backslashes" "$got" 'DB_PASSWORD="p\"ass\$word\\x"'

printf 'APP_KEY=base64:old\n' > "$envfile"
printf '%s' 'appended' | php "$REWRITE" "$envfile"
got="$(grep '^DB_PASSWORD=' "$envfile")"
expect_eq "appends DB_PASSWORD when missing" "$got" 'DB_PASSWORD="appended"'

set +e
printf '%s' '' | php "$REWRITE" "$envfile" >/tmp/rotate-empty.out 2>/tmp/rotate-empty.err
empty_rc=$?
set -e
if [ "$empty_rc" -ne 0 ] && grep -q 'empty' /tmp/rotate-empty.err; then
  echo "PASS rejects empty stdin"
else
  echo "FAIL rejects empty stdin rc=$empty_rc"
  cat /tmp/rotate-empty.err
  fail=1
fi

set +e
printf 'has\nnewline' | php "$REWRITE" "$envfile" >/tmp/rotate-nl.out 2>/tmp/rotate-nl.err
nl_rc=$?
set -e
if [ "$nl_rc" -ne 0 ] && grep -q 'newlines' /tmp/rotate-nl.err; then
  echo "PASS rejects newlines"
else
  echo "FAIL rejects newlines rc=$nl_rc"
  cat /tmp/rotate-nl.err
  fail=1
fi

app="$tmp/app"
mkdir -p "$app"
printf 'APP_KEY=base64:old\nDB_PASSWORD=old-secret\n' > "$app/.env"
chmod 644 "$app/.env"
cat > "$app/artisan" <<'ARTISAN'
#!/usr/bin/env php
<?php
file_put_contents(__DIR__.'/artisan.log', implode(' ', $argv).PHP_EOL, FILE_APPEND);
if (in_array('tinker', $argv, true)) {
    fwrite(STDERR, "tinker failed\n");
    exit(1);
}
exit(0);
ARTISAN
chmod +x "$app/artisan"

set +e
printf '%s' 'new-secret' | DEPLOY_ROOT="$app" bash "$REMOTE" >"$tmp/remote.out" 2>"$tmp/remote.err"
remote_rc=$?
set -e
if [ "$remote_rc" -ne 0 ] \
  && grep -q 'database connection failed' "$tmp/remote.err" \
  && grep -q 'DB_PASSWORD=old-secret' "$app/.env" \
  && ls "$app"/.env.bak-rotate-* >/dev/null 2>&1; then
  echo "PASS remote script restores backup when tinker fails"
else
  echo "FAIL remote script restore path rc=$remote_rc"
  echo "--- stdout ---"; cat "$tmp/remote.out"
  echo "--- stderr ---"; cat "$tmp/remote.err"
  echo "--- env ---"; cat "$app/.env"
  fail=1
fi

if grep -q 'new-secret' "$tmp/remote.out" "$tmp/remote.err"; then
  echo "FAIL remote script leaked the new password into logs"
  fail=1
else
  echo "PASS remote script does not print the new password"
fi

if [ "$fail" -ne 0 ]; then
  exit 1
fi
echo "rotate-db-password helpers: ok"
