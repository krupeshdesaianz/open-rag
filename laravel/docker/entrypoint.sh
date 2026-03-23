#!/bin/bash
set -e

cd /var/www

# Generate app key if not set
php artisan key:generate --no-interaction --force 2>/dev/null || true

# Ensure SQLite database file exists
[ -f database/database.sqlite ] || touch database/database.sqlite
chown www-data:www-data database/database.sqlite

# Run migrations
php artisan migrate --force --no-interaction

exec supervisord -n -c /etc/supervisor/supervisord.conf
