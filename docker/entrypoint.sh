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
    if [ "${APP_ENV:-dev}" != 'prod' ] && [ ! -f vendor/autoload_runtime.php ]; then
        echo 'Installation des dépendances Composer…'
        composer install --prefer-dist --no-progress --no-interaction
    fi

    if grep -q '^DATABASE_URL=' .env 2>/dev/null || [ -n "${DATABASE_URL:-}" ]; then
        echo 'Attente de la base de données…'
        tentatives=60
        until erreur=$(COLUMNS=300 php bin/console dbal:run-sql -q 'SELECT 1' 2>&1); do
            tentatives=$((tentatives - 1))
            if [ "$tentatives" -le 0 ]; then
                echo 'La base de données ne répond pas : abandon. Dernière erreur :' >&2
                printf '%s\n' "$erreur" >&2
                exit 1
            fi
            # Toutes les 20 secondes, affiche la raison de l'attente (accès refusé, hôte introuvable…)
            if [ $((tentatives % 10)) -eq 0 ]; then
                echo "Base de données toujours inaccessible (encore $tentatives essais). Dernière erreur :" >&2
                printf '%s\n' "$erreur" | grep -E 'SQLSTATE|Unable|Cannot|denied|refused|Unknown|getaddrinfo' | head -n 2 >&2 || true
            fi
            sleep 2
        done

        if [ "${RUN_MIGRATIONS:-1}" = '1' ] && [ -n "$(find migrations -iname '*.php' -print -quit 2>/dev/null)" ]; then
            php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing
        fi

        # Développement : données de démonstration chargées une seule fois, sur une base vide
        if [ "${APP_ENV:-dev}" != 'prod' ] && [ "${CHARGER_DONNEES_DEMO:-0}" = '1' ]; then
            comptes=$(php bin/console dbal:run-sql --force-fetch 'SELECT COUNT(*) AS nombre FROM utilisateur' | tr -d ' ' | grep -Ex '[0-9]+' || true)
            if [ "$comptes" = '0' ]; then
                echo 'Base vide : chargement des données de démonstration…'
                php bin/console doctrine:fixtures:load --no-interaction
            fi
        fi
    fi

    mkdir -p var/cache var/log
fi

exec docker-php-entrypoint "$@"
