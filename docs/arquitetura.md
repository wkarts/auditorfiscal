# Arquitetura

## Visão
A solução adota monorepo com três aplicações: API Laravel, SPA Vue e motor fiscal Python. O CloudPanel termina TLS e encaminha o tráfego ao Nginx interno do frontend; operações pesadas seguem por RabbitMQ; PostgreSQL mantém dados e snapshots; MinIO preserva originais, XMLs normalizados e relatórios.

```text
Internet → Nginx/CloudPanel (TLS) → porta Web em 127.0.0.1
                                      ↓
                                 frontend/Nginx
                                      ├─ /api → Laravel API
                                      ├─ PostgreSQL (dados e catálogos)
                                      ├─ Redis (cache, sessões, locks)
                                      ├─ RabbitMQ (jobs)
                                      ├─ MinIO/S3 (originais e artefatos)
                                      └─ FastAPI/Python (motor fiscal)
```

## Decisões
- O XLSX inicial é convertido em seed comprimido; não participa da auditoria em produção.
- Catálogos publicados são imutáveis. Edição exige revisão.
- Cada lote grava `catalog_version_id`, hash da fonte, versão das regras e template.
- Cálculos monetários usam Decimal; IA não decide valor tributário.
- O motor é idempotente para o mesmo arquivo, catálogo e versão de regra.

## Escalabilidade
Workers Laravel podem ser escalados com `docker compose up -d --scale auditor-fiscal-worker=4`. O motor fiscal é stateless e também pode receber múltiplas réplicas. PostgreSQL deve usar storage SSD e backups externos; MinIO pode ser substituído por S3 compatível.
