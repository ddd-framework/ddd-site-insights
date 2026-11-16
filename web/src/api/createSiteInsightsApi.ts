import type {
  Api,
  BaseQueryFn,
  coreModuleName,
  FetchArgs,
  FetchBaseQueryError,
  FetchBaseQueryMeta,
} from '@reduxjs/toolkit/query';
import type { reactHooksModuleName, TypedUseMutation, TypedUseQuery } from '@reduxjs/toolkit/query/react';

import type { GetAdminSiteInsightsSitesApiArg } from '../generated/models/_endpoints/GetAdminSiteInsightsSitesApiArg';
import type { GetAdminSiteInsightsSitesApiResponse } from '../generated/models/_endpoints/GetAdminSiteInsightsSitesApiResponse';
import type { GetAdminSiteInsightsSitesSearchCountriesBySiteKeyApiArg } from '../generated/models/_endpoints/GetAdminSiteInsightsSitesSearchCountriesBySiteKeyApiArg';
import type { GetAdminSiteInsightsSitesSearchCountriesBySiteKeyApiResponse } from '../generated/models/_endpoints/GetAdminSiteInsightsSitesSearchCountriesBySiteKeyApiResponse';
import type { GetAdminSiteInsightsSitesSearchDevicesBySiteKeyApiArg } from '../generated/models/_endpoints/GetAdminSiteInsightsSitesSearchDevicesBySiteKeyApiArg';
import type { GetAdminSiteInsightsSitesSearchDevicesBySiteKeyApiResponse } from '../generated/models/_endpoints/GetAdminSiteInsightsSitesSearchDevicesBySiteKeyApiResponse';
import type { GetAdminSiteInsightsSitesSearchOverviewBySiteKeyApiArg } from '../generated/models/_endpoints/GetAdminSiteInsightsSitesSearchOverviewBySiteKeyApiArg';
import type { GetAdminSiteInsightsSitesSearchOverviewBySiteKeyApiResponse } from '../generated/models/_endpoints/GetAdminSiteInsightsSitesSearchOverviewBySiteKeyApiResponse';
import type { GetAdminSiteInsightsSitesSearchPageDetailBySiteKeyApiArg } from '../generated/models/_endpoints/GetAdminSiteInsightsSitesSearchPageDetailBySiteKeyApiArg';
import type { GetAdminSiteInsightsSitesSearchPageDetailBySiteKeyApiResponse } from '../generated/models/_endpoints/GetAdminSiteInsightsSitesSearchPageDetailBySiteKeyApiResponse';
import type { GetAdminSiteInsightsSitesSearchPageGroupsBySiteKeyApiArg } from '../generated/models/_endpoints/GetAdminSiteInsightsSitesSearchPageGroupsBySiteKeyApiArg';
import type { GetAdminSiteInsightsSitesSearchPageGroupsBySiteKeyApiResponse } from '../generated/models/_endpoints/GetAdminSiteInsightsSitesSearchPageGroupsBySiteKeyApiResponse';
import type { GetAdminSiteInsightsSitesSearchPagesBySiteKeyApiArg } from '../generated/models/_endpoints/GetAdminSiteInsightsSitesSearchPagesBySiteKeyApiArg';
import type { GetAdminSiteInsightsSitesSearchPagesBySiteKeyApiResponse } from '../generated/models/_endpoints/GetAdminSiteInsightsSitesSearchPagesBySiteKeyApiResponse';
import type { GetAdminSiteInsightsSitesSearchQueriesBySiteKeyApiArg } from '../generated/models/_endpoints/GetAdminSiteInsightsSitesSearchQueriesBySiteKeyApiArg';
import type { GetAdminSiteInsightsSitesSearchQueriesBySiteKeyApiResponse } from '../generated/models/_endpoints/GetAdminSiteInsightsSitesSearchQueriesBySiteKeyApiResponse';
import type { GetAdminSiteInsightsSitesSearchQueryDetailBySiteKeyApiArg } from '../generated/models/_endpoints/GetAdminSiteInsightsSitesSearchQueryDetailBySiteKeyApiArg';
import type { GetAdminSiteInsightsSitesSearchQueryDetailBySiteKeyApiResponse } from '../generated/models/_endpoints/GetAdminSiteInsightsSitesSearchQueryDetailBySiteKeyApiResponse';
import type { PostAdminSiteInsightsSitesAccessChecksBySiteKeyApiArg } from '../generated/models/_endpoints/PostAdminSiteInsightsSitesAccessChecksBySiteKeyApiArg';
import type { PostAdminSiteInsightsSitesAccessChecksBySiteKeyApiResponse } from '../generated/models/_endpoints/PostAdminSiteInsightsSitesAccessChecksBySiteKeyApiResponse';
import { siteInsightsApiRequests } from '../generated/siteInsightsApiRequests';

