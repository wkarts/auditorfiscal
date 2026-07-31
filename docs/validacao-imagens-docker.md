# Validação e publicação das imagens Docker

## Pull Requests

Pull Requests não publicam imagens e não executam o build integral das três imagens.
O workflow executa `docker/build-push-action` com `call: check`, que valida o
Dockerfile e as opções de build sem executar as etapas `RUN`.

A validação funcional continua coberta pelos jobs da API, Web e motor fiscal. O
contrato de plataforma da API também verifica explicitamente a extensão PHP
`sockets`, exigida pelo cliente AMQP.

## Main, tags e execução manual

O build integral e multi-arquitetura ocorre somente em:

- push na branch `main`;
- tags SemVer, como `v1.0.0`;
- execução manual do workflow.

Nesses eventos, as imagens são publicadas no GHCR para `linux/amd64` e
`linux/arm64`, com SBOM, proveniência e atestação OCI.

## Cota de artefatos

O upload automático dos arquivos internos `.dockerbuild` está desativado por
`DOCKER_BUILD_RECORD_UPLOAD=false`. Esses registros não são necessários para
implantar ou consumir as imagens do GHCR e podem consumir a cota de artifacts do
GitHub Actions.
