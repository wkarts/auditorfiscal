# Deploy com Docker Compose

## Requisitos

- Linux 64 bits;
- Docker Engine e plugin Docker Compose V2;
- 8 GB de RAM recomendados para a stack completa;
- domínio apontado para a VPS quando o Caddy interno for utilizado.

Instale o Docker pelo repositório oficial da distribuição e valide:

```bash
docker version
docker compose version
docker run --rm hello-world
```

## Instalação por código-fonte

```bash
git clone <REPOSITORIO> auditor-fiscal
cd auditor-fiscal
cp .env.example .env
nano .env
```

Defina:

```dotenv
DEPLOY_MODE=source
COMPOSE_PROFILES=edge-caddy
AUDITOR_DOMAIN=auditor.example.com
APP_URL=https://auditor.example.com
FRONTEND_URL=https://auditor.example.com
```

Preencha todas as senhas e execute:

```bash
./scripts/install.sh
```

## Instalação por imagens publicadas

```dotenv
DEPLOY_MODE=ghcr
GHCR_NAMESPACE=usuario-ou-organizacao
AUDITOR_IMAGE_TAG=1.0.1
```

Para pacotes privados:

```bash
echo "$GHCR_TOKEN" | docker login ghcr.io -u "$GHCR_USER" --password-stdin
```

Depois:

```bash
./scripts/install.sh
```

## Operação

```bash
make ps
make logs
make backup
./scripts/update.sh v1.0.2
./scripts/rollback.sh v1.0.1
```
