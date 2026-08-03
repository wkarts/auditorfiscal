# Deploy isolado com Dockge

O Dockge administra stacks baseadas em Compose no disco (normalmente em
`/opt/stacks`). O arquivo `deploy/dockge/compose.yaml` não define
`container_name`: o Compose deriva os containers do projeto e permite escalar
serviços. A rede e os cinco volumes persistentes recebem um prefixo explícito da
instalação; nenhum recurso é externo ou compartilhado.

## Criar uma instância

Crie um diretório diferente por cliente e copie o contrato de ambiente:

```bash
sudo mkdir -p /opt/stacks/auditor-fiscal-wwsoftwares
sudo cp deploy/dockge/compose.yaml /opt/stacks/auditor-fiscal-wwsoftwares/compose.yaml
sudo cp .env.example /opt/stacks/auditor-fiscal-wwsoftwares/.env
sudo cp deploy/monitoring/prometheus.yml /opt/stacks/auditor-fiscal-wwsoftwares/prometheus.yml
sudo nano /opt/stacks/auditor-fiscal-wwsoftwares/.env
```

Configure valores únicos e coerentes:

```dotenv
COMPOSE_PROJECT_NAME=auditor-fiscal-wwsoftwares
INSTANCE_NAME=wwsoftwares
RESOURCE_PREFIX=auditor-fiscal-wwsoftwares
WEB_BIND_HOST=127.0.0.1
WEB_PUBLISHED_PORT=8081
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
migrations, seed do administrador, declara as filas `high`, `default` e `reports`
e comprova escrita/leitura no MinIO antes da API iniciar. Quando
`AWS_SECRET_ACCESS_KEY` estiver vazia, API e engine reutilizam automaticamente
`MINIO_ROOT_PASSWORD`; não é necessário duplicar o segredo. O seed atualiza a senha do administrador somente quando o
valor de `ADMIN_PASSWORD` mudou. Para conferir ou recuperar manualmente:

```bash
docker compose logs auditor-fiscal-app-init
docker compose run --rm auditor-fiscal-app-init
docker compose run --rm --no-deps auditor-fiscal-api php artisan storage:verify
docker compose exec auditor-fiscal-rabbitmq rabbitmqctl list_queues name
```

Após o deploy, `storage-init`, `app-init` e `minio-init` devem aparecer como
`Exited (0)`; isso representa conclusão bem-sucedida. API, Web, engine, worker,
scheduler, PostgreSQL, Redis, RabbitMQ e MinIO devem permanecer `running` ou
`healthy`. O contrato não usa `abort-on-container-exit`, portanto a conclusão de
um init não encerra serviços permanentes.

O Compose da raiz usa `build` para desenvolvimento por código-fonte; o Compose
do Dockge usa as imagens publicadas porque a pasta da stack não contém os contextos
de build. Essa é a única diferença estrutural intencional: a CI compara as listas
de serviços, e ambos oferecem API, inicialização, worker, scheduler, Web, engine,
PostgreSQL, Redis, RabbitMQ, MinIO, Prometheus e Grafana. O proxy público não
faz parte da stack: o CloudPanel encaminha o domínio para a porta Web local.

## Várias instâncias no mesmo host

Repita o procedimento em outra pasta, usando, por exemplo,
`COMPOSE_PROJECT_NAME=auditor-fiscal-wwsoftwares-homolog`, `INSTANCE_NAME=wwsoftwares-homolog`,
`RESOURCE_PREFIX=auditor-fiscal-wwsoftwares-homolog`, `AWS_BUCKET=auditor-fiscal-wwsoftwares-homolog`
e `WEB_PUBLISHED_PORT=8082`. Os hostnames internos (`auditor-fiscal-postgres`, `auditor-fiscal-redis`,
`auditor-fiscal-rabbitmq`, `auditor-fiscal-minio` e `auditor-fiscal-engine`) permanecem estáveis, mas só resolvem dentro da rede isolada.
Somente a porta Web é publicada, vinculada ao loopback por padrão. Configure um
Reverse Proxy diferente no CloudPanel para cada porta de instância.

Verifique antes do deploy:

```bash
docker compose --env-file .env -f compose.yaml config --quiet
docker compose --env-file .env -f compose.yaml config --volumes
docker compose --env-file .env -f compose.yaml config --networks
```

Os dados são bind mounts sob o próprio diretório da stack: `./api_storage`,
`./postgres_data`, `./redis_data`, `./rabbitmq_data` e `./minio_data`. Não use
remoção de volumes nem prune durante atualização, rollback ou validação.
O serviço idempotente `auditor-fiscal-storage-init` prepara a propriedade do
storage compartilhado da API antes de sua inicialização. Nunca aponte duas
stacks para o mesmo diretório. Para remover uma instalação, faça backup e exclua
os diretórios manualmente; para mover dados, use os procedimentos em
`docs/deploy/backup-restore.md`.

## Atualização e rollback

Antes da atualização, faça backup e atualize também o `compose.yaml` da pasta da
stack com a versão da release. Alterar apenas `.env`, usar `APP_IMAGE_TAG=latest`
ou executar `pull` não corrige um contrato Compose antigo. Em especial, confirme
que o engine recebe `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` e
`DB_PASSWORD` separadamente e que seu healthcheck usa `/health/ready`.

Depois execute `docker compose pull && docker compose up -d --wait --remove-orphans`.
O último
argumento remove o antigo container órfão do proxy, sem tocar nos bind mounts de
dados. Para uma janela controlada,
fixe temporariamente `APP_IMAGE_TAG` na versão homologada. Em um rollback, restaure
o backup quando houver migration incompatível, use a tag anterior imutável e
repita o comando; depois da recuperação, decida quando voltar para `latest`.

Verifique o contrato efetivamente aplicado, sem exibir segredos:

```bash
docker compose config --services
docker compose images
docker compose ps
docker compose logs auditor-fiscal-app-init
```

No contrato Dockge, `DEPLOY_MODE=source` não compila o repositório: essa stack
sempre consome as imagens definidas por `IMAGE_REGISTRY`, `IMAGE_NAMESPACE` e
`APP_IMAGE_TAG`.
