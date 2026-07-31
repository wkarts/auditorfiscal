#!/usr/bin/env bash
set -Eeuo pipefail
set -a; source .env; set +a
STAMP=$(date +%Y%m%d_%H%M%S); DIR="backups/$STAMP"; mkdir -p "$DIR/minio"
docker compose exec -T postgres pg_dump -Fc -U "$DB_USERNAME" "$DB_DATABASE" > "$DIR/postgres.dump"
docker compose run --rm --entrypoint /bin/sh -v "$PWD/$DIR/minio:/backup" minio-init -c "mc alias set local http://minio:9000 '$MINIO_ROOT_USER' '$MINIO_ROOT_PASSWORD' && mc mirror local/$AWS_BUCKET /backup"
tar -czf "backups/auditor-$STAMP.tar.gz" -C backups "$STAMP"
sha256sum "backups/auditor-$STAMP.tar.gz" > "backups/auditor-$STAMP.tar.gz.sha256"
rm -rf "$DIR"; echo "Backup criado: backups/auditor-$STAMP.tar.gz"
