# Deploy com Portainer

1. Abra **Stacks** e escolha **Add stack**.
2. Use **Repository** para apontar ao Git ou cole o conteúdo de
   `deploy/dockge/compose.yaml` no editor.
3. Cadastre as variáveis do `.env` na área de ambiente.
4. Para GHCR privado, registre `ghcr.io` em **Registries** usando token com
   `read:packages`.
5. Faça o deploy e, no console da stack, execute migração e seed:

```bash
docker compose run --rm auditor-fiscal-minio-init
docker compose run --rm auditor-fiscal-api php artisan migrate --force
docker compose run --rm auditor-fiscal-api php artisan db:seed --force
```

Use a tag exata da release para permitir rollback previsível.
