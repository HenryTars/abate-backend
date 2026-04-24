#!/bin/bash

PORT="${PORT:-10000}"

echo "==> Configuring Apache on port $PORT"
sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf
sed -i "s/\*:10000/*:$PORT/" /etc/apache2/sites-available/000-default.conf

echo "==> Caching config"
php artisan config:cache || echo "[WARN] config:cache failed"

echo "==> Caching routes"
php artisan route:cache || echo "[WARN] route:cache failed"

echo "==> Running migrations"
php artisan migrate --force
MIGRATE_EXIT=$?
if [ $MIGRATE_EXIT -ne 0 ]; then
    echo "[ERROR] Migrations failed with exit code $MIGRATE_EXIT"
    echo "[ERROR] DB_CONNECTION=$DB_CONNECTION"
    echo "[ERROR] DB_HOST=$DB_HOST"
    echo "[ERROR] DB_PORT=$DB_PORT"
    echo "[ERROR] DB_DATABASE=$DB_DATABASE"
    echo "[ERROR] DB_USERNAME=$DB_USERNAME"
fi

echo "==> Starting Apache"
exec apache2-foreground
