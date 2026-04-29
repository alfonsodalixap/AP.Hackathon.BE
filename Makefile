.PHONY: help build up down restart install fresh shell shell-root db migrate seed test logs clean status

# ─── Colors ──────────────────────────────────────────────
GREEN  := \033[0;32m
YELLOW := \033[0;33m
CYAN   := \033[0;36m
RESET  := \033[0m

# ─── Fix Git Bash en Windows (evita conversión de rutas Linux) ───
export MSYS_NO_PATHCONV=1

help: ## Muestra esta ayuda
	@echo ""
	@echo "$(CYAN)╔══════════════════════════════════════════════╗$(RESET)"
	@echo "$(CYAN)║     🚀 AP Hackathon BE - Comandos Make      ║$(RESET)"
	@echo "$(CYAN)╠══════════════════════════════════════════════╣$(RESET)"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "$(CYAN)║$(RESET)  $(GREEN)%-15s$(RESET) %s\n", $$1, $$2}'
	@echo "$(CYAN)╚══════════════════════════════════════════════╝$(RESET)"
	@echo ""

# ─── Docker ──────────────────────────────────────────────
build: ## Construye las imágenes de Docker
	docker compose build --no-cache

up: ## Levanta todos los contenedores
	docker compose up -d
	@echo "$(GREEN)✅ App corriendo en http://localhost:8000$(RESET)"

down: ## Detiene y elimina los contenedores
	docker compose down

restart: ## Reinicia todos los contenedores
	docker compose restart

status: ## Muestra el estado de los contenedores
	docker compose ps

logs: ## Muestra los logs en tiempo real
	docker compose logs -f

logs-app: ## Muestra los logs solo de la app
	docker compose logs -f app

# ─── Instalación ─────────────────────────────────────────
install: ## Primera instalación completa del proyecto
	@echo "$(YELLOW)📦 Construyendo imágenes...$(RESET)"
	docker compose build
	@echo "$(YELLOW)🚀 Levantando contenedores...$(RESET)"
	docker compose up -d
	@echo "$(YELLOW)📥 Instalando dependencias...$(RESET)"
	docker compose exec app composer install
	@echo "$(YELLOW)⚙️  Configurando entorno...$(RESET)"
	@if [ ! -f .env ]; then cp .env.example .env; fi
	docker compose exec app php artisan key:generate
	@echo "$(YELLOW)🗄️  Esperando a SQL Server...$(RESET)"
	@sleep 5
	@echo "$(YELLOW)🏗️  Creando base de datos...$(RESET)"
	docker compose exec sqlserver bash -c "/opt/mssql-tools18/bin/sqlcmd -S localhost -U sa -P 'Hackathon@2026!' -C -Q \"IF NOT EXISTS (SELECT name FROM sys.databases WHERE name = 'hackathon') CREATE DATABASE hackathon;\""
	@echo "$(YELLOW)📋 Ejecutando migraciones...$(RESET)"
	docker compose exec app php artisan migrate
	@echo "$(GREEN)✅ ¡Proyecto instalado! Visitá http://localhost:8000$(RESET)"

init: ## Crear proyecto Laravel desde cero (solo primera vez)
	docker compose build
	docker compose up -d
	docker compose exec app composer create-project --prefer-dist laravel/laravel:^13.0 temp-laravel
	docker compose exec app bash -c "shopt -s dotglob && mv temp-laravel/* /var/www/ && rmdir temp-laravel"
	@if [ ! -f .env ]; then cp .env.example .env; fi
	docker compose exec app php artisan key:generate
	@sleep 5
	docker compose exec sqlserver bash -c "/opt/mssql-tools18/bin/sqlcmd -S localhost -U sa -P 'Hackathon@2026!' -C -Q \"IF NOT EXISTS (SELECT name FROM sys.databases WHERE name = 'hackathon') CREATE DATABASE hackathon;\""
	docker compose exec app php artisan migrate
	@echo "$(GREEN)✅ Proyecto Laravel 13 creado! Visitá http://localhost:8000$(RESET)"

# ─── Laravel ─────────────────────────────────────────────
migrate: ## Ejecuta las migraciones
	docker compose exec app php artisan migrate

migrate-fresh: ## Elimina todas las tablas y re-ejecuta migraciones
	docker compose exec app php artisan migrate:fresh

seed: ## Ejecuta los seeders
	docker compose exec app php artisan db:seed

fresh: ## migrate:fresh + seed
	docker compose exec app php artisan migrate:fresh --seed

cache: ## Limpia todas las cachés de Laravel
	docker compose exec app php artisan optimize:clear

routes: ## Lista todas las rutas
	docker compose exec app php artisan route:list

tinker: ## Abre Tinker (REPL de Laravel)
	docker compose exec app php artisan tinker

test: ## Ejecuta los tests
	docker compose exec app php artisan test

test-coverage: ## Ejecuta tests con cobertura
	docker compose exec app php artisan test --coverage

# ─── Shell Access ────────────────────────────────────────
shell: ## Abre una terminal en el contenedor de la app
	docker compose exec app bash

shell-root: ## Abre una terminal como root
	docker compose exec -u root app bash

db: ## Abre una sesión SQL en el servidor
	docker compose exec sqlserver bash -c "/opt/mssql-tools18/bin/sqlcmd -S localhost -U sa -P 'Hackathon@2026!' -C -d hackathon"

# ─── Composer ────────────────────────────────────────────
composer-install: ## Instala dependencias de Composer
	docker compose exec app composer install

composer-update: ## Actualiza dependencias de Composer
	docker compose exec app composer update

composer-require: ## Instala un paquete (uso: make composer-require p=paquete)
	docker compose exec app composer require $(p)

# ─── Artisan ─────────────────────────────────────────────
artisan: ## Ejecuta un comando artisan (uso: make artisan c="make:model User")
	docker compose exec app php artisan $(c)

# ─── Limpieza ────────────────────────────────────────────
clean: ## Elimina contenedores, volúmenes e imágenes del proyecto
	docker compose down -v --rmi local
	@echo "$(GREEN)🧹 Todo limpio$(RESET)"