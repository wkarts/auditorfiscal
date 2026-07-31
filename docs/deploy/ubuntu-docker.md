# Preparação de uma VPS Ubuntu com Docker

Este guia prepara uma VPS limpa para executar o Auditor Fiscal com Docker
Engine e Docker Compose V2. Use uma conta com `sudo` e mantenha um snapshot da
VPS antes de alterações estruturais.

## Requisitos mínimos recomendados

- Ubuntu Server 24.04 LTS ou versão suportada pelo Docker;
- 4 vCPU;
- 8 GB de RAM;
- 80 GB de SSD, ajustado ao volume de XMLs, relatórios e backups;
- DNS do domínio apontando para o IP público da VPS;
- portas 22, 80 e 443 controladas pelo firewall.

## Instalar pelo repositório oficial do Docker

```bash
sudo apt-get update
sudo apt-get install -y ca-certificates curl
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg \
  -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc

. /etc/os-release
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] \
https://download.docker.com/linux/ubuntu ${UBUNTU_CODENAME:-$VERSION_CODENAME} stable" \
| sudo tee /etc/apt/sources.list.d/docker.list >/dev/null

sudo apt-get update
sudo apt-get install -y \
  docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

sudo systemctl enable --now docker
sudo docker run --rm hello-world
sudo docker compose version
```

## Operar sem `sudo`

```bash
sudo usermod -aG docker "$USER"
newgrp docker
```

A associação ao grupo `docker` concede privilégios equivalentes aos de root.
Restrinja esse grupo a administradores da VPS.

## Diretórios da aplicação

```bash
sudo mkdir -p /opt/auditor-fiscal /opt/auditor-fiscal/backups
sudo chown -R "$USER":"$USER" /opt/auditor-fiscal
cd /opt/auditor-fiscal
```

Depois clone o repositório ou extraia o pacote de implantação da release e siga
`docker-compose.md`, `dockge.md` ou `cloudpanel.md`.

## Firewall

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

Portas publicadas pelo Docker exigem atenção especial, pois podem contornar
regras simples do UFW. A stack mantém banco, Redis, RabbitMQ e MinIO sem portas
públicas. Para restrições adicionais, use regras na cadeia `DOCKER-USER`.
