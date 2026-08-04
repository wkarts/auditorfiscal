# syntax=docker/dockerfile:1.8
# check=error=true

ARG BASE_IMAGE=ghcr.io/wkarts/auditorfiscal-api-base:php8.4-v2
FROM ${BASE_IMAGE} AS php-base

FROM php-base AS vendor

WORKDIR /app

# composer.* permite builds locais sem lock e usa composer.lock automaticamente
# quando ele estiver versionado ou for gerado pelo pipeline.
COPY composer.* ./

RUN set -eux; \
    composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --no-scripts \
        --no-autoloader

COPY . .

RUN set -eux; \
    composer dump-autoload \
        --no-dev \
        --no-interaction \
        --optimize \
        --classmap-authoritative; \
    php artisan package:discover --ansi; \
    composer clear-cache; \
    rm -rf /root/.composer/cache /tmp/*

FROM php-base AS runtime

ARG APP_VERSION=dev
ENV APP_VERSION=${APP_VERSION}

WORKDIR /var/www/html

COPY --from=vendor --chown=www-data:www-data /app /var/www/html
COPY docker/php.ini /usr/local/etc/php/conf.d/99-auditor.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

RUN set -eux; \
    chmod +x /usr/local/bin/entrypoint; \
    mkdir -p \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache; \
    chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 8080

ENTRYPOINT ["entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]
