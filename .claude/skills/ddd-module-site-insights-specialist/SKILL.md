---
name: ddd-module-site-insights-specialist
description: "Read Google Search Console data with ddd-site-insights: live reads through ddd-argus, cached, coverage in every answer. Covers install, config/app/SiteInsights (sites, credentials entry with env variable and revision, page groups, cache, call budgets, typed getters), search performance (overview, lists by query, page, country, device with comparison counterparts, page groups, page and query detail, export), admin endpoints (Sites and Search controller traits), agent commands app:site-insights:sites, check-access, overview, queries, pages, countries, devices, page-groups, page, query, export (--format=json or toon, exit codes), the npm factory createSiteInsightsApi, extension points, reading rules (Pacific Time days, final vs all, partial comparisons, notObserved, sortScope, limitReached, anonymised queries, byPage vs byProperty). Use when wiring site insights into an app, reading search data as an agent, comparing periods, or a site answers keyUnreadable, tokenRefused or notUserOfProperty."
metadata:
  author: mgamadeus
  version: "0.1.0-rc.3"
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

The module registers itself (`extra.ddd-module`). Requires `mgamadeus/ddd ^3.33.3`, `mgamadeus/ddd-argus ^1.5.1`.
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
`schemaVersion`), `budgets.php` (share of Google's quotas, `rowsPerCall` 2,500, `maxRowsPerCall` 10,000,
`sortFetchCeiling` 5,000, timeouts, retention, comparison limits, `counterpart` limits (`keysPerChunk` 100,
`maxChunks` 5, `regexMaxLength` 4,000), `maxPageGroups` 20, `overviewListSize` 25, `detailListSize` 50,
`callsPerOperation`), `opportunities.php` (thresholds of the rules, used from a later version).

Code reads configuration only through `SiteInsightsConfigService`, which returns value objects:
`getSiteEntries(): SiteInsightsSiteEntries`, `getCredentialsConfig(): SiteInsightsCredentialsConfig` (entries by name,
`findEntryByName()`), `getCacheConfig(): SiteInsightsCacheConfig`, `getBudgetsConfig(): SiteInsightsBudgetsConfig`
(`getCallsAllowedForOperation()`). A setting a file leaves out keeps the value object's default.

Invalid site entries fail loudly (`InternalErrorException` naming site and problem).

## Console for agents

All commands run as the account `SiteInsightsAccessPolicyService::findAccountForCliOperations()` names (default:
`CLI_DEFAULT_ACCOUNT_ID_FOR_CLI_OPERATIONS`) and ask the access policy; the account must pass it. `--format=json` prints
exactly the payload of the matching endpoint under the same property name (`--pretty` indents); `toon` puts the row
sets into TOON and keeps window and coverage as JSON. Diagnostics go to stderr. Exit codes: 0 success, 1 operational
failure (forbidden, provider, budget, configuration, or a part of the answer carries `error` — the payload is printed
all the same), 2 invalid input (bad option, unknown site).

Window options (every search command): `--preset=7d|28d|90d|6m|12m|16m` (default 28d, ending on the last final day),
`--from=Y-m-d --to=Y-m-d` (provider days, instead of the preset), `--since=<days>` (with `--to`),
`--compare=none|previousPeriod|previousYear`, `--searchType=web|image|video|news|discover|googleNews`,
`--dataState=final|all`.
Filter options (lists): `--queryContains`, `--queryEquals`, `--pageContains`, `--pageEquals`, `--pageGroup=<key>`,
`--country=<alpha-3 lower case>`, `--device=desktop|mobile|tablet`.
List options: `--limit=1..1000` (50), `--offset=0..24999` (0), `--sortBy=clicks|impressions|ctr|position`,
`--sortDirection=desc|asc` (default desc, asc for position).

| Command | Options | JSON property |
|---|---|---|
| `app:site-insights:sites` | format | `insightsSites` |
| `app:site-insights:check-access [site]` | format; asks Google live, counts one URL inspection; exit 1 when a capability failed | `siteInsightsAccessChecks` |
| `app:site-insights:overview <site>` | window, format | `searchPerformanceOverview` |
| `app:site-insights:queries <site>` · `pages` · `countries` · `devices` | window, filter, list, format | `searchPerformanceRows` |
| `app:site-insights:page-groups <site>` | window, format | `searchPageGroupPerformances` |
| `app:site-insights:page <site> <url>` | window, format | `searchPageDetail` |
| `app:site-insights:query <site> "<query>"` | window, format | `searchQueryDetail` |
| `app:site-insights:export <site>` | window, `--dimensions=query,page` (subset of query, page, country, device, date), `--byDay` / `--no-byDay` (default on for query and page together), `--output=<file>` | NDJSON rows (stdout or the file), then one line `{"searchExportSummary": …}`; exit 1 when it stopped before its end (`limitReached` or `error`) |

