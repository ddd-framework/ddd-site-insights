---
name: ddd-module-site-insights-specialist
description: "Read Google Search Console data for configured sites with ddd-site-insights: live reads through ddd-argus, cached, nothing stored. Covers install, config/app/SiteInsights (sites, property, credentials entries with an env variable holding the base64 service-account key and a revision, page groups, brand patterns, cache lifetimes, call budgets), the console for agents (app:site-insights:sites, app:site-insights:check-access, --format=json or toon, exit codes), admin endpoints (SiteInsightsSitesControllerTrait), extension points (access policy, sites and credentials providers), reading rules (Pacific Time days, final vs all, retention, comparisons, aggregation, anonymised queries), error causes, the token exchange outside Argus and APCu on the CLI. Use when wiring site insights into an app, configuring a Search Console property or key, a site answers keyUnreadable, tokenRefused, notUserOfProperty or apiNotEnabled, reading search data as an agent, or a console run exchanges a token every time."
metadata:
  author: mgamadeus
  version: "0.1.0-rc.1"
  module: mgamadeus/ddd-site-insights
---

# Site Insights Module Specialist

Google Search Console in the admin and for agents: a live reader with honest coverage. It reads through
`mgamadeus/ddd-argus`, caches the answers, stores nothing, and states in every answer what it covers.

> **Base patterns:** Core skills (entity, service, endpoint, CLI command) and `ddd-module-argus-specialist`.

## Critical rules

- **Secrets never pass a recorded or logged transport.** The token exchange runs outside Argus (own Guzzle client);
  every Google call is an Argus `EXTERNAL_CALL` on the `PRIVATE_TRANSPORT`, built only by
  `GoogleApiRequestsService::buildPayload()`, with the token in the `Authorization` header only. Without a token the
  builder throws `tokenMissing` and nothing is sent. Never add another way to call Google.
- **Never print environment variables, key material or tokens.** `check-access` prints states only.
- **Access first.** Every endpoint and command asks `SiteInsightsAccessPolicyService` before anything is read; the
  cache key holds no account.
- **Dates.** Provider days are `Y-m-d` in Pacific Time; arithmetic only through `SearchDays` (UTC
  `DateTimeImmutable`); a framework `Date` is built with `SearchDays::toDate()` and never modified.
- **Failures are never cached**; the module's own freshness check (`fetchedAt` against the question's lifetime)
  applies to every cache hit.

## Install

```bash
composer require mgamadeus/ddd-site-insights
```

The module registers itself (`extra.ddd-module`). Requires `mgamadeus/ddd ^3.33.2`, `mgamadeus/ddd-argus ^1.5.1`.
Link this skill into the application's `.claude/skills/` like the other module skills.

## Configuration (`config/app/SiteInsights/`, an application file replaces the module file as a whole)

`sites.php`:

```php
return ['sites' => [[
    'key' => 'example-site',                    // ^[a-z0-9][a-z0-9-]{0,62}$
    'label' => 'Example site',
    'provider' => 'google-search-console',
    'property' => 'https://example.test/',      // or 'sc-domain:example.test'
    'credentials' => 'searchReader',            // entry of credentials.php
    'brandQueryPatterns' => ['example'],        // RE2: no lookarounds, no backreferences
    'pageGroups' => [['key' => 'guides', 'label' => 'Guides', 'pathPatterns' => ['^/guides/']]],
]]];
```

`credentials.php`: `'entries' => ['searchReader' => ['envName' => 'SITE_INSIGHTS_SEARCH_READER_KEY_B64', 'revision' => 1]]`.
The variable holds the JSON key file of a service account, base64 in one line. Give the account restricted
(read-only) access to the property; scope `webmasters.readonly` (a scope not ending in `.readonly` is refused).
Raise `revision` when the account behind the entry changes: older cache entries become unreachable.

`cache.php` (lifetimes: final windows 1 day, fresh windows 30 min, probe and sitemaps 1 h, inspections 1 day;
`schemaVersion`), `budgets.php` (share of Google's quotas, rows per call, response size, timeouts, retention,
comparison limits, calls per operation), `opportunities.php` (thresholds of the rules, used from a later version).

