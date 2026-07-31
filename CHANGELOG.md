# Changelog

## [1.1.0](https://github.com/wkarts/auditorfiscal/compare/auditor-fiscal-v1.0.0...auditor-fiscal-v1.1.0) (2026-07-31)


### Features

* entrega completa do Auditor Fiscal v1.0.0 ([4de676e](https://github.com/wkarts/auditorfiscal/commit/4de676e600de68d572cc0c88bfd31dc3e76bc871))
* entrega completa do Auditor Fiscal v1.0.0 ([b66cf38](https://github.com/wkarts/auditorfiscal/commit/b66cf388c51e8d44b26532d8d15af1424c23ad16))
* entrega completa do Auditor Fiscal v1.0.0 ([fff40ae](https://github.com/wkarts/auditorfiscal/commit/fff40ae4755c3feb73c043d6382f366f080d3207))


### Bug Fixes

* **ci:** corrige Gitleaks e publica imagens no GHCR ([9433d09](https://github.com/wkarts/auditorfiscal/commit/9433d0970474a5dfc3290398057ceba475748ae0))
* **ci:** corrige validações remanescentes do frontend e deploy ([db0a4be](https://github.com/wkarts/auditorfiscal/commit/db0a4be30587ab5cc9c04440ca19308a056e6bac))
* **ci:** otimiza validação Docker em PR e habilita sockets ([39bd2ad](https://github.com/wkarts/auditorfiscal/commit/39bd2ad2944596f6b2565bc479d65f0b90fc5651))
* **docker:** corrige build Composer da API e atualiza Docker Actions ([63a7c95](https://github.com/wkarts/auditorfiscal/commit/63a7c95d9b91555cb74b9955d7bbe832d8eb4376))

## [Unreleased]

### Fixed
- Habilita explicitamente a extensão PHP `sockets`, exigida pelo cliente AMQP usado pelo driver RabbitMQ.
- Substitui o build integral das três imagens em Pull Requests por validação estática com BuildKit `call: check`.
- Mantém build multi-arquitetura e publicação no GHCR somente em `main`, tags SemVer e execução manual.
- Desativa o upload de registros `.dockerbuild` para evitar consumo desnecessário da cota de artifacts.
- Desativa o cache concorrente da imagem QEMU e reduz o cache de build publicado para `mode=min`.


### Fixed
- Corrige o build da imagem Laravel ao executar o Composer no mesmo ambiente PHP 8.4 da aplicação, com todas as extensões exigidas.
- Remove a dependência da imagem enxuta `composer:2` como ambiente de resolução das dependências.
- Atualiza as Docker Actions para versões baseadas em Node.js 24.
- Adiciona validação preventiva dos insumos de build e `.dockerignore` da API.

## [1.0.0] - 2026-07-30

- Entrega inicial integral do Auditor Fiscal IBS/CBS.
- Catálogo NCM × ClassTrib portado para seed PostgreSQL.
- Corrige a compatibilidade do driver RabbitMQ com Laravel 13.
- Corrige a configuração das extensões PHP no GitHub Actions.
- Atualiza as actions para a geração baseada em Node.js 24.
- Corrige as validações de Trivy e Gitleaks em pull requests.

### Correções de validação adicionais

- Corrige o fechamento dos templates em `DashboardView.vue` e `AnalysisDetailView.vue`; o painel também foi tornado legível e tipado.
- Adiciona validação dedicada de todos os componentes Vue SFC antes do typecheck e do build.
- Valida `compose.production.yaml` como override de `compose.yaml`, conforme a finalidade do arquivo.
- Adiciona configuração precisa do Gitleaks para o placeholder histórico conhecido em `.env.example`.
- Remove o valor de senha administrativa do arquivo `.env.example` atual.
- Torna a leitura da versão da API tolerante à ausência do arquivo `VERSION`.
