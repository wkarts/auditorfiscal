# syntax=docker/dockerfile:1.8
# check=error=true
ARG PHP_VERSION=8.4
ARG COMPOSER_VERSION=2.10.2
FROM composer:${COMPOSER_VERSION} AS composer-bin
FROM php:${PHP_VERSION}-cli-alpine
ENV APP_ENV=production APP_DEBUG=false COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_NO_INTERACTION=1 COMPOSER_NO_AUDIT=1
RUN set -eux; \
    apk upgrade --no-cache; \
    apk add --no-cache ca-certificates curl freetype icu-libs libjpeg-turbo libpng libxml2 libzip oniguruma postgresql-libs rabbitmq-c; \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS curl-dev freetype-dev icu-dev libjpeg-turbo-dev libpng-dev libxml2-dev libzip-dev linux-headers oniguruma-dev postgresql-dev rabbitmq-c-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" bcmath curl dom gd intl mbstring opcache pcntl pdo_pgsql sockets xml xmlwriter zip; \
    pecl install redis amqp; docker-php-ext-enable redis amqp; php -m | grep -Fxq sockets; \
    apk del .build-deps; rm -rf /tmp/pear /var/cache/apk/*
COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer
LABEL org.opencontainers.image.title="Auditor Fiscal API build base" \
      org.opencontainers.image.description="PHP, Composer e extensões nativas estáveis; não contém código da aplicação" \
      org.opencontainers.image.source="https://github.com/wkarts/auditorfiscal" \
      org.opencontainers.image.licenses="MIT"
