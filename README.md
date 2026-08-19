# Libok

An API-first PHP kernel with an optional Bootstrap HTML adapter.

JSON under `/api/v1` is the primary interface. The HTML login and user screens are a demo client of the same use cases.

## Features

- JSON envelope (`success` / `data` / `message` / `code`)
- Middleware pipeline (request id, CORS allow-list, security headers)
- Doctrine ORM + **migrations** (MySQL, PostgreSQL, SQLite)
- Cookie JWT auth (`/api/v1/auth/*`, `/api/v1/me`) with roles loaded from the database
- Health probes: `GET /api/v1/health/live` and `GET /api/v1/health/ready`
- JSON logs on stderr with request ids
- Optional HTML adapter (Bootstrap 5)

## Requirements

- PHP >= 8.1
- Composer
- A database (PostgreSQL recommended; MySQL and SQLite are supported)

## Installation

```bash
git clone https://github.com/mmarcwabo/libok.git
cd libok
composer install
cp .env.example .env
```

Set `DB_*`, `CORS_ORIGIN`, and `JWT_SECRET` (at least 32 random bytes) in `.env`. `STORAGE_PATH` defaults to `storage/` when empty.

```bash
composer migrate
composer serve
```

Open `http://localhost:8000` for the HTML demo, or:

```bash
curl http://localhost:8000/api/v1/health/live
curl http://localhost:8000/api/v1/health/ready
curl -c cookies.txt -H "Content-Type: application/json" -d "{\"name\":\"Ada\",\"email\":\"ada@example.test\",\"password\":\"password123\"}" http://localhost:8000/api/v1/auth/register
curl -b cookies.txt -c cookies.txt -H "Content-Type: application/json" -d "{\"email\":\"ada@example.test\",\"password\":\"password123\"}" http://localhost:8000/api/v1/auth/login
curl -b cookies.txt http://localhost:8000/api/v1/me
```

## Quality

```bash
composer test
composer phpstan
composer cs
composer schema-validate
```

Tests use in-memory SQLite. `composer migrate` is the only supported way to change schema — do not run `orm:schema-tool:update` in production.
