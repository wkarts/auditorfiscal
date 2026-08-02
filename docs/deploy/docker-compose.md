# Deploy com Docker Compose

## Requisitos

- Linux 64 bits;
- Docker Engine e plugin Docker Compose V2;
- 8 GB de RAM recomendados para a stack completa;
- CloudPanel/Nginx configurado como proxy reverso para a porta Web local.

Instale o Docker pelo repositório oficial da distribuição e valide:

```bash
docker version
docker compose version
docker run --rm hello-world
```

## Instalação por código-fonte

```bash
git clone https://github.com/wkarts/auditorfiscal.git auditor-fiscal
cd auditor-fiscal
cp .env.example .env
nano .env
```

Configure:

```dotenv
DEPLOY_MODE=source
WEB_BIND_HOST=127.0.0.1
WEB_PUBLISHED_PORT=8080
AUDITOR_DOMAIN=auditor.wwsoftwares.com.br
APP_URL=https://auditor.wwsoftwares.com.br
FRONTEND_URL=https://auditor.wwsoftwares.com.br
```

Preencha todas as senhas e execute:

```bash
./scripts/install.sh
```

## Instalação por imagens publicadas

```dotenv
DEPLOY_MODE=ghcr
GHCR_NAMESPACE=wkarts
AUDITOR_IMAGE_TAG=latest
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
