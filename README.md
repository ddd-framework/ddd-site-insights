# mgamadeus/ddd-site-insights

Search Console insights for applications built with [mgamadeus/ddd](https://github.com/ddd-framework/ddd): what Google
knows about your websites, readable in the admin and by agents. The module reads Google Search Console live through
[`mgamadeus/ddd-argus`](https://github.com/ddd-framework/ddd-argus), caches the answers, stores nothing, and states in
every answer what it covers (which days, which data state, how many rows of how many, how aggregated).

Status: `0.1.0-rc.4` — module core (sites, credentials, token exchange, request builder, budgets, window arithmetic,
coverage model, access checks), search performance (overview, lists with comparison counterparts, page groups,
page and query detail, export) and indexing (sitemap states, URL inspection). Opportunities follow.

## Install

```bash
composer require mgamadeus/ddd-site-insights
```

The module registers itself through `extra.ddd-module`. Requirements: PHP 8.3+, `mgamadeus/ddd ^3.33.3`,
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

Console (runs as the application's CLI account, which must pass the access policy; `--format=json` prints the payload
of the matching endpoint):

```bash
php bin/console app:site-insights:sites --format=json
php bin/console app:site-insights:check-access example-site
php bin/console app:site-insights:overview example-site --compare=previousPeriod --format=json
php bin/console app:site-insights:queries example-site --preset=90d --sortBy=impressions --limit=100 --compare=previousYear
php bin/console app:site-insights:pages example-site --pageGroup=guides --format=toon
php bin/console app:site-insights:countries example-site --device=mobile
php bin/console app:site-insights:devices example-site --from=2026-10-01 --to=2026-10-31
php bin/console app:site-insights:page-groups example-site --compare=previousPeriod
php bin/console app:site-insights:page example-site https://example.test/guides/alpha/
php bin/console app:site-insights:query example-site "example widget"
php bin/console app:site-insights:export example-site --dimensions=query,page --output=rows.ndjson
# a run that ended with an error: same window, continue after its lastFinishedDay
php bin/console app:site-insights:export example-site --from=2025-07-20 --to=2026-11-13 --resumeAfter=2026-05-29 --output=rows-2.ndjson
php bin/console app:site-insights:sitemaps example-site
php bin/console app:site-insights:inspect example-site https://example.test/ https://example.test/guides/alpha/ --format=json
```

Admin API: mount `SiteInsightsSitesControllerTrait` (`GET /sites`, `POST /sites/{siteKey}/accessChecks`) and
`SiteInsightsSearchControllerTrait` (`GET /sites/{siteKey}/search/overview`, `…/queries`, `…/pages`, `…/countries`,
`…/devices`, `…/pageGroups`, `…/pageDetail?url=`, `…/queryDetail?query=`) and `SiteInsightsIndexingControllerTrait`
(`GET /sites/{siteKey}/indexing/sitemaps`, `POST /sites/{siteKey}/indexing/inspections` with the body
`{"urls": [...], "languageCode": "en-US"}`) in controllers of the application. Who may read and who may inspect is
decided by `SiteInsightsAccessPolicyService` (default: admin accounts); an application registers its own subclass under
that service id.

Lists return at most 1,000 rows per call (`--limit`, `limit`); page further with `--offset` / `offset`, or export.
The export asks a failed page again (`budgets.exportRetries`, default 2) before it ends; a run that ended with an
`error` continues with the same `--from` / `--to` and `--resumeAfter=<lastFinishedDay>` of its summary.

**Anything that counts a URL inspection is never GET.** Google allows 2,000 inspections per day and 600 per minute per
property; the module uses its configured share (`budgets.inspectionShare`, default half) and counts every inspection
it sends. Inspecting is therefore a POST (and a mutation in the npm package): no client, cache or prefetch repeats it
on its own. An inspection is cached a day per URL; a cached one counts nothing. A request names at most
`budgets.maxInspectionUrls` URLs (default 20; more are refused). The skill `.claude/skills/ddd-module-site-insights-specialist/SKILL.md` lists every
parameter and how to read the coverage of an answer.

## Security

- The service-account key is read from the environment only; the token exchange runs outside Argus; the token lives
  in the per-pod APCu memory (or the process) and travels only in the `Authorization` header.
- Every Google call is an Argus external call on the private transport: none of the application's headers, no
  tracking, no debug flags, not in the call log, redacted in displayed calls, no token in recordings or caches.
- Failures are never cached.

## Frontend

The npm package [`@mgamadeus/ddd-site-insights`](web/README.md) in `web/` carries `createSiteInsightsApi` (the
endpoints injected into the application's RTK Query api), the types generated from the module's API, and pure view
logic (windows, deltas, coverage wording); it is released with the same version.

## Development

```bash
composer install
vendor/bin/phpunit                                     # synthetic fixtures, no network
SITE_INSIGHTS_WRITE_SCHEMA=1 vendor/bin/phpunit tests/Schema   # after an API change; then `pnpm generate` in web/
```

## License

MIT
