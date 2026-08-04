#!/usr/bin/env bash
set -Eeuo pipefail

BASE_REF="${1:-}"
if [[ -z "$BASE_REF" ]]; then
  echo 'Uso: scripts/check-api-base-version.sh <commit-base>' >&2
  exit 2
fi

git rev-parse --verify --quiet "${BASE_REF}^{commit}" >/dev/null || {
  echo "Commit-base indisponível: ${BASE_REF}" >&2
  exit 1
}

if git diff --quiet "${BASE_REF}...HEAD" -- docker/base/api.Dockerfile; then
  echo 'Dockerfile da imagem-base não foi alterado.'
  exit 0
fi

if git diff --quiet "${BASE_REF}...HEAD" -- docker/base/versions.env; then
  cat >&2 <<'EOF'
O Dockerfile da imagem-base foi alterado sem incrementar API_BASE_VERSION.
Atualize docker/base/versions.env para uma nova tag imutável antes do merge.
EOF
  exit 1
fi

base_version="$({ git show "${BASE_REF}:docker/base/versions.env" 2>/dev/null || true; } | awk -F= '$1 == "API_BASE_VERSION" { print $2; exit }')"
current_version="$(awk -F= '$1 == "API_BASE_VERSION" { print $2; exit }' docker/base/versions.env)"

if [[ -z "$current_version" || "$current_version" == "$base_version" ]]; then
  echo 'API_BASE_VERSION deve ser diferente da versão anterior da imagem-base.' >&2
  exit 1
fi

echo "Contrato da imagem-base aprovado: ${base_version:-sem-versão} -> ${current_version}."
