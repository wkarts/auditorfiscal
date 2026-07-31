# API REST
Base: `/api/v1`. Autenticação por Bearer Token (Sanctum).

- `POST /auth/login`, `GET /auth/me`, `POST /auth/logout`
- `GET|POST|PATCH /companies`
- `GET|POST|PATCH /users`
- `GET /catalogs`, `POST /catalogs/import`
- `GET /catalogs/{id}/entries`, `POST|PATCH /catalogs/{id}/entries`
- `POST /catalogs/{id}/revision`, `POST /catalogs/{id}/publish`
- `GET|POST /analyses`
- `GET /analyses/{id}/documents`, `GET /analyses/{id}/findings`
- `PATCH /analyses/{id}/findings/{finding}`
- `GET /reports/{id}/download`

Respostas de validação seguem HTTP 422. Processos assíncronos retornam HTTP 202.
