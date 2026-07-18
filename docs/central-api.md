# Central Landlord API

Consumer guide for the **Central (Landlord)** REST API of this multi-tenant SaaS commerce platform.

Interactive OpenAPI UI:
- Central: [`/docs/central`](/docs/central) ([`/docs/central.json`](/docs/central.json))
- Tenant: [`/docs/tenant`](/docs/tenant) ([`/docs/tenant.json`](/docs/tenant.json))

Exported OpenAPI documents:
- Central: [`docs/openapi.json`](./openapi.json) — `php artisan scramble:export`
- Tenant: [`docs/openapi-tenant.json`](./openapi-tenant.json) — `php artisan scramble:export --api=tenant`

## Stack

| Layer | Choice |
| --- | --- |
| Framework | Laravel 13 / PHP 8.3 |
| Auth | Sanctum bearer tokens + optional TOTP 2FA |
| Tenancy | Stancl (database-per-tenant); this API is **central only** |
| RBAC | Spatie Permission |
| Audit | Spatie Activity Log |
| Docs | Scramble (OpenAPI) |

## Base URL & envelope

Base path: `/api/v1`

```json
{
  "status": true,
  "message": "...",
  "data": {},
  "meta": {},
  "errors": null
}
```

Paginated list endpoints put page info in `meta` (`current_page`, `per_page`, `total`, …).

## Auth quickstart

1. `POST /api/v1/auth/login` with `email` + `password`
2. If `data.requires_two_factor` is true, complete `POST /api/v1/auth/two-factor/confirm`
3. Send `Authorization: Bearer {token}` on subsequent requests
4. `POST /api/v1/auth/logout` to revoke the current token

## Public self-serve trial signup

Unauthenticated (no Sanctum token):

1. `GET /api/v1/public/plans` — active + publicly visible plans
2. `GET /api/v1/public/plans/options` — dropdown pairs `{ value: plan_id, label: "Pro — $29.00/mo" }`
3. `POST /api/v1/public/signup` — body: `name`, `email`, `password`, `password_confirmation`, `plan_id` (optional `slug`, `phone`, `domain`, `owner_name`)

Creates a tenant on **trial**, a **trialing** subscription (no invoice/charge), and an immediately active owner. Owner then logs in on the **tenant** domain (`POST /api/v1/auth/login` on that host).

### Trial reminders, end, and checkout

Scheduled daily: `php artisan billing:process-trials` (also registered on the Laravel scheduler).

| When | What happens |
| --- | --- |
| ~`BILLING_TRIAL_REMINDER_DAYS` (default 3) before `trial_ends_at` | Email `TrialEndingMail` with signed **Subscribe / Pay** link |
| `trial_ends_at` reached | Open invoice created, subscription → `past_due` + grace (`BILLING_TRIAL_GRACE_DAYS`), tenant → `grace_period`, email `TrialEndedMail` with the same link |

Signed checkout (from email):

- `GET /api/v1/public/billing/checkout/{subscription}?signature=…`  
  Creates/charges the conversion invoice and **302 redirects** to the gateway `checkout_url`.  
  Add `format=json` (or `Accept: application/json`) to receive `{ checkout_url, completed, payment_id, invoice_id }` instead.

When payment completes (via provider return URL or webhook), the subscription becomes `active` and the tenant `active`.

Central admin `POST /tenants` still uses the owner **invite** flow unchanged.

## Module map

| Area | Route prefix examples | Permission prefix |
| --- | --- | --- |
| Public signup / trial pay | `/public/plans`, `/public/plans/options`, `/public/signup`, `/public/billing/checkout/{subscription}` | _(none — public / signed)_ |
| Auth / profile / 2FA / sessions / PATs | `/auth/*`, `/profile`, `/sessions`, `/tokens` | `sessions.*`, `tokens.*` |
| RBAC | `/roles`, `/permissions` | `roles.*`, `permissions.*` |
| Users | `/users` | `users.*` |
| Tenants & domains | `/tenants`, `/tenants/{id}/domains` | `tenants.*`, `domains.*` |
| Features & plans | `/features`, `/plans`, `/feature-usages` | `features.*`, `plans.*` |
| Subscriptions & billing | `/subscriptions`, `/invoices`, `/payments` | `subscriptions.*`, `billing.*` |
| Dashboard | `/dashboard` | `dashboard.*` |
| Settings | `/settings` | `settings.*` |
| Audit | `/audit-logs` | `audit.*` |
| Notifications & announcements | `/notifications`, `/announcements` | `notifications.*`, `announcements.*` |
| Support | `/tickets`, `/ticket-categories` | `support.*` |
| Monitoring | `/monitoring` | `monitoring.*` |
| API clients & webhooks | `/api-clients`, `/webhooks` | `api.*` |
| AI providers | `/ai-providers` | `ai.*` |
| Integrations | `/integrations`, `/installed-integrations` | `integrations.*` |
| Themes | `/themes`, `/theme-installations` | `themes.*` |
| Backups | `/backups`, `/backup-schedules` | `backups.*` |
| Platform versions | `/platform-versions` | `versions.*` |

