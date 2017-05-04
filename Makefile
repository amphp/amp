PHP_BIN := php
COMPOSER_BIN := composer

COVERAGE = coverage
SRCS = lib test

find_php_files = $(shell find $(1) -type f -name "*.php")
src = $(foreach d,$(SRCS),$(call find_php_files,$(d)))

.PHONY: test
test: setup phpunit code-style

.PHONY: clean
clean: clean-coverage clean-vendor

.PHONY: clean-coverage
clean-coverage:
	test ! -e coverage || rm -r coverage

.PHONY: clean-vendor
clean-vendor:
	test ! -e vendor || rm -r vendor

.PHONY: setup
setup: vendor/autoload.php

.PHONY: deps-update
deps-update:
	$(COMPOSER_BIN) update

.PHONY: phpunit
phpunit: setup
	$(PHP_BIN) vendor/bin/phpunit

.PHONY: code-style
code-style: setup
	PHP_CS_FIXER_IGNORE_ENV=1 $(PHP_BIN) vendor/bin/php-cs-fixer --diff -v fix

composer.lock: composer.json
	$(COMPOSER_BIN) install
	touch $@

vendor/autoload.php: composer.lock
	$(COMPOSER_BIN) install
	touch $@
