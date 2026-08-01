#!/usr/bin/env bash
set -Eeuo pipefail

TAG=${1:-main}
./scripts/backup.sh

git fetch --all --tags
git checkout "$TAG"

# shellcheck disable=SC1091
source "$(dirname "$0")/lib/compose.sh"
dc config --quiet

if [[ "${DEPLOY_MODE:-source}" == "ghcr" ]]; then
  if [[ "$TAG" =~ ^v?[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    export AUDITOR_IMAGE_TAG="${TAG#v}"
  fi
  dc pull auditor-fiscal-api auditor-fiscal-web auditor-fiscal-fiscal-engine
  dc run --rm --no-deps auditor-fiscal-storage-init
  dc run --rm --no-deps auditor-fiscal-api php artisan migrate --force
  dc up -d --remove-orphans --no-build
else
  dc build --pull auditor-fiscal-api auditor-fiscal-web auditor-fiscal-fiscal-engine
  dc run --rm --no-deps auditor-fiscal-storage-init
  dc run --rm auditor-fiscal-api php artisan migrate --force
  dc up -d --remove-orphans
fi

./scripts/healthcheck.sh
