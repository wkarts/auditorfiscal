# Opções de implantação

| Cenário | Arquivos principais | Proxy/HTTPS |
|---|---|---|
| Docker Compose standalone | `compose.yaml` | Caddy interno (`COMPOSE_PROFILES=edge-caddy`) |
| Docker Compose + GHCR | `compose.yaml` + `compose.production.yaml` | Caddy ou proxy externo |
| Dockge | `deploy/dockge/compose.yaml` | Proxy externo ou Caddy separado |
| CloudPanel | stack Docker com porta local | Reverse Proxy do CloudPanel |
| Portainer | `deploy/dockge/compose.yaml` como Stack | Proxy externo |
| Nginx no host | stack sem Caddy | `deploy/nginx/auditor-fiscal.conf.example` |
| Traefik | stack + override de labels | Traefik externo |

Guias:

- `ubuntu-docker.md`
- `docker-compose.md`
- `ghcr.md`
- `dockge.md`
- `cloudpanel.md`
- `portainer.md`
- `reverse-proxy.md`
- `backup-restore.md`
- `update-rollback.md`
- `hardening.md`
- `history-sanitization.md`
