#!/usr/bin/env bash
set -Eeuo pipefail
docker compose ps
for service in api fiscal-engine postgres redis rabbitmq minio; do
  docker compose ps --status running "$service" | grep -q "$service" || { echo "Serviço indisponível: $service"; exit 1; }
done
echo 'Stack operacional.'
