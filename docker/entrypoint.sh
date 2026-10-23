#!/bin/sh
# Point d'entrée du conteneur de l'application.
# - installe les dépendances si le dossier vendor est absent (développement, code monté) ;
# - attend la base de données puis applique les migrations Doctrine ;
# - lance ensuite la commande demandée (FrankenPHP par défaut, ou un worker Messenger).
set -e

# Options seules (ex. « --watch ») : transmises à FrankenPHP
if [ "${1#-}" != "$1" ]; then
    set -- frankenphp run "$@"
fi

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
    if [ "$APP_ENV" != 'prod' ] && [ ! -f vendor/autoload_runtime.php ]; then
        echo 'Installation des dépendances Composer…'
        composer install --prefer-dist --no-progress --no-interaction
    fi

    if grep -q '^DATABASE_URL=' .env 2>/dev/null || [ -n "$DATABASE_URL" ]; then
        echo 'Attente de la base de données…'
        tentatives=60
        until php bin/console dbal:run-sql -q 'SELECT 1' > /dev/null 2>&1; do
            tentatives=$((tentatives - 1))
            if [ "$tentatives" -le 0 ]; then
                echo 'La base de données ne répond pas : abandon.' >&2
                php bin/console dbal:run-sql 'SELECT 1'
                exit 1
            fi
            sleep 2
        done

        if [ "${RUN_MIGRATIONS:-1}" = '1' ] && [ -n "$(find migrations -iname '*.php' -print -quit 2>/dev/null)" ]; then
            php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing
        fi
    fi

    mkdir -p var/cache var/log
fi

exec docker-php-entrypoint "$@"
