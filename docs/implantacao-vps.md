# Implantação em VPS

A documentação operacional foi dividida por cenário em `docs/deploy/`.

Sequência recomendada:

1. instale Docker Engine e Compose V2;
2. clone a tag da release;
3. copie `.env.example` para `.env`;
4. defina credenciais, domínio e modo `source` ou `ghcr`;
5. configure o Nginx do CloudPanel como proxy para a porta Web local;
6. execute `./scripts/install.sh`;
7. valide com `./scripts/healthcheck.sh`;
8. configure backup externo e monitoramento.

Consulte `docs/deploy/README.md` para os guias completos.