/** The one cache tag of the module: every query provides it; the access check (a POST) invalidates nothing. */
export const SITE_INSIGHTS_TAG = 'SiteInsights';

/** The prefix the generated request builders carry (the fixture application of the schema). */
export const SITE_INSIGHTS_SCHEMA_API_PREFIX = '/api/admin/siteInsights';

/** The base query the endpoints run on: a fetch-style one (`createDddBaseQuery` of `@mgamadeus/ddd-web/api`). */
export type SiteInsightsBaseQuery = BaseQueryFn<string | FetchArgs, unknown, FetchBaseQueryError, object, FetchBaseQueryMeta>;

/** The application's RTK Query api (`createApi` from `@reduxjs/toolkit/query/react`) the endpoints are injected into. */
export type SiteInsightsBaseApi<ReducerPath extends string = string, TagTypes extends string = string> = Api<
  SiteInsightsBaseQuery,
  // RTK Query's own type of "no endpoints yet" (`createApi({ endpoints: () => ({}) })`)
  // biome-ignore lint/complexity/noBannedTypes: see above
  {},
  ReducerPath,
  TagTypes,
  typeof coreModuleName | typeof reactHooksModuleName
>;

export interface CreateSiteInsightsApiOptions<ReducerPath extends string, TagTypes extends string> {
  /** The application's api; its base query carries the origin, the session and the refresh. */
  baseApi: SiteInsightsBaseApi<ReducerPath, TagTypes>;
  /** Path prefix of the module's routes as the application mounts them, without a trailing slash (e.g. `/api/admin/siteInsights`). */
  apiPrefix: string;
}

type SiteInsightsQueryHook<ResultType, QueryArg> = TypedUseQuery<ResultType, QueryArg, SiteInsightsBaseQuery>;
type SiteInsightsMutationHook<ResultType, QueryArg> = TypedUseMutation<ResultType, QueryArg, SiteInsightsBaseQuery>;

/** The hooks `createSiteInsightsApi` returns. */
export interface SiteInsightsApi {
  /** `GET /sites`: the configured sites. */
  useSitesQuery: SiteInsightsQueryHook<GetAdminSiteInsightsSitesApiResponse, GetAdminSiteInsightsSitesApiArg>;
  /** `POST /sites/{siteKey}/accessChecks`: asks the provider live and counts one URL inspection — call it on demand only. */
  useCheckAccessMutation: SiteInsightsMutationHook<
    PostAdminSiteInsightsSitesAccessChecksBySiteKeyApiResponse,
    PostAdminSiteInsightsSitesAccessChecksBySiteKeyApiArg
  >;
  /** `GET /sites/{siteKey}/search/overview` */
  useSearchOverviewQuery: SiteInsightsQueryHook<
    GetAdminSiteInsightsSitesSearchOverviewBySiteKeyApiResponse,
    GetAdminSiteInsightsSitesSearchOverviewBySiteKeyApiArg
  >;
  /** `GET /sites/{siteKey}/search/queries` */
  useSearchQueriesQuery: SiteInsightsQueryHook<
    GetAdminSiteInsightsSitesSearchQueriesBySiteKeyApiResponse,
    GetAdminSiteInsightsSitesSearchQueriesBySiteKeyApiArg
  >;
  /** `GET /sites/{siteKey}/search/pages` */
  useSearchPagesQuery: SiteInsightsQueryHook<
    GetAdminSiteInsightsSitesSearchPagesBySiteKeyApiResponse,
    GetAdminSiteInsightsSitesSearchPagesBySiteKeyApiArg
  >;
  /** `GET /sites/{siteKey}/search/countries` */
  useSearchCountriesQuery: SiteInsightsQueryHook<
    GetAdminSiteInsightsSitesSearchCountriesBySiteKeyApiResponse,
    GetAdminSiteInsightsSitesSearchCountriesBySiteKeyApiArg
  >;
  /** `GET /sites/{siteKey}/search/devices` */
  useSearchDevicesQuery: SiteInsightsQueryHook<
    GetAdminSiteInsightsSitesSearchDevicesBySiteKeyApiResponse,
    GetAdminSiteInsightsSitesSearchDevicesBySiteKeyApiArg
  >;
  /** `GET /sites/{siteKey}/search/pageGroups` */
  useSearchPageGroupsQuery: SiteInsightsQueryHook<
    GetAdminSiteInsightsSitesSearchPageGroupsBySiteKeyApiResponse,
    GetAdminSiteInsightsSitesSearchPageGroupsBySiteKeyApiArg
  >;
  /** `GET /sites/{siteKey}/search/pageDetail?url=` */
  useSearchPageDetailQuery: SiteInsightsQueryHook<
    GetAdminSiteInsightsSitesSearchPageDetailBySiteKeyApiResponse,
    GetAdminSiteInsightsSitesSearchPageDetailBySiteKeyApiArg
  >;
  /** `GET /sites/{siteKey}/search/queryDetail?query=` */
  useSearchQueryDetailQuery: SiteInsightsQueryHook<
    GetAdminSiteInsightsSitesSearchQueryDetailBySiteKeyApiResponse,
    GetAdminSiteInsightsSitesSearchQueryDetailBySiteKeyApiArg
  >;
}

