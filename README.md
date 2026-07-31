# Auditor Fiscal IBS/CBS

Plataforma web para importação, normalização, análise e auditoria de NF-e XML, com motor determinístico de IBS/CBS, validação NCM × CST × cClassTrib, conciliação documental, grids analíticos/sintéticos e relatórios PDF/Excel.

## O catálogo NCM × ClassTrib

A planilha `classificacao_trib_final.xlsx` **não é consultada em produção**. Seu conteúdo integral foi portado para arquivos de seed comprimidos e é carregado nas tabelas PostgreSQL por `FiscalCatalogSeeder`. A aplicação possui módulo administrativo para editar registros, importar novas planilhas, validar, comparar, aprovar e publicar versões. Cada auditoria congela o `catalog_version_id` utilizado.

## Inicialização rápida

```bash
cp .env.example .env
# ajuste senhas, domínio e e-mail
make install
```

Acesse `https://$AUDITOR_DOMAIN`. O primeiro usuário administrador é criado pelo seeder conforme `ADMIN_EMAIL` e `ADMIN_PASSWORD`. O instalador bloqueia valores vazios ou placeholders em credenciais obrigatórias.

Por padrão, `DEPLOY_MODE=ghcr` baixa as imagens oficiais publicadas em `ghcr.io/wkarts`. Para desenvolvimento local com compilação dos Dockerfiles, altere para `DEPLOY_MODE=source`.

## Componentes

- Laravel API: autenticação, RBAC, empresas, catálogos, lotes, achados e relatórios.
- Vue 3/TypeScript: painel, grids, detalhe XML e administração fiscal.
- Python/FastAPI: parsing seguro, cálculo Decimal, regras, conciliação e geração de artefatos.
- PostgreSQL, Redis, RabbitMQ, MinIO, Caddy e monitoramento opcional.

Leia `docs/implantacao-vps.md`, `docs/arquitetura.md`, `docs/modelo-fiscal.md` e `docs/operacao.md`.

## Validar o lote de referência

O ZIP original não é armazenado no repositório. Para validar localmente os mesmos totais e achados:

```bash
python3 scripts/validate-reference-dataset.py /caminho/NotasFiscais.zip
```

O resultado esperado e os testes já executados estão documentados em `docs/validacao-entrega.md`.
