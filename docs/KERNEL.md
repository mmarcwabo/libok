# Libok kernel

Libok is an API-first PHP starter. Clone it, migrate, and grow a product (CRM, stock, case file, …) without deleting leftover LMS domain.

HTML Bootstrap screens are a **demo adapter**. JSON under `/api/v1` is the interface.

## Core (keep this)

These belong in every product:

- HTTP pipeline (request id, CORS allow-list, security headers, JSON body, audit)
- Envelope `{success, data, message}` / `{success, false, message, code}`
- PHP-DI, Doctrine migrations, UUID ids
- Cookie JWT auth; roles loaded from the database, not from token claims
- Health `live` / `ready`, JSON logs on stderr
- Uploads with MIME + path checks
- Mail port, filesystem queue, transactional outbox, `bin/libok worker`
- Idempotency keys on create-once POSTs
- OpenAPI (`docs/openapi-v1.yaml`) checked in CI

## Modules (optional)

Turn these on only when the product needs them:

| Module | What it is |
|---|---|
| Tenancy | `Organization` + membership + `TenantFilter` + `X-Organization` |
| HTML demo | Session login and user screens under `/` |
| Sample `Item` | Tenant-owned CRUD at `/api/v1/items` so a new clone is not empty |

Tenancy is a module, not a hidden global. If you are single-tenant, leave organizations unused and do not put `tenant` on routes.

## What does not belong here

Product domain (invoices, stock moves, courses, …) lives in the **product repo**. Kernel improvements come back as PRs to libok.

`Item` is a sample. Replace it with your aggregate; copy the pattern (use case → resource → tenant filter → isolation tests), not the noun.

## Growing a product

1. Tag or copy this kernel (`v0.1.0` or later).
2. Keep `/api/v1` and the envelope.
3. Add entities and use cases for your domain.
4. If two customers must not see each other’s rows, keep the tenancy module and isolation tests.
