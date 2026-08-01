# CI/CD, imagens e releases

## Pull Request

A PR só pode ser mesclada quando forem aprovados:

1. testes Laravel, Vue e Python;
2. validação dos arquivos Compose;
3. Gitleaks e Trivy de filesystem;
4. varredura de privacidade;
5. contrato de versão;
6. build real das imagens API, Web e Fiscal Engine em `linux/amd64`;
7. migração, seed, inicialização da stack e smoke test autenticado;
8. Trivy sobre as três imagens construídas.

As imagens executam atualização dos pacotes do sistema operacional durante o build. Vulnerabilidades com correção disponível permanecem bloqueantes; não são ignoradas pelo pipeline.

A PR não publica imagens. Ela executa o mesmo Dockerfile que será usado na
release, eliminando erros de Composer, NPM, PIP, extensões PHP e
inicialização antes do merge.

## Release automática

Toda PR destinada a `main` precisa alterar `VERSION` para uma versão SemVer ainda
não publicada. Use:

```bash
./scripts/release.sh 1.0.2
```

Se a versão da PR já tiver uma tag publicada, o job **Repository contracts**
calcula o próximo patch após a maior tag SemVer, sincroniza todos os metadados e
faz um commit na própria branch da PR com a identidade oficial do repositório.
O novo evento `synchronize` reexecuta os gates usando o commit versionado. O
processo é idempotente: uma versão sem tag não é alterada. Para executar o mesmo
fluxo localmente, use `./scripts/release.sh --next`. Pull Requests originadas de
forks não recebem permissão de escrita; nesses casos, o colaborador deve executar
o comando local e enviar o commit para sua branch.

Por padrão, o workflow usa o `GITHUB_TOKEN`, envia o commit e dispara
explicitamente uma nova execução por `workflow_dispatch`, pois pushes desse token
não geram eventos recursivos. Portanto, nenhum secret adicional é obrigatório.
Opcionalmente, cadastre `VERSIONING_TOKEN` com um token fine-grained de @wkarts,
limitado a este repositório e a **Contents: Read and write**; nesse caso, o push
gera o evento `synchronize` naturalmente. Não conceda permissões administrativas,
packages ou acesso a outros repositórios.

Após o merge em `main`, `.github/workflows/release.yml`:

1. valida versão, changelog e privacidade;
2. cria imagens `linux/amd64`;
3. publica as imagens no GHCR;
4. gera SBOM, proveniência e atestações;
5. baixa as imagens publicadas em um host limpo;
6. executa migrações, seeds e smoke tests;
7. cria a tag e a GitHub Release;
8. anexa ZIP, TAR.GZ, bundle de deploy, lista de imagens e SHA-256.

Se uma etapa falhar, a GitHub Release não é criada.

## Contratos de infraestrutura

- A série PostgreSQL 17 é usada nesta release para manter o volume em
  `/var/lib/postgresql/data` e evitar a mudança de layout introduzida pela imagem 18.
- O job de integração usa `docker compose up --wait` e só executa migrations,
  seeds e inicialização do bucket após todos os serviços essenciais estarem saudáveis.
- Scripts versionados são chamados com `bash`, tornando o pipeline independente do
  bit executável preservado pelo sistema operacional usado para preparar o commit.

## Imagem-base versionada da API

A compilação das extensões PHP/PECL é estável e cara; por isso somente a API usa
`ghcr.io/wkarts/auditorfiscal-api-base:php8.4-v1`. Node e Python continuam com
suas imagens oficiais e caches de dependências: suas dependências nativas são
menores/diferentes e uma base comum aumentaria tamanho e acoplamento. A base não
contém código, `composer.json` nem dependências da aplicação.

O workflow `API Base Image` publica `linux/amd64` e `linux/arm64`, usa Buildx e
cache GHA, gera SBOM/provenance, atesta e bloqueia vulnerabilidades altas/críticas
corrigíveis. Ele executa somente quando `docker/base/api.Dockerfile`,
`docker/base/versions.env` ou o próprio workflow muda, ou por **Actions > API
Base Image > Run workflow**. A autenticação usa exclusivamente `GITHUB_TOKEN`
com permissões mínimas declaradas.

Para atualizar, altere runtime/toolchain no Dockerfile e incremente
`API_BASE_VERSION` em `docker/base/versions.env`; tags publicadas são imutáveis e
não há `latest`. Após publicar e validar, atualize `API_BASE_IMAGE` e o argumento
do workflow de release. Correções de segurança seguem o mesmo fluxo, mesmo sem
mudança de versão do PHP. Para forçar uma reconstrução, incremente
`API_BASE_VERSION`, faça commit e dispare o workflow manualmente. O workflow
recusa sobrescrever tags existentes; a tag `sha-<commit completo>` preserva
rastreabilidade.

Rollback não reconstrói a base: restaure `API_BASE_IMAGE`/`BASE_IMAGE` para uma
tag versionada anterior, reconstrua as imagens finais e publique uma nova versão
da aplicação. O cache `type=gha` acelera PRs/releases, mas nunca substitui a
imagem-base referenciada pelo build.
