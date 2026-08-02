# Changelog

## [1.1.5] - 2026-08-02

### Alterado

- Branch sincronizada com a release publicada `v1.1.4` antes da reserva automática da próxima versão.

### Corrigido

- Reserva de versão passa a atualizar as tags do remoto com retry antes de calcular o próximo patch.
- Inicialização idempotente de banco, administrador e filas RabbitMQ antes da API e dos workers.
- Paridade funcional entre os serviços dos contratos Compose raiz e Dockge.

## [1.1.4] - 2026-08-01

### Alterado

- Versão patch reservada automaticamente pelo workflow para evitar reutilização de tag.

## [1.1.3] - 2026-08-01

### Alterado

- Contratos Compose e de ambiente sincronizados com a release corretiva.
- Contratos raiz e Dockge passam a oferecer a mesma lista de serviços, mantendo apenas build local versus imagem publicada como diferença intencional.

### Corrigido

- Geração atômica e persistente de `APP_KEY` quando ausente no primeiro deploy.
- Autenticação do Redis habilitada somente quando `REDIS_PASSWORD` é informado, com healthcheck equivalente.
- Inicialização idempotente de migrations, administrador e filas RabbitMQ antes da API e dos workers.
- Atualização segura da senha administrativa pelo seed quando `ADMIN_PASSWORD` mudar, sem rehash desnecessário.

## [1.1.2] - 2026-08-01

### Alterado

- Reserva automática de versão sem exigir `VERSIONING_TOKEN`, com revalidação explícita do novo SHA.
- Validação de isolamento atualizada para bind mounts resolvidos em diretórios distintos por stack Dockge.

### Corrigido

- Preparação explícita das permissões de `api_storage` antes de migrations em PRs, releases e rotinas operacionais.
- Nome lógico do motor fiscal simplificado para `auditor-fiscal-engine` em todos os contratos internos.

## [1.1.1] - 2026-08-01

### Alterado

- Reserva automática da próxima versão patch disponível em Pull Requests para evitar reutilização de tags.
- Sincronização de `VERSION`, metadados dos componentes, tags de deploy e changelog pelo mesmo contrato idempotente.
- Persistência em bind mounts locais por stack e `env_file` compatível com versões do Compose embarcadas no Dockge.

## [1.1.0] - 2026-08-01

### Adicionado

- Isolamento de stacks Docker por projeto, prefixo de recursos e portas configuráveis.
- Imagem-base versionada da API, workflow multi-arquitetura, SBOM, provenance, attestation e scan.
- Validação automatizada de duas instâncias Dockge simultâneas e documentação de rollback.

### Alterado

- Imagens auxiliares do Dockge passam a aceitar tags parametrizadas e versionadas.
- Builds da API reutilizam PHP, Composer e extensões nativas sem incorporar código da aplicação na base.
- Todos os serviços Compose e seus endereços DNS internos passam a usar o prefixo `auditor-fiscal-`.

## [1.0.1] - 2026-07-31

### Corrigido

- Validação completa das imagens Docker em Pull Requests, com build AMD64, smoke tests e Trivy.
- Correção da imagem PHP Alpine com `linux-headers` para compilar `ext-sockets`.
- Validação e distribuição Docker restritas a `linux/amd64`.
- Ajuste dos gates ShellCheck e Ruff ao estado real do código.
- Atualização dos pacotes de sistema das imagens API, Web e Fiscal Engine antes da varredura Trivy.
- Correção das 12 vulnerabilidades HIGH corrigíveis herdadas da imagem Nginx/Alpine.
- Correção da inicialização do MinIO Client com comando shell atômico.
- PostgreSQL fixado na série 17 para preservar o layout de volume suportado.
- Workflows passam a aguardar a saúde da infraestrutura antes de migrations e seeds.
- Scripts de versão são executados explicitamente com Bash, sem depender do bit executável.
- Pipeline automático de release baseado no arquivo `VERSION`.
- Publicação das imagens API, Web e Fiscal Engine no GitHub Container Registry.
- Verificação das imagens já publicadas antes da criação da GitHub Release.
- Remoção de nomes, identificadores e referências de clientes do código, testes e documentação.
- Remoção de credenciais padrão da tela de login e dos seeders.
- Guias completos de Docker Compose, Dockge, CloudPanel, Portainer, Caddy, Nginx e Traefik.

### Segurança

- Varredura automática de dados pessoais e identificadores fiscais no repositório.
- Histórico de distribuição preparado para ser recriado sem dados de terceiros.

## [1.0.0] - 2026-07-30

### Adicionado

- Estrutura inicial da plataforma de auditoria fiscal IBS/CBS.
- Backend Laravel, frontend Vue e motor fiscal Python.
- Catálogo NCM × CST × cClassTrib portado para o banco de dados.
- Importação XML/ZIP, filas, relatórios PDF/Excel e infraestrutura Docker.
