# Changelog

## 0.1.0-rc.9

### Added
- Access check: `detailCode` and the new typed `values` (`SiteInsightsAccessCheckValues`: `rows`, `files`, `verdict`,
  `level`, `listedProperties`; only the code's fields set) on every line that words a found value, so a translated
  host words it itself: `propertyListed` → `propertyListed` `{ level }` or, when the property is not in the sites
  list, `propertyNotListed` `{ listedProperties }`; `permissionLevel` → `permissionLevel` `{ level }`;
  `searchAnalytics` → `rowsRead` `{ rows }`; `sitemaps` → `sitemapsListed` `{ files }`; `urlInspection` →
  `inspectionVerdict` `{ verdict }`. `detail` stays the English sentence; `budgetCountersShared` keeps its codes.
- npm: type `SiteInsightsAccessCheckValues`.

## 0.1.0-rc.8

### Changed
- Parts that are complete by construction — totals (no dimension) and a series asked for every day of its window —
  carry the new coverage flag `isCompleteByConstruction` true, with `limitReached` and `providerRowsExhausted` both
  false. This replaces rc.7's `providerRowsExhausted: true` on these parts. Other parts keep their flags.
- npm: `formatCoverageNotes()` emits no row note (`limitReached`, `providerRowsExhausted`) for a part complete by
  construction (also for an rc.7 answer that still carries `providerRowsExhausted: true` there).
- Access check: new nullable field `detailCode` for coded details. `budgetCountersShared` puts its code there
  (`shared`, `perPod`, `notCounted`, values unchanged), and its `detail` is a readable English sentence again.

## 0.1.0-rc.7

Coverage flags, translatable view helpers, and what a screen needs without host constants.

### Fixed
- `limitReached` is false (and `providerRowsExhausted` true) for questions that cannot have more rows: totals (no
  dimension) and a series asked for every day of its window — in the overview, the page and query details and the
  page groups. Top lists that hit their limit keep `limitReached` true.

### Changed
- Access check `budgetCountersShared`: state `warning` for `perPod` and `notCounted` (was `ok`), `ok` for `shared`; new
  state `warning` (works with a limit, see `detail`) and `SiteInsightsAccessChecks::hasWarnings()`. A warning does not
  fail `check-access`.
- `weakClickRateForPosition` note: a hit's baseline leaves the hit out, so it can be usable in a bucket that
  `positionBuckets` (whole buckets) marks `belowMinBaselineClickRate`.

### Added
- `maxInspectionUrls` (budgets.maxInspectionUrls) on each site of `insightsSites` and on `urlIndexInspections`.
- npm (additive): `formatCoverageNotes()` notes carry `values` next to `code` and `text`; `describeWindow()` returns the
  window as `{code, values, text}`; `formatDelta()` returns `value` and `unit` (`perDay`, `percentagePoints` or null).
  `text` stays the English default.

### Documentation
- Skill: a series filtered by one query or page can come with rows Google sent for days without impressions; they
  are reported as sent (`isFilledDay` false, `hasData` true, 0 impressions). The module fills only days without a row.

## 0.1.0-rc.6

Rule `weakClickRateForPosition` judges against a usable baseline and ranks by missing clicks.

### Changed
- A row is judged against its position bucket without itself (leave-one-out: (Σ clicks − its clicks) ÷
  (Σ impressions − its impressions)); a bucket with fewer than `minRowsPerBucket` other rows has no baseline for that
  row. `baseline.clickRate` and `baseline.rowCount` describe the other rows.
- New setting `minBaselineClickRate` (default 0.005 = 0.5 %, overridable per site): a baseline below it judges no row.
  `positionBuckets` say why a bucket has none (`noBaselineReason` `tooFewRows` or `belowMinBaselineClickRate`); when no
  bucket has a usable baseline, the kind is empty with `emptyReason` `baselineBelowMinClickRate` (not `thinBaseline`).
- Hits are ordered by missing clicks, impressions × (baseline − click rate), descending; each carries `missingClicks`.
  Before, they were ordered by impressions, so the zero-click rows that lowered a baseline led the list.

## 0.1.0-rc.5

Opportunities.

### Added
- `SearchOpportunitiesService::getOpportunities()`: fixed rules over the rows of one window that return candidates with
  their evidence rows, never diagnoses. Kinds `strikingDistanceQuery`, `weakClickRateForPosition`,
  `severalPagesForQuery`, `risingQuery`, `decliningQuery`, `risingPage`, `decliningPage`; one kind result each with
  `resultCount`, `emptyReason` (`noRows`, `belowThresholds`, `windowTooShort`, `noComparison`, `thinBaseline`,
  `sourceFailed`), `error`, `thresholds` (scaled to the window), `sourceCoverage`, `providerCalls`, `note`.
- `weakClickRateForPosition`: position buckets per device with an all-devices fallback for a thin device bucket (on by
  default; `baseline.baselineScope` `device` or `allDevices`); `positionBuckets` with the row count of every bucket
  (why a result is `thinBaseline`). Module defaults `minImpressions` 50 and `minRowsPerBucket` 5.
- Rising and declining: candidates from both periods (comparison `previousPeriod` unless `compare` asks another),
  counterparts asked by key, keys of one period only in `observedInOnePeriodOnly`.
- Thresholds per site: a site entry's `opportunities` setting overrides `opportunities.php` setting by setting
  (`SiteInsightsConfigService::getOpportunitiesConfig($siteKey)`, typed `SiteInsightsOpportunitiesConfig`); unknown
  settings fail loudly.
- Call budget `callsPerOperation.opportunities` (20) for all kinds together; sources are shared between kinds; a kind
  beyond it carries `error` `budgetRefused`.
- Endpoint `GET /sites/{siteKey}/search/opportunities` (`kind` optional) in `SiteInsightsSearchControllerTrait`;
  command `app:site-insights:opportunities <site> [--kind=]` (`--format=table|json|toon`).
- npm: `useSearchOpportunitiesQuery`, `searchOpportunities` in `buildSiteInsightsRequests`; the opportunity types.

### Fixed
- npm: the two POSTs that only read (`…/sites/{siteKey}/indexing/inspections`, `…/sites/{siteKey}/accessChecks`) invalidate
  no tag, in the factory and in the generated enhance step (before, the generated step invalidated the whole tag type,
  and every query on screen refetched after each call): consumers can drop their own invalidation override for these
  two paths.

## 0.1.0-rc.4

Indexing.

### Added
- `SearchIndexingService`: the sitemap files of a site (`getSitemapStatuses()`: type, index, pending, last submitted
  and downloaded, errors, warnings, submitted URLs per content type; cached `cache.sitemapsTtlSeconds`) and the
  inspection of a bounded list of URLs (`inspectUrls()`: 1 to `budgets.maxInspectionUrls`, duplicates once, URLs
  outside the property fail alone with `invalidRequest` without a call; the others from the cache or in one batch,
  reserved against the inspection share in one step, cached a day per URL). Inspecting needs
  `SiteInsightsAccessPolicyService::canInspect()` (new `requireInspector()`).
- Value objects `SitemapStatus(es)`, `SitemapContentCount(s)`, `UrlIndexInspection(s)`, `IndexingCoverage`
  (`requestedParts`, `partsFromCache`, `failedParts`, `providerCalls`, `inspectionsCounted`, `fromCache`, `fetchedAt`,
  `cacheLifetimeSeconds`); per-URL `error`; `inspectionCounterScope`, `duplicateUrlsDropped`.
- Endpoints `SiteInsightsIndexingControllerTrait`: `GET /sites/{siteKey}/indexing/sitemaps`,
  `POST /sites/{siteKey}/indexing/inspections` (body `urls`, `languageCode`). Anything that counts a URL inspection is
  never GET.
- Commands `app:site-insights:sitemaps <site>` and `app:site-insights:inspect <site> <url>…` (`--languageCode`,
  `--format=table|json|toon`; JSON = the endpoint's payload; exit 2 beyond `budgets.maxInspectionUrls`, exit 1 when a
  URL failed).
- Raw answers: sitemap files carry `contents` (`GoogleSitemapContent(s)`, submitted per type; Google's deprecated
  `indexed` is not read); URL inspections carry `crawledAs`, `sitemaps`, `referringUrls`; batch root
  `ArgusGoogleUrlInspections`. The schema version of both raw answers is 2: older cache entries are not read.
- `SearchConsoleSourceService::loadSitemapStatuses()` and `inspectUrls()`.
- npm: `useIndexingSitemapsQuery`, `useInspectUrlsMutation` (a mutation that invalidates nothing), `indexingSitemaps`
  and `inspectUrls` in `buildSiteInsightsRequests`; the indexing types.

- Export: a page whose call fails with a retryable cause (transport, provider, quota) is asked again up to
  `budgets.exportRetries` times (default 2) after `budgets.exportRetryBackoffMilliseconds` (1,000) × the retry's
  number; retries count against `callsPerOperation.export` (`retries` in the summary and per day). A part that still
  fails ends the run as before.
- `SearchPartError::$attempts` and `::$transportFailure` (`SearchTransportFailure`: `kind` `timeout` or
  `endedBeforeTimeout`, `elapsedMilliseconds`, `timeoutSeconds`), set by the export. The private transport hides the
  HTTP client's exception, so connection, DNS and stall errors are not told apart; the HTTP status stays
  `providerStatus` when Google answered.
- `app:site-insights:export --resumeAfter=<Y-m-d>` (`SearchExportRequest::$resumeAfter`,
  `SearchExportSummary::$resumedAfter`): continues after that day of the same window; a day outside the window is
  refused (exit 2).

### Documentation
- Skill and README: `--limit` is at most 1,000, longer lists are paged with `--offset`; how to continue an export that
  ended with an error.

### Fixed
- A single provider read with the cache (sitemaps) asked Google twice on a cache miss, the first time outside the
  call budget: the cache is now read like the batches read it, and only the counted call goes out.
  `loadSingleAnswer()` returns the provider calls it made.

## 0.1.0-rc.3

Search performance.

### Added
- `SearchPerformanceService`: overview (totals with comparison, series by day with filled and marked days, top
  queries, top pages, countries, devices — one batch of distinct questions), lists by query, page, country and device
  (provider order, or pages up to `sortFetchCeiling` sorted by the module) with the comparison window's counterpart per
  row (keys asked in RE2 chunks; `notObserved` instead of zero), page groups, the detail of one page and of one query,
  and the export (paged to its end, day by day, never cached, streamed). Every answer carries window, comparison window
  and coverage, or `error` per part.
- Endpoints `SiteInsightsSearchControllerTrait`: `GET /sites/{siteKey}/search/overview`, `…/queries`, `…/pages`,
  `…/countries`, `…/devices`, `…/pageGroups`, `…/pageDetail`, `…/queryDetail`.
- Commands `app:site-insights:overview`, `queries`, `pages`, `countries`, `devices`, `page-groups`, `page`, `query`,
  `export` (`--format=table|json|toon`; JSON = the endpoint's payload).
- Value objects `SearchListRequest`, `SearchExportRequest`, `SearchTotals`, `SearchPerformanceOverview`,
  `SearchPageDetail`, `SearchQueryDetail`, `SearchPageGroupPerformance(s)`, `SearchExportSummary`, `SearchExportDay(s)`;
  `SearchMetricsComparison::createFromMetrics()`, `previousDate`, `error`; `SearchRegex`.
- Budgets `overviewListSize` (25) and `detailListSize` (50).
- npm: `createSiteInsightsApi({ baseApi, apiPrefix })` with ten hooks, `buildSiteInsightsRequests`,
  `formatDelta`, `formatCoverageNotes`, `formatWindowNote`; the search types. Generated with `@mgamadeus/ddd-web`
  0.15.0.

### Changed (breaking)
- `SiteInsightsConfigService` returns value objects: `getSiteEntries(): SiteInsightsSiteEntries`,
  `getCredentialsConfig(): SiteInsightsCredentialsConfig`, `getCacheConfig(): SiteInsightsCacheConfig`,
  `getBudgetsConfig(): SiteInsightsBudgetsConfig`. `getCacheSettingInt()`, `getBudgetSetting()` and the `*_DEFAULTS`
  constants are gone; the defaults live on the value objects.
- The console account comes from `SiteInsightsAccessPolicyService::findAccountForCliOperations()` (default unchanged:
  the application's CLI account).

- Requires `mgamadeus/ddd ^3.33.3`: a framework `Date` keeps the calendar day it names in every PHP default zone, so
  the window parameters `from` / `to` of the search endpoints are `Date` (hydrated from `Y-m-d`).

## 0.1.0-rc.2

### Changed
- The access check is `POST /sites/{siteKey}/accessChecks` (was `GET`): each call asks the provider live and counts one
  URL inspection, so a client must not refetch it on its own. Generated type names follow
  (`PostAdminSiteInsightsSitesAccessChecksBySiteKey…`).

### Documentation
- Skill: an extension-point subclass must live in a public `…\Services\` resource of the application; which cache
  variables make the call counters `shared` across pods.

## 0.1.0-rc.1

First pre-release: module core.

### Added
- Module `SiteInsightsModule` and configuration `config/app/SiteInsights/` (`sites`, `credentials`, `cache`,
  `budgets`, `opportunities`).
- Sites from configuration, validated (`SiteInsightsSitesProviderService`, extension point), lookup and page groups
  (`InsightsSitesService`).
- Access policy (`SiteInsightsAccessPolicyService`, extension point; default: admin accounts).
- Service-account token exchange outside Argus (`GoogleServiceAccountTokensService`, JWT bearer grant, APCu or process
  cache) behind `SiteInsightsCredentialsProviderService` (extension point).
- The one Google request builder (`GoogleApiRequestsService`): external call, private transport, token in the
  `Authorization` header only.
- Raw Google answers with Argus repositories: Search Analytics report and batch root, sites list, site, sitemaps, URL
  inspection; error classification (`GoogleApiFailure`, `SiteInsightsErrorCause`).
- Neutral report classes with the coverage model: `SearchPerformanceRows` / `SearchPerformanceRow`, `SearchMetrics`,
  `SearchMetricsComparison`, `SearchWindow`, `SearchComparisonWindow`, `SearchRowsCoverage`, `SearchPartError`,
  `SearchDataAvailability`, questions and reports.
- `GoogleSearchConsoleSourceService`: distinct questions per batch in settle mode, cache reads with the module's own
  freshness check, joined cold computations, part-by-part retry of a batch Argus could not read, one retry after a
  401, availability probe.
- Window arithmetic (`SearchDays`, `SearchWindowsService`), call budgets (`SiteInsightsProviderBudgetsService`),
  access checks (`SiteInsightsAccessCheckService`).
- Admin endpoints `SiteInsightsSitesControllerTrait`; commands `app:site-insights:sites`,
  `app:site-insights:check-access` on `SiteInsightsCommand`.
- npm package `@mgamadeus/ddd-site-insights`: generated types and window logic.
