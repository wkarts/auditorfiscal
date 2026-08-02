# Opções de implantação

| Cenário | Arquivos principais | Proxy/HTTPS |
|---|---|---|
| Docker Compose standalone | `compose.yaml` | CloudPanel/Nginx no host |
| Docker Compose + GHCR | `compose.yaml` + `compose.production.yaml` | CloudPanel/Nginx no host |
| Dockge | `deploy/dockge/compose.yaml` | CloudPanel/Nginx no host |
| CloudPanel | stack Docker com porta local | Reverse Proxy do CloudPanel |
| Portainer | `deploy/dockge/compose.yaml` como Stack | Proxy externo |
| Nginx no host | stack com porta Web local | `deploy/nginx/auditor-fiscal.conf.example` |
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
