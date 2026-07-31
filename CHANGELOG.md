# Changelog

## [Unreleased]

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
