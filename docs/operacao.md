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

## Processamento, erros e tentativas

O detalhe da auditoria possui a guia **Processamento e erros**. Ela registra, em
ordem cronológica, envio à fila, início de cada tentativa, chamada ao motor
fiscal, resposta HTTP, persistência, repetição automática e conclusão ou falha.
O erro do lote inclui código, incidente, tentativa, horário e detalhe técnico
sanitizado. Senhas, tokens, chaves, credenciais em URLs e cabeçalhos de
autorização são removidos antes da persistência.

O worker faz até três tentativas. Durante a repetição o lote usa `retrying`; ao
esgotar as tentativas, usa `failed`. O status `superseded` identifica uma fila
antiga substituída manualmente. Os eventos também continuam no log JSON do
Docker, permitindo correlação pelo `incident_id` e `request_id`.

Administradores podem abrir **Logs da aplicação** para pesquisar todos os
componentes, níveis, eventos, auditorias e incidentes. Esses registros são
mantidos por `APPLICATION_LOG_RETENTION_DAYS` (90 dias por padrão) e removidos
pelo `model:prune` diário. O log global é restrito à permissão `logs.view`; o
histórico de uma auditoria respeita o acesso à empresa.

## Reprocessamento

Use **Reprocessar auditoria** no detalhe do lote. A operação valida a presença
dos arquivos no armazenamento, cria outro lote ligado ao original, preserva os
resultados anteriores e impede reprocessamentos concorrentes. São elegíveis:

- lotes `failed` ou `completed`;
- lotes `queued` sem atualização além de `ANALYSIS_STALE_QUEUE_MINUTES`.

Lotes `processing` ou `retrying` não podem ser duplicados enquanto houver uma
tentativa ativa. O endpoint possui limite de cinco solicitações por minuto.

## Diagnóstico de infraestrutura

O fluxo de inicialização valida migrations, filas e escrita/leitura no MinIO. O
healthcheck `/health/ready` do motor fiscal valida PostgreSQL e armazenamento de
objetos. Se um serviço essencial estiver indisponível, o worker não inicia.

Para diagnóstico fora da interface:

```bash
docker compose ps
docker compose logs --since=30m auditor-fiscal-worker auditor-fiscal-engine auditor-fiscal-api
docker compose run --rm --no-deps auditor-fiscal-api php artisan storage:verify
docker compose exec auditor-fiscal-rabbitmq rabbitmqctl list_queues name messages consumers
```

Requisições `GET` do navegador e chamadas de healthcheck apenas confirmam que a
API respondeu; elas não explicam uma falha do job. Para isso, consulte a guia do
lote, o log da aplicação ou os serviços `worker` e `engine`.
