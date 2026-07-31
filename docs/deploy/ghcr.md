# GitHub Container Registry

As releases publicam três imagens:

```text
ghcr.io/<namespace>/auditorfiscal-api:<versão>
ghcr.io/<namespace>/auditorfiscal-web:<versão>
ghcr.io/<namespace>/auditorfiscal-fiscal-engine:<versão>
```

São geradas tags de versão completa, major/minor, major, `latest` e SHA. Em
produção use a tag completa, por exemplo `1.0.1`, porque ela permite rollback
previsível.

## Publicação automática

O workflow `.github/workflows/release.yml` autentica no GHCR com o
`GITHUB_TOKEN`. O repositório precisa permitir escrita para workflows e o job
precisa de `packages: write`.

Após o primeiro push, abra **Packages** no GitHub e escolha a visibilidade:

- público: a VPS baixa sem autenticação;
- privado: a VPS precisa de token com `read:packages`.

## Login para pacote privado

Crie um Personal Access Token Classic ou Fine-grained compatível com o pacote e
execute na VPS:

```bash
export GHCR_USER='<usuario>'
export GHCR_TOKEN='<token-read-packages>'
echo "$GHCR_TOKEN" | docker login ghcr.io -u "$GHCR_USER" --password-stdin
```

Não grave o token no repositório nem no histórico do shell. Para Dockge, monte o
diretório de autenticação do Docker conforme o guia específico.

## Implantação

No `.env`:

```dotenv
DEPLOY_MODE=ghcr
GHCR_REGISTRY=ghcr.io
GHCR_NAMESPACE=usuario-ou-organizacao
AUDITOR_IMAGE_TAG=1.0.1
```

Então:

```bash
./scripts/install.sh
```

Para confirmar as imagens usadas:

```bash
docker compose -f compose.yaml -f compose.production.yaml images
```
