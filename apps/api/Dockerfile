# syntax=docker/dockerfile:1.8
# check=error=true

ARG PHP_VERSION=8.4
ARG COMPOSER_VERSION=2.10.2

FROM composer:${COMPOSER_VERSION} AS composer-bin

FROM php:${PHP_VERSION}-cli-alpine AS php-base

ENV APP_ENV=production \
    APP_DEBUG=false \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    COMPOSER_NO_AUDIT=1

RUN set -eux; \
    apk add --no-cache \
        ca-certificates \
        curl \
        icu-libs \
        libxml2 \
        libzip \
        oniguruma \
        postgresql-libs \
        rabbitmq-c; \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        curl-dev \
        icu-dev \
        libxml2-dev \
        libzip-dev \
        oniguruma-dev \
        postgresql-dev \
        rabbitmq-c-dev; \
    docker-php-ext-install -j"$(nproc)" \
        bcmath \
        curl \
        dom \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_pgsql \
        sockets \
        xml \
        xmlwriter \
        zip; \
    pecl install redis amqp; \
    docker-php-ext-enable redis amqp; \
    php -m | grep -Fxq sockets; \
    apk del .build-deps; \
    rm -rf /tmp/pear /var/cache/apk/*

FROM php-base AS vendor

COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer

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
