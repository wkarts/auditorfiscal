SHELL := /bin/bash
.PHONY: install up down logs ps test lint privacy config seed backup restore update rollback release

install:
	./scripts/install.sh
up:
	bash -lc 'source scripts/lib/compose.sh && dc up -d --remove-orphans'
down:
	bash -lc 'source scripts/lib/compose.sh && dc down'
logs:
	bash -lc 'source scripts/lib/compose.sh && dc logs -f --tail=200'
ps:
	bash -lc 'source scripts/lib/compose.sh && dc ps'
test:
	bash -lc 'source scripts/lib/compose.sh && dc run --rm auditor-fiscal-api php artisan test'
	bash -lc 'source scripts/lib/compose.sh && dc run --rm auditor-fiscal-engine pytest'
lint:
	python3 scripts/scan-repository-data.py
	find apps/api -name "*.php" -not -path "*/vendor/*" -print0 | xargs -0 -n1 php -l
	python3 -m compileall -q services/fiscal-engine/app services/fiscal-engine/tests
privacy:
	python3 scripts/scan-repository-data.py
config:
	bash -lc 'source scripts/lib/compose.sh && dc config --quiet'
seed:
	bash -lc 'source scripts/lib/compose.sh && dc run --rm auditor-fiscal-api php artisan db:seed --class=FiscalCatalogSeeder --force'
backup:
	./scripts/backup.sh
restore:
	./scripts/restore.sh $(FILE)
update:
	./scripts/update.sh $(TAG)
rollback:
	./scripts/rollback.sh $(TAG)
release:
	./scripts/release.sh $(VERSION)
