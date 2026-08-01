# Manual operacional

1. Cadastre a empresa e vincule os usuários.
2. Confirme que existe uma versão de catálogo `published`.
3. Em **Nova auditoria**, informe empresa, competência e arquivos XML/ZIP.
4. Acompanhe o lote até `completed`.
5. Analise documentos, itens e críticas; registre responsável e resolução.
6. Gere PDF e Excel. Ambos usam o mesmo snapshot e o mesmo `ReportModel`.

## Atualização de NCM × ClassTrib
- Importe o novo XLSX pelo módulo administrativo.
- A versão fica em `importing`, depois `draft` ou `validated`.
- Corrija erros diretamente no formulário ou reimporte.
- Compare com a versão vigente.
- Publique somente quando não houver erros impeditivos.
- Auditorias antigas permanecem ligadas à versão anterior.

## Reprocessamento
Use reprocessamento quando desejar comparar uma auditoria com nova versão do catálogo. A operação cria outro lote; nunca sobrescreve o resultado original.

## Tratamento de falhas
Consulte `docker compose logs -f auditor-fiscal-worker auditor-fiscal-engine auditor-fiscal-api`. Lotes com falha preservam o erro e os arquivos originais. Após corrigir infraestrutura ou fonte, reexecute o lote.
