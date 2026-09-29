#!/bin/sh
set -e

# Railway injects $PORT; Apache must listen on it.
PORT="${PORT:-8080}"
export PORT
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
echo "[entrypoint] Apache will listen on port ${PORT}"

# Create tables and seed data (idempotent). Retries while MySQL is still starting.
php /var/www/app/bin/migrate.php

exec apache2-foreground