`check-access` prints states only: per site `keyReadable`, `tokenObtained`, `propertyListed`, `permissionLevel`,
`searchAnalytics`, `sitemaps`, `urlInspection`, `budgetCountersShared` — each `ok`, `failed` with `cause`, or `skipped`.
`export` is console only (no endpoint), never cached, needs `canExport()`, and stops at
`budgets.callsPerOperation.export` calls with `limitReached` and `lastFinishedDay`.

## Admin endpoints

Mount each trait in a controller of the application (its route prefix, tag, firewall rule):

```php
#[Route('/siteInsights')]
#[Tag(group: 'Website', name: 'SiteInsights', description: 'Search Console insights')]
class AdminSiteInsightsSearchController extends AdminController
{
    use SiteInsightsSearchControllerTrait;
}
```

| Method and path (relative to the prefix) | Trait | Parameters | Response property |
|---|---|---|---|
| `GET /sites` | Sites | — | `insightsSites` |
| `POST /sites/{siteKey}/accessChecks` | Sites | — (counts one URL inspection against the daily quota: POST, never refetched on its own) | `siteInsightsAccessChecks` |
| `GET /sites/{siteKey}/search/overview` | Search | window | `searchPerformanceOverview` |
| `GET /sites/{siteKey}/search/queries` · `/pages` · `/countries` · `/devices` | Search | window, filters, list | `searchPerformanceRows` |
| `GET /sites/{siteKey}/search/pageGroups` | Search | window | `searchPageGroupPerformances` |
| `GET /sites/{siteKey}/search/pageDetail` | Search | window, `url` (required) | `searchPageDetail` |
| `GET /sites/{siteKey}/search/queryDetail` | Search | window, `query` (required, exact) | `searchQueryDetail` |

Query parameters: window `preset`, `from`, `to` (`Y-m-d` strings), `compare`, `searchType`, `dataState`; filters
`queryContains`, `queryEquals`, `pageContains`, `pageEquals`, `pageGroup`, `country`, `device`; list `limit`,
`offset`, `sortBy`, `sortDirection` — the same values as the console options. The search reads are GET: they count
only against the module's per-minute Search Analytics budget, are cached, and consume no daily quota. Every action asks
the access policy first; the service validates every value again (400).

Request graph: the overview asks totals, series, top queries (25), top pages (25), countries (250), devices (5) and,
with a comparison, totals and series of the comparison window — distinct questions in **one** batch after the cached
availability probe. A list sorted by clicks descending is one call from `offset`; any other sort pages
`rowsPerCall` up to `sortFetchCeiling` and sorts those rows. Counterparts: one batch of at most `counterpart.maxChunks`
calls (`includingRegex ^(?:k1|…)$` per chunk; countries and devices: one call). Page groups: two calls per group.
Details: five calls. Budgets: `callsPerOperation` (overview 10, list 8, pageGroups 41, details 7, export 1,500).

npm: `createSiteInsightsApi({ baseApi, apiPrefix: '/api/admin/siteInsights' })` of `@mgamadeus/ddd-site-insights`
injects these operations into the application's RTK Query api once (tag `SiteInsights`) and returns the hooks
`useSitesQuery`, `useCheckAccessMutation`, `useSearchOverviewQuery`, `useSearchQueriesQuery`, `useSearchPagesQuery`,
`useSearchCountriesQuery`, `useSearchDevicesQuery`, `useSearchPageGroupsQuery`, `useSearchPageDetailQuery`,
`useSearchQueryDetailQuery`. Keep the module's tag out of the application's own generated SDK (`filterEndpoints`).
View logic of the same entry: `formatDelta()`, `formatCoverageNotes()`, `formatWindowNote()`, `windowPresets`.

## Extension points (register a subclass under the module's class id)

