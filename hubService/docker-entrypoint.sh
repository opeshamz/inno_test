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

SERVICE_MODE=${SERVICE_MODE:-web}

if [ "$SERVICE_MODE" = "worker" ]; then
    echo "==> Starting HubService Queue Worker (RabbitMQ consumer)..."
    exec php artisan queue:work rabbitmq \
        --queue=employee-events \
        --tries=3 \
        --backoff=10 \
        --timeout=60 \
        --sleep=3 \
        --verbose
else
    echo "==> Starting HubService API on port 8000..."
    php artisan route:cache
    exec php artisan serve --host=0.0.0.0 --port=8000
fi
