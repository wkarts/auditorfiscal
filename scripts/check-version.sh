#!/usr/bin/env bash
set -Eeuo pipefail

MODE="${1:-}"
[[ -n "$MODE" ]] || MODE="--local"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

case "$MODE" in
  --ci | --local | --release) ;;
  *)
    echo 'Uso: scripts/check-version.sh [--ci|--local|--release]' >&2
    exit 2
    ;;
esac

VERSION_VALUE="$(tr -d '[:space:]' < VERSION)"
[[ "$VERSION_VALUE" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || {
  echo "VERSION inválida: $VERSION_VALUE" >&2
  exit 1
}

python3 - "$VERSION_VALUE" <<'PY'
import json
import re
import sys
from pathlib import Path

version = sys.argv[1]
root = Path('.')
web = json.loads((root / 'apps/web/package.json').read_text())['version']
if web != version:
    raise SystemExit(f'apps/web/package.json={web}, esperado {version}')
pyproject = (root / 'services/fiscal-engine/pyproject.toml').read_text()
match = re.search(r'^version\s*=\s*"([^"]+)"', pyproject, re.M)
if not match or match.group(1) != version:
    raise SystemExit('Versão do fiscal-engine não coincide com VERSION')
main = (root / 'services/fiscal-engine/app/main.py').read_text()
if f"version='{version}'" not in main:
    raise SystemExit('Versão da API FastAPI não coincide com VERSION')
changelog = (root / 'CHANGELOG.md').read_text()
if f'## [{version}]' not in changelog:
    raise SystemExit(f'CHANGELOG.md não possui seção [{version}]')

# Tags de deploy possuem ciclo próprio: `latest` acompanha releases e uma tag
# SemVer pode ser fixada para homologação/rollback. Elas nunca precisam coincidir
# com VERSION, que representa exclusivamente os metadados da aplicação.
env = (root / '.env.example').read_text()
for variable in ('AUDITOR_IMAGE_TAG', 'APP_IMAGE_TAG'):
    match = re.search(rf'^{variable}=(\S+)$', env, re.M)
    if not match or not re.fullmatch(r'(?:latest|\d+\.\d+\.\d+)', match.group(1)):
        raise SystemExit(f'{variable} deve ser latest ou uma tag SemVer')
print(f'Contrato de versão {version} aprovado.')
PY

# No CI de Pull Request, auto-version.sh é a autoridade para reservar a próxima
# versão. A disponibilidade da tag não é revalidada aqui para evitar uma corrida
# entre o commit automático e a execução que o originou.
if [[ "$MODE" == "--ci" ]]; then
  echo 'Disponibilidade da tag delegada ao versionamento automático do workflow.'
  exit 0
fi

TAG="v$VERSION_VALUE"
if git rev-parse "$TAG" >/dev/null 2>&1; then
  if [[ "$MODE" == "--release" ]]; then
    TAG_SHA="$(git rev-list -n 1 "$TAG")"
    HEAD_SHA="$(git rev-parse HEAD)"
    if [[ "$TAG_SHA" == "$HEAD_SHA" ]]; then
      echo "A tag $TAG já aponta para o commit atual; reexecução da release autorizada."
      exit 0
    fi
  fi
  echo "A tag $TAG já existe. Incremente VERSION antes do merge." >&2
  exit 1
fi
