#!/bin/sh
set -e

# Railway injects $PORT; Apache must listen on it.
PORT="${PORT:-8080}"
export PORT
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
echo "[entrypoint] Apache will listen on port ${PORT}"

# mod_php needs the prefork MPM, and Apache refuses to start if more than one MPM
# is enabled ("AH00534: More than one MPM loaded"). Enforce exactly one.
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*
[ -e /etc/apache2/mods-enabled/mpm_prefork.load ] || a2enmod -q mpm_prefork
echo "[entrypoint] Enabled MPM: $(ls /etc/apache2/mods-enabled | grep '^mpm_.*\.load$' | tr '\n' ' ')"
apache2ctl -t

# Create tables and seed data (idempotent). Retries while MySQL is still starting.
php /var/www/app/bin/migrate.php

exec apache2-foreground
