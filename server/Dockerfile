# syntax=docker/dockerfile:1

# =============================================================================
# Image de base : FrankenPHP (Caddy + PHP embarqué) + libcurl-impersonate pour Waze
# =============================================================================
FROM dunglas/frankenphp:1-php8.5@sha256:ec7839c3cf2983e363d672d05d10d401c8414bc6f119f7f7e2884719530a4b08 AS base

ARG TARGETARCH
# Waze n'accepte que l'empreinte TLS d'un Chrome récent : penser à monter ces versions
# (https://github.com/lexiforest/curl-impersonate/releases) si Waze se met à répondre 403.
ARG CURL_IMPERSONATE_VERSION=2.2.3
ARG CURL_IMPERSONATE_PROFILE=chrome150

WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions imagick intl opcache pdo_sqlite zip

RUN set -eux; \
    arch="$([ "$TARGETARCH" = arm64 ] && echo aarch64 || echo x86_64)"; \
    curl -fsSL "https://github.com/lexiforest/curl-impersonate/releases/download/v${CURL_IMPERSONATE_VERSION}/libcurl-impersonate-v${CURL_IMPERSONATE_VERSION}.${arch}-linux-gnu.tar.gz" \
        | tar -xz -C /usr/local/lib --wildcards 'libcurl-impersonate.so*'; \
    ldconfig

COPY docker/php.ini $PHP_INI_DIR/conf.d/app.ini

# PHP (et donc le HttpClient de Symfony) utilise libcurl-impersonate à la place de libcurl
ENV LD_PRELOAD=/usr/local/lib/libcurl-impersonate.so.4 \
    CURL_IMPERSONATE=${CURL_IMPERSONATE_PROFILE} \
    SERVER_NAME=:80 \
    TZ=Europe/Paris

# =============================================================================
# Développement : le code est monté par compose.yaml
# =============================================================================
FROM base AS dev

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APP_ENV=dev

# =============================================================================
# Builder : dépendances et cache de production
# =============================================================================
FROM base AS builder

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APP_ENV=prod

# Copiés en premier pour que le cache Docker survive aux changements de code
COPY composer.json composer.lock symfony.lock ./

RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress \
    && composer clear-cache

COPY . .

RUN composer dump-autoload --no-dev --classmap-authoritative \
    && php bin/console assets:install public \
    && php bin/console importmap:install \
    && php bin/console asset-map:compile \
    && php bin/console cache:warmup \
    && rm -rf var/log/*

# =============================================================================
# Runtime
# =============================================================================
FROM base AS runtime

ENV APP_ENV=prod \
    APP_DEBUG=0

COPY --from=builder /app /app
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
    CMD curl -fsS http://localhost/health || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
