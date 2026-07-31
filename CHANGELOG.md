# Changelog

## [1.0.1] - 2026-07-31

### Corrigido

- Validação completa das imagens Docker em Pull Requests, com build AMD64, smoke tests e Trivy.
- Build adicional ARM64 antes do merge para evitar falhas exclusivas de arquitetura.
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