/**
 * A request of a generated builder with the schema's prefix replaced by the application's. Pure: no RTK runtime.
 *
 * @throws Error when the request does not start with the schema's prefix (the generated builders always do)
 */
export function rebaseSiteInsightsRequest(request: string | FetchArgs, apiPrefix: string): FetchArgs {
  const fetchArgs: FetchArgs = typeof request === 'string' ? { url: request } : request;
  if (!fetchArgs.url.startsWith(SITE_INSIGHTS_SCHEMA_API_PREFIX)) {
    throw new Error(`Site insights request outside ${SITE_INSIGHTS_SCHEMA_API_PREFIX}: ${fetchArgs.url}`);
  }
  return { ...fetchArgs, url: apiPrefix.replace(/\/+$/, '') + fetchArgs.url.slice(SITE_INSIGHTS_SCHEMA_API_PREFIX.length) };
}

/** The generated request builders of every operation, rebased onto `apiPrefix`. Pure: no RTK runtime. */
export function buildSiteInsightsRequests(apiPrefix: string) {
  const rebase =
    <QueryArg>(buildRequest: (queryArg: QueryArg) => string | FetchArgs) =>
    (queryArg: QueryArg): FetchArgs =>
      rebaseSiteInsightsRequest(buildRequest(queryArg), apiPrefix);
  return {
    sites: (): FetchArgs => rebaseSiteInsightsRequest(siteInsightsApiRequests.getAdminSiteInsightsSites(), apiPrefix),
    checkAccess: rebase(siteInsightsApiRequests.postAdminSiteInsightsSitesAccessChecksBySiteKey),
    searchOverview: rebase(siteInsightsApiRequests.getAdminSiteInsightsSitesSearchOverviewBySiteKey),
    searchQueries: rebase(siteInsightsApiRequests.getAdminSiteInsightsSitesSearchQueriesBySiteKey),
    searchPages: rebase(siteInsightsApiRequests.getAdminSiteInsightsSitesSearchPagesBySiteKey),
    searchCountries: rebase(siteInsightsApiRequests.getAdminSiteInsightsSitesSearchCountriesBySiteKey),
    searchDevices: rebase(siteInsightsApiRequests.getAdminSiteInsightsSitesSearchDevicesBySiteKey),
    searchPageGroups: rebase(siteInsightsApiRequests.getAdminSiteInsightsSitesSearchPageGroupsBySiteKey),
    searchPageDetail: rebase(siteInsightsApiRequests.getAdminSiteInsightsSitesSearchPageDetailBySiteKey),
    searchQueryDetail: rebase(siteInsightsApiRequests.getAdminSiteInsightsSitesSearchQueryDetailBySiteKey),
  };
}

const PROVIDED = [{ type: SITE_INSIGHTS_TAG, id: 'LIST' }] as const;

/** One result per base api and prefix: a second call with the same api returns the first result. */
const createdApisByBaseApi = new WeakMap<object, Map<string, SiteInsightsApi>>();

/**
 * Injects the module's operations into the application's RTK Query api and returns their hooks. Call it once (module
 * scope); a second call with the same base api and prefix returns the first result.
 *
 * Cache: one tag (`SiteInsights`), provided by every query. The reads are GET and cached on the server (a final window
 * a day, a fresh one 30 minutes); the access check is a mutation that invalidates nothing — it counts a URL inspection
 * against the provider's daily quota, so nothing refetches it on its own.
 */
