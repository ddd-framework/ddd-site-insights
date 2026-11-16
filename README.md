# mgamadeus/ddd-site-insights

Search Console insights for applications built with [mgamadeus/ddd](https://github.com/ddd-framework/ddd): what Google
knows about your websites, readable in the admin and by agents. The module reads Google Search Console live through
[`mgamadeus/ddd-argus`](https://github.com/ddd-framework/ddd-argus), caches the answers, stores nothing, and states in
every answer what it covers (which days, which data state, how many rows of how many, how aggregated).

Status: `0.1.0-rc.1` — module core (sites, credentials, token exchange, request builder, budgets, window arithmetic,
the Search Analytics report with its coverage model, access checks). Search performance endpoints, indexing and
opportunities follow.

## Install

```bash
composer require mgamadeus/ddd-site-insights
```

The module registers itself through `extra.ddd-module`. Requirements: PHP 8.3+, `mgamadeus/ddd ^3.33.1`,
`mgamadeus/ddd-argus ^1.5.1`.

## Configure

Files under `config/app/SiteInsights/` of the application replace the module's files of the same name as a whole:

- `sites.php` — the sites: key, label, Search Console property (`https://example.test/` or `sc-domain:example.test`),
  credentials entry, brand query patterns and page groups (RE2 patterns).
- `credentials.php` — entries naming the environment variable that holds the service-account JSON key (base64, one
  line) and a revision; the read-only scope.
- `cache.php`, `budgets.php`, `opportunities.php` — lifetimes, call budgets and limits, rule thresholds.

```php
// config/app/SiteInsights/credentials.php
return [
    'entries' => ['searchReader' => ['envName' => 'SITE_INSIGHTS_SEARCH_READER_KEY_B64', 'revision' => 1]],
    'tokenEndpoint' => 'https://oauth2.googleapis.com/token',
    'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
    'assertionLifetimeSeconds' => 300,
    'tokenSafetyMarginSeconds' => 300,
    'httpTimeoutSeconds' => 10,
];
```

## Use

Console (runs as the application's CLI account, which must pass the access policy):

```bash
php bin/console app:site-insights:sites --format=json
php bin/console app:site-insights:check-access example-site
```

Admin API: mount `SiteInsightsSitesControllerTrait` in a controller of the application (`GET /sites`,
`GET /sites/{siteKey}/accessChecks`). Who may read is decided by `SiteInsightsAccessPolicyService` (default: admin
accounts); an application registers its own subclass under that service id.

## Security

- The service-account key is read from the environment only; the token exchange runs outside Argus; the token lives
  in the per-pod APCu memory (or the process) and travels only in the `Authorization` header.
- Every Google call is an Argus external call on the private transport: none of the application's headers, no
  tracking, no debug flags, not in the call log, redacted in displayed calls, no token in recordings or caches.
- Failures are never cached.

## Frontend

The npm package [`@mgamadeus/ddd-site-insights`](web/README.md) in `web/` carries the types generated from the module's
API and the window logic; it is released with the same version.

## Development

```bash
composer install
vendor/bin/phpunit                                     # synthetic fixtures, no network
SITE_INSIGHTS_WRITE_SCHEMA=1 vendor/bin/phpunit tests/Schema   # after an API change; then `pnpm generate` in web/
```

## License

MIT
