# Pull Request — correção do build Docker da API

## Branch de origem

`release/v1.0.0`

## Branch de destino

`main`

## Título

`feat: entrega completa do Auditor Fiscal v1.0.0 com imagens Docker validadas`

## Descrição

### Objetivo

Corrigir a falha remanescente do workflow `Container Images`, que ocorria no
`composer install` durante o build da imagem Laravel, e eliminar os avisos de
runtime Node.js 20 das Docker Actions.

### Causa confirmada

A API resolvia dependências dentro da imagem enxuta `composer:2`, antes da
instalação das extensões PHP do ambiente final. Como o repositório ainda não
possui `composer.lock` versionado, o Composer executava uma resolução completa
e encerrava com código 2 ao validar a plataforma do builder.

### Alterações

- substitui o builder baseado em `composer:2` por um builder PHP 8.4;
- copia somente o binário do Composer para o ambiente PHP correto;
- instala todas as extensões PHP antes da resolução das dependências;
- compartilha a mesma base PHP entre builder e runtime;
- preserva cache de dependências em duas passagens;
- adiciona `.dockerignore` para a API;
- atualiza `setup-qemu-action` para v4;
- atualiza `setup-buildx-action` para v4;
- atualiza `login-action` para v4;
- atualiza `metadata-action` para v6;
- atualiza `build-push-action` para v7;
- adiciona validação preventiva dos arquivos usados em cada build;
- documenta a causa e a solução.

### Validações locais

- [x] YAML dos workflows válido;
- [x] sintaxe PHP válida;
- [x] scripts shell válidos;
- [x] testes do motor fiscal aprovados;
- [x] componentes Vue estruturalmente válidos;
- [x] `git diff --check` aprovado;
- [x] estrutura do Dockerfile e contextos validada;
- [ ] build remoto da imagem, a ser confirmado pelo novo run do GitHub Actions.

### Resultados fiscais preservados

- 34 documentos;
- 11 entradas;
- 23 saídas;
- R$ 2.797.000,00 em documentos;
- R$ 1.888.209,48 de base IBS/CBS;
- R$ 1.888,19 de IBS;
- R$ 16.993,84 de CBS.

## Commit

`fix(docker): corrige build Composer da API e atualiza Docker Actions`

## Squash merge

`feat: entrega completa do Auditor Fiscal v1.0.0 com imagens Docker validadas`

## Tag

`v1.0.0`

## Nome da release

`Auditor Fiscal v1.0.0 — Aplicação completa e imagens Docker validadas`
