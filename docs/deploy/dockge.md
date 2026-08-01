# Deploy isolado com Dockge

O Dockge administra stacks baseadas em Compose no disco (normalmente em
`/opt/stacks`). O arquivo `deploy/dockge/compose.yaml` não define
`container_name`: o Compose deriva os containers do projeto e permite escalar
serviços. A rede e os cinco volumes persistentes recebem um prefixo explícito da
instalação; nenhum recurso é externo ou compartilhado.

## Criar uma instância

Crie um diretório diferente por cliente e copie o contrato de ambiente:

```bash
sudo mkdir -p /opt/stacks/auditor-fiscal-cliente01
sudo cp deploy/dockge/compose.yaml /opt/stacks/auditor-fiscal-cliente01/compose.yaml
sudo cp .env.example /opt/stacks/auditor-fiscal-cliente01/.env
sudo nano /opt/stacks/auditor-fiscal-cliente01/.env
```

Defina, obrigatoriamente, valores únicos e coerentes:

```dotenv
COMPOSE_PROJECT_NAME=auditor-fiscal-cliente01
INSTANCE_NAME=cliente01
RESOURCE_PREFIX=auditor-fiscal-cliente01
APP_HTTP_PORT=8081
```

Preencha também `APP_KEY`, senhas, tokens e URLs no `.env` local, nunca no
Compose ou no Git. `IMAGE_NAMESPACE=wkarts` e `APP_IMAGE_TAG` selecionam as
imagens publicadas. Se o package for privado, autentique o host com um token de
leitura fornecido por variável/secret manager (`docker login ghcr.io`).

O `env_file` usa o formato de lista de strings compatível com versões do Compose
embarcadas pelo Dockge. O deploy exige o arquivo `/opt/stacks/<stack>/.env`;
confirme sua existência e permissões antes de clicar em **Deploy**:

```bash
test -s .env
chmod 600 .env
mkdir -p api_storage postgres_data redis_data rabbitmq_data minio_data
```

Na interface, use **Scan Stacks Folder**, abra a pasta criada e clique em
**Deploy**. Inicialize os recursos de forma idempotente pelo terminal da stack:

```bash
docker compose run --rm auditor-fiscal-minio-init
docker compose run --rm auditor-fiscal-api php artisan migrate --force
docker compose run --rm auditor-fiscal-api php artisan db:seed --force
```

## Várias instâncias no mesmo host

Repita o procedimento em outra pasta, usando, por exemplo,
`COMPOSE_PROJECT_NAME=auditor-fiscal-cliente02`, `INSTANCE_NAME=cliente02`,
`RESOURCE_PREFIX=auditor-fiscal-cliente02`, `AWS_BUCKET=auditor-fiscal-cliente02`
e `APP_HTTP_PORT=8082`. Os hostnames internos (`auditor-fiscal-postgres`, `auditor-fiscal-redis`,
`auditor-fiscal-rabbitmq`, `auditor-fiscal-minio` e `auditor-fiscal-fiscal-engine`) permanecem estáveis, mas só resolvem dentro da rede isolada.
Somente a porta Web é publicada. Caso o perfil Caddy do Compose principal seja
usado, altere também `CADDY_HTTP_PORT` e `CADDY_HTTPS_PORT`.

Verifique antes do deploy:

```bash
docker compose --env-file .env -f compose.yaml config --quiet
docker compose --env-file .env -f compose.yaml config --volumes
docker compose --env-file .env -f compose.yaml config --networks
```

Os dados são bind mounts sob o próprio diretório da stack: `./api_storage`,
`./postgres_data`, `./redis_data`, `./rabbitmq_data` e `./minio_data`. Assim,
`docker compose down`, inclusive com `--volumes`, não apaga esses diretórios.
O serviço idempotente `auditor-fiscal-storage-init` prepara a propriedade do
storage compartilhado da API antes de sua inicialização. Nunca aponte duas
stacks para o mesmo diretório. Para remover uma instalação, faça backup e exclua
os diretórios manualmente; para mover dados, use os procedimentos em
`docs/deploy/backup-restore.md`.

## Atualização e rollback

Antes da atualização, faça backup, fixe `APP_IMAGE_TAG` na nova versão e execute
`docker compose pull && docker compose up -d --wait`. Para rollback, restaure o
backup quando houver migration incompatível, volte `APP_IMAGE_TAG` para a versão
anterior imutável e repita o comando. Não use `latest` em produção.
