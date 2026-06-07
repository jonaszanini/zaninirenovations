#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${DEPLOY_ROOT:?DEPLOY_ROOT is required}"
if [ ! -d "${APP_DIR}" ]; then
  echo "ERROR: App directory does not exist: ${APP_DIR}"
  exit 1
fi

echo "[remote] Running deploy commands in: ${APP_DIR}"
cd "${APP_DIR}"

if command -v composer >/dev/null 2>&1 && [ -f composer.lock ]; then
  LOCK_HASH_FILE="${APP_DIR}/.deploy-composer-lock-hash"
  if command -v md5sum >/dev/null 2>&1; then
    CURRENT_HASH=$(md5sum composer.lock | awk '{print $1}')
  elif command -v md5 >/dev/null 2>&1; then
    CURRENT_HASH=$(md5 -q composer.lock)
  else
    CURRENT_HASH=""
  fi

  if [ -n "${CURRENT_HASH}" ] && [ -f "${LOCK_HASH_FILE}" ] && [ "$(cat "${LOCK_HASH_FILE}")" = "${CURRENT_HASH}" ]; then
    echo "[composer] composer.lock unchanged, skipping install"
  else
    echo "[composer] installing dependencies..."
    composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
    if [ -n "${CURRENT_HASH}" ]; then
      echo "${CURRENT_HASH}" > "${LOCK_HASH_FILE}"
    fi
  fi
fi

if [ -f artisan ]; then
  if [ ! -f .env ]; then
    echo "ERROR: ${APP_DIR}/.env is missing."
    exit 1
  fi

  echo "[artisan] linking public storage..."
  php artisan storage:link 2>/dev/null || true
  if [ ! -d public/storage ]; then
    mkdir -p public/storage
  fi
  if [ -d storage/app/public ]; then
    rsync -a storage/app/public/ public/storage/ 2>/dev/null || cp -R storage/app/public/. public/storage/ 2>/dev/null || true
  fi

  echo "[artisan] fixing permissions..."
  chmod -R 775 storage bootstrap/cache 2>/dev/null || true

  echo "[artisan] clearing caches..."
  php artisan optimize:clear

  echo "[artisan] running migrations..."
  php artisan migrate --force

  echo "[artisan] rebuilding caches..."
  php artisan config:cache
  php artisan route:clear
  php artisan view:cache
fi

echo "[remote] Deploy commands completed successfully"