The module fetches each extension point by its class id from the container. The application's subclass must therefore
live in a namespace the application registers as a public `…\Services\` resource (or be declared `public: true`);
otherwise the fetch fails at run time with "service or alias has been removed or inlined".

| Service | Default |
|---|---|
| `SiteInsightsAccessPolicyService` | `canView()` = admin role; `canInspect()`, `canExport()` = `canView()`; `findAccountForCliOperations()` = the application's CLI account |
| `SiteInsightsSitesProviderService` | sites from `sites.php`, validated |
| `SiteInsightsCredentialsProviderService` | token from `GoogleServiceAccountTokensService` |

## Reading rules (what an answer means)

Every answer carries `window` (and `comparisonWindow` when a comparison was asked); every row list and totals part
carries `coverage` or `error`. Read these before a number:

- **Days**: `window.from` / `to` are inclusive days in `window.dayTimeZone` (`America/Los_Angeles`), never converted.
- **Data state**: `final` ends on `window.dataThroughDate`, the last final day (`dataThroughDateSource` `probe`: read
  from a probe over the last ten days; `fallback`: assumed today − 3); `all` reaches today and may still change.
- **Retention**: Google keeps about 16 months; a window is raised to the earliest day (`windowClamped`).
- **Comparison**: `previousPeriod` (same length before) or `previousYear` (364 days back, weekdays aligned). Refused
  with `comparisonUnavailableReason` (`outsideRetention`, `tooShortInsideRetention`), or shortened
  (`comparisonIsPartial`: clicks and impressions compared per day, `perDay` true).
- **Counterparts** (`SearchMetricsComparison.counterpartState`): `observed` (deltas set), `notObserved` (Google
  reported no row for the key in the comparison window: zero, below its threshold or anonymised — never a zero),
  `notRequested` (limits, or no comparison), `failed` (`error`). Series rows compare day i with day i of the comparison
  window (`previousDate`).
- **Totals** are their own question without dimensions, never a sum of rows; click rate = clicks ÷ impressions of
  the aggregate.
- **Rows**: Google returns top rows only. `limitReached`: the module stopped while the last page was full (more may
  exist); `providerRowsExhausted`: fewer rows than asked — not "all rows". `sortScope` `fetchedRows`: sorted within the
  `fetchedRows` fetched, not over all rows. `duplicateRowsDropped`, `counterpartChunks`, `counterpartsNotRequested`.
- **Anonymised queries**: rows grouped or filtered by query omit them (`anonymizedQueriesOmitted`): they add up to
  less than the totals. Query × page loses rows (`joinedDimensionsMayLoseRows`).
- **Aggregation**: `byProperty` vs `byPage` (`coverage.responseAggregation`, `responseAggregationKnown`). Page lists,
  page filters and page groups count per page — higher than the property totals. Page groups may overlap; never sum
  them.
- **Series**: every day of the window once; a day without a row is filled with zeros, `hasData` false and
  `isFilledDay` true.
- **Freshness**: `coverage.fromCache`, `coverage.fetchedAt` (when Google answered), `providerCalls`.

## Error causes (`SiteInsightsErrorCause`)

`keyUnreadable` (entry, variable, base64, JSON, type, fields, private key — the failed check is named),
`tokenMissing`, `tokenRefused` (Google's `error` and description, or a second 401), `tokenEndpointFailed`,
`notUserOfProperty` (403 permission), `apiNotEnabled` (enable the Search Console API in the account's project),
`quotaExceeded` (retryable), `budgetRefused` (the module's own budget, 429), `invalidRequest` (400),
`providerFailed` (5xx, retryable), `transport` (rejected call), `answerUnreadable` (answer too large, stalled or
incomplete: the batch is asked again part by part), `outsideRetention`.

## Call counters: shared or per pod

`check-access` reports `budgetCountersShared` as `shared` (Redis) or `perPod` (APCu). The counters use Core's cache
group `REDIS_SENTINEL`: they are shared across pods only when the application configures that group, i.e.
`CACHE_REDIS_SENTINEL_CACHE_GROUP=redisSentinel` (with `CACHE_REDIS_SENTINEL_SENTINELS`, `…_PASSWORD`, `…_NAMESPACE`)
or `CACHE_REDIS_SENTINEL_CACHE_GROUP=redis` (with `CACHE_REDIS_SENTINEL_HOST`, `…_PORT`, `…_PASSWORD`, `…_DATABASE`).
Without it Core falls back to APCu: every pod counts on its own, so N pods may use up to N times a quota. Configure it
before production; Argus's `CACHELEVEL_DB` entries use the same group.

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
