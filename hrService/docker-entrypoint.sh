#!/bin/bash
set -e

echo "==> Waiting for PostgreSQL to be ready..."
until php -r "
    try {
        \$pdo = new PDO(
            'pgsql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'),
            getenv('DB_USERNAME'), getenv('DB_PASSWORD')
        );
        echo 'Connected';
    } catch (Exception \$e) {
        exit(1);
    }
"; do
    echo "   ... waiting"
    sleep 2
done

echo "==> Running migrations..."
php artisan migrate --force --no-interaction

echo "==> Caching config..."
php artisan config:cache
php artisan route:cache

echo "==> Seeding demo data (if first run)..."
php artisan db:seed --class=EmployeeSeeder --no-interaction 2>/dev/null || true

echo "==> Starting HR Service on port 8001..."
exec php artisan serve --host=0.0.0.0 --port=8001
