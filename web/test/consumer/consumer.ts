// A strict TypeScript consumer of entry `.`: compiles only if the published declarations are complete.
import { createApi, fetchBaseQuery } from '@reduxjs/toolkit/query/react';
import {
  buildWindowLabel,
  computePresetWindow,
  type CoverageNote,
  createSiteInsightsApi,
  DEFAULT_WINDOW_PRESET,
  type FormattedDelta,
  formatCoverageNotes,
  formatDelta,
  type GetAdminSiteInsightsSitesApiResponse,
  type GetAdminSiteInsightsSitesSearchQueriesBySiteKeyApiArg,
  type InsightsSite,
  type PostAdminSiteInsightsSitesAccessChecksBySiteKeyApiArg,
  type SearchPerformanceOverview,
  type SearchPerformanceRows,
  type SiteInsightsAccessCheck,
  type SiteInsightsApi,
  type WindowDays,
  windowPresets,
} from '@mgamadeus/ddd-site-insights';

export const window: WindowDays = computePresetWindow(DEFAULT_WINDOW_PRESET, '2026-11-13', '2025-07-19');
export const label: string = buildWindowLabel(window);
export const presetKeys: string[] = windowPresets.map(windowPreset => windowPreset.key);

export const site: InsightsSite = {
  key: 'example-site',
  label: 'Example site',
  property: 'https://example.test/',
  capabilities: { dayTimeZone: 'America/Los_Angeles', retentionMonths: 16 },
};
export const check: SiteInsightsAccessCheck = { siteKey: 'example-site', capability: 'searchAnalytics', state: 'ok' };
export const accessChecksArg: PostAdminSiteInsightsSitesAccessChecksBySiteKeyApiArg = { siteKey: 'example-site' };
export const sitesResponse: GetAdminSiteInsightsSitesApiResponse = { insightsSites: { elements: [site] } };
export const queriesArg: GetAdminSiteInsightsSitesSearchQueriesBySiteKeyApiArg = { siteKey: 'example-site', compare: 'previousYear', sortBy: 'impressions', limit: 100 };

export const rows: SearchPerformanceRows = {
  dimensions: ['query'],
  coverage: { fetchedRows: 1, providerRowsExhausted: true, anonymizedQueriesOmitted: true },
  elements: [{ query: 'example widget', metrics: { clicks: 3, impressions: 40, ctr: 0.075, position: 4.2 }, comparison: { counterpartState: 'notObserved' } }],
};
export const notes: CoverageNote[] = formatCoverageNotes(rows);
export const delta: FormattedDelta = formatDelta(rows.elements?.[0]?.comparison, 'clicks');
export const overviewDays: number | undefined = ({} as SearchPerformanceOverview).window?.days;

const baseApi = createApi({ reducerPath: 'api', baseQuery: fetchBaseQuery({ baseUrl: '' }), endpoints: () => ({}) });
export const siteInsightsApi: SiteInsightsApi = createSiteInsightsApi({ baseApi, apiPrefix: '/api/admin/siteInsights' });
