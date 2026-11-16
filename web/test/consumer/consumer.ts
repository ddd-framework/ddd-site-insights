// A strict TypeScript consumer of entry `.`: compiles only if the published declarations are complete.
import {
  buildWindowLabel,
  computePresetWindow,
  DEFAULT_WINDOW_PRESET,
  type PostAdminSiteInsightsSitesAccessChecksBySiteKeyApiArg,
  type GetAdminSiteInsightsSitesApiResponse,
  type InsightsSite,
  type SiteInsightsAccessCheck,
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
