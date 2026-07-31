#!/usr/bin/env bash
set -Eeuo pipefail
# shellcheck disable=SC1091
source "$(dirname "$0")/lib/compose.sh"

dc ps
for service in api fiscal-engine postgres redis rabbitmq minio web; do
  dc ps --status running "$service" | grep -q "$service" || {
    echo "Serviço indisponível: $service" >&2
    exit 1
  }
done

URL="http://${APP_HTTP_BIND:-127.0.0.1}:${APP_HTTP_PORT:-8080}/health"
for attempt in $(seq 1 60); do
  if curl --fail --silent "$URL" >/dev/null; then
    echo "Stack operacional em $URL"
    exit 0
  fi
  sleep 2
done

echo "A interface não respondeu em $URL" >&2
exit 1
