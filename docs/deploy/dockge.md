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
sudo cp deploy/caddy/Caddyfile /opt/stacks/auditor-fiscal-cliente01/Caddyfile
sudo cp deploy/monitoring/prometheus.yml /opt/stacks/auditor-fiscal-cliente01/prometheus.yml
sudo nano /opt/stacks/auditor-fiscal-cliente01/.env
```

Defina, obrigatoriamente, valores únicos e coerentes:

```dotenv
COMPOSE_PROJECT_NAME=auditor-fiscal-cliente01
INSTANCE_NAME=cliente01
RESOURCE_PREFIX=auditor-fiscal-cliente01
APP_HTTP_PORT=8081
```

Preencha senhas, tokens e URLs no `.env` local, nunca no Compose ou no Git.
`APP_KEY` pode ficar vazio apenas no primeiro deploy: a API gera uma chave forte
em `./api_storage/.app_key`, com escrita atômica e permissão restrita, e todos os
processos reutilizam esse mesmo arquivo. Inclua-o no backup; perdê-lo invalida
sessões e dados criptografados. `IMAGE_NAMESPACE=wkarts` seleciona as imagens
oficiais e `APP_IMAGE_TAG=latest` acompanha automaticamente a release mais recente.
Se o package for privado, autentique o host com um token de
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
**Deploy**. O serviço one-shot `auditor-fiscal-app-init` executa automaticamente
migrations, seed do administrador e declara as filas `high`, `default` e `reports`
antes da API iniciar. O seed atualiza a senha do administrador somente quando o
valor de `ADMIN_PASSWORD` mudou. Para conferir ou recuperar manualmente:

```bash
docker compose logs auditor-fiscal-app-init
docker compose run --rm auditor-fiscal-app-init
docker compose exec auditor-fiscal-rabbitmq rabbitmqctl list_queues name
```

O Compose da raiz usa `build` para desenvolvimento por código-fonte; o Compose
do Dockge usa as imagens publicadas porque a pasta da stack não contém os contextos
de build. Essa é a única diferença estrutural intencional: a CI compara as listas
de serviços, e ambos oferecem API, inicialização, worker, scheduler, Web, engine,
PostgreSQL, Redis, RabbitMQ, MinIO, Caddy, Prometheus e Grafana.

## Várias instâncias no mesmo host

Repita o procedimento em outra pasta, usando, por exemplo,
`COMPOSE_PROJECT_NAME=auditor-fiscal-cliente02`, `INSTANCE_NAME=cliente02`,
`RESOURCE_PREFIX=auditor-fiscal-cliente02`, `AWS_BUCKET=auditor-fiscal-cliente02`
e `APP_HTTP_PORT=8082`. Os hostnames internos (`auditor-fiscal-postgres`, `auditor-fiscal-redis`,
`auditor-fiscal-rabbitmq`, `auditor-fiscal-minio` e `auditor-fiscal-engine`) permanecem estáveis, mas só resolvem dentro da rede isolada.
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

Antes da atualização automática, faça backup e execute
`docker compose pull && docker compose up -d --wait`. Para uma janela controlada,
fixe temporariamente `APP_IMAGE_TAG` na versão homologada. Em um rollback, restaure
o backup quando houver migration incompatível, use a tag anterior imutável e
repita o comando; depois da recuperação, decida quando voltar para `latest`.
