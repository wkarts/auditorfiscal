#!/usr/bin/env bash
set -Eeuo pipefail
[[ -f .env ]] || cp .env.example .env
if grep -q '^APP_KEY=$' .env; then KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"; sed -i "s|^APP_KEY=.*|APP_KEY=$KEY|" .env; fi
set -a; source .env; set +a
docker compose build
docker compose up -d postgres redis rabbitmq minio minio-init
for i in {1..60}; do docker compose exec -T postgres pg_isready -U "${DB_USERNAME:-auditor}" -d "${DB_DATABASE:-auditor_fiscal}" >/dev/null 2>&1 && break; sleep 2; done
docker compose run --rm api php artisan migrate --force
docker compose run --rm api php artisan db:seed --force
docker compose up -d --remove-orphans
./scripts/healthcheck.sh
