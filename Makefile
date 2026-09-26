# PHP is not required on the host: everything runs in Docker.
PHP_IMAGE ?= php:8.3-cli
COMPOSER_IMAGE ?= composer:2
PORT ?= 8080

DOCKER_RUN = docker run --rm -u "$$(id -u):$$(id -g)" -v "$(CURDIR)":/app -w /app
PHP = $(DOCKER_RUN) $(PHP_IMAGE) php

.PHONY: help install test lint live-test fixtures serve

help:
	@echo "make install    install dev dependencies (PHPUnit) into vendor/"
	@echo "make test       offline tests against saved pages in tests/fixtures"
	@echo "make lint       php -l on every PHP file"
	@echo "make live-test  tests against the real biblionet.gr (network)"
	@echo "make fixtures   re-download fixtures and rewrite expected JSON (network)"
	@echo "make serve      run the endpoint at http://localhost:$(PORT)/index.php?isbn=9789600316483"

vendor/autoload.php: composer.json
	$(DOCKER_RUN) -e COMPOSER_HOME=/tmp/composer $(COMPOSER_IMAGE) install --no-interaction --no-progress
	@touch $@

install: vendor/autoload.php

test: vendor/autoload.php
	$(PHP) vendor/bin/phpunit --testsuite unit

lint:
	@$(DOCKER_RUN) $(PHP_IMAGE) sh -c 'for f in index.php src/*.php scripts/*.php tests/*.php tests/live/*.php; do php -l "$$f" >/dev/null || exit 1; done' && echo "lint ok"

live-test: vendor/autoload.php
	$(PHP) vendor/bin/phpunit --testsuite live

fixtures: vendor/autoload.php
	$(PHP) scripts/refresh-fixtures.php

serve:
	$(DOCKER_RUN) -p $(PORT):8080 $(PHP_IMAGE) php -S 0.0.0.0:8080
