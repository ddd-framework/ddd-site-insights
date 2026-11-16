/**
 * Public surface of the site insights frontend (published as `@mgamadeus/ddd-site-insights`). See README.md.
 *
 * Entry `.`: the API factory (injects the module's endpoints into the application's RTK Query api), the types
 * generated from the module's API, and pure view logic (windows, deltas, coverage wording). No React runtime, no
 * stylesheet: the pure functions are callable on a server. Screens follow in `./screens`.
 */
export {
  buildSiteInsightsRequests,
  type CreateSiteInsightsApiOptions,
  createSiteInsightsApi,
  rebaseSiteInsightsRequest,
  SITE_INSIGHTS_SCHEMA_API_PREFIX,
  SITE_INSIGHTS_TAG,
  type SiteInsightsApi,
  type SiteInsightsBaseApi,
  type SiteInsightsBaseQuery,
} from './api/createSiteInsightsApi';
export {
  type CounterpartState,
  DEFAULT_COUNTERPART_STATE_TEXTS,
  type DeltaUnit,
  type FormatDeltaOptions,
  type FormattedDelta,
  formatDelta,
  type SearchMetricName,
} from './logic/comparison';
export {
  type CoveredAnswerPart,
  type CoverageNote,
  type CoverageNoteCode,
  type CoverageNoteValues,
  describeWindow,
  formatCoverageNotes,
  formatWindowNote,
  type WindowNote,
} from './logic/coverageWording';
export {
  addDays,
  buildWindowLabel,
  computePresetWindow,
  daysInclusive,
  DEFAULT_WINDOW_PRESET,
  isValidDay,
  subtractMonthsClamped,
  type WindowDays,
  type WindowPreset,
  type WindowPresetKey,
  windowPresets,
} from './logic/windows';

