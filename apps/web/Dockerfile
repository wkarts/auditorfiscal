# syntax=docker/dockerfile:1.8
# check=error=true

FROM node:24-alpine AS build
WORKDIR /app
ENV CI=true
COPY package*.json ./
RUN --mount=type=cache,target=/root/.npm \
    if [ -f package-lock.json ]; then \
      npm ci --no-audit --no-fund; \
    else \
      npm install --no-audit --no-fund; \
    fi
COPY . .
RUN npm run validate:sfc && npm run typecheck && npm run test:unit && npm run build

FROM nginx:1.29-alpine AS runtime
RUN apk upgrade --no-cache
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
EXPOSE 80
HEALTHCHECK --interval=15s --timeout=5s --retries=5 \
  CMD wget -qO- http://127.0.0.1/health >/dev/null || exit 1
