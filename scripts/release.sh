#!/usr/bin/env bash
set -Eeuo pipefail
VERSION=${1:?Uso: ./scripts/release.sh 1.2.3}
[[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo 'Versão SemVer inválida'; exit 1; }
echo "$VERSION" > VERSION
git add VERSION CHANGELOG.md
git commit -m "chore(release): v$VERSION"
git tag -s "v$VERSION" -m "Auditor Fiscal v$VERSION" || git tag "v$VERSION"
echo "Release preparada. Execute: git push origin main --tags"
