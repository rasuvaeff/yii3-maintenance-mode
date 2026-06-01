DOCKER := docker run --rm -v "$(PWD)":/app -w /app composer:2
DOCKER_HOST := docker run --rm --network host -v "$(PWD)":/app -w /app

.PHONY: build cs cs-fix psalm test mutation rector rector-fix install normalize require-checker \
       test-coverage test-coverage-ci update-deps

install:
	$(DOCKER) composer install --no-interaction --no-progress --prefer-dist

build:
	$(DOCKER) composer build

cs:
	$(DOCKER) composer cs

cs-fix:
	$(DOCKER) composer cs:fix

psalm:
	$(DOCKER) composer psalm

test:
	$(DOCKER) composer test

test-coverage:
	$(DOCKER) composer test:coverage

test-coverage-ci:
	$(DOCKER) composer test:coverage:ci

mutation:
	$(DOCKER) composer mutation

rector:
	$(DOCKER) composer rector

rector-fix:
	$(DOCKER) composer rector:fix

normalize:
	$(DOCKER) sh -c 'git config --global --add safe.directory /app; composer normalize'

require-checker:
	$(DOCKER) composer require-checker

update-deps:
	$(DOCKER) sh -c 'git config --global --add safe.directory /app; composer update -q; composer normalize'

help:
	@echo "Usage: make <target>"
	@echo ""
	@echo "Targets:"
	@echo "  install          composer install"
	@echo "  build            full gate (validate + normalize + cs + psalm + test)"
	@echo "  cs               check code style (dry-run)"
	@echo "  cs-fix           fix code style"
	@echo "  psalm            static analysis"
	@echo "  test             run phpunit"
	@echo "  test-coverage    run phpunit with coverage"
	@echo "  mutation         mutation testing"
	@echo "  rector           check rector (dry-run)"
	@echo "  rector-fix       apply rector fixes"
	@echo "  normalize        normalize composer.json"
	@echo "  require-checker  check composer dependencies"
	@echo "  update-deps      composer update + normalize"
