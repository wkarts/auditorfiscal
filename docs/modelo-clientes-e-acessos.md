# Modelo de empresas da plataforma, clientes auditados e acessos

## Hierarquia de negócio

O Auditor Fiscal separa explicitamente quem assina e acessa a plataforma de quem é auditado.

```text
Plataforma Auditor Fiscal
└── Empresa assinante (conta): Codesplan
    ├── Usuários internos da Codesplan
    └── Clientes auditados
        ├── Dubahia
        ├── Unifrio
        ├── Proteja-se
        └── Segmax
```

- **Empresa da plataforma / conta assinante:** cliente direto da plataforma, como a Codesplan.
- **Usuário:** pertence a exatamente uma empresa assinante e entra com e-mail e senha.
- **Cliente auditado:** cliente da empresa assinante. É selecionado ao criar a auditoria e identifica XMLs, notas, DANFEs, achados e relatórios.

Os nomes internos `tenant` e `company` são mantidos no banco para compatibilidade. No negócio, `tenant` representa a conta assinante e `company` representa o cliente auditado.

## Administrador master da plataforma

O usuário criado por `ADMIN_EMAIL` não pertence a uma conta assinante e é o administrador master. Ele pode:

- cadastrar, editar e inativar qualquer empresa assinante, inclusive corrigir seu CNPJ;
- cadastrar e editar usuários de qualquer empresa assinante;
- cadastrar, editar e inativar qualquer cliente auditado;
- acessar todas as auditorias, notas, relatórios e logs.

Administradores de uma empresa assinante possuem acesso total somente dentro da própria conta.

## Acesso dos usuários aos clientes auditados

O cadastro de usuário possui dois escopos:

- **Todos os clientes da empresa:** inclui clientes atuais e futuros da conta do usuário.
- **Clientes selecionados:** libera apenas os clientes marcados.

Mesmo com `all_clients=true`, um usuário da Codesplan não acessa clientes de outra empresa assinante. O filtro de conta é aplicado antes do escopo individual.

## Auditoria e identificação nos relatórios

A criação da auditoria solicita um cliente auditado. O identificador interno enviado ao motor fiscal é o registro de `companies`, e os dados desse cliente (`legal_name`, `trade_name`, `tax_id` e inscrição estadual) são usados no relatório.

Assim, uma auditoria criada pela Codesplan para Dubahia exibe **Dubahia** nos relatórios — não Codesplan.

## DANFE e detalhamento da NF-e

1. Quando um DANFE PDF correspondente é importado, o arquivo original é preservado e priorizado.
2. Quando nenhum PDF correspondente é encontrado, o motor gera um DANFE de consulta a partir do XML.
3. O XML original continua sendo a fonte fiscal e permanece disponível para download.
4. A tela da NF-e apresenta identificação, emitente, destinatário, protocolo, totais, transporte, cobrança, pagamentos, informações adicionais, itens, tributos e críticas.

## Contratos da API

Contratos de negócio:

- `/api/v1/accounts`: empresas assinantes da plataforma;
- `/api/v1/clients`: clientes auditados;
- `/api/v1/users`: usuários vinculados a uma conta e seus escopos;
- `/api/v1/analyses`: auditorias vinculadas ao cliente auditado por `company_id`.

Os endpoints legados `/tenants` e `/companies` permanecem disponíveis durante a transição.

Payload de usuário:

```json
{
  "account_id": "uuid-da-codesplan",
  "all_clients": false,
  "client_ids": ["uuid-da-dubahia", "uuid-da-unifrio"]
}
```

## Migração e compatibilidade

A migration `2026_08_03_050000_assign_users_to_subscriber_accounts.php` adiciona o vínculo singular da conta em `users.tenant_id`. O administrador master configurado em `ADMIN_EMAIL` permanece sem conta. Para usuários legados, a conta é inferida primeiro pela empresa padrão e depois pelo vínculo histórico em `tenant_user`.

As tabelas de associação antigas são mantidas durante a transição para permitir rollback e compatibilidade com consumidores anteriores.
