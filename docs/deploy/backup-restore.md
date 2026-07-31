# Backup e restauração

O backup inclui PostgreSQL e o bucket do MinIO:

```bash
./scripts/backup.sh
```

Os arquivos são gravados em `backups/` com SHA-256. Copie-os para armazenamento
externo criptografado e teste restaurações periodicamente.

Restauração:

```bash
./scripts/restore.sh backups/auditor-AAAAmmdd_HHMMSS.tar.gz
```

Antes de restaurar em produção, interrompa usuários e workers e confirme que o
backup pertence à mesma linha de versão do banco.
