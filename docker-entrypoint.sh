#!/bin/sh
set -e

if [ -z "$APP_KEY" ]; then
    echo "ERROR: APP_KEY is not set. Aborting startup." >&2
    exit 1
fi

if [ "${MIGRATE_ON_STARTUP:-false}" = "true" ]; then
    php artisan migrate --force
fi

exec /usr/local/bin/rr serve -c .rr.yaml
