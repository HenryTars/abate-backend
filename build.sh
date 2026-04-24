#!/usr/bin/env bash
set -e

composer install --no-dev --optimize-autoloader

php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan migrate --force
