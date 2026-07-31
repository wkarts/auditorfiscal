SHELL := /bin/bash
.PHONY: install up down logs test lint seed backup restore release
install:
	cp -n .env.example .env || true
	docker compose build
	docker compose up -d postgres redis rabbitmq minio minio-init
	docker compose run --rm api php artisan key:generate --force
	docker compose run --rm api php artisan migrate --force
	docker compose run --rm api php artisan db:seed --force
	docker compose up -d
up:
	docker compose up -d
down:
	docker compose down
logs:
	docker compose logs -f --tail=200
test:
	docker compose run --rm api php artisan test
	docker compose run --rm fiscal-engine pytest
	docker compose run --rm web-build sh -lc "npm install && npm run test:unit && npm run typecheck"
lint:
	docker compose run --rm api ./vendor/bin/pint --test
	docker compose run --rm fiscal-engine ruff check .
seed:
	docker compose run --rm api php artisan db:seed --class=FiscalCatalogSeeder --force
backup:
	./scripts/backup.sh
restore:
	./scripts/restore.sh $(FILE)
release:
	./scripts/release.sh $(VERSION)
