#!/usr/bin/env bash
set -Eeuo pipefail

VERSION_VALUE=${1:?Uso: ./scripts/release.sh 1.2.3}
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

./scripts/check-version.sh
git add VERSION apps/web/package.json services/fiscal-engine/pyproject.toml \
  services/fiscal-engine/app/main.py CHANGELOG.md
git commit -m "chore(release): prepara v$VERSION_VALUE"
echo "Versão v$VERSION_VALUE preparada. Abra o Pull Request e aguarde a release automática após o merge em main."