// generated from schema/site-insights.openapi.json (do not edit the generated files; run `pnpm generate`)
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Access/SiteInsightsAccessCheck';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Access/SiteInsightsAccessCheckValues';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Access/SiteInsightsAccessChecks';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Indexing/IndexingCoverage';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Indexing/SitemapContentCount';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Indexing/SitemapContentCounts';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Indexing/SitemapStatus';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Indexing/SitemapStatuses';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Indexing/UrlIndexInspection';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Indexing/UrlIndexInspections';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Opportunities/SearchClickRateBaseline';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Opportunities/SearchOpportunities';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Opportunities/SearchOpportunity';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Opportunities/SearchOpportunityKindResult';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Opportunities/SearchOpportunityKindResults';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Opportunities/SearchOpportunityThresholds';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Opportunities/SearchPositionBucket';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Opportunities/SearchPositionBuckets';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Search/SearchComparisonWindow';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Search/SearchMetrics';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Search/SearchMetricsComparison';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Search/SearchPageDetail';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Search/SearchPageGroupPerformance';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Search/SearchPageGroupPerformances';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Search/SearchPartError';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Search/SearchPerformanceOverview';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Search/SearchPerformanceRow';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Search/SearchPerformanceRows';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Search/SearchQueryDetail';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Search/SearchRowsCoverage';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Search/SearchTotals';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Search/SearchTransportFailure';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Search/SearchWindow';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Sites/InsightsPageGroup';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Sites/InsightsPageGroups';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Sites/InsightsProviderCapabilities';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Sites/InsightsSite';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Sites/InsightsSites';
export type * from './generated/models/DDD/Presentation/Api/Admin/SiteInsights/Dtos/SiteInsightsAccessChecksGetResponseDto';
export type * from './generated/models/DDD/Presentation/Api/Admin/SiteInsights/Dtos/SiteInsightsIndexingSitemapsGetResponseDto';
export type * from './generated/models/DDD/Presentation/Api/Admin/SiteInsights/Dtos/SiteInsightsInspectionsPostRequestDto';
export type * from './generated/models/DDD/Presentation/Api/Admin/SiteInsights/Dtos/SiteInsightsInspectionsPostResponseDto';
export type * from './generated/models/DDD/Presentation/Api/Admin/SiteInsights/Dtos/SiteInsightsSearchOpportunitiesGetResponseDto';
export type * from './generated/models/DDD/Presentation/Api/Admin/SiteInsights/Dtos/SiteInsightsSearchOverviewGetResponseDto';
export type * from './generated/models/DDD/Presentation/Api/Admin/SiteInsights/Dtos/SiteInsightsSearchPageDetailGetResponseDto';
export type * from './generated/models/DDD/Presentation/Api/Admin/SiteInsights/Dtos/SiteInsightsSearchPageGroupsGetResponseDto';
export type * from './generated/models/DDD/Presentation/Api/Admin/SiteInsights/Dtos/SiteInsightsSearchQueryDetailGetResponseDto';
export type * from './generated/models/DDD/Presentation/Api/Admin/SiteInsights/Dtos/SiteInsightsSearchRowsGetResponseDto';
export type * from './generated/models/DDD/Presentation/Api/Admin/SiteInsights/Dtos/SiteInsightsSitesGetResponseDto';
export type * from './generated/models/_endpoints/GetAdminSiteInsightsSitesApiArg';
export type * from './generated/models/_endpoints/GetAdminSiteInsightsSitesApiResponse';
export type * from './generated/models/_endpoints/GetAdminSiteInsightsSitesIndexingSitemapsBySiteKeyApiArg';
export type * from './generated/models/_endpoints/GetAdminSiteInsightsSitesIndexingSitemapsBySiteKeyApiResponse';
export type * from './generated/models/_endpoints/GetAdminSiteInsightsSitesSearchCountriesBySiteKeyApiArg';
export type * from './generated/models/_endpoints/GetAdminSiteInsightsSitesSearchCountriesBySiteKeyApiResponse';
export type * from './generated/models/_endpoints/GetAdminSiteInsightsSitesSearchDevicesBySiteKeyApiArg';
export type * from './generated/models/_endpoints/GetAdminSiteInsightsSitesSearchDevicesBySiteKeyApiResponse';
export type * from './generated/models/_endpoints/GetAdminSiteInsightsSitesSearchOpportunitiesBySiteKeyApiArg';
export type * from './generated/models/_endpoints/GetAdminSiteInsightsSitesSearchOpportunitiesBySiteKeyApiResponse';
export type * from './generated/models/_endpoints/GetAdminSiteInsightsSitesSearchOverviewBySiteKeyApiArg';
export type * from './generated/models/_endpoints/GetAdminSiteInsightsSitesSearchOverviewBySiteKeyApiResponse';
export type * from './generated/models/_endpoints/GetAdminSiteInsightsSitesSearchPageDetailBySiteKeyApiArg';
export type * from './generated/models/_endpoints/GetAdminSiteInsightsSitesSearchPageDetailBySiteKeyApiResponse';
export type * from './generated/models/_endpoints/GetAdminSiteInsightsSitesSearchPageGroupsBySiteKeyApiArg';
export type * from './generated/models/_endpoints/GetAdminSiteInsightsSitesSearchPageGroupsBySiteKeyApiResponse';
export type * from './generated/models/_endpoints/GetAdminSiteInsightsSitesSearchPagesBySiteKeyApiArg';
export type * from './generated/models/_endpoints/GetAdminSiteInsightsSitesSearchPagesBySiteKeyApiResponse';
export type * from './generated/models/_endpoints/GetAdminSiteInsightsSitesSearchQueriesBySiteKeyApiArg';
export type * from './generated/models/_endpoints/GetAdminSiteInsightsSitesSearchQueriesBySiteKeyApiResponse';
export type * from './generated/models/_endpoints/GetAdminSiteInsightsSitesSearchQueryDetailBySiteKeyApiArg';
export type * from './generated/models/_endpoints/GetAdminSiteInsightsSitesSearchQueryDetailBySiteKeyApiResponse';
export type * from './generated/models/_endpoints/PostAdminSiteInsightsSitesAccessChecksBySiteKeyApiArg';
export type * from './generated/models/_endpoints/PostAdminSiteInsightsSitesAccessChecksBySiteKeyApiResponse';
export type * from './generated/models/_endpoints/PostAdminSiteInsightsSitesIndexingInspectionsBySiteKeyApiArg';
export type * from './generated/models/_endpoints/PostAdminSiteInsightsSitesIndexingInspectionsBySiteKeyApiResponse';
