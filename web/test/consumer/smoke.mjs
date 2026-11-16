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
const opportunities = requests.searchOpportunities({ siteKey: 'example-site', kind: 'strikingDistanceQuery' });
if (opportunities.url !== '/api/v2/admin/insights/sites/example-site/search/opportunities' || (opportunities.method ?? 'GET') !== 'GET' || opportunities.params.kind !== 'strikingDistanceQuery') {
  fail(`unexpected request ${JSON.stringify(opportunities)}`);
}
const sitemaps = requests.indexingSitemaps({ siteKey: 'example-site' });
if (sitemaps.url !== '/api/v2/admin/insights/sites/example-site/indexing/sitemaps' || (sitemaps.method ?? 'GET') !== 'GET') {
  fail(`unexpected request ${JSON.stringify(sitemaps)}`);
}
// anything that counts a URL inspection is never GET
const inspections = requests.inspectUrls({
  siteKey: 'example-site',
  dddPresentationApiAdminSiteInsightsDtosSiteInsightsInspectionsPostRequestDto: { urls: ['https://example.test/'] },
});
if (inspections.method !== 'POST' || inspections.body?.urls?.[0] !== 'https://example.test/') fail(`the inspection must be a POST with a body: ${JSON.stringify(inspections)}`);
if (main.formatDelta({ counterpartState: 'notObserved' }, 'clicks').direction !== 'none') fail('a missing counterpart must not be a number');

// the factory against a real host api (RTK Query of the consumer's installation)
const { createApi, fetchBaseQuery } = await import('@reduxjs/toolkit/query/react');
const baseApi = createApi({ reducerPath: 'api', baseQuery: fetchBaseQuery({ baseUrl: 'https://example.test' }), endpoints: () => ({}) });
const siteInsightsApi = main.createSiteInsightsApi({ baseApi, apiPrefix: '/api/admin/siteInsights' });
if (main.createSiteInsightsApi({ baseApi, apiPrefix: '/api/admin/siteInsights' }) !== siteInsightsApi) fail('a second call must return the first result');
for (const hookName of ['useSearchOverviewQuery', 'useCheckAccessMutation', 'useIndexingSitemapsQuery', 'useInspectUrlsMutation', 'useSearchOpportunitiesQuery']) {
  if (typeof siteInsightsApi[hookName] !== 'function') fail(`hook ${hookName} missing`);
}
const injected = Object.keys(baseApi.endpoints).filter(name => name.startsWith('siteInsights'));
if (injected.length !== 13) fail(`expected 13 injected endpoints, got ${injected.join(', ')}`);


// the two reading POSTs are mutations that invalidate nothing: a query on screen is not refetched after them
const { configureStore } = await import('@reduxjs/toolkit');
const callsByUrl = {};
const countingBaseQuery = async request => {
  const url = typeof request === 'string' ? request : request.url;
  callsByUrl[url] = (callsByUrl[url] ?? 0) + 1;
  return { data: {} };
};
const countingApi = createApi({ reducerPath: 'countingApi', baseQuery: countingBaseQuery, endpoints: () => ({}) });
main.createSiteInsightsApi({ baseApi: countingApi, apiPrefix: '/api/admin/siteInsights' });
const store = configureStore({ reducer: { [countingApi.reducerPath]: countingApi.reducer }, middleware: getDefault => getDefault().concat(countingApi.middleware) });
const sitesSubscription = store.dispatch(countingApi.endpoints.siteInsightsSites.initiate());
await sitesSubscription;
await store.dispatch(countingApi.endpoints.siteInsightsCheckAccess.initiate({ siteKey: 'example-site' }));
await store.dispatch(countingApi.endpoints.siteInsightsInspectUrls.initiate({
  siteKey: 'example-site',
  dddPresentationApiAdminSiteInsightsDtosSiteInsightsInspectionsPostRequestDto: { urls: ['https://example.test/'] },
}));
await new Promise(resolve => setTimeout(resolve, 20));
if (callsByUrl['/api/admin/siteInsights/sites'] !== 1) fail(`the reading POSTs invalidated the sites query: ${JSON.stringify(callsByUrl)}`);
sitesSubscription.unsubscribe();

console.log(`ok      main entry exports ${Object.keys(main).length} names; factory injects ${injected.length} endpoints`);
