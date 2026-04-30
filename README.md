# AP Hackathon - Backend

Backend for the Hackathon project built with **Laravel 13** + **PHP 8.4** + **SQL Server 2022**, fully Dockerized so any team member can spin up the environment regardless of their operating system.

## Stack

| Technology | Version |
| ---------- | ------- |
| PHP        | 8.4     |
| Laravel    | 13.x    |
| SQL Server | 2022    |
| Nginx      | Alpine  |
| Docker     | 24+     |

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) installed and running
- [Make](https://www.gnu.org/software/make/) (included on Linux/Mac; on Windows use WSL2 or install via Chocolatey: `choco install make`)
- Git

> **Note:** You do not need PHP, Composer, or SQL Server installed locally. Everything runs inside Docker.

## Quick Start

```bash
# 1. Clone the repository
git clone https://github.com/alfonsodalixap/AP.Hackathon.BE.git
cd AP.Hackathon.BE

# 2. FIRST TIME only (no Laravel code yet):
make init

# 3. Project already has Laravel code (subsequent runs):
make install
```

Done. The app will be available at **http://localhost:8000**

## Makefile Commands

Run `make help` to see all available commands. Quick reference:

### Docker

| Command         | Description                    |
| --------------- | ------------------------------ |
| `make build`    | Build Docker images            |
| `make up`       | Start all containers           |
| `make down`     | Stop and remove containers     |
| `make restart`  | Restart all containers         |
| `make status`   | Show container status          |
| `make logs`     | Stream logs in real time       |
| `make logs-app` | Stream app container logs only |

### Installation

| Command        | Description                                                      |
| -------------- | ---------------------------------------------------------------- |
| `make init`    | **First time only** — Create a fresh Laravel project from scratch |
| `make install` | Full setup (composer install + migrate + key)                    |

### Laravel / Artisan

| Command                            | Description                          |
| ---------------------------------- | ------------------------------------ |
| `make migrate`                     | Run migrations                       |
| `make migrate-fresh`               | Drop all tables and re-run migrations |
| `make seed`                        | Run seeders                          |
| `make fresh`                       | migrate:fresh + seed                 |
| `make cache`                       | Clear all Laravel caches             |
| `make routes`                      | List all registered routes           |
| `make tinker`                      | Open Tinker (interactive REPL)       |
| `make test`                        | Run tests                            |
| `make test-coverage`               | Run tests with coverage report       |
| `make artisan c="make:model Post"` | Run any artisan command              |

### Composer

| Command                                             | Description              |
| --------------------------------------------------- | ------------------------ |
| `make composer-install`                             | Install dependencies     |
| `make composer-update`                              | Update dependencies      |
| `make composer-require p=spatie/laravel-permission` | Install a new package    |

### Shell Access

| Command           | Description                           |
| ----------------- | ------------------------------------- |
| `make shell`      | Terminal in the app container         |
| `make shell-root` | Terminal as root in the app container |
| `make db`         | Interactive SQL session on SQL Server |

### Cleanup

| Command      | Description                                      |
| ------------ | ------------------------------------------------ |
| `make clean` | Remove project containers, volumes, and images   |

## Database Configuration

The SQL Server connection is pre-configured in `.env.example`. Running `make install` or `make init` will automatically create the `hackathon` database.

Default credentials:

| Field    | Value             |
| -------- | ----------------- |
| Host     | `sqlserver`       |
| Port     | `1433`            |
| DB       | `hackathon`       |
| User     | `sa`              |
| Password | `Hackathon@2026!` |

To connect from an external SQL client (DBeaver, Azure Data Studio, etc.) use `localhost:1433` with the same credentials.

## Docker Structure

```
├── docker/
│   ├── nginx/
│   │   └── nginx.conf          # Nginx config
│   └── php/
│       ├── Dockerfile          # PHP 8.4 image + SQL Server drivers
│       └── local.ini           # PHP config for development
├── docker-compose.yml          # Service orchestration
├── Makefile                    # Project commands
└── .env.example                # Environment variables
```

## Testing

```bash
# Run all tests (uses SQLite in-memory — no Docker required)
make test

# Run with HTML coverage report
make test-coverage

# Run a specific test file
php artisan test tests/Unit/Services/RosterAnalysisServiceTest.php
```

> Tests use an in-memory SQLite database defined in `phpunit.xml`, so you can run them without starting the Docker stack.

Test suite overview:

| Suite | File | What it tests |
|-------|------|---------------|
| Unit | `RosterAnalysisServiceTest` | Header normalisation, aggregations, blank rows, DB persistence |
| Unit | `SecEdgarServiceTest` | Ticker resolution, EBITDA computation, latest 10-K selection, 404/502 errors |
| Feature | `HealthControllerTest` | `GET /api/health` response |
| Feature | `RosterControllerTest` | File upload, validation, grouping, DB persistence |
| Feature | `FinancialsControllerTest` | Ticker lookup, financial data mapping, error codes |

## Troubleshooting

**SQL Server container won't start:**
SQL Server requires at least 2 GB of RAM allocated to Docker. Check your Docker Desktop resource settings.

**Permission error on storage/:**

```bash
make shell-root
chmod -R 775 storage bootstrap/cache
chown -R laravel:www-data storage bootstrap/cache
```

**Reset everything from scratch:**

```bash
make clean
make install
```

## Team

Project developed for the AlixPartners Hackathon.
