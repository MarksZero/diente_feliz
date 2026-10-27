#!/bin/sh
set -eu
umask 027
: "${APP_KEY:?Define APP_KEY estable en .env antes de iniciar}"
: "${APP_URL:?Define APP_URL con HTTPS}"
: "${APP_DOMAIN:?Define el dominio de la aplicacion}"
: "${DB_HOST:?Define el endpoint de RDS}"
: "${DB_PASSWORD:?Define la clave de la cuenta de base de datos}"
if [ "${APP_ENV:-}" != "production" ] || [ "${APP_DEBUG:-false}" != "false" ]; then
    echo 'Esta imagen requiere APP_ENV=production y APP_DEBUG=false.' >&2
    exit 1
fi
case "$APP_URL" in https://*) ;; *) echo 'APP_URL debe usar HTTPS.' >&2; exit 1;; esac
if [ "${DB_SSLMODE:-}" != "verify-full" ] || [ ! -s /run/certs/global-bundle.pem ]; then
    echo 'Falta la validacion TLS verify-full o el certificado CA de RDS.' >&2
    exit 1
fi
mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
php artisan package:discover --no-ansi
php artisan config:cache --no-ansi
php artisan view:cache --no-ansi
chown -R www-data:www-data bootstrap/cache storage/framework
# Migrations and account provisioning are explicit operations, never startup side effects.
if [ "$1" = "apache2-foreground" ]; then
    exec docker-php-entrypoint "$@"
fi
exec su -s /bin/sh www-data -c 'exec "$@"' -- sh "$@"
