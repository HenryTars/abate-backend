#!/bin/bash
set -e

PORT="${PORT:-10000}"

# Make Apache listen on the port Render assigns
sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf
sed -i "s/\*:10000/*:$PORT/" /etc/apache2/sites-available/000-default.conf

# Laravel bootstrap
php artisan config:cache
php artisan route:cache
php artisan migrate --force

exec apache2-foreground
