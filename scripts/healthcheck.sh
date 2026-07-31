#!/usr/bin/env bash
set -Eeuo pipefail
# shellcheck disable=SC1091
source "$(dirname "$0")/lib/compose.sh"
dc ps
for service in api fiscal-engine postgres redis rabbitmq minio; do
  dc ps --status running "$service" | grep -q "$service" || {
    echo "Serviço indisponível: $service"
    exit 1
  }
done
echo 'Stack operacional.'
