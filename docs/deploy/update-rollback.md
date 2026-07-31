# Atualização e rollback

## Atualização

```bash
./scripts/update.sh v1.0.2
```

O script cria backup, troca a versão, baixa ou compila imagens, executa migrations
e valida a saúde da stack.

## Rollback

```bash
./scripts/rollback.sh v1.0.1
```

O rollback de aplicação não desfaz migrations destrutivas. As migrations devem
seguir estratégia expand/contract. Para alterações incompatíveis, restaure o
backup correspondente.
