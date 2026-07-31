# Correção da validação da imagem Docker da API

## Falha observada

O workflow `Container Images` falhava no estágio de dependências da API:

```text
composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts
exit code: 2
```

Os demais checks da API Laravel, frontend, motor fiscal, Compose, Trivy e
Gitleaks já estavam aprovados.

## Causa

O Dockerfile usava `composer:2` como ambiente de resolução das dependências.
Essa imagem é propositalmente enxuta e não representa o ambiente PHP da
aplicação. Como o projeto ainda não possui `composer.lock` versionado, o
`composer install` executa uma resolução completa e valida requisitos de
plataforma. A resolução ocorreu antes da instalação das extensões PHP usadas
pela aplicação e pelo conjunto de dependências.

## Correção

- Criação de uma base `php:8.4-cli-alpine` compartilhada pelo builder e runtime.
- Instalação prévia de `pdo_pgsql`, `bcmath`, `pcntl`, `intl`, `zip`, `curl`,
  `mbstring`, `xml`, `dom`, `xmlwriter`, `opcache`, `redis` e `amqp`.
- Cópia apenas do binário do Composer para o builder PHP.
- Instalação em duas passagens para preservar cache e executar o discovery do
  Laravel somente depois que o código-fonte estiver disponível.
- Inclusão de `.dockerignore` para impedir cópia de `vendor`, `.env`, caches e
  logs locais.
- Atualização das Docker Actions para os majors baseados em Node.js 24.

## Observação sobre o lockfile

O Dockerfile agora funciona com ou sem `composer.lock`. Para releases
imutáveis, o lockfile deverá ser gerado e versionado antes da estabilização da
tag. A ausência do lockfile não volta a colocar o Composer em um ambiente sem
as extensões exigidas.
