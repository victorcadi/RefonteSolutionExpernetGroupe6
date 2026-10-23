# syntax=docker/dockerfile:1
#
# Image de l'application Expernet (Symfony 7.4) servie par FrankenPHP (Caddy + PHP).
#
#   Développement : docker-compose up -d          (cible « dev », code monté en volume)
#   Production    : docker build --target prod -t expernet .
#
# Aucun secret n'est copié dans l'image : APP_SECRET, DATABASE_URL, MAILER_DSN…
# sont fournis au lancement par des variables d'environnement.

ARG PHP_VERSION=8.4
ARG FRANKENPHP_VERSION=1.9

# ---------------------------------------------------------------------------
# Base commune : PHP, extensions, Composer
# ---------------------------------------------------------------------------
FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-php${PHP_VERSION}-bookworm AS base

WORKDIR /app

# Outils système : curl (contrôle de santé), git et unzip (Composer)
RUN apt-get update \
    && apt-get install -y --no-install-recommends curl git unzip \
    && rm -rf /var/lib/apt/lists/*

# Extensions PHP : MySQL, intl (dates en français), zip, gd (PDF dompdf), cache
RUN install-php-extensions \
        @composer \
        apcu \
        gd \
        intl \
        opcache \
        pdo_mysql \
        zip

# Réglages PHP communs (fuseau horaire de La Réunion, cohérent avec APP_TIMEZONE)
RUN { \
        echo 'date.timezone = Indian/Reunion'; \
        echo 'expose_php = Off'; \
        echo 'memory_limit = 256M'; \
        echo 'upload_max_filesize = 10M'; \
        echo 'post_max_size = 12M'; \
        echo 'realpath_cache_size = 4096K'; \
        echo 'realpath_cache_ttl = 600'; \
        echo 'opcache.interned_strings_buffer = 16'; \
        echo 'opcache.max_accelerated_files = 20000'; \
        echo 'opcache.memory_consumption = 256'; \
    } > "$PHP_INI_DIR/conf.d/10-expernet.ini"

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    APP_TIMEZONE=Indian/Reunion \
    # Caddy écoute en HTTP sur le port 80 (le HTTPS est assuré par le proxy en production)
    SERVER_NAME=:80

# Script de démarrage : nettoyé pour fonctionner même depuis un checkout Windows
# (BOM UTF-8 retiré, fins de ligne CRLF converties, droit d'exécution forcé).
COPY --link docker/entrypoint.sh /usr/local/bin/docker-entrypoint
RUN sed -i '1s/^\xEF\xBB\xBF//' /usr/local/bin/docker-entrypoint \
    && sed -i 's/\r$//' /usr/local/bin/docker-entrypoint \
    && chmod 755 /usr/local/bin/docker-entrypoint

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/docker-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]

HEALTHCHECK --interval=10s --timeout=3s --retries=5 --start-period=30s \
    CMD curl -fsS http://127.0.0.1:2019/metrics > /dev/null || exit 1

# ---------------------------------------------------------------------------
# Développement : le code est monté depuis la machine hôte
# ---------------------------------------------------------------------------
FROM base AS dev

ENV APP_ENV=dev

# Le conteneur tourne avec l'utilisateur de la machine hôte (voir compose.override.yaml) :
# port non privilégié et dossiers de Caddy et Composer accessibles en écriture.
ENV SERVER_NAME=:8080 \
    COMPOSER_HOME=/tmp/composer

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    && echo 'opcache.validate_timestamps = 1' > "$PHP_INI_DIR/conf.d/20-dev.ini" \
    && mkdir -p /data/caddy /config/caddy \
    && chmod -R a+rwX /data /config

EXPOSE 8080

# ---------------------------------------------------------------------------
# Production : image autonome, dépendances et assets compilés au build
# ---------------------------------------------------------------------------
FROM base AS prod

ENV APP_ENV=prod \
    APP_DEBUG=0

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && echo 'opcache.validate_timestamps = 0' > "$PHP_INI_DIR/conf.d/20-prod.ini"

# 1. Dépendances (couche mise en cache tant que composer.* ne change pas)
COPY --link composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-autoloader --no-scripts --no-progress --prefer-dist --no-interaction

# 2. Code de l'application
COPY --link . ./

# 3. Autoload optimisé, puis scripts Symfony (cache, bundles, importmap) et assets compilés
RUN composer dump-autoload --classmap-authoritative --no-dev \
    && composer run-script --no-dev post-install-cmd \
    && php bin/console asset-map:compile
