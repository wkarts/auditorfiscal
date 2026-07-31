# Deploy com CloudPanel

O CloudPanel deve atuar apenas como proxy reverso e gerenciador de certificado.
A aplicação Docker fica vinculada à interface local da VPS.

## Stack

No `.env`:

```dotenv
COMPOSE_PROFILES=
APP_HTTP_BIND=127.0.0.1
APP_HTTP_PORT=8080
DEPLOY_MODE=ghcr
GHCR_NAMESPACE=usuario-ou-organizacao
AUDITOR_IMAGE_TAG=1.0.1
```

Suba a aplicação:

```bash
./scripts/install.sh
curl http://127.0.0.1:8080/health
```

## Site no CloudPanel

Crie um **Reverse Proxy** com:

```text
Domain Name: auditor.example.com
Reverse Proxy URL: http://127.0.0.1:8080
```

Pela CLI do CloudPanel, o equivalente é:

```bash
clpctl site:add:reverse-proxy \
  --domainName=auditor.example.com \
  --reverseProxyUrl='http://127.0.0.1:8080' \
  --siteUser=auditor \
  --siteUserPassword='<senha-forte>'
```

Em seguida, emita ou instale o certificado no CloudPanel. Não habilite o perfil
`edge-caddy`, pois CloudPanel já ocupa as portas 80 e 443.
