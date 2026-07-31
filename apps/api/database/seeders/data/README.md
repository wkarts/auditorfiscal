# Dados de seed do catálogo fiscal

Esta pasta contém a portabilidade persistente das planilhas fornecidas para o projeto.
A aplicação **não consulta os arquivos XLSX em produção**.

Arquivos:

- `ncm_class_trib.jsonl.gz`: todas as 15.638 linhas da planilha `classificacao_trib_final.xlsx`;
- `cst_catalog.jsonl.gz`: 19 registros da tabela oficial de CST IBS/CBS;
- `cclass_catalog.jsonl.gz`: 142 registros da tabela oficial cClassTrib;
- `manifest.json`: hashes SHA-256, versão, contagens e indicadores de qualidade da importação.

O `FiscalCatalogSeeder` carrega os arquivos em lotes de 500 registros para o PostgreSQL.
Depois do seed inicial, as alterações são feitas no módulo administrativo por revisão versionada ou por nova importação XLSX. Versões publicadas são imutáveis e cada auditoria registra o `catalog_version_id` usado.
