# Boursa API

Backend Laravel 11 pour Boursa — marketplace véhicules Mauritanie.

## Stack

- PHP 8.3+ / Laravel 11
- PostgreSQL 16 + PostGIS 3.4
- Redis 7 (cache + queue + session)
- MinIO S3 (storage media)
- Mailpit (SMTP dev)

## Quickstart

```bash
git clone git@github.com:limamdahman/boursa-api.git
cd boursa-api
composer install
cp .env.example .env
php artisan key:generate

# Stack Docker depuis ~/projects/boursa/docker
docker compose up -d

php artisan migrate
php artisan serve
```

## Structure

- `app/Actions/` — Use cases métier (1 action = 1 cas d'usage)
- `app/Services/` — Intégrations externes (SMS, Meta, MinIO, géoloc)
- `app/Enums/` — Énumérations PHP 8.1+
- `app/Http/Controllers/Api/V1/` — Endpoints publics + utilisateur
- `app/Http/Controllers/Api/Agency/` — Endpoints espace agence
- `app/Http/Controllers/Api/Admin/` — Endpoints back-office

## Conventions

- `declare(strict_types=1)` obligatoire
- Logs fichier uniquement (`/logs/`)
- CSRF sur tout endpoint mutant
- Prepared statements avec `ATTR_EMULATE_PREPARES=false`
