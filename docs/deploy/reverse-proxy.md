# Proxy reverso: Caddy, Nginx e Traefik

## Caddy interno

No `.env`:

```dotenv
COMPOSE_PROFILES=edge-caddy
AUDITOR_DOMAIN=auditor.example.com
CADDY_EMAIL=admin@example.com
```

Libere 80/TCP e 443/TCP e execute `./scripts/install.sh`.

## Nginx no host

Deixe `COMPOSE_PROFILES=` e mantenha `APP_HTTP_BIND=127.0.0.1`. Copie
`deploy/nginx/auditor-fiscal.conf.example`, ajuste o domínio e habilite o site.

## Traefik

Use `deploy/traefik/compose.override.yaml`, conecte a stack à rede externa do
Traefik e ajuste `TRAEFIK_NETWORK`, `AUDITOR_DOMAIN` e o resolver de certificados.