export const createSiteInsightsApi = <ReducerPath extends string, TagTypes extends string>({
  baseApi,
  apiPrefix,
}: CreateSiteInsightsApiOptions<ReducerPath, TagTypes>): SiteInsightsApi => {
  const createdApisByPrefix = createdApisByBaseApi.get(baseApi) ?? new Map<string, SiteInsightsApi>();
  createdApisByBaseApi.set(baseApi, createdApisByPrefix);
  const createdApi = createdApisByPrefix.get(apiPrefix);
  if (createdApi) return createdApi;

  const requests = buildSiteInsightsRequests(apiPrefix);
  // Widened to plain strings: the returned hooks do not depend on the application's reducer path and tag names. An api
  // is invariant in both, hence the cast; it also keeps the hook types nameable in the package's declaration files.
  const widenedBaseApi = baseApi as unknown as SiteInsightsBaseApi;
  const api = widenedBaseApi.enhanceEndpoints({ addTagTypes: [SITE_INSIGHTS_TAG] }).injectEndpoints({
    endpoints: build => ({
      siteInsightsSites: build.query<GetAdminSiteInsightsSitesApiResponse, GetAdminSiteInsightsSitesApiArg>({
        query: requests.sites,
        providesTags: PROVIDED,
      }),
      siteInsightsCheckAccess: build.mutation<
        PostAdminSiteInsightsSitesAccessChecksBySiteKeyApiResponse,
        PostAdminSiteInsightsSitesAccessChecksBySiteKeyApiArg
      >({
        query: requests.checkAccess,
      }),
      siteInsightsSearchOverview: build.query<
        GetAdminSiteInsightsSitesSearchOverviewBySiteKeyApiResponse,
        GetAdminSiteInsightsSitesSearchOverviewBySiteKeyApiArg
      >({ query: requests.searchOverview, providesTags: PROVIDED }),
      siteInsightsSearchQueries: build.query<
        GetAdminSiteInsightsSitesSearchQueriesBySiteKeyApiResponse,
        GetAdminSiteInsightsSitesSearchQueriesBySiteKeyApiArg
      >({ query: requests.searchQueries, providesTags: PROVIDED }),
      siteInsightsSearchPages: build.query<
        GetAdminSiteInsightsSitesSearchPagesBySiteKeyApiResponse,
        GetAdminSiteInsightsSitesSearchPagesBySiteKeyApiArg
      >({ query: requests.searchPages, providesTags: PROVIDED }),
      siteInsightsSearchCountries: build.query<
        GetAdminSiteInsightsSitesSearchCountriesBySiteKeyApiResponse,
        GetAdminSiteInsightsSitesSearchCountriesBySiteKeyApiArg
      >({ query: requests.searchCountries, providesTags: PROVIDED }),
      siteInsightsSearchDevices: build.query<
        GetAdminSiteInsightsSitesSearchDevicesBySiteKeyApiResponse,
        GetAdminSiteInsightsSitesSearchDevicesBySiteKeyApiArg
      >({ query: requests.searchDevices, providesTags: PROVIDED }),
      siteInsightsSearchPageGroups: build.query<
        GetAdminSiteInsightsSitesSearchPageGroupsBySiteKeyApiResponse,
        GetAdminSiteInsightsSitesSearchPageGroupsBySiteKeyApiArg
      >({ query: requests.searchPageGroups, providesTags: PROVIDED }),
      siteInsightsSearchPageDetail: build.query<
        GetAdminSiteInsightsSitesSearchPageDetailBySiteKeyApiResponse,
        GetAdminSiteInsightsSitesSearchPageDetailBySiteKeyApiArg
      >({ query: requests.searchPageDetail, providesTags: PROVIDED }),
      siteInsightsSearchQueryDetail: build.query<
        GetAdminSiteInsightsSitesSearchQueryDetailBySiteKeyApiResponse,
        GetAdminSiteInsightsSitesSearchQueryDetailBySiteKeyApiArg
      >({ query: requests.searchQueryDetail, providesTags: PROVIDED }),
    }),
    overrideExisting: false,
  });

  const siteInsightsApi: SiteInsightsApi = {
    useSitesQuery: api.useSiteInsightsSitesQuery,
    useCheckAccessMutation: api.useSiteInsightsCheckAccessMutation,
    useSearchOverviewQuery: api.useSiteInsightsSearchOverviewQuery,
    useSearchQueriesQuery: api.useSiteInsightsSearchQueriesQuery,
    useSearchPagesQuery: api.useSiteInsightsSearchPagesQuery,
    useSearchCountriesQuery: api.useSiteInsightsSearchCountriesQuery,
    useSearchDevicesQuery: api.useSiteInsightsSearchDevicesQuery,
    useSearchPageGroupsQuery: api.useSiteInsightsSearchPageGroupsQuery,
    useSearchPageDetailQuery: api.useSiteInsightsSearchPageDetailQuery,
    useSearchQueryDetailQuery: api.useSiteInsightsSearchQueryDetailQuery,
  };
  createdApisByPrefix.set(apiPrefix, siteInsightsApi);
  return siteInsightsApi;
};
