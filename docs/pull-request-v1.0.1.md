# Pull Request — Auditor Fiscal v1.0.1

## Branches

- origem: `release/v1.0.1`
- destino: `main`

## Título

`feat(release): valida integralmente imagens e automatiza a release v1.0.1`

## Objetivo

Entregar a versão 1.0.1 com validação real das imagens Docker antes do merge,
publicação AMD64 no GitHub Container Registry após o merge, criação
automática da GitHub Release e remoção de dados pessoais ou referências de
clientes do código e do histórico distribuído.

## Alterações principais

### Validação de Pull Request

- constrói integralmente API, Web e Fiscal Engine para `linux/amd64`;
- inicia PostgreSQL, Redis, RabbitMQ e MinIO;
- executa migrations e seeds;
- inicia as três imagens geradas;
- valida healthchecks, autenticação e consulta do catálogo;
- verifica extensões PHP e importação do motor Python;
- executa Trivy em cada imagem;
- não considera apenas `docker build --check`, pois essa operação não executa
  as instruções `RUN` dos Dockerfiles.

### Release automática

Após o merge em `main`:

- valida versão, changelog, privacidade, scripts, Compose e testes;
- constrói imagens AMD64;
- publica API, Web e Fiscal Engine no GHCR;
- gera tags SemVer, major/minor, major, `latest` e SHA;
- gera SBOM, proveniência e atestações;
- baixa as imagens publicadas em uma stack limpa;
- executa migration, seed, healthchecks e smoke test autenticado;
- cria `v1.0.1` e a GitHub Release somente após a verificação das imagens;
- anexa pacotes source/deploy, lista de imagens e checksums SHA-256.

### Privacidade e histórico

- remove nomes, documentos, e-mails, chaves fiscais e referências de clientes;
- mantém apenas dados fiscais públicos e exemplos explicitamente sintéticos;
- adiciona varredura automática do repositório;
- fornece bundle Git com histórico limpo;
- documenta a substituição do histórico remoto anterior.

### Implantação

- Docker Compose por código-fonte;
- Docker Compose com imagens GHCR;
- Dockge;
- CloudPanel como reverse proxy;
- Portainer;
- Nginx e Traefik;
- backup, restauração, atualização, rollback e hardening da VPS.

## Validações locais concluídas

- [x] workflows YAML analisados;
- [x] arquivos Compose analisados;
- [x] scripts Bash aprovados por `bash -n`;
- [x] 54 arquivos PHP aprovados no lint;
- [x] compilação do código Python;
- [x] quatro testes do motor fiscal aprovados;
- [x] dataset sintético aprovado;
- [x] varredura de privacidade da árvore atual;
- [x] varredura de privacidade de todos os commits do bundle limpo;
- [x] `git diff --check` aprovado.

O ambiente de geração do pacote não possui daemon Docker nem acesso aos
registries externos. Por isso, o build integral e os testes de integração das
imagens são confirmados no workflow obrigatório da Pull Request.

## Migração do repositório anterior

Como o histórico anterior continha materiais usados apenas como referência de
construção, o PR antigo deve ser fechado. Publique primeiro `main` e a tag
`v1.0.0` do bundle limpo, depois publique `release/v1.0.1` e abra um novo PR.

## Commit sugerido

`feat(release): valida imagens e automatiza a release v1.0.1`

## Squash merge

`feat(release): entrega o Auditor Fiscal v1.0.1 com imagens validadas e release automática`

## Tag

`v1.0.1`

## Nome da release

`Auditor Fiscal v1.0.1 — Imagens validadas, GHCR e deploy completo`
