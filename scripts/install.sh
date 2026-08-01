#!/usr/bin/env bash
set -Eeuo pipefail

command -v docker >/dev/null || { echo 'Docker não encontrado.' >&2; exit 1; }
docker compose version >/dev/null || { echo 'Docker Compose V2 não encontrado.' >&2; exit 1; }

[[ -f .env ]] || cp .env.example .env

if grep -q '^APP_KEY=$' .env; then
  KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
  sed -i "s|^APP_KEY=.*|APP_KEY=$KEY|" .env
fi

# shellcheck disable=SC1091
source "$(dirname "$0")/lib/compose.sh"

required_secrets=(
  ADMIN_PASSWORD DB_PASSWORD RABBITMQ_PASSWORD AWS_SECRET_ACCESS_KEY
  FISCAL_ENGINE_TOKEN MINIO_ROOT_PASSWORD GRAFANA_ADMIN_PASSWORD
)

invalid=0
for key in "${required_secrets[@]}"; do
  value="${!key:-}"
  if [[ -z "$value" ]]; then
    echo "Credencial obrigatória não configurada: $key" >&2
    invalid=1
  fi
done

if [[ "${DEPLOY_MODE:-source}" == "ghcr" ]]; then
  [[ -n "${GHCR_NAMESPACE:-}" ]] || { echo 'GHCR_NAMESPACE é obrigatório em DEPLOY_MODE=ghcr.' >&2; invalid=1; }
  [[ -n "${AUDITOR_IMAGE_TAG:-}" ]] || { echo 'AUDITOR_IMAGE_TAG é obrigatório em DEPLOY_MODE=ghcr.' >&2; invalid=1; }
fi

(( invalid == 0 )) || exit 1

dc config --quiet

if [[ "${DEPLOY_MODE:-source}" == "ghcr" ]]; then
  dc pull auditor-fiscal-api auditor-fiscal-web auditor-fiscal-engine
else
  dc build --pull auditor-fiscal-api auditor-fiscal-web auditor-fiscal-engine
fi

dc up -d auditor-fiscal-postgres auditor-fiscal-redis auditor-fiscal-rabbitmq auditor-fiscal-minio

dc run --rm --no-deps auditor-fiscal-storage-init

dc run --rm auditor-fiscal-minio-init

dc run --rm auditor-fiscal-api php artisan migrate --force
dc run --rm auditor-fiscal-api php artisan db:seed --force

if [[ "${DEPLOY_MODE:-source}" == "ghcr" ]]; then
  dc up -d --remove-orphans --no-build
else
  dc up -d --remove-orphans
fi
./scripts/healthcheck.sh
