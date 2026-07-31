# Deploy com Dockge

O Dockge administra stacks baseadas em `compose.yaml` e mantém os arquivos no
disco. O diretório padrão é `/opt/stacks` e a interface usa normalmente a porta
5001.

## Instalar o Dockge

```bash
sudo mkdir -p /opt/stacks /opt/dockge
cd /opt/dockge
curl -fsSL https://raw.githubusercontent.com/louislam/dockge/master/compose.yaml -o compose.yaml
docker compose up -d
```

Acesse `http://IP_DA_VPS:5001` e proteja a interface com firewall ou proxy HTTPS.

## Criar a stack

```bash
sudo mkdir -p /opt/stacks/auditor-fiscal
sudo cp deploy/dockge/compose.yaml /opt/stacks/auditor-fiscal/compose.yaml
sudo cp .env.example /opt/stacks/auditor-fiscal/.env
sudo nano /opt/stacks/auditor-fiscal/.env
```

Configure `GHCR_NAMESPACE`, `AUDITOR_IMAGE_TAG`, credenciais e porta local. Para
registro privado, faça `docker login ghcr.io` no host e monte `/root/.docker` no
container do Dockge conforme a documentação do próprio Dockge.

Na interface, use **Scan Stacks Folder**, abra `auditor-fiscal` e clique em
**Deploy**. Depois execute pelo terminal da stack:

```bash
docker compose run --rm minio-init
docker compose run --rm api php artisan migrate --force
docker compose run --rm api php artisan db:seed --force
```
