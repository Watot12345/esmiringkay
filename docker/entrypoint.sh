#!/bin/sh
set -e

# Default Render port is 10000 if not set
export PORT="${PORT:-10000}"

echo "Starting Emiringkay on port ${PORT}..."

# Substitute $PORT into Nginx configuration
envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# Fix permissions for runtime storage and cache
mkdir -p /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/framework/cache \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# If DB_CONNECTION is sqlite and database does not exist, create it
if [ "${DB_CONNECTION}" = "sqlite" ] && [ ! -f "/var/www/html/database/database.sqlite" ]; then
    touch /var/www/html/database/database.sqlite
    chown www-data:www-data /var/www/html/database/database.sqlite
fi

# Run database migrations
echo "Running migrations..."
php /var/www/html/artisan migrate --force || echo "Migrations failed or database not reachable yet"

# Optimize Laravel caching for production
echo "Caching configuration and routes..."
php /var/www/html/artisan config:cache || true
php /var/www/html/artisan route:cache || true
php /var/www/html/artisan view:cache || true

echo "Starting Supervisord (Nginx + PHP-FPM + Reverb + Worker + Scheduler)..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
