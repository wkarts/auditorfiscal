# Auditor Fiscal IBS/CBS

Plataforma web para importação e auditoria de NF-e XML, com validação de IBS,
CBS, formação da base de cálculo, NCM, CST, cClassTrib, conciliação documental e
geração de relatórios PDF/Excel.

Também oferece segregação por tenant e empresa, permissões explícitas de usuários,
consulta assistida de CNPJ, visualização do XML original e do DANFE, análise crítica
por camadas e exportação de logs globais ou vinculados a cada auditoria.

## Arquitetura

- **Laravel 13 / PHP 8.4**: API, autenticação, RBAC, empresas, catálogos, lotes e relatórios.
- **Vue 3 / TypeScript**: dashboard, grids sintéticos e analíticos, detalhe do XML e administração.
- **Python 3.13 / FastAPI**: parser XML, cálculos `Decimal`, regras fiscais, conciliações e relatórios.
- **PostgreSQL, Redis, RabbitMQ e MinIO**: persistência, cache, filas e arquivos.
- **Docker Compose**: desenvolvimento, produção, Dockge, Portainer e integração com CloudPanel.

## Catálogo NCM × ClassTrib

A planilha original de parametrização não é consultada durante a execução. Seu
conteúdo foi portado para seeds comprimidos e carregado no PostgreSQL. A área
administrativa permite importar novas planilhas, revisar inconsistências, editar
registros, criar revisões, aprovar e publicar versões. Cada auditoria registra o
snapshot do catálogo utilizado.

## Dados do repositório

O repositório contém apenas dados sintéticos de demonstração. XMLs, relatórios e
identificadores de clientes não devem ser versionados. Consulte `PRIVACY.md` e
execute `python3 scripts/scan-repository-data.py` antes de cada commit.

## Instalação rápida compilando localmente

```bash
cp .env.example .env
# preencha todas as credenciais obrigatórias
sed -i 's/^DEPLOY_MODE=.*/DEPLOY_MODE=source/' .env
./scripts/install.sh
```

## Instalação usando imagens do GHCR

Depois que a release for publicada:

```bash
cp .env.example .env
# configure GHCR_NAMESPACE, AUDITOR_IMAGE_TAG e credenciais
sed -i 's/^DEPLOY_MODE=.*/DEPLOY_MODE=ghcr/' .env
./scripts/install.sh
```

## CI, imagens e releases

Em cada Pull Request, as três imagens são realmente construídas para
`linux/amd64`, iniciadas em uma stack de integração, submetidas a smoke tests e
escaneadas pelo Trivy. Também são construídas para `linux/arm64` sem publicação.

Após o merge em `main`, a versão registrada em `VERSION` é construída para
`amd64/arm64`, publicada no GHCR, verificada novamente a partir das imagens
publicadas e só então transformada em GitHub Release com pacotes de implantação.

## Documentação

- `docs/arquitetura.md`
- `docs/modelo-fiscal.md`
- `docs/banco-e-seed.md`
- `docs/ci-cd.md`
- `docs/deploy/README.md`
- `docs/deploy/ubuntu-docker.md`
- `docs/operacao.md`

## Demonstração sintética

```bash
python3 scripts/validate-demo-dataset.py
```

O arquivo `examples/NotasFiscais-demo.zip` é sintético e não possui validade fiscal.

## Senhas com caracteres reservados

`DB_PASSWORD` aceita caracteres como `@`, `:`, `/` e `?` sem codificação manual.
O motor monta a conexão a partir de `DB_HOST`, `DB_PORT`, `DB_DATABASE`,
`DB_USERNAME` e `DB_PASSWORD`; não monte manualmente uma `DATABASE_URL` no Compose.
