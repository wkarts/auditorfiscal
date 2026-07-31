# Implantação em VPS

## Requisitos
- Ubuntu 24.04 LTS ou distribuição equivalente.
- 4 vCPU, 8 GB RAM e 120 GB SSD para ambiente inicial; ajuste pelo volume.
- Docker Engine e Docker Compose Plugin.
- Domínio apontado para o IPv4/IPv6 da VPS.

## DNS e firewall
Crie registro `A` para o domínio e, quando aplicável, `AAAA`. Libere apenas SSH, HTTP e HTTPS:

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

PostgreSQL, Redis, RabbitMQ e MinIO não possuem portas publicadas no Compose.

## Instalação

```bash
git clone <URL_DO_REPOSITORIO> auditor-fiscal
cd auditor-fiscal
cp .env.example .env
nano .env
./scripts/install.sh
```

Preencha senhas fortes, `AUDITOR_DOMAIN`, `CADDY_EMAIL`, `APP_URL`, `FRONTEND_URL`, `ADMIN_EMAIL` e `ADMIN_PASSWORD`.

Para implantar usando as imagens do GitHub Container Registry, mantenha:

```dotenv
DEPLOY_MODE=ghcr
GHCR_REGISTRY=ghcr.io
GHCR_NAMESPACE=wkarts
AUDITOR_IMAGE_TAG=latest
```

Para uma release imutável, use `AUDITOR_IMAGE_TAG=1.0.0` em vez de `latest`. Se os pacotes forem privados, execute `docker login ghcr.io` antes do instalador. O Caddy solicita e renova o certificado TLS automaticamente.

## Verificação

```bash
docker compose -f compose.yaml -f compose.production.yaml ps
curl -fsS https://SEU_DOMINIO/api/v1/health/live
curl -fsS https://SEU_DOMINIO/api/v1/health/ready
./scripts/healthcheck.sh
```

## Workers e agendador
O serviço `worker` executa filas `high,default,reports`; `scheduler` executa o Laravel Scheduler. Para ampliar capacidade:

```bash
docker compose -f compose.yaml -f compose.production.yaml up -d --scale worker=4 --no-build
```

## Monitoramento

```bash
docker compose -f compose.yaml -f compose.production.yaml --profile monitoring up -d --no-build
```

Restrinja o acesso ao Grafana por VPN, firewall ou proxy autenticado. O endpoint de métricas do motor é interno.

## Atualização e rollback

```bash
./scripts/update.sh v1.1.0
./scripts/rollback.sh v1.0.0
```

Faça backup antes de qualquer atualização. Migrations destrutivas devem usar estratégia expand/contract.
