# Changelog

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
