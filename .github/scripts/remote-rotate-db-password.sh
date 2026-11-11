#!/usr/bin/env bash
# Apply NEW_DB_PASSWORD (stdin) to $DEPLOY_ROOT/.env, recache config, verify DB.
# Never prints the password. Restores the backup .env if verification fails.
set -euo pipefail

APP_DIR="${DEPLOY_ROOT:?DEPLOY_ROOT is required}"
if [ ! -d "${APP_DIR}" ]; then
  echo "ERROR: App directory does not exist: ${APP_DIR}" >&2
  exit 1
fi

cd "${APP_DIR}"

if [ ! -f artisan ]; then
  echo "ERROR: artisan is missing in ${APP_DIR}" >&2
  exit 1
fi

ENV_FILE="${APP_DIR}/.env"
if [ ! -f "${ENV_FILE}" ]; then
  echo "ERROR: ${ENV_FILE} is missing." >&2
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REWRITE="${REWRITE_ENV_DB_PASSWORD_PHP:-${SCRIPT_DIR}/rewrite-env-db-password.php}"
if [ ! -f "${REWRITE}" ]; then
  echo "ERROR: rewrite-env-db-password.php is missing." >&2
  exit 1
fi

PWFILE="$(mktemp "${APP_DIR}/.rotate-pw.XXXXXX")"
chmod 600 "${PWFILE}"
cat > "${PWFILE}"
if [ ! -s "${PWFILE}" ]; then
  rm -f "${PWFILE}"
  echo "ERROR: NEW_DB_PASSWORD is empty." >&2
  exit 1
fi

STAMP="$(date +%Y%m%d%H%M%S)"
BACKUP="${ENV_FILE}.bak-rotate-${STAMP}"
cp "${ENV_FILE}" "${BACKUP}"
chmod 600 "${BACKUP}"
echo "[rotate] Backed up .env to $(basename "${BACKUP}")"

ORIG_MODE="$(stat -c '%a' "${ENV_FILE}" 2>/dev/null || echo 600)"

restored=0
restore_env() {
  if [ "${restored}" -eq 1 ]; then
    return 0
  fi
  restored=1
  echo "ERROR: DB password rotation failed; restoring $(basename "${BACKUP}")." >&2
  cp "${BACKUP}" "${ENV_FILE}"
  chmod "${ORIG_MODE}" "${ENV_FILE}" || true
  php artisan config:clear || true
  php artisan config:cache || true
}

cleanup() {
  rm -f "${PWFILE}"
}
trap cleanup EXIT
trap restore_env ERR

php "${REWRITE}" "${ENV_FILE}" < "${PWFILE}"
chmod "${ORIG_MODE}" "${ENV_FILE}" || true
rm -f "${PWFILE}"
echo "[rotate] Wrote DB_PASSWORD in .env"

echo "[artisan] clearing config cache..."
php artisan config:clear

echo "[artisan] rebuilding config cache..."
php artisan config:cache

echo "[rotate] Verifying database connection..."
if ! php artisan tinker --execute='DB::select("select 1");' >/dev/null; then
  echo "ERROR: database connection failed after password update." >&2
  restore_env
  exit 1
fi

trap - ERR
echo "[rotate] Database connection verified."
