# Changelog

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
