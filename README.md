# Libok

An API-first PHP kernel with an optional Bootstrap HTML adapter.

JSON under `/api/v1` is the primary interface. The HTML login and user screens are a demo client of the same use cases.

## Features

- JSON envelope (`success` / `data` / `message` / `code`)
- Middleware pipeline (request id, CORS allow-list, security headers)
- Doctrine ORM + **migrations** (MySQL, PostgreSQL, SQLite)
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

Set `DB_*` and `CORS_ORIGIN` in `.env`. `STORAGE_PATH` defaults to `storage/` when empty.

```bash
composer migrate
composer serve
```

Open `http://localhost:8000` for the HTML demo, or:

```bash
curl http://localhost:8000/api/v1/health/live
curl http://localhost:8000/api/v1/health/ready
```

## Quality

```bash
composer test
composer phpstan
composer cs
composer schema-validate
```

Tests use in-memory SQLite. `composer migrate` is the only supported way to change schema — do not run `orm:schema-tool:update` in production.
