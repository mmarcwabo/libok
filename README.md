# Libok

An API-first PHP kernel with an optional Bootstrap HTML adapter.

JSON under `/api/v1` is the primary interface. The HTML login and user screens are a demo client of the same use cases. See [docs/KERNEL.md](docs/KERNEL.md) for core vs module.

## Requirements

- PHP >= 8.1
- Composer
- A database (PostgreSQL recommended; MySQL and SQLite are supported)

## Create a project

```bash
composer create-project --repository='{"type":"vcs","url":"https://github.com/mmarcwabo/libok.git"}' libok/libok my-app
cd my-app
cp .env.example .env
```

Or clone a tagged release:

```bash
git clone --branch v0.1.0 https://github.com/mmarcwabo/libok.git my-app
cd my-app
composer install
cp .env.example .env
```

Set `DB_*`, `CORS_ORIGIN`, and `JWT_SECRET` (at least 32 random bytes) in `.env`. `STORAGE_PATH` defaults to `storage/` when empty.

```bash
composer migrate
composer test
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

Welcome mail is written to the outbox on register. Run a worker (SMTP is not required for the HTTP 201):

```bash
php bin/libok worker --once
```

API contract: [docs/openapi-v1.yaml](docs/openapi-v1.yaml). Sample tenant-owned resource: `GET/POST /api/v1/items`, `GET/PATCH/DELETE /api/v1/items/{id}` (send `X-Organization` after you join an organization).

## Quality

```bash
composer test
composer phpstan
composer cs
composer schema-validate
```

Tests use in-memory SQLite. `composer migrate` is the only supported way to change schema — do not run `orm:schema-tool:update` in production.
