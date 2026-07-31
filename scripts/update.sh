#!/usr/bin/env bash
set -Eeuo pipefail
TAG=${1:-main}
./scripts/backup.sh
git fetch --all --tags
git checkout "$TAG"

# shellcheck disable=SC1091
source "$(dirname "$0")/lib/compose.sh"

if [[ "${DEPLOY_MODE:-source}" == "ghcr" ]]; then
  dc pull
else
  dc build --pull
fi

dc run --rm api php artisan migrate --force
if [[ "${DEPLOY_MODE:-source}" == "ghcr" ]]; then
  dc up -d --remove-orphans --no-build
else
  dc up -d --remove-orphans
fi
./scripts/healthcheck.sh
