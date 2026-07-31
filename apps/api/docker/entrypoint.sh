#!/bin/sh
set -e
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
php artisan config:cache || true
php artisan route:cache || true
exec "$@"
