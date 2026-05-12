#!/bin/sh
set -e

# Generate APP_KEY only if not set (first-time setup)
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

# Run database migrations
php artisan migrate --force

# Start RoadRunner
exec /usr/local/bin/rr serve -c .rr.yaml
