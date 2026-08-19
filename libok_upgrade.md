# libok upgrade — from boilerplate to a world-class kernel

**Audience:** clone [mmarcwabo/libok](https://github.com/mmarcwabo/libok), then apply this document.  
**Source of battle-tested patterns:** `oklass/core` (Tangako). Copy *capabilities*, rename `Tangako` → `Libok`. Do not copy the LMS.  
**Outcome:** an API-first starter you can `composer create-project` and grow into any product (CRM, stock, case file, second LMS, …).

This is a specification, not a dump of Oklass. Every file that lands in libok must still make sense in an app that has never heard of a course or a certificate.

---

## 0. Non-goals

- Do not merge `oklass/core` into libok.
- Do not copy courses, cohorts, enrollments, quizzes, certificates, Dompdf, GrapesJS, or ISRM seeders.
- Do not make oklass import libok on day one. Tag libok `0.1.0` first; wire oklass later if you want.
- Do not keep HTML as the primary API. Bootstrap views become an optional adapter.

---

## 1. Where libok is today vs where it must land

| Area | libok now | Target |
|---|---|---|
| Delivery | Server-rendered Bootstrap + session-ish login | JSON API first (`/api/v1`), optional HTML |
| HTTP | Thin custom router | Pipeline: globals + groups + per-route middleware |
| DI | Ad-hoc bootstrap | PHP-DI, constructor injection only |
| Persistence | `orm:schema-tool:create` | Doctrine **migrations**, MySQL **and** PostgreSQL |
| Auth | Login use case + cookie/session in views | HttpOnly access cookie, refresh cookie, logout, status checks, roles from DB not JWT |
| Errors | Mixed | Envelope + sanitised messages; never leak SQL/driver text |
| Tenancy | None | Optional module: Organization + membership + Doctrine SQL filter |
| Observability | Almost none | Request ID, JSON logs, health live/ready |
| Security | Basic | CSP-ready headers, CORS allow-list, rate limit, upload MIME allow-list, `X-Content-Type-Options` |
| Tests | Register user unit + one integration | Kernel, auth, tenancy isolation, security headers, pagination |
| Ops | `php -S` | 12-factor env, migrations in deploy, `/health/live` + `/health/ready` |

---

## 2. Target architecture

```
libok/
  public/index.php              # front controller only
  config/
    bootstrap.php               # env, timezone, Doctrine types
    services.php                # PHP-DI definitions
    api_routes.php              # /api/v1 registration
    migrations.php
  migrations/                   # versioned SQL, platform-aware
  src/
    Domain/
      Entities/
      Repositories/             # interfaces only
      Enums/
    Application/
      UseCases/
      Services/
      Contracts/                # Clock, Mailer, Storage, Queue, Cache, RateLimiter
    Infrastructure/
      Persistence/              # Doctrine repos, TenantFilter, PlatformSql
      Auth/                     # JwtService, PasswordService
      Storage/
      Mail/
      Queue/
      Cache/
      Observability/            # JsonLogger, RequestContext, ContextSanitizer
    Framework/
      Core/                     # Application, Router, MiddlewareRegistry, MiddlewareInterface
      Middleware/
      Controllers/              # API only in v1
      Resources/                # array transformers (no entity leakage)
  tests/
    Unit/
    Integration/
    Framework/                  # HTTP-level security tests
  docs/
    KERNEL.md
    openapi-v1.yaml
```

**Rules**

- Controllers do not talk to EntityManager. They call use cases / services.
- Entities never appear in JSON. Map through `*Resource` classes.
- Time comes from `ClockInterface` (injectable, freezeable in tests).
- IDs are UUIDs (Ramsey), never auto-increment in public APIs.
- `APP_DEBUG=true` may add a `debug` object to 500s; production never does.

---

## 3. API contract (lock this first)

### Envelope

Success:

```json
{ "success": true, "data": {}, "message": "" }
```

Error:

```json
{ "success": false, "message": "Human-readable, safe message.", "code": "auth.expired" }
```

Collection:

```json
{
  "success": true,
  "data": [],
  "pagination": { "total": 0, "page": 1, "per_page": 20, "total_pages": 0 }
}
```

Copy the helpers from `oklass/core/Framework/Controllers/BaseController.php` (`json`, `error`, `paginated`, `jsonCached`) **without** the LMS `canManageCourseContent` methods.

### HTTP

- Versioned prefix: `/api/v1`. Keep `/api` as an alias that points at v1 until v2 exists.
- Verbs: GET/POST/PUT/PATCH/DELETE. Support `OPTIONS` in CORS middleware (preflight).
- Auth: **cookies only** for browser SPAs (`access_token`, `refresh_token`, `HttpOnly`, `Secure` in production, `SameSite=Lax` or `None`+Secure if cross-site). Do not accept `Authorization: Bearer` for the default SPA mode (XSS would steal it). Optionally add a machine token later (`Authorization` on `/api/v1/m2m/*` only).
- Idempotency: `Idempotency-Key` header on POST payments/webhooks/create-once operations. Persist key + hash of body + response for 24h.
- Tracing: echo `X-Request-ID` (generate if missing). Accept `X-Correlation-ID`.

### Status codes

| Code | When |
|---|---|
| 200/201 | Success / created |
| 204 | Empty delete OK |
| 400 | Validation (`InvalidArgumentException`) |
| 401 | Missing/expired session |
| 403 | Authenticated but not allowed |
| 404 | Not found (same message for “not in this tenant”) |
| 409 | Conflict (unique, stale version) |
| 422 | Unprocessable (upload, semantic) |
| 429 | Rate limited (`Retry-After`) |
| 500 | Unexpected; log the real exception |
| 503 | DB/storage down (`ready` check) |

Never return 500 with a SQLSTATE or stack in production.

---

## 4. What to copy from oklass (with paths)

Rename namespaces and strip LMS identifiers (`institution` → `organization` where it is the tenant).

### 4.1 Kernel (mandatory)

| Piece | Copy from |
|---|---|
| Front controller + storage route pattern | `core/public/index.php` (keep `/storage/{path+}` with directory allow-list) |
| Bootstrap / env | `core/config/bootstrap.php` |
| PHP-DI | `core/config/services.php` — **only** kernel bindings |
| Application | `core/Framework/Core/Application.php` |
| Router + groups | `core/Framework/Core/Router.php` |
| Middleware registry | `core/Framework/Core/MiddlewareRegistry.php` |
| Middleware interface | `core/Framework/Core/MiddlewareInterface.php` |
| CORS | `core/Framework/Middleware/CorsMiddleware.php` |
| JSON body | `core/Framework/Middleware/JsonBodyMiddleware.php` |
| Security headers | `core/Framework/Middleware/SecurityHeadersMiddleware.php` |
| Rate limit | `core/Framework/Middleware/RateLimitMiddleware.php` + `RateLimiterInterface` + `FixedWindowRateLimiter` |
| Request context | `core/Framework/Middleware/RequestContextMiddleware.php` + `RequestContext` |
| JSON logger + sanitizer | `core/src/Infrastructure/Observability/*` |
| Health live/ready | `core/Framework/Controllers/HealthController.php` — replace InstitutionSettings check with `SELECT 1` + storage ping |

Global middleware order (do not reshuffle lightly):

1. `request_context`
2. `cors`
3. `security`
4. `json`
5. then route group: `auth` → `tenant` → `audit` → `role:…`

### 4.2 Auth (mandatory)

| Piece | Copy from |
|---|---|
| Cookie JWT access | `core/Framework/Middleware/AuthMiddleware.php` |
| Jwt + password | `core/src/Infrastructure/Services/JwtService.php`, `PasswordService.php` |
| Refresh tokens | `RefreshToken` entity + repo + logout/refresh use cases |
| User status gate | refuse suspended/archived in AuthMiddleware (Oklass does this) |
| Roles from DB | `auth_roles` from `$user->getRoleNames()`, **not** from JWT claims |

Login/register/me/logout/refresh as JSON. Port use cases from libok’s existing `LoginUserUseCase` / `RegisterUserUseCase` onto this cookie model.

**Password:** `password_hash` / `password_verify` (PASSWORD_DEFAULT). Never roll a cipher.  
**JWT secret:** ≥ 32 random bytes, required at boot (`dotenv->required`). Rotate by supporting `JWT_SECRET_PREVIOUS` for one TTL window.

### 4.3 Persistence (mandatory)

| Piece | Copy from |
|---|---|
| Doctrine migrations | `core/config/migrations_config.php`, `migrations/` style |
| Cross-engine SQL | `core/src/Infrastructure/Persistence/PlatformSql.php` |
| Lenient datetime types | `LenientDateTimeImmutableType`, `LenientDateTimeTzImmutableType` (MySQL `DATETIME(6)` vs Doctrine) |
| Repositories | Interface in Domain, Doctrine class in Infrastructure |

**Never** use `orm:schema-tool:update` in production. Schema changes = a migration or it did not happen.

UUID primary keys. `created_at` / `updated_at` as `DateTimeImmutable`. Add indexes for every FK and every `WHERE` you will filter in lists (`email`, `organization_id`, `status`, `created_at`).

### 4.4 Files (mandatory)

Copy `FileStorageService` + `ObjectStorageInterface` + local driver. Keep S3-compatible as a second driver behind the interface (`S3CompatibleStorage`).

Rules already proven in Oklass:

- Allow-list MIME by extension **and** `mime_content_type`.
- Reject `..` in paths.
- Serve public files only from named directories (`avatars`, `uploads`, …).
- `MAX_UPLOAD_MB` in env.
- Optional malware-scanner port (`MalwareScannerInterface`) — no-op implementation is fine.

### 4.5 Mail + queue (recommended before product #1 ships)

| Piece | Copy from |
|---|---|
| Mailer | `MailerService` — strip LMS templates; keep HTML wrapper + escape |
| Queue ports | `JobQueueInterface`, `JobHandlerInterface`, filesystem queue, `EmailJobHandler` |

Transactional emails must be **queued**, not sent inside the HTTP request, except a `MAIL_SYNC=true` dev flag.

### 4.6 Audit (recommended)

Copy `AuditLog` entity + `AuditLogService` + `AuditMiddleware`. Log actor, action, resource type/id, IP, user-agent, request id. Never log passwords or tokens (`ContextSanitizer` already redacts).

### 4.7 Optional tenancy (module)

Only if the next product is multi-tenant.

| Piece | Adapt from |
|---|---|
| Tenant | Institution → **Organization** |
| Membership | `InstitutionMembership` → `OrganizationMembership` + roles |
| Filter | `TenantFilter` on entities that have `organizationId` |
| Resolver | `TenantResolutionMiddleware` (host header / `X-Organization` / membership default) |
| Assignment | `TenantAssignmentSubscriber` so new rows cannot forget the tenant |

**Isolation tests are mandatory:** creating a row as org A must 404 as org B. Same message as unknown id.

Public vs system rows: if something is global, it has `organization_id NULL` and the filter must say so explicitly (Oklass does this for system certificate templates — reuse the idea, not the entity).

### 4.8 Authorization (recommended)

Copy the *idea* of `AuthorizationService` + `RoleMiddleware` (`role:admin,manager`). Start with a small static map:

- `super_admin` — platform
- `admin` / `manager` — tenant operators
- `member` — default
- `guest` — unauthenticated public routes only

Product-specific roles live in the product repo, not in libok.

---

## 5. World-class gaps (Oklass does not fully give you these — add them)

These are what make libok *better than* a grown LMS core reused as a kernel.

### 5.1 Clock, IDs, uniqueness

- `ClockInterface::now(): DateTimeImmutable`
- `UuidGeneratorInterface` wrapping Ramsey (fake in tests)
- Optimistic locking (`version` integer) on money, inventory, case status

### 5.2 Validation

- One place: use-case receives a DTO; invalid → `InvalidArgumentException` with a stable `code`.
- Do not validate only in the controller.
- String length limits on every text column (Oklass learned this the hard way with titles on PDFs).

### 5.3 Pagination & list APIs

- Default `per_page=20`, max `100`.
- Stable sort: `sort=created_at:desc` whitelist.
- No `SELECT *` over unbounded tables.

### 5.4 Idempotency & outbox

Oklass already sketched this in `core/docs/LMS_BASELINE.md`. Put a **minimal** version in libok:

- `idempotency_keys` table: key, tenant, request hash, status, response body, expires_at
- `outbox_events` table: type, payload, created_at, published_at, attempts  
  HTTP handlers write the outbox **in the same transaction** as the domain row. A worker publishes (mail, webhook). No “fire HTTP inside the controller.”

### 5.5 OpenAPI

- `docs/openapi-v1.yaml` generated or hand-maintained, **tested** (`json_decode` / Spectral in CI).
- Example: Oklass checks OpenAPI in CI — copy that discipline.

### 5.6 Feature flags

- `extra` JSON on Organization or a `feature_flags` table.
- Kernel ships `FlagBag::enabled(string $name): bool` from env `FLAGS=billing,webhooks`.

### 5.7 Soft delete policy

Pick one and document it:

- **Prefer** `status=archived` + unique email that allows reuse via partial unique indexes, **or**
- Soft `deleted_at` with `UNIQUE (email) WHERE deleted_at IS NULL` (Postgres).

Do not mix both.

### 5.8 Caching

- Port `CacheStoreInterface` + filesystem store.
- Cache **public** read models only. Authenticated GETs: `Cache-Control: private, no-store` by default (`json()` already does this in Oklass).
- Any cache key must include `organization_id`.

### 5.9 Webhooks (optional module)

- HMAC-SHA256(`timestamp.body`), 5-minute replay window (already specified in Oklass baseline).
- Persist deliveries; retry with backoff; do not block the request.

### 5.10 Internationalization of API messages

- Error `code` is stable English slug.
- `message` may be FR/EN from `Accept-Language` or org setting.
- Do not mix FR literals in the kernel the way Oklass controllers often do — kernel messages in English, product can translate.

### 5.11 Domain events (in-process)

- After successful use case: `EventDispatcher` (even a sync list of listeners).
- Listeners may enqueue jobs; they must not break the HTTP response if mail fails (log + outbox).

---

## 6. Security baseline (treat as a definition of done)

- [ ] `declare(strict_types=1);` everywhere
- [ ] HttpOnly cookies; no JWT in `localStorage`
- [ ] `CORS_ORIGIN` allow-list, never `*` with credentials
- [ ] `X-Content-Type-Options: nosniff`
- [ ] `X-Frame-Options: DENY` (API) / CSP on any HTML
- [ ] HSTS only when HTTPS
- [ ] Rate limit login and refresh (stricter than generic API)
- [ ] Upload: MIME allow-list, size cap, random stored names
- [ ] Path traversal blocked (`..`, NUL)
- [ ] Mass assignment: DTOs with explicit fields only
- [ ] CSRF: SameSite cookies + SPA same-site **or** double-submit CSRF if you add cookie-authenticated forms
- [ ] Secrets only in env; `ContextSanitizer` redacts `password`, `token`, `secret`, `authorization`
- [ ] User enumeration: same 401 message for bad email and bad password
- [ ] Timing: `password_verify` even when user is missing (hash a dummy)
- [ ] SQL only via Doctrine parameters
- [ ] Debug payload off when `APP_ENV=production`

Copy `SecurityHeadersMiddleware` as the default for API responses. HTML adapters get their own CSP (Oklass SPA `.htaccess` is a reference, not a copy-paste for an API).

---

## 7. Scalability practices (so “any project” does not mean “toy”)

- **Stateless app processes.** Session = JWT cookies + DB refresh rows. Horizontal scale = more PHP-FPM workers.
- **No PHP `$_SESSION`.**
- **Indexes** for tenant + status + created_at on every tenant-owned table.
- **Transactions** around “write aggregate + outbox + audit”.
- **N+1:** list endpoints use `join` / `addSelect` or a dedicated query. Add a test that counts queries if you can.
- **Queue** for mail, webhooks, PDF, thumbnails.
- **File storage** on object storage in production (local disk is dev).
- **DB:** one writer. Read replicas later; do not abstract them until you have a real read load.
- **Timeouts:** HTTP client (when you add one) 3–10s; never default infinite.
- **Limits:** list max 100; upload max from env; JSON body size cap in `JsonBodyMiddleware`.
- **Migrations** expand-contract (add column → deploy → backfill → drop old). No destructive change in the same release as the code that still reads the old column.

---

## 8. Testing & quality

Steal Oklass’s *attitude*, not every LMS test.

**Must have**

- PHPUnit 10, phpunit.xml with a separate test env (sqlite `:memory:` or postgres test db)
- PHPStan **level 6+** (Oklass is still low — do not copy that)
- CI: `composer test` + `composer phpstan` + `composer cs` (php-cs-fixer or pint-equivalent)
- Kernel test: `Application::handle()` returns JSON 404 envelope
- Auth tests: no cookie → 401; revoked role takes effect without waiting JWT TTL (roles from DB)
- Tenant tests: org A cannot read org B
- Security tests: error payload has no SQL; CORS rejects unknown origin
- Upload tests: `../` rejected; disallowed MIME rejected

**Controller tests** in Oklass (`*SecurityTest.php`) are the right shape: request in, status + JSON out, no browser.

**Composer scripts** to add:

```
test, phpstan, cs, cs-fix, migrate, migrate-test, serve, schema-validate
```

`orm:validate-schema --skip-sync` in CI like Oklass.

---

## 9. Configuration (12-factor)

`.env.example` required keys:

```
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=UTC
FRONTEND_URL=http://localhost:3000

DB_DRIVER=pdo_pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_NAME=libok
DB_USER=libok
DB_PASSWORD=

JWT_SECRET=
JWT_ACCESS_TTL=900
JWT_REFRESH_TTL=1209600

CORS_ORIGIN=http://localhost:3000
STORAGE_PATH=/abs/path/storage
PUBLIC_STORAGE_PATH=/abs/path/public/storage
MAX_UPLOAD_MB=20
LOG_DESTINATION=php://stderr

MAIL_HOST=
MAIL_PORT=587
MAIL_USER=
MAIL_PASS=
MAIL_FROM=
MAIL_SYNC=true

QUEUE_DRIVER=filesystem
CACHE_DRIVER=filesystem
FLAGS=
```

Boot **requires** secrets in production (`JWT_SECRET`, `DB_*`, `CORS_ORIGIN`). Mirror process env into `$_ENV` (Oklass bootstrap does this for CI — copy it).

---

## 10. Implementation phases (apply in order)

### Phase 1 — Kernel (do this first)

1. Add PHP-DI, `Framework/Core/*`, middleware listed in §4.1.
2. Replace schema-tool with migrations + `PlatformSql`.
3. `GET /api/v1/health/live` and `GET /api/v1/health/ready`.
4. JSON envelope + global exception handler from `Application`.
5. JsonLogger + request IDs.
6. CI green.

**Exit:** `curl` health, 404 JSON, PHPStan 6, tests pass on SQLite and (ideally) Postgres.

### Phase 2 — Auth API

1. Port cookie JWT + refresh + logout.
2. `POST /api/v1/auth/register|login|refresh|logout`, `GET /api/v1/me`.
3. AuthMiddleware on a `/api/v1/secure/ping` test route.
4. Rate-limit login.

**Exit:** register → login → me → logout; missing cookie 401; dummy hash on unknown email.

### Phase 3 — Hardening

1. Security headers, CORS allow-list, upload service, audit middleware on mutating routes.
2. Pagination helper + one sample resource (`Items` or keep `Users` as the sample).
3. OpenAPI file for auth + health + users.

**Exit:** security tests as in §8.

### Phase 4 — Tenancy (optional)

1. Organization + membership + TenantFilter + resolver.
2. Isolation tests.

**Exit:** two orgs, zero leaks.

### Phase 5 — Mail, queue, outbox

1. Ports + filesystem queue + SMTP.
2. Outbox table + worker CLI `bin/libok worker`.
3. Idempotency table for POSTs that create side effects.

**Exit:** register sends mail asynchronously; killing SMTP does not 500 the HTTP request.

### Phase 6 — Template UX

1. README: create-project, migrate, serve, test.
2. `docs/KERNEL.md` (short: what is core vs module).
3. Sample `Item` CRUD under `/api/v1/items` so a new repo is not empty.
4. Tag `v0.1.0`.

### Phase 7 — First product

New GitHub repo from that tag. Domain code stays in the product. Generic improvements **PR back to libok**.

---

## 11. Suggested first routes (v0.1)

```
GET  /api/v1/health/live
GET  /api/v1/health/ready
POST /api/v1/auth/register
POST /api/v1/auth/login
POST /api/v1/auth/refresh
POST /api/v1/auth/logout
GET  /api/v1/me
GET  /api/v1/users          # admin
GET  /api/v1/users/{id}
POST /api/v1/users
PATCH /api/v1/users/{id}
DELETE /api/v1/users/{id}
```

HTML login/register from current libok can stay under `/` as a demo client of the same use cases, or be deleted until you have a SPA.

---

## 12. Definition of “goat-level” (v1.0)

libok is goat-level when **all** of these are true:

1. A new engineer can clone, `composer install`, `cp .env.example .env`, `composer migrate`, `composer test`, `composer serve` and hit health + register in under 15 minutes.
2. Two tenants cannot see each other’s rows (if tenancy module is on).
3. Production logs are JSON on stderr, no secrets, with request ids.
4. Deploy is: migrate → switch traffic. No manual SQL.
5. Auth is cookie-based, roles live in DB, refresh works, logout revokes refresh.
6. Files cannot escape storage; uploads are typed and sized.
7. Mail/webhooks never depend on the HTTP request succeeding at the provider.
8. PHPStan ≥ 6, tests cover auth and tenant isolation, OpenAPI exists and CI checks it.
9. You can build a CRM *or* a stock app *without* deleting LMS ghosts — because there are none.

Until then it is a strong starter, not a kernel.

---

## 13. Working agreement while you apply this

- One PR per phase. Do not mix tenancy into the kernel PR.
- If a file from Oklass references Course, Certificate, Cohort, Enrollment, Assessment — stop and extract a sliver instead.
- Prefer copying a proven class and deleting lines over rewriting from memory.
- After v0.1, oklass remains the LMS. libok remains the seed. The next product is the proof.

When you clone libok, start at **Phase 1** and do not skip the envelope + health + migrations. Everything else hangs on that.
