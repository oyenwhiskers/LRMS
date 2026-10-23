#!/bin/sh
set -e

cd /var/www/html

# Runtime caches after Dokploy injects env vars (do not bake these at image build).
php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan storage:link --force >/dev/null 2>&1 || true

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

# Seeds permissions + rack/shelf layout from database/data/storage_layout.json
if [ "${RUN_SEEDERS:-false}" = "true" ]; then
    php artisan db:seed --force --no-interaction
fi

chown -R www-data:www-data storage bootstrap/cache

exec "$@"
