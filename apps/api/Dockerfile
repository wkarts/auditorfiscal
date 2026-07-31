FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts
COPY . .
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader

FROM php:8.4-cli-alpine
RUN apk add --no-cache postgresql-libs libzip libpng freetype libjpeg-turbo icu-libs rabbitmq-c && \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev libzip-dev icu-dev rabbitmq-c-dev curl-dev oniguruma-dev libxml2-dev && \
    docker-php-ext-install pdo_pgsql bcmath pcntl intl zip opcache curl mbstring xml && pecl install redis amqp && docker-php-ext-enable redis amqp && apk del .build-deps
WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
COPY docker/php.ini /usr/local/etc/php/conf.d/99-auditor.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache && chown -R www-data:www-data storage bootstrap/cache
USER www-data
EXPOSE 8080
ENTRYPOINT ["entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]
