#!/usr/bin/env bash
set -Eeuo pipefail
# shellcheck disable=SC1091
source "$(dirname "$0")/lib/compose.sh"
STAMP=$(date +%Y%m%d_%H%M%S); DIR="backups/$STAMP"; mkdir -p "$DIR/minio"
dc exec -T postgres pg_dump -Fc -U "$DB_USERNAME" "$DB_DATABASE" > "$DIR/postgres.dump"
dc run --rm --entrypoint /bin/sh -v "$PWD/$DIR/minio:/backup" minio-init -c "mc alias set local http://minio:9000 '$MINIO_ROOT_USER' '$MINIO_ROOT_PASSWORD' && mc mirror local/$AWS_BUCKET /backup"
tar -czf "backups/auditor-$STAMP.tar.gz" -C backups "$STAMP"
sha256sum "backups/auditor-$STAMP.tar.gz" > "backups/auditor-$STAMP.tar.gz.sha256"
rm -rf "$DIR"; echo "Backup criado: backups/auditor-$STAMP.tar.gz"
