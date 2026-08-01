#!/usr/bin/env bash
set -Eeuo pipefail
# shellcheck disable=SC1091
source "$(dirname "$0")/lib/compose.sh"
FILE=${1:?Uso: ./scripts/restore.sh backups/auditor-AAAAmmdd_HHMMSS.tar.gz}
TMP=$(mktemp -d); trap 'rm -rf "$TMP"' EXIT
tar -xzf "$FILE" -C "$TMP"; DIR=$(find "$TMP" -mindepth 1 -maxdepth 1 -type d | head -n1)
dc exec -T auditor-fiscal-postgres dropdb -U "$DB_USERNAME" --if-exists "$DB_DATABASE"
dc exec -T auditor-fiscal-postgres createdb -U "$DB_USERNAME" "$DB_DATABASE"
dc exec -T auditor-fiscal-postgres pg_restore -U "$DB_USERNAME" -d "$DB_DATABASE" --clean --if-exists < "$DIR/postgres.dump"
dc run --rm --entrypoint /bin/sh -v "$DIR/minio:/restore:ro" auditor-fiscal-minio-init -c "mc alias set local http://auditor-fiscal-minio:9000 '$MINIO_ROOT_USER' '$MINIO_ROOT_PASSWORD' && mc mirror /restore local/$AWS_BUCKET"
echo 'Restauração concluída.'
