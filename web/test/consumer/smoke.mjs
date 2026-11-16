// Runtime import of entry `.` in Node: pure functions load without React, Redux or a stylesheet; the API factory
// injects its endpoints into a host's RTK Query api and rebases the generated requests onto the host's prefix.
const main = await import('@mgamadeus/ddd-site-insights');

const fail = message => {
  console.error(message);
  process.exit(1);
};

const expected = [
  'windowPresets', 'computePresetWindow', 'buildWindowLabel', 'addDays', 'subtractMonthsClamped', 'daysInclusive', 'isValidDay',
  'DEFAULT_WINDOW_PRESET', 'createSiteInsightsApi', 'buildSiteInsightsRequests', 'rebaseSiteInsightsRequest', 'SITE_INSIGHTS_TAG',
  'formatDelta', 'formatCoverageNotes', 'formatWindowNote', 'DEFAULT_COUNTERPART_STATE_TEXTS',
];
const missing = expected.filter(name => !(name in main));
if (missing.length > 0) fail(`missing exports: ${missing.join(', ')}`);

const window = main.computePresetWindow('28d', '2026-11-13');
if (window.from !== '2026-10-17' || window.days !== 28) fail(`unexpected window ${JSON.stringify(window)}`);

const requests = main.buildSiteInsightsRequests('/api/v2/admin/insights');
const queries = requests.searchQueries({ siteKey: 'example-site', compare: 'previousPeriod', limit: 10 });
if (queries.url !== '/api/v2/admin/insights/sites/example-site/search/queries' || queries.params.compare !== 'previousPeriod') {
  fail(`unexpected request ${JSON.stringify(queries)}`);
}
if (requests.checkAccess({ siteKey: 'example-site' }).method !== 'POST') fail('the access check must be a POST');
if (main.formatDelta({ counterpartState: 'notObserved' }, 'clicks').direction !== 'none') fail('a missing counterpart must not be a number');

// the factory against a real host api (RTK Query of the consumer's installation)
const { createApi, fetchBaseQuery } = await import('@reduxjs/toolkit/query/react');
const baseApi = createApi({ reducerPath: 'api', baseQuery: fetchBaseQuery({ baseUrl: 'https://example.test' }), endpoints: () => ({}) });
const siteInsightsApi = main.createSiteInsightsApi({ baseApi, apiPrefix: '/api/admin/siteInsights' });
if (main.createSiteInsightsApi({ baseApi, apiPrefix: '/api/admin/siteInsights' }) !== siteInsightsApi) fail('a second call must return the first result');
if (typeof siteInsightsApi.useSearchOverviewQuery !== 'function' || typeof siteInsightsApi.useCheckAccessMutation !== 'function') fail('hooks missing');
const injected = Object.keys(baseApi.endpoints).filter(name => name.startsWith('siteInsights'));
if (injected.length !== 10) fail(`expected 10 injected endpoints, got ${injected.join(', ')}`);

console.log(`ok      main entry exports ${Object.keys(main).length} names; factory injects ${injected.length} endpoints`);
