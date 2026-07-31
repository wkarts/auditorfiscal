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
8. Trivy sobre as três imagens construídas;
9. build das três imagens em `linux/arm64`.

A PR não publica imagens. Ela executa o mesmo Dockerfile que será usado na
release, eliminando erros de Composer, NPM, PIP, extensões PHP, arquitetura e
inicialização antes do merge.

## Release automática

Toda PR destinada a `main` precisa alterar `VERSION` para uma versão SemVer ainda
não publicada. Use:

```bash
./scripts/release.sh 1.0.2
```

Após o merge em `main`, `.github/workflows/release.yml`:

1. valida versão, changelog e privacidade;
2. cria imagens `linux/amd64` e `linux/arm64`;
3. publica as imagens no GHCR;
4. gera SBOM, proveniência e atestações;
5. baixa as imagens publicadas em um host limpo;
6. executa migrações, seeds e smoke tests;
7. cria a tag e a GitHub Release;
8. anexa ZIP, TAR.GZ, bundle de deploy, lista de imagens e SHA-256.

Se uma etapa falhar, a GitHub Release não é criada.
