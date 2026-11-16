# Changelog

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
