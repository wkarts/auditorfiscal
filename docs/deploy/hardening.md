# Hardening da VPS

- exponha publicamente apenas 80/443;
- mantenha PostgreSQL, Redis, RabbitMQ e MinIO sem portas públicas;
- use `APP_HTTP_BIND=127.0.0.1` com proxy externo;
- aplique atualizações de segurança do sistema e do Docker;
- configure SSH por chave, desative login direto de root e senha quando possível;
- use regras na cadeia `DOCKER-USER`, pois portas publicadas pelo Docker podem
  contornar regras simples do UFW;
- faça backup externo criptografado;
- use tags de imagem imutáveis;
- monitore espaço, memória, filas e saúde dos containers;
- rotacione credenciais e tokens após incidentes ou mudança de equipe.
