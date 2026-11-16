# @mgamadeus/ddd-site-insights

Frontend of the site insights module for backends built with [mgamadeus/ddd](https://github.com/ddd-framework/ddd)
(Composer package [`mgamadeus/ddd-site-insights`](https://github.com/ddd-framework/ddd-site-insights), same version).

Entry `.` holds the API factory, the TypeScript types generated from the module's API, and pure view logic (windows,
deltas, coverage wording). It loads no React runtime and no stylesheet; the pure functions run on a server. The screens
follow in a later version (`./screens`).

## Install

```bash
npm install @mgamadeus/ddd-site-insights
```

ESM only. Peer dependencies: `@mgamadeus/ddd-web` `>=0.13.0 <1`, `@reduxjs/toolkit` `^2.6.0`, `react` `^19.0.0`,
`react-redux` `^9.0.0`.

## API factory

```ts
import { createSiteInsightsApi } from '@mgamadeus/ddd-site-insights';
import { api } from './api'; // the application's createApi(...) with its DDD base query

export const siteInsightsApi = createSiteInsightsApi({ baseApi: api, apiPrefix: '/api/admin/siteInsights' });

const { data } = siteInsightsApi.useSearchQueriesQuery({ siteKey: 'example-site', compare: 'previousPeriod', limit: 100 });
```

The factory injects the module's thirteen operations into the application's RTK Query api once (a second call with the
same api returns the first result) under the tag `SiteInsights`, rebasing the generated request builders onto
`apiPrefix`. Hooks: `useSitesQuery`, `useCheckAccessMutation` (a POST that counts a URL inspection: call it on demand),
`useSearchOverviewQuery`, `useSearchQueriesQuery`, `useSearchPagesQuery`, `useSearchCountriesQuery`,
`useSearchDevicesQuery`, `useSearchPageGroupsQuery`, `useSearchPageDetailQuery`, `useSearchQueryDetailQuery`,
`useSearchOpportunitiesQuery`, `useIndexingSitemapsQuery` and `useInspectUrlsMutation`. Keep the module's tag out of the application's own generated
SDK (`filterEndpoints`).

Anything that counts a URL inspection against the provider's daily quota is a mutation (a POST on the server), never a
query: nothing refetches it on its own, and it invalidates nothing (`invalidatesTags: []`, also in the generated
enhance step; the access check likewise), so a call never refetches the queries on screen. An application that
overrode the invalidation of these two paths can drop its override. Trigger it from a user action:

```ts
const [inspectUrls, { data }] = siteInsightsApi.useInspectUrlsMutation();
await inspectUrls({
  siteKey: 'example-site',
  dddPresentationApiAdminSiteInsightsDtosSiteInsightsInspectionsPostRequestDto: { urls: ['https://example.test/guides/'] },
});
// data.urlIndexInspections.elements[i]: verdict, coverageState, … or error; coverage.inspectionsCounted
```

## Deltas and coverage

```ts
import { formatCoverageNotes, formatDelta } from '@mgamadeus/ddd-site-insights';

formatDelta(row.comparison, 'clicks');   // { text: '+12', direction: 'up', improvement: true, … }; notObserved is never 0
formatDelta(row.comparison, 'position'); // a lower position is an improvement
formatCoverageNotes(rows);               // [{ code: 'sortedByModule', text: … }, { code: 'anonymizedQueriesOmitted', … }]
```

Hosts translate the notes by `code` and the counterpart states through `stateTexts`.

## Window logic

```ts
import { buildWindowLabel, computePresetWindow, windowPresets } from '@mgamadeus/ddd-site-insights';

const window = computePresetWindow('28d', '2026-11-13', '2025-07-19');
// { from: '2026-10-17', to: '2026-11-13', days: 28, preset: '28d', windowClamped: false }
buildWindowLabel(window); // 'Last 28 days · 2026-10-17 – 2026-11-13'
```

Days are `YYYY-MM-DD` in the provider's day zone; the arithmetic matches the backend's.

## Generated types

`src/generated/` is generated from `schema/site-insights.openapi.json`, which the PHP schema test writes. After an API
change: `SITE_INSIGHTS_WRITE_SCHEMA=1 vendor/bin/phpunit tests/Schema` in the repository root, then `pnpm generate`.
CI fails when the two drift (`check:generated`).

## License

MIT
