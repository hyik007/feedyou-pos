#!/bin/sh
# First-boot bootstrap: .env, app key, SQLite file, schema + demo data.
set -e
cd /var/www/html

[ -f vendor/autoload.php ] || composer install --no-interaction --prefer-dist
[ -f .env ] || cp .env.example .env
grep -q '^APP_KEY=base64' .env || php artisan key:generate --force
[ -f database/database.sqlite ] || touch database/database.sqlite

if php artisan migrate:status 2>/dev/null | grep -q 'create_pos_tables.*Ran'; then
  php artisan migrate --force
else
  php artisan migrate --force --seed
fi

exec "$@"
