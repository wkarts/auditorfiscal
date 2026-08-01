# Validação das imagens publicadas no GHCR

A validação pós-publicação usa esta ordem de arquivos Compose:

```text
compose.yaml
compose.production.yaml
deploy/ci/compose.release.yaml
```

`compose.production.yaml` é a única origem dos nomes das imagens GHCR.
`compose.release.yaml` contém somente portas e perfis necessários ao smoke test.
O workflow também verifica os nomes resolvidos antes de executar `docker compose pull`.

O arquivo `deploy/ci/compose.ci.yaml` continua exclusivo da Pull Request, onde as
imagens locais `auditor-fiscal/*:pr` são construídas e testadas antes do merge.

A `.gitleaksignore` contém apenas dois fingerprints históricos de placeholders
sintéticos já conhecidos. Novos achados, inclusive nos mesmos caminhos, continuam
bloqueando o pipeline.
