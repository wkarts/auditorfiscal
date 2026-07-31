#!/usr/bin/env bash
set -Eeuo pipefail
FILE=${1:?Uso: ./scripts/restore.sh backups/auditor-AAAAmmdd_HHMMSS.tar.gz}
set -a; source .env; set +a
TMP=$(mktemp -d); trap 'rm -rf "$TMP"' EXIT
tar -xzf "$FILE" -C "$TMP"; DIR=$(find "$TMP" -mindepth 1 -maxdepth 1 -type d | head -n1)
docker compose exec -T postgres dropdb -U "$DB_USERNAME" --if-exists "$DB_DATABASE"
docker compose exec -T postgres createdb -U "$DB_USERNAME" "$DB_DATABASE"
docker compose exec -T postgres pg_restore -U "$DB_USERNAME" -d "$DB_DATABASE" --clean --if-exists < "$DIR/postgres.dump"
docker compose run --rm --entrypoint /bin/sh -v "$DIR/minio:/restore:ro" minio-init -c "mc alias set local http://minio:9000 '$MINIO_ROOT_USER' '$MINIO_ROOT_PASSWORD' && mc mirror /restore local/$AWS_BUCKET"
echo 'Restauração concluída.'
