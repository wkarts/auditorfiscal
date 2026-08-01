#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

CURRENT="$(tr -d '[:space:]' < VERSION)"
[[ "$CURRENT" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || {
  echo "VERSION inválida: $CURRENT" >&2
  exit 1
}

# Uma versão ainda não publicada é preservada. Quando a tag já existe, escolhe
# atomicamente o próximo patch após a maior tag SemVer conhecida no checkout.
if ! git rev-parse "v$CURRENT" >/dev/null 2>&1; then
  echo "Versão $CURRENT ainda disponível; nenhum incremento necessário."
  exit 0
fi

NEXT="$(python3 - "$CURRENT" <<'PY'
import re
import subprocess
import sys

current = tuple(map(int, sys.argv[1].split('.')))
versions = [current]
for tag in subprocess.check_output(['git', 'tag', '--list', 'v*'], text=True).splitlines():
    match = re.fullmatch(r'v(\d+)\.(\d+)\.(\d+)', tag)
    if match:
        versions.append(tuple(map(int, match.groups())))
major, minor, patch = max(versions)
print(f'{major}.{minor}.{patch + 1}')
PY
)"

python3 - "$CURRENT" "$NEXT" <<'PY'
import json
import re
import sys
from datetime import date
from pathlib import Path

current, version = sys.argv[1:]
root = Path('.')
(root / 'VERSION').write_text(version + '\n')

package = root / 'apps/web/package.json'
data = json.loads(package.read_text())
data['version'] = version
package.write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n')

pyproject = root / 'services/fiscal-engine/pyproject.toml'
pyproject.write_text(re.sub(
    r'^version\s*=\s*"[^"]+"', f'version = "{version}"',
    pyproject.read_text(), count=1, flags=re.M,
))

main = root / 'services/fiscal-engine/app/main.py'
main.write_text(re.sub(r"version='[^']+'", f"version='{version}'", main.read_text(), count=1))

env_example = root / '.env.example'
text = env_example.read_text()
text = re.sub(r'^AUDITOR_IMAGE_TAG=.*$', f'AUDITOR_IMAGE_TAG={version}', text, count=1, flags=re.M)
text = re.sub(r'^APP_IMAGE_TAG=.*$', f'APP_IMAGE_TAG={version}', text, count=1, flags=re.M)
env_example.write_text(text)

dockge = root / 'deploy/dockge/compose.yaml'
dockge.write_text(dockge.read_text().replace(
    f'${{APP_IMAGE_TAG:-{current}}}', f'${{APP_IMAGE_TAG:-{version}}}',
))

changelog = root / 'CHANGELOG.md'
text = changelog.read_text()
heading = (
    f'## [{version}] - {date.today().isoformat()}\n\n'
    '### Alterado\n\n'
    '- Versão patch reservada automaticamente pelo workflow para evitar reutilização de tag.\n\n'
)
if f'## [{version}]' not in text:
    text = text.replace('# Changelog\n\n', '# Changelog\n\n' + heading, 1)
    changelog.write_text(text)
PY

echo "Versão publicada $CURRENT detectada; contrato atualizado automaticamente para $NEXT."
