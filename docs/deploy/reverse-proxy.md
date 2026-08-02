# Proxy reverso do CloudPanel

O Nginx gerenciado pelo CloudPanel é o único proxy público. Ele termina TLS e
encaminha as requisições para o frontend/Nginx da stack:

```text
https://auditor.wwsoftwares.com.br → http://127.0.0.1:8080
```

No `.env`, mantenha a publicação restrita ao loopback:

```dotenv
WEB_BIND_HOST=127.0.0.1
WEB_PUBLISHED_PORT=8080
```

O frontend encaminha `/api/` para `auditor-fiscal-api:8080` pela rede Docker.
Banco, Redis, RabbitMQ, MinIO, engine e API não possuem portas publicadas.
