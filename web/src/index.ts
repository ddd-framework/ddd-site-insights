/**
 * Public surface of the site insights frontend (published as `@mgamadeus/ddd-site-insights`). See README.md.
 *
 * Entry `.`: the types generated from the module's API and pure window logic. No React, no stylesheet, callable on a
 * server. The API factory and the screens follow.
 */
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
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Sites/InsightsSites';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Sites/InsightsSite';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Sites/InsightsPageGroups';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Sites/InsightsPageGroup';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Sites/InsightsProviderCapabilities';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Access/SiteInsightsAccessChecks';
export type * from './generated/models/DDD/Domain/SiteInsights/Entities/Access/SiteInsightsAccessCheck';
export type * from './generated/models/DDD/Presentation/Api/Admin/SiteInsights/Dtos/SiteInsightsSitesGetResponseDto';
export type * from './generated/models/DDD/Presentation/Api/Admin/SiteInsights/Dtos/SiteInsightsAccessChecksGetResponseDto';
export type * from './generated/models/_endpoints/GetAdminSiteInsightsSitesApiArg';
export type * from './generated/models/_endpoints/GetAdminSiteInsightsSitesApiResponse';
export type * from './generated/models/_endpoints/GetAdminSiteInsightsSitesAccessChecksBySiteKeyApiArg';
export type * from './generated/models/_endpoints/GetAdminSiteInsightsSitesAccessChecksBySiteKeyApiResponse';
