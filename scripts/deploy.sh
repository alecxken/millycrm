#!/usr/bin/env bash
# Server-side deploy for WanderLink CRM.
# Called by .github/workflows/deploy.yml over SSH after the built assets
# (public/build) have been uploaded. Safe to run by hand too:
#   APP_DIR=/var/www/millycrm BRANCH=main bash scripts/deploy.sh
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/millycrm}"
BRANCH="${BRANCH:-main}"
WEB_USER="${WEB_USER:-www-data}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.3-fpm}"
export COMPOSER_ALLOW_SUPERUSER=1

cd "$APP_DIR"
echo "==> Deploying $BRANCH to $APP_DIR"

if [ -f vendor/autoload.php ] && [ -f .env ]; then
    php artisan down --retry=15 || true
fi
# Always bring the site back up, even if a step below fails.
trap 'php artisan up >/dev/null 2>&1 || true' EXIT

echo "==> Fetching code"
git fetch --prune origin "$BRANCH"
git reset --hard "origin/$BRANCH"

echo "==> Installing PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress

if [ ! -f .env ]; then
    echo "==> First install: creating .env (edit APP_URL etc. afterwards)"
    cp .env.example .env
    sed -i 's/^APP_ENV=.*/APP_ENV=production/; s/^APP_DEBUG=.*/APP_DEBUG=false/' .env
    php artisan key:generate --force
elif ! grep -qE '^APP_KEY=.+' .env; then
    echo "==> .env has no APP_KEY: generating one"
    php artisan key:generate --force
fi

touch database/database.sqlite

echo "==> Migrating database"
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force   # idempotent: picks up new permissions
php artisan crm:seed-demo --if-empty
php artisan db:seed --class=AdminAccountsSeeder --force   # idempotent: named admin accounts

echo "==> Caching config, routes, views and events"
php artisan optimize:clear
php artisan optimize
php artisan storage:link >/dev/null 2>&1 || true

echo "==> Fixing permissions"
chown -R "$WEB_USER:$WEB_USER" storage bootstrap/cache database public/build 2>/dev/null || true
chmod -R ug+rwX storage bootstrap/cache database

if [ ! -f public/build/manifest.json ]; then
    echo "!! public/build/manifest.json is missing — front-end assets were not uploaded" >&2
fi

systemctl reload "$PHP_FPM_SERVICE" 2>/dev/null || echo "   (could not reload $PHP_FPM_SERVICE — reload it manually if OPcache is on)"

echo "==> Done: $(git log -1 --format='%h %s')"
