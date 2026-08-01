#!/usr/bin/env bash
set -Eeuo pipefail
TAG=${1:?Uso: ./scripts/rollback.sh v1.0.0}
[[ "$TAG" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo 'Informe uma tag SemVer, por exemplo v1.0.0.' >&2; exit 1; }

git fetch --tags
git checkout "$TAG"

# shellcheck disable=SC1091
source "$(dirname "$0")/lib/compose.sh"

if [[ "${DEPLOY_MODE:-source}" == "ghcr" ]]; then
  export AUDITOR_IMAGE_TAG="${TAG#v}"
  dc pull auditor-fiscal-api auditor-fiscal-web auditor-fiscal-engine
  dc up -d --remove-orphans --no-build
else
  dc build auditor-fiscal-api auditor-fiscal-web auditor-fiscal-engine
  dc up -d --remove-orphans
fi
./scripts/healthcheck.sh
