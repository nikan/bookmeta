# PHP runs in Docker (no host PHP needed). Python runs on the host through uv, TypeScript through npm.
PHP_IMAGE ?= php:8.3-cli
COMPOSER_IMAGE ?= composer:2
PORT ?= 8080

DOCKER_RUN = docker run --rm -u "$$(id -u):$$(id -g)" -v "$(CURDIR)":/app -w /app/php
PHP = $(DOCKER_RUN) $(PHP_IMAGE) php
UV = cd python && uv run

.PHONY: help test lint live-test \
	install-php test-php lint-php live-test-php fixtures serve-php \
	install-py test-py lint-py live-test-py serve-py \
	install-ts test-ts lint-ts live-test-ts serve-ts build-ts pack-check-ts

help:
	@echo "make test          offline tests, all languages (test-php, test-py, test-ts)"
	@echo "make lint          lint, all languages (lint-php, lint-py, lint-ts)"
	@echo "make live-test     tests against the real biblionet.gr (network)"
	@echo "make fixtures      re-download contract/fixtures and rewrite expected JSON with PHP (network)"
	@echo "make serve-php     PHP endpoint at http://localhost:$(PORT)/index.php?isbn=9789600316483"
	@echo "make serve-py      Python endpoint at http://localhost:$(PORT)/index.php?isbn=9789600316483"
	@echo "make serve-ts      TypeScript endpoint at http://localhost:$(PORT)/index.php?isbn=9789600316483"

test: test-php test-py test-ts
lint: lint-php lint-py lint-ts
live-test: live-test-php live-test-py live-test-ts

# --- PHP ---
php/vendor/autoload.php: php/composer.json
	$(DOCKER_RUN) -e COMPOSER_HOME=/tmp/composer $(COMPOSER_IMAGE) install --no-interaction --no-progress
	@touch $@

install-php: php/vendor/autoload.php

test-php: php/vendor/autoload.php
	$(PHP) vendor/bin/phpunit --testsuite unit

lint-php:
	@$(DOCKER_RUN) $(PHP_IMAGE) sh -c 'for f in index.php src/*.php scripts/*.php tests/*.php tests/live/*.php; do php -l "$$f" >/dev/null || exit 1; done' && echo "php lint ok"

live-test-php: php/vendor/autoload.php
	$(PHP) vendor/bin/phpunit --testsuite live

fixtures: php/vendor/autoload.php
	$(PHP) scripts/refresh-fixtures.php

serve-php:
	$(DOCKER_RUN) -p $(PORT):8080 $(PHP_IMAGE) php -S 0.0.0.0:8080

# --- Python ---
install-py:
	cd python && uv sync

test-py:
	$(UV) pytest -m "not live"

lint-py:
	cd python && uv run ruff check . && uv run ruff format --check . && uv run mypy

live-test-py:
	$(UV) pytest -m live

serve-py:
	$(UV) uvicorn bookmeta.app:app --port $(PORT)

# --- TypeScript ---
ts/node_modules/.package-lock.json: ts/package.json ts/package-lock.json
	cd ts && npm ci

install-ts: ts/node_modules/.package-lock.json

test-ts: install-ts
	cd ts && npm test

lint-ts: install-ts
	cd ts && npm run lint

live-test-ts: install-ts
	cd ts && npm run test:live

build-ts: install-ts
	cd ts && npm run build

pack-check-ts: install-ts
	cd ts && npm run pack:check

serve-ts: install-ts
	cd ts && PORT=$(PORT) npm run serve
