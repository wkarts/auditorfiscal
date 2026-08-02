# Deploy com CloudPanel

O CloudPanel deve atuar apenas como proxy reverso e gerenciador de certificado.
A aplicação Docker fica vinculada à interface local da VPS.

## Stack

No `.env`:

```dotenv
WEB_BIND_HOST=127.0.0.1
WEB_PUBLISHED_PORT=8080
DEPLOY_MODE=ghcr
GHCR_NAMESPACE=wkarts
AUDITOR_IMAGE_TAG=latest
```

Suba a aplicação:

```bash
./scripts/install.sh
curl http://127.0.0.1:8080/health
```

## Site no CloudPanel

Crie um **Reverse Proxy** com:

```text
Domain Name: auditor.wwsoftwares.com.br
Reverse Proxy URL: http://127.0.0.1:8080
```

Em seguida, emita ou instale o certificado no CloudPanel e habilite o
redirecionamento HTTP para HTTPS. A stack não publica 80/443 e não executa outro
proxy público. Se o CloudPanel estiver em outro host, use `WEB_BIND_HOST=0.0.0.0`
somente com firewall permitindo a porta configurada exclusivamente a esse host.

Valide pelo host e pelo domínio:

```bash
curl --fail http://127.0.0.1:8080/health
curl --fail https://auditor.wwsoftwares.com.br/health
curl --fail https://auditor.wwsoftwares.com.br/api/v1/health/live
```

## Atualização segura de uma stack existente

Faça backup e valide a configuração antes de recriar os containers. Não use
`down -v`, prune de volumes nem remova os diretórios persistentes:

```bash
./scripts/backup.sh
git pull --ff-only
docker compose config
docker compose config --services
docker compose up -d --build --wait --wait-timeout 300 --remove-orphans
docker compose ps
docker compose logs --tail=200
curl --fail http://127.0.0.1:8080/health
curl --fail http://127.0.0.1:8080/api/v1/health/live
```

Antes de liberar uploads, confirme o contrato do armazenamento. O comando cria,
lê e remove um objeto temporário; ele não altera XMLs ou relatórios existentes:

```bash
docker compose run --rm --no-deps auditor-fiscal-api php artisan storage:verify
```

Se `AWS_ACCESS_KEY_ID` ou `AWS_SECRET_ACCESS_KEY` estiverem vazias, a aplicação
reutiliza respectivamente `MINIO_ROOT_USER` e `MINIO_ROOT_PASSWORD`. Para AWS S3
externo, configure as variáveis AWS explicitamente via secret manager.

`--remove-orphans` remove somente o container do antigo proxy que deixou de
pertencer ao projeto; não remove bind mounts nem dados de PostgreSQL, Redis,
RabbitMQ ou MinIO. Os serviços `storage-init`, `app-init` e `minio-init` são
one-shot: o estado `Exited (0)` é sucesso e não aciona a parada da stack.

O erro anterior ocorria porque o Dockge resolvia o bind mount do arquivo do proxy
relativamente à pasta da stack. Quando a origem não existia, o Docker criava um
diretório nesse caminho e falhava ao montá-lo sobre um destino que era arquivo. A
implantação era então cancelada pelo processo gerenciador, que enviava `SIGTERM`
coordenado aos containers já iniciados; os encerramentos limpos com código zero
não eram falhas individuais. A repetição de `Running` era o progresso das novas
tentativas de implantação, e não um loop interno dos serviços.

## Rollback sem perda de dados

Fixe `AUDITOR_IMAGE_TAG` ou `APP_IMAGE_TAG` na versão homologada anterior e
restaure o Compose correspondente pelo Git. Mantenha o proxy do CloudPanel
apontando para `127.0.0.1:8080` e execute somente `docker compose up -d --wait
--wait-timeout 300 --remove-orphans`. Restaure o backup apenas se a versão revertida não for
compatível com uma migration já aplicada. Nunca use `docker compose down -v`.
