#!/bin/sh
set -e

php artisan config:cache
php artisan route:cache
php artisan migrate --force
php artisan db:seed --force

exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
