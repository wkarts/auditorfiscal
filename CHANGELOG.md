# Changelog

## [Unreleased]

### Fixed
- Corrige o Gitleaks usando allowlist global restrita e fingerprints históricos exatos.
- Evita que documentação de validação recrie o falso positivo de senha administrativa.

### Added
- Publicação das imagens API, Web e Fiscal Engine no GitHub Container Registry.
- Builds multi-arquitetura, cache, SBOM, proveniência e atestação OCI.
- Deploy de produção por imagens GHCR com tag configurável e fallback para build local.


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
