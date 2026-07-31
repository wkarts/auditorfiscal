#!/usr/bin/env bash
set -Eeuo pipefail
TAG=${1:-main}
./scripts/backup.sh
git fetch --all --tags
git checkout "$TAG"
docker compose build --pull
docker compose run --rm api php artisan migrate --force
docker compose up -d --remove-orphans
./scripts/healthcheck.sh
