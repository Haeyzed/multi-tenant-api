<?php

use App\Http\Middleware\IncreaseDocsTimeLimit;
use App\Scramble\Extensions\ApiResponseTypeInfer;
use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;
use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;

return [
    /*
     * Document Central landlord routes under /api/v1.
     */
    'api_path' => 'api/v1',

    'api_domain' => null,

    'export_path' => 'docs/openapi.json',

    'cache' => [
        'key' => 'scramble.openapi',
        'store' => 'file',
    ],

    'info' => [
        'version' => env('API_VERSION', '1.0.0'),
        'description' => <<<'MD'
# Central (Landlord) API

REST API for the multi-tenant SaaS commerce **central** application.

## Base URL

All documented endpoints are relative to `/api/v1`.

## Authentication

- Use **Laravel Sanctum** bearer tokens (`Authorization: Bearer {token}`).
- Obtain a token via `POST /auth/login`.
- Public auth routes (login, password reset, email verification) do not require a token.

## Response envelope

Every JSON response uses:

```json
{
  "status": true,
  "message": "Human readable summary",
  "data": {},
  "meta": {},
  "errors": null
}
```

- Validation failures return **422** with field errors in `errors`.
- Authorization failures return **403**.
- Unauthenticated requests return **401**.

## Authorization

Endpoints are gated by Spatie permissions (for example `tenants.view`, `billing.invoices.manage`).  
Central roles are seeded via `RbacSeeder` (`super-admin`, `operator`).

## Interactive docs

- Central: `/docs/central`
- Tenant: `/docs/tenant`
MD,
    ],

    'ui' => [
        'title' => 'Central Landlord API',
    ],

    'renderer' => 'elements',

    'renderers' => [
        'elements' => [
            'view' => 'scramble::docs',
            'theme' => 'light',
            'hideTryIt' => false,
            'hideSchemas' => false,
            'logo' => '',
            'tryItCredentialsPolicy' => 'include',
            'layout' => 'responsive',
            'router' => 'hash',
        ],
        'scalar' => [
            'view' => 'scramble::scalar',
            'cdn' => 'https://cdn.jsdelivr.net/npm/@scalar/api-reference',
            'theme' => 'laravel',
            'proxyUrl' => 'https://proxy.scalar.com',
            'darkMode' => false,
            'showDeveloperTools' => 'never',
            'agent' => ['disabled' => true],
            'credentials' => 'include',
        ],
    ],

    'servers' => null,

    'enum_cases_description_strategy' => 'description',

    'enum_cases_names_strategy' => false,

    'flatten_deep_query_parameters' => true,

    'middleware' => [
        'web',
        IncreaseDocsTimeLimit::class,
        RestrictedDocsAccess::class,
    ],

    'extensions' => [
        ApiResponseTypeInfer::class,
    ],

    'security_strategy' => MiddlewareAuthSecurityStrategy::class,
];
