#!/bin/sh
set -eu

export APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:-http://localhost}}"

: "${APP_KEY:?APP_KEY must be configured in Render}"
: "${BOUTIK_PATRON_EMAIL:?Set BOUTIK_PATRON_EMAIL in Render}"
: "${BOUTIK_PATRON_PASSWORD:?Set BOUTIK_PATRON_PASSWORD in Render}"

mkdir -p storage/app/public
chown www-data:www-data storage/app/public

php artisan migrate --force
php artisan storage:link --force
php artisan db:seed --force
php artisan optimize

exec "$@"
