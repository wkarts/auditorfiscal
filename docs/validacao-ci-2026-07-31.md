# Correções da segunda rodada de validação — 31/07/2026

## Logs analisados

- `logs_82982640961.zip`: CI principal.
- `logs_82982641257.zip`: segurança.

## Falhas identificadas

### Frontend Vue

O build interrompia em `DashboardView.vue` com `Element is missing end tag`. Após a correção desse componente, a inspeção estrutural completa também encontrou o mesmo fechamento ausente em `AnalysisDetailView.vue`.

Correções:

- fechamento dos dois templates externos;
- reorganização e tipagem do `DashboardView.vue`;
- criação de `npm run validate:sfc`, que analisa todos os arquivos `.vue` antes do typecheck e do build.

### Docker Compose

`compose.production.yaml` é um arquivo de override e estava sendo validado isoladamente. Por isso, os serviços declarados apenas com limites de recursos não possuíam `image` ou `build` quando lidos sozinhos.

Correção:

```bash
docker compose -f compose.yaml -f compose.production.yaml config --quiet
```

### Gitleaks

O scanner encontrou no histórico o placeholder antigo:

```text
ADMIN_PASSWORD=troque-por-uma-senha-de-16-caracteres
```

O valor não era uma credencial real, mas disparava a regra `generic-api-key`.

Correções:

- o valor atual de `.env.example` passou a ser `replace-me`;
- o instalador bloqueia placeholders e credenciais obrigatórias vazias;
- `.gitleaks.toml` permite somente o padrão histórico, somente em `.env.example` e somente para a regra `generic-api-key`;
- o workflow informa explicitamente o arquivo de configuração ao Gitleaks.

### Aviso do teste Laravel

O endpoint informativo lia `VERSION` sem verificar se o arquivo estava disponível. A rota passou a verificar `is_readable()` e utilizar `dev` como fallback seguro. Também foi incluído teste específico para o endpoint informativo.

## Validações locais executadas

- 54 arquivos PHP aprovados no lint.
- Scripts shell aprovados em `bash -n`.
- Workflow YAML, Compose YAML e configuração TOML aprovados estruturalmente.
- Todos os componentes Vue apresentam tags estruturalmente balanceadas.
- Script de validação Vue aprovado em `node --check`.
- 4 testes do motor fiscal Python aprovados.
- Golden dataset aprovado com 34 documentos, 11 entradas, 23 saídas e os totais esperados.
- `git diff --check` aprovado.

A validação final de `npm install`, `npm run validate:sfc`, `npm run typecheck`, `npm run test:unit`, `npm run build`, `composer install` e `php artisan test` será realizada pelo GitHub Actions, onde as dependências externas estão disponíveis.
