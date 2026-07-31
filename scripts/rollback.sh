#!/usr/bin/env bash
set -Eeuo pipefail
TAG=${1:?Uso: ./scripts/rollback.sh v1.0.0}
git fetch --tags
git checkout "$TAG"
docker compose build
docker compose up -d --remove-orphans
./scripts/healthcheck.sh