Invalid site entries fail loudly (`InternalErrorException` naming site and problem).

## Console for agents

All commands run as the application's CLI account and ask the access policy (the account must pass it).

| Command | Payload (`--format=json`, `--pretty`; `toon` puts row sets into TOON) | Exit |
|---|---|---|
| `app:site-insights:sites` | `insightsSites` | 0; 1 forbidden/config |
| `app:site-insights:check-access [site]` | `siteInsightsAccessChecks`: per site `keyReadable`, `tokenObtained`, `propertyListed`, `permissionLevel`, `searchAnalytics`, `sitemaps`, `urlInspection`, `budgetCountersShared` — each `ok`, `failed` with `cause`, or `skipped` | 1 when one failed; 2 invalid input |

`check-access` asks Google live (no cache) and counts one URL inspection. Diagnostics go to stderr; stdout is the
payload. JSON equals the payload of the matching admin endpoint.

## Admin endpoints

```php
#[Route('/siteInsights')]
#[Tag(group: 'Website', name: 'SiteInsights', description: 'Search Console insights')]
class AdminSiteInsightsSitesController extends AdminController
{
    use SiteInsightsSitesControllerTrait;   // GET /sites, GET /sites/{siteKey}/accessChecks
}
```

## Extension points (register a subclass under the module's class id)

| Service | Default |
|---|---|
| `SiteInsightsAccessPolicyService` | `canView()` = admin role; `canInspect()`, `canExport()` = `canView()` |
| `SiteInsightsSitesProviderService` | sites from `sites.php`, validated |
| `SiteInsightsCredentialsProviderService` | token from `GoogleServiceAccountTokensService` |

## Reading rules (what an answer means)

- `dataState final` ends on the last final day (read with a probe over the last ten days, else today − 3,
  `dataThroughDateSource fallback`); `all` reaches today and may still change.
- Google keeps about 16 months: a window is raised to the earliest day (`windowClamped`); a comparison outside it is
  refused (`outsideRetention`) or shortened (`comparisonIsPartial`, per-day deltas) or refused as too short.
- Totals are their own call, never a sum of rows. Page aggregation (`byPage`) counts higher than property totals.
- Rows grouped or filtered by query omit anonymised queries (`anonymizedQueriesOmitted`); query × page loses rows
  (`joinedDimensionsMayLoseRows`). Google returns top rows only: `providerRowsExhausted` means "fewer than asked",
  not "all rows".

## Error causes (`SiteInsightsErrorCause`)

`keyUnreadable` (entry, variable, base64, JSON, type, fields, private key — the failed check is named),
`tokenMissing`, `tokenRefused` (Google's `error` and description, or a second 401), `tokenEndpointFailed`,
`notUserOfProperty` (403 permission), `apiNotEnabled` (enable the Search Console API in the account's project),
`quotaExceeded` (retryable), `budgetRefused` (the module's own budget, 429), `invalidRequest` (400),
`providerFailed` (5xx, retryable), `transport` (rejected call), `answerUnreadable` (answer too large, stalled or
incomplete: the batch is asked again part by part), `outsideRetention`.

## Token cache and APCu on the console

The token is cached in the APC group only when `apcu_enabled()` is true (per pod, lifetime `expires_in` − 300 s);
never in Redis or an entity. On the CLI without `apc.enable_cli=1` APCu is off: the token lives in the process only,
so every console run exchanges once — harmless, and never an error. Budget counters use the Redis Sentinel group
(`shared`), else APCu (`perPod`), else are not counted (`notCounted`); `check-access` reports which.

## Troubleshooting

| Symptom | Check |
|---|---|
| `keyUnreadable: its environment variable is not set` | the variable reaches the PHP process (FPM pool, workers, console) |
| `tokenRefused (invalid_grant …)` | deleted or rotated key, clock skew; raise `revision` after replacing the key |
| `propertyListed failed` | the property string must match Search Console exactly (trailing slash, `sc-domain:`) |
| `apiNotEnabled` | enable the Search Console API in the service account's project |
| commands answer 403 | the CLI account does not pass the access policy |
