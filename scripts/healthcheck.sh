#!/usr/bin/env bash
set -Eeuo pipefail
# shellcheck disable=SC1091
source "$(dirname "$0")/lib/compose.sh"

dc ps
for service in auditor-fiscal-api auditor-fiscal-engine auditor-fiscal-postgres auditor-fiscal-redis auditor-fiscal-rabbitmq auditor-fiscal-minio auditor-fiscal-web auditor-fiscal-scheduler auditor-fiscal-worker; do
  dc ps --status running "$service" | grep -q "$service" || {
    echo "Serviço indisponível: $service" >&2
    exit 1
  }
done

BASE_URL="http://${WEB_BIND_HOST:-127.0.0.1}:${WEB_PUBLISHED_PORT:-8080}"
for attempt in $(seq 1 60); do
  if curl --fail --silent "$BASE_URL/health" >/dev/null \
    && curl --fail --silent "$BASE_URL/api/v1/health/live" >/dev/null; then
    echo "Frontend e API operacionais em $BASE_URL"
    exit 0
  fi
  if (( attempt < 60 )); then
    sleep 2
  fi
done

echo "Frontend ou API não respondeu em $BASE_URL" >&2
exit 1
