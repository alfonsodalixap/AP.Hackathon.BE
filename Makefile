.PHONY: help build up down restart install fresh shell shell-root db migrate seed test logs clean status

# ─── Colors ──────────────────────────────────────────────
GREEN  := \033[0;32m
YELLOW := \033[0;33m
CYAN   := \033[0;36m
RESET  := \033[0m

# ─── Fix Git Bash on Windows (prevents Linux path conversion) ────
export MSYS_NO_PATHCONV=1

help: ## Show this help
	@echo ""
	@echo "$(CYAN)╔══════════════════════════════════════════════╗$(RESET)"
	@echo "$(CYAN)║     🚀 AP Hackathon BE - Comandos Make      ║$(RESET)"
	@echo "$(CYAN)╠══════════════════════════════════════════════╣$(RESET)"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "$(CYAN)║$(RESET)  $(GREEN)%-15s$(RESET) %s\n", $$1, $$2}'
	@echo "$(CYAN)╚══════════════════════════════════════════════╝$(RESET)"
	@echo ""

# ─── Docker ──────────────────────────────────────────────
build: ## Build Docker images
	docker compose build --no-cache

up: ## Start all containers
	docker compose up -d
	@echo "$(GREEN)✅ App running at http://localhost:8000$(RESET)"

down: ## Stop and remove containers
	docker compose down

restart: ## Restart all containers
	docker compose restart

status: ## Show container status
	docker compose ps

logs: ## Stream logs in real time
	docker compose logs -f

logs-app: ## Stream app container logs only
	docker compose logs -f app

# ─── Installation ────────────────────────────────────────
install: ## Full first-time project setup
	@echo "$(YELLOW)📦 Building images...$(RESET)"
	docker compose build
	@echo "$(YELLOW)🚀 Starting containers...$(RESET)"
	docker compose up -d
	@echo "$(YELLOW)📥 Installing dependencies...$(RESET)"
	docker compose exec app composer install
	@echo "$(YELLOW)⚙️  Configuring environment...$(RESET)"
	@if [ ! -f .env ]; then cp .env.example .env; fi
	docker compose exec app php artisan key:generate
	@echo "$(YELLOW)🗄️  Waiting for SQL Server...$(RESET)"
	@sleep 5
	@echo "$(YELLOW)🏗️  Creating database...$(RESET)"
	docker compose exec sqlserver bash -c "/opt/mssql-tools18/bin/sqlcmd -S localhost -U sa -P 'Hackathon@2026!' -C -Q \"IF NOT EXISTS (SELECT name FROM sys.databases WHERE name = 'hackathon') CREATE DATABASE hackathon;\""
	@echo "$(YELLOW)📋 Running migrations...$(RESET)"
	docker compose exec app php artisan migrate
	@echo "$(GREEN)✅ Project installed! Visit http://localhost:8000$(RESET)"

init: ## Create a fresh Laravel project (first time only)
	docker compose build
	docker compose up -d
	docker compose exec app composer create-project --prefer-dist laravel/laravel:^13.0 temp-laravel
	docker compose exec app bash -c "shopt -s dotglob && mv temp-laravel/* /var/www/ && rmdir temp-laravel"
	@if [ ! -f .env ]; then cp .env.example .env; fi
	docker compose exec app php artisan key:generate
	@sleep 5
	docker compose exec sqlserver bash -c "/opt/mssql-tools18/bin/sqlcmd -S localhost -U sa -P 'Hackathon@2026!' -C -Q \"IF NOT EXISTS (SELECT name FROM sys.databases WHERE name = 'hackathon') CREATE DATABASE hackathon;\""
	docker compose exec app php artisan migrate
	@echo "$(GREEN)✅ Laravel 13 project created! Visit http://localhost:8000$(RESET)"

# ─── Laravel ─────────────────────────────────────────────
migrate: ## Run migrations
	docker compose exec app php artisan migrate

migrate-fresh: ## Drop all tables and re-run migrations
	docker compose exec app php artisan migrate:fresh

seed: ## Run seeders
	docker compose exec app php artisan db:seed

fresh: ## migrate:fresh + seed
	docker compose exec app php artisan migrate:fresh --seed

cache: ## Clear all Laravel caches
	docker compose exec app php artisan optimize:clear

routes: ## List all routes
	docker compose exec app php artisan route:list

tinker: ## Open Tinker (Laravel REPL)
	docker compose exec app php artisan tinker

test: ## Run tests
	docker compose exec app php artisan test

test-coverage: ## Run tests with coverage
	docker compose exec app php artisan test --coverage

# ─── Shell Access ────────────────────────────────────────
shell: ## Open a terminal in the app container
	docker compose exec app bash

shell-root: ## Open a terminal as root
	docker compose exec -u root app bash

db: ## Open a SQL session on the server
	docker compose exec sqlserver bash -c "/opt/mssql-tools18/bin/sqlcmd -S localhost -U sa -P 'Hackathon@2026!' -C -d hackathon"

# ─── Composer ────────────────────────────────────────────
composer-install: ## Install Composer dependencies
	docker compose exec app composer install

composer-update: ## Update Composer dependencies
	docker compose exec app composer update

composer-require: ## Install a package (usage: make composer-require p=package)
	docker compose exec app composer require $(p)

# ─── Artisan ─────────────────────────────────────────────
artisan: ## Run an artisan command (usage: make artisan c="make:model User")
	docker compose exec app php artisan $(c)

# ─── Cleanup ─────────────────────────────────────────────
clean: ## Remove project containers, volumes, and images
	docker compose down -v --rmi local
	@echo "$(GREEN)🧹 All clean$(RESET)"