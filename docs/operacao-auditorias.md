# Operação do ciclo de vida das auditorias

## Estados operacionais

- `uploading`, `queued`, `processing` e `retrying`: processamento ativo.
- `cancelling`: cancelamento solicitado; o motor fiscal ainda está encerrando no próximo ponto seguro.
- `cancelled`: encerramento confirmado sem persistência parcial dos resultados.
- `completed`, `failed` e `superseded`: estados terminais existentes.

Auditorias ativas podem ser canceladas por usuários com `analyses.cancel`. Auditorias terminais podem ser movidas para a área de excluídas por administradores com `analyses.delete`. A exclusão é lógica e reversível: XMLs, DANFEs, relatórios, achados, arquivos de origem e logs não são apagados.

## Contrato da API

### Solicitar cancelamento

`POST /api/v1/analyses/{id}/cancel`

```json
{"reason":"Solicitação operacional opcional"}
```

Chamadas repetidas são idempotentes. Lotes na fila são cancelados imediatamente; lotes em execução passam por `cancelling` até o motor confirmar o ponto seguro. Estados terminais incompatíveis retornam `409`.

### Mover para excluídas

`DELETE /api/v1/analyses/{id}`

```json
{"reason":"Organização ou retenção operacional"}
```

Somente lotes terminais podem ser excluídos. A tentativa de excluir processamento ativo retorna `409` e orienta o cancelamento prévio.

### Consultar e restaurar excluídas

- `GET /api/v1/analyses?visibility=deleted`
- `POST /api/v1/analyses/{id}/restore`

Essas operações exigem `analyses.restore`. A listagem padrão continua retornando somente registros ativos.

## Concorrência e consistência

O worker verifica o pedido de cancelamento antes da chamada ao motor, após a resposta e dentro da transação que persiste documentos, itens, achados e relatórios. A última verificação usa bloqueio de linha, impedindo que um resultado seja confirmado depois de um cancelamento concorrente.

O motor consulta o mesmo registro nos seguintes checkpoints: fonte, XML, DANFE, regras cruzadas, geração e publicação de relatórios. Ao detectar cancelamento, responde `409/AUDIT_CANCELLED`; essa resposta é tratada como encerramento operacional, não como falha passível de retry.

## Deploy

1. Fazer backup do PostgreSQL e confirmar saúde da fila.
2. Aplicar a migration aditiva `2026_08_03_060000_add_analysis_lifecycle_controls.php`.
3. Publicar API e worker.
4. Publicar o motor fiscal.
5. Publicar o frontend.
6. Executar `php artisan db:seed --force` para criar e associar as novas permissões.
7. Validar `/api/v1/health/live`, que também informa a versão instalada.
8. Testar cancelamento de um lote sintético na fila e de outro em processamento.

## Rollback

Reverter primeiro frontend, motor, worker e API. A migration pode permanecer aplicada porque é retrocompatível. Removê-la com `migrate:rollback` apaga apenas metadados de cancelamento/exclusão; antes disso, restaure todos os registros excluídos e confirme que não há lotes em `cancelling`, pois esses metadados seriam perdidos.
