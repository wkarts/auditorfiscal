# Banco de dados e seed fiscal

O diretório `apps/api/database/seeders/data` contém:
- `ncm_class_trib.jsonl.gz`: 15.638 linhas portadas da classificação tributária final.
- `cst_catalog.jsonl.gz`: 19 CSTs.
- `cclass_catalog.jsonl.gz`: 142 cClassTrib.
- `manifest.json`: hashes, versão e estatísticas.

`FiscalCatalogSeeder` grava tudo em `fiscal_catalog_versions`, `ncm_class_trib_entries`, `cst_catalog_entries`, `cclass_catalog_entries` e `catalog_import_issues`. O XLSX original não é necessário depois da geração do seed.

```bash
docker compose run --rm auditor-fiscal-api php artisan db:seed --class=FiscalCatalogSeeder --force
```
