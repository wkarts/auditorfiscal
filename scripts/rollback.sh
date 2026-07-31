#!/usr/bin/env bash
set -Eeuo pipefail
TAG=${1:?Uso: ./scripts/rollback.sh v1.0.0}
git fetch --tags
git checkout "$TAG"

# shellcheck disable=SC1091
source "$(dirname "$0")/lib/compose.sh"

if [[ "${DEPLOY_MODE:-source}" == "ghcr" ]]; then
  export AUDITOR_IMAGE_TAG="${TAG#v}"
  dc pull
  dc up -d --remove-orphans --no-build
else
  dc build
  dc up -d --remove-orphans
fi
./scripts/healthcheck.sh
