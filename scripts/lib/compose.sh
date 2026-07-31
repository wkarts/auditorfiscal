#!/usr/bin/env bash

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$PROJECT_ROOT"

if [[ -f .env ]]; then
  set -a
  # shellcheck disable=SC1091
  source .env
  set +a
fi

COMPOSE_ARGS=(-f compose.yaml)
case "${DEPLOY_MODE:-source}" in
  source)
    ;;
  ghcr)
    COMPOSE_ARGS+=(-f compose.production.yaml)
    ;;
  *)
    echo "DEPLOY_MODE inválido: ${DEPLOY_MODE}. Use source ou ghcr." >&2
    exit 1
    ;;
esac

dc() {
  docker compose "${COMPOSE_ARGS[@]}" "$@"
}
