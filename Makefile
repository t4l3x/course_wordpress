SHELL := /bin/bash

COMPOSE := docker compose
FILE ?= database.sql
ARGS ?=

.DEFAULT_GOAL := help

.PHONY: help init setup up down restart status logs shell wp seed composer composer-install composer-update composer-validate composer-audit lint cs cs-fix analyse test test-unit test-integration test-feature quality test-database-up db-export db-import reset

help: ## Show available development commands.
	@awk 'BEGIN {FS = ":.*## "}; /^[a-zA-Z0-9_-]+:.*## / {printf "  %-18s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

init: ## Create local config, start services and install WordPress.
	@./scripts/bootstrap.sh

setup: ## Activate the plugin and normalize its public Course Discovery page.
	@$(COMPOSE) run --rm --no-deps wp-cli plugin activate course-discovery
	@$(COMPOSE) run --rm --no-deps wp-cli course-discovery setup --force

up: ## Build and start the application.
	@$(COMPOSE) up --detach --build database wordpress web

down: ## Stop containers without deleting data.
	@$(COMPOSE) down --remove-orphans

restart: ## Restart the application containers.
	@$(COMPOSE) restart database wordpress web

status: ## Show container status.
	@$(COMPOSE) ps

logs: ## Follow application logs.
	@$(COMPOSE) logs --follow --tail=100 database wordpress web

shell: ## Open a shell in the WordPress PHP container.
	@$(COMPOSE) exec wordpress sh

wp: ## Run WP-CLI, for example: make wp ARGS="plugin list".
	@$(COMPOSE) run --rm --no-deps wp-cli $(ARGS)

seed: ## Reset and generate 40 deterministic demo Courses. Pass ARGS="--count=50" to change the count.
	@$(COMPOSE) run --rm --no-deps wp-cli course-discovery seed --reset $(ARGS)

composer: ## Run Composer, for example: make composer ARGS="--version".
	@$(COMPOSE) run --rm --no-deps composer $(ARGS)

composer-install: ## Install the locked Composer dependencies.
	@$(COMPOSE) run --rm --no-deps composer install --no-interaction

composer-update: ## Update Composer dependencies and refresh the lock file.
	@$(COMPOSE) run --rm --no-deps composer update --with-all-dependencies --no-interaction

composer-validate: ## Validate and normalize the plugin Composer manifest.
	@$(COMPOSE) run --rm --no-deps composer run composer:validate

composer-audit: ## Check locked Composer dependencies for security advisories.
	@$(COMPOSE) run --rm --no-deps composer run composer:audit

lint: ## Check syntax in plugin-controlled PHP files.
	@$(COMPOSE) run --rm --no-deps composer run lint

cs: ## Check the plugin against WordPress coding standards.
	@$(COMPOSE) run --rm --no-deps composer run cs

cs-fix: ## Fix automatically correctable coding-standard violations.
	@$(COMPOSE) run --rm --no-deps composer run cs:fix

analyse: ## Run WordPress-aware PHPStan analysis.
	@$(COMPOSE) run --rm --no-deps composer run analyse

test: test-database-up ## Run all plugin test suites.
	@$(COMPOSE) run --rm --no-deps composer run test

test-unit: ## Run isolated unit tests without WordPress.
	@$(COMPOSE) run --rm --no-deps composer run test:unit

test-integration: test-database-up ## Run tests that boot WordPress.
	@$(COMPOSE) run --rm --no-deps composer run test:integration

test-feature: test-database-up ## Run feature tests in WordPress.
	@$(COMPOSE) run --rm --no-deps composer run test:feature

quality: test-database-up ## Run the complete plugin quality pipeline.
	@$(COMPOSE) run --rm --no-deps composer run quality

test-database-up:
	@$(COMPOSE) up --detach --wait test-database

db-export: ## Export the database. Override with FILE=exports/database.sql.
	@mkdir -p "$(dir $(FILE))"
	@$(COMPOSE) exec -T database sh -c 'MARIADB_PWD="$${MARIADB_PASSWORD}" exec mariadb-dump --single-transaction --quick --skip-lock-tables --user="$${MARIADB_USER}" "$${MARIADB_DATABASE}"' > "$(FILE)"
	@printf 'Database exported to %s\n' "$(FILE)"

db-import: ## Import FILE into the local database.
	@test -f "$(FILE)" || { printf 'Database file not found: %s\n' "$(FILE)" >&2; exit 1; }
	@$(COMPOSE) exec -T database sh -c 'MARIADB_PWD="$${MARIADB_PASSWORD}" exec mariadb --user="$${MARIADB_USER}" "$${MARIADB_DATABASE}"' < "$(FILE)"
	@printf 'Database imported from %s\n' "$(FILE)"

reset: ## Delete local containers and database volumes after confirmation.
	@./scripts/reset.sh