## Roles

Seeded by `Database\Seeders\Central\RbacSeeder` (also via `DemoSeeder` / `db:seed`):

- **super-admin** — all permissions from `PermissionCatalog`
- **operator** — day-to-day ops subset (tenants, billing ops, support, read monitoring, etc.)

## Demo seed

```bash
php artisan migrate --seed
# or
php artisan db:seed --class=Database\\Seeders\\Central\\DemoSeeder
```

| Account | Email | Password |
| --- | --- | --- |
| Super Admin | `admin@example.com` | `password` |
| Operator | `operator@example.com` | `password` |
| Support Agent | `support@example.com` | `password` |

Demo data includes plans/features, 4 tenants (domains, subscriptions, invoices, payments), notifications, announcements, tickets, API clients/webhooks, AI providers, integrations, themes, backups, and platform versions.

**Acme owner login** (after demo seed provisions the Acme tenant DB):

| Field | Value |
| --- | --- |
| Host | `acme.localhost` |
| Email | `acme@example.com` |
| Password | `password` |
| Login | `POST http://acme.localhost/api/v1/auth/login` |

Backfill / re-invite any tenant:

```bash
php artisan tenants:provision-owner {id|slug} --migrate
php artisan tenants:provision-owner acme --password=password --migrate
```

## Tenant owner onboarding

When Central creates a tenant **with an email**, the platform:

1. Sets status to `trial` and ensures a primary domain (`{slug}.{TENANT_BASE_DOMAIN}` if omitted)
2. Creates the tenant database and runs tenant migrations
3. Creates an invited owner user in the **tenant** DB
4. Emails `WelcomeTenantOwner` with a set-password link (`TENANT_SETUP_PASSWORD_URL`)

Tenant-domain API (host must be the tenant domain, not the central host):

| Method | Path | Purpose |
| --- | --- | --- |
| POST | `/api/v1/auth/setup-password` | Accept invite token + set password |
| POST | `/api/v1/auth/login` | Owner login → Sanctum token |
| GET | `/api/v1/auth/me` | Current user (Bearer) |
| POST | `/api/v1/auth/logout` | Revoke token |
| POST | `/api/v1/auth/impersonate` | Redeem Central impersonation token |

Central resend: `POST /api/v1/tenants/{tenant}/owner/resend-invite` (`tenants.update`).

Scramble’s UI covers both APIs (`/docs/central`, `/docs/tenant`). Exported OpenAPI files are separate (`docs/openapi.json`, `docs/openapi-tenant.json`).

## Method documentation (Scramble)

Controller actions use Scramble `#[Endpoint(operationId, title, description)]` so OpenAPI summaries stay explicit (not inferred from method names alone). After changing controllers:

```bash
php artisan scramble:export
php artisan scramble:export --api=tenant
```

## Conventions for frontend clients

- Prefer named resources under `data`; never assume a bare model payload at the root.
- Treat encrypted secrets (`client_secret`, webhook `secret`, SMTP password, AI `api_key`) as **write-only**; list/show responses mask or omit them.
- Soft-deleted models use restore routes where exposed (users, tenants, features, plans).
- Webhook dispatch/retry is simulated over HTTP with HMAC header `X-Webhook-Signature`.
- Backups write a JSON snapshot to the configured disk (local by default); restore marks lifecycle state (not a full DB restore engine).

## Local docs access

Scramble docs are behind `RestrictedDocsAccess`. In local development, open `/docs/central` or `/docs/tenant` (no auth required in `local`).

Regenerate the OpenAPI files after route changes:

```bash
php artisan scramble:export
php artisan scramble:export --api=tenant
```

## Testing

```bash
php artisan test --compact
```

Feature coverage lives under `tests/Feature/Central/`.
