#!/bin/sh
set -e

# Default Render port is 10000 if not set
export PORT="${PORT:-10000}"

echo "Starting Emiringkay on port ${PORT}..."

# Substitute $PORT into Nginx configuration
envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# Ensure required directories exist
mkdir -p /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/framework/cache \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache \
         /var/www/html/database

# Dump environment variables to .env so both CLI and PHP-FPM have full access
printenv | grep -E '^(APP_|DB_|DATABASE_|CACHE_|QUEUE_|SESSION_|REVERB_|VITE_|LOG_)' > /var/www/html/.env || true

# Ensure APP_KEY exists in environment or .env
if ! grep -q "^APP_KEY=base64:" /var/www/html/.env; then
    if [ -z "${APP_KEY}" ] || [ "${APP_KEY}" = "base64:" ]; then
        echo "Generating new APP_KEY..."
        NEW_KEY=$(php /var/www/html/artisan key:generate --show)
        export APP_KEY="${NEW_KEY}"
    fi
    echo "APP_KEY=${APP_KEY}" >> /var/www/html/.env
fi

# Ensure SQLite file exists if using sqlite connection
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ] && [ ! -f "/var/www/html/database/database.sqlite" ]; then
    touch /var/www/html/database/database.sqlite
fi

# Run database migrations
echo "Running migrations..."
php /var/www/html/artisan migrate --force || echo "Migrations warning: Database may not be ready yet"

# Optimize Laravel caching for production
echo "Caching configuration and routes..."
php /var/www/html/artisan config:cache || true
php /var/www/html/artisan route:cache || true
php /var/www/html/artisan view:cache || true

# Fix permissions so www-data can read/write everything
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database /var/www/html/.env
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database
chmod 664 /var/www/html/.env 2>/dev/null || true
chmod 664 /var/www/html/database/database.sqlite 2>/dev/null || true

echo "Starting Supervisord (Nginx + PHP-FPM + Reverb + Worker + Scheduler)..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
