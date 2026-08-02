#!/bin/sh
set -eu

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs

# Em instalações Dockge o .env pode iniciar sem APP_KEY. A primeira instância
# gera a chave uma única vez no storage compartilhado; scheduler e worker leem
# exatamente o mesmo valor. O rename atômico impede leitura parcial.
if [ -z "${APP_KEY:-}" ]; then
    key_file="storage/.app_key"
    lock_dir="storage/.app_key.lock"

    if [ ! -s "$key_file" ]; then
        attempt=0
        lock_acquired=0
        while [ "$lock_acquired" -eq 0 ] && [ ! -s "$key_file" ]; do
            if mkdir "$lock_dir" 2>/dev/null; then
                lock_acquired=1
                break
            fi
            attempt=$((attempt + 1))
            if [ "$attempt" -ge 30 ]; then
                echo 'Não foi possível adquirir o lock para gerar APP_KEY.' >&2
                exit 1
            fi
            sleep 1
        done

        if [ "$lock_acquired" -eq 1 ]; then
            trap 'rm -rf "$lock_dir"' EXIT INT TERM
            if [ ! -s "$key_file" ]; then
                umask 077
                php -r 'echo "base64:", base64_encode(random_bytes(32)), PHP_EOL;' > "${key_file}.tmp"
                mv "${key_file}.tmp" "$key_file"
            fi
            rm -rf "$lock_dir"
            trap - EXIT INT TERM
        fi
    fi

    APP_KEY="$(tr -d '\r\n' < "$key_file")"
    export APP_KEY
    echo 'APP_KEY carregada do storage persistente.'
fi

php artisan config:cache || true
php artisan route:cache || true
exec "$@"
