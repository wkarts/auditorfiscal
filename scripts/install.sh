#!/usr/bin/env bash
set -Eeuo pipefail

[[ -f .env ]] || cp .env.example .env

if grep -q '^APP_KEY=$' .env; then
  KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
  sed -i "s|^APP_KEY=.*|APP_KEY=$KEY|" .env
fi

set -a
# shellcheck disable=SC1091
source .env
set +a

required_secrets=(
  ADMIN_PASSWORD
  DB_PASSWORD
  RABBITMQ_PASSWORD
  AWS_SECRET_ACCESS_KEY
  FISCAL_ENGINE_TOKEN
  MINIO_ROOT_PASSWORD
  GRAFANA_ADMIN_PASSWORD
)

invalid=0
for key in "${required_secrets[@]}"; do
  value="${!key:-}"
  case "$value" in
    ''|replace-me|troque_esta_senha|troque-token-interno)
      echo "Credencial obrigatória não configurada: $key" >&2
      invalid=1
      ;;
  esac
done

if (( invalid != 0 )); then
  echo 'Edite o arquivo .env e defina credenciais fortes antes de continuar.' >&2
  exit 1
fi

docker compose build
docker compose up -d postgres redis rabbitmq minio minio-init

for i in {1..60}; do
  if docker compose exec -T postgres pg_isready \
    -U "${DB_USERNAME:-auditor}" \
    -d "${DB_DATABASE:-auditor_fiscal}" >/dev/null 2>&1; then
    break
  fi
  sleep 2
done

docker compose run --rm api php artisan migrate --force
docker compose run --rm api php artisan db:seed --force
docker compose up -d --remove-orphans
./scripts/healthcheck.sh
