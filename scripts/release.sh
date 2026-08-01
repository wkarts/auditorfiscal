#!/usr/bin/env bash
set -Eeuo pipefail

if [[ "${1:-}" == "--next" ]]; then
  bash ./scripts/auto-version.sh
  VERSION_VALUE="$(tr -d '[:space:]' < VERSION)"
else
  VERSION_VALUE=${1:?Uso: ./scripts/release.sh 1.2.3 ou ./scripts/release.sh --next}
fi
[[ "$VERSION_VALUE" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || {
  echo 'Versão SemVer inválida.' >&2
  exit 1
}

python3 - "$VERSION_VALUE" <<'PY'
import json
import re
import sys
from pathlib import Path

version = sys.argv[1]
root = Path('.')
(root / 'VERSION').write_text(version + '\n')

package = root / 'apps/web/package.json'
data = json.loads(package.read_text())
data['version'] = version
package.write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n')

pyproject = root / 'services/fiscal-engine/pyproject.toml'
text = pyproject.read_text()
text = re.sub(r'^version\s*=\s*"[^"]+"', f'version = "{version}"', text, count=1, flags=re.M)
pyproject.write_text(text)

main = root / 'services/fiscal-engine/app/main.py'
text = main.read_text()
text = re.sub(r"version='[^']+'", f"version='{version}'", text, count=1)
main.write_text(text)

changelog = root / 'CHANGELOG.md'
text = changelog.read_text()
heading = f'## [{version}] - A DEFINIR\n\n### Alterado\n\n- Descreva as alterações desta versão.\n\n'
if f'## [{version}]' not in text:
    marker = '# Changelog\n\n'
    text = text.replace(marker, marker + heading, 1)
    changelog.write_text(text)
PY

bash ./scripts/check-version.sh
git add VERSION CHANGELOG.md \
  apps/web/package.json services/fiscal-engine/pyproject.toml \
  services/fiscal-engine/app/main.py
if git diff --cached --quiet; then
  echo "Versão v$VERSION_VALUE já estava preparada; nenhum commit necessário."
  exit 0
fi
git commit -m "chore(release): prepara v$VERSION_VALUE"
echo "Versão v$VERSION_VALUE preparada. Abra o Pull Request e aguarde a release automática após o merge em main."
