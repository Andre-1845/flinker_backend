#!/bin/sh
# Entrypoint do container `app`. Roda em todo start (app, queue e scheduler
# usam essa mesma imagem — ver docker-compose.yml), então as partes que só
# fazem sentido uma vez (migrations) são condicionadas por RUN_MIGRATIONS pra
# não rodar em paralelo nos três serviços ao mesmo tempo.
set -e

echo "Aguardando o banco de dados ($DB_HOST:$DB_PORT)..."
timeout=30
until php -r "new PDO('pgsql:host=${DB_HOST:-postgres};port=${DB_PORT:-5432};dbname=${DB_DATABASE:-flinker}', '${DB_USERNAME:-postgres}', '${DB_PASSWORD:-}');" 2>/dev/null; do
    timeout=$((timeout - 1))
    if [ "$timeout" -le 0 ]; then
        echo "Banco de dados não respondeu a tempo, seguindo assim mesmo."
        break
    fi
    sleep 1
done

if [ -z "$APP_KEY" ] && [ -f /var/www/html/.env ]; then
    php artisan key:generate --force || true
fi

if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Rodando migrations..."
    php artisan migrate --force
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:cache-components || true

exec "$@"
