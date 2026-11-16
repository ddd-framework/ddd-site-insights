import { GetAdminSiteInsightsSitesApiResponse } from "./models/_endpoints/GetAdminSiteInsightsSitesApiResponse";
import { GetAdminSiteInsightsSitesApiArg } from "./models/_endpoints/GetAdminSiteInsightsSitesApiArg";
import { PostAdminSiteInsightsSitesAccessChecksBySiteKeyApiResponse } from "./models/_endpoints/PostAdminSiteInsightsSitesAccessChecksBySiteKeyApiResponse";
import { PostAdminSiteInsightsSitesAccessChecksBySiteKeyApiArg } from "./models/_endpoints/PostAdminSiteInsightsSitesAccessChecksBySiteKeyApiArg";
import { GetAdminSiteInsightsSitesSearchOverviewBySiteKeyApiResponse } from "./models/_endpoints/GetAdminSiteInsightsSitesSearchOverviewBySiteKeyApiResponse";
import { GetAdminSiteInsightsSitesSearchOverviewBySiteKeyApiArg } from "./models/_endpoints/GetAdminSiteInsightsSitesSearchOverviewBySiteKeyApiArg";
import { GetAdminSiteInsightsSitesSearchQueriesBySiteKeyApiResponse } from "./models/_endpoints/GetAdminSiteInsightsSitesSearchQueriesBySiteKeyApiResponse";
import { GetAdminSiteInsightsSitesSearchQueriesBySiteKeyApiArg } from "./models/_endpoints/GetAdminSiteInsightsSitesSearchQueriesBySiteKeyApiArg";
import { GetAdminSiteInsightsSitesSearchPagesBySiteKeyApiResponse } from "./models/_endpoints/GetAdminSiteInsightsSitesSearchPagesBySiteKeyApiResponse";
import { GetAdminSiteInsightsSitesSearchPagesBySiteKeyApiArg } from "./models/_endpoints/GetAdminSiteInsightsSitesSearchPagesBySiteKeyApiArg";
import { GetAdminSiteInsightsSitesSearchCountriesBySiteKeyApiResponse } from "./models/_endpoints/GetAdminSiteInsightsSitesSearchCountriesBySiteKeyApiResponse";
import { GetAdminSiteInsightsSitesSearchCountriesBySiteKeyApiArg } from "./models/_endpoints/GetAdminSiteInsightsSitesSearchCountriesBySiteKeyApiArg";
import { GetAdminSiteInsightsSitesSearchDevicesBySiteKeyApiResponse } from "./models/_endpoints/GetAdminSiteInsightsSitesSearchDevicesBySiteKeyApiResponse";
import { GetAdminSiteInsightsSitesSearchDevicesBySiteKeyApiArg } from "./models/_endpoints/GetAdminSiteInsightsSitesSearchDevicesBySiteKeyApiArg";
import { GetAdminSiteInsightsSitesSearchPageGroupsBySiteKeyApiResponse } from "./models/_endpoints/GetAdminSiteInsightsSitesSearchPageGroupsBySiteKeyApiResponse";
import { GetAdminSiteInsightsSitesSearchPageGroupsBySiteKeyApiArg } from "./models/_endpoints/GetAdminSiteInsightsSitesSearchPageGroupsBySiteKeyApiArg";
import { GetAdminSiteInsightsSitesSearchPageDetailBySiteKeyApiResponse } from "./models/_endpoints/GetAdminSiteInsightsSitesSearchPageDetailBySiteKeyApiResponse";
import { GetAdminSiteInsightsSitesSearchPageDetailBySiteKeyApiArg } from "./models/_endpoints/GetAdminSiteInsightsSitesSearchPageDetailBySiteKeyApiArg";
import { GetAdminSiteInsightsSitesSearchQueryDetailBySiteKeyApiResponse } from "./models/_endpoints/GetAdminSiteInsightsSitesSearchQueryDetailBySiteKeyApiResponse";
import { GetAdminSiteInsightsSitesSearchQueryDetailBySiteKeyApiArg } from "./models/_endpoints/GetAdminSiteInsightsSitesSearchQueryDetailBySiteKeyApiArg";
import { codegenBaseApi as api } from "../api/codegenBaseApi";
import { siteInsightsApiRequests } from "./siteInsightsApiRequests";

const injectedRtkApi = api.injectEndpoints({
  endpoints: (build) => ({
    getAdminSiteInsightsSites: build.query<
      GetAdminSiteInsightsSitesApiResponse,
      GetAdminSiteInsightsSitesApiArg
    >({
      query: siteInsightsApiRequests.getAdminSiteInsightsSites,
    }),
    postAdminSiteInsightsSitesAccessChecksBySiteKey: build.mutation<
      PostAdminSiteInsightsSitesAccessChecksBySiteKeyApiResponse,
      PostAdminSiteInsightsSitesAccessChecksBySiteKeyApiArg
    >({
      query:
        siteInsightsApiRequests.postAdminSiteInsightsSitesAccessChecksBySiteKey,
    }),
    getAdminSiteInsightsSitesSearchOverviewBySiteKey: build.query<
      GetAdminSiteInsightsSitesSearchOverviewBySiteKeyApiResponse,
      GetAdminSiteInsightsSitesSearchOverviewBySiteKeyApiArg
    >({
      query:
        siteInsightsApiRequests.getAdminSiteInsightsSitesSearchOverviewBySiteKey,
    }),
    getAdminSiteInsightsSitesSearchQueriesBySiteKey: build.query<
      GetAdminSiteInsightsSitesSearchQueriesBySiteKeyApiResponse,
      GetAdminSiteInsightsSitesSearchQueriesBySiteKeyApiArg
    >({
      query:
        siteInsightsApiRequests.getAdminSiteInsightsSitesSearchQueriesBySiteKey,
    }),
    getAdminSiteInsightsSitesSearchPagesBySiteKey: build.query<
      GetAdminSiteInsightsSitesSearchPagesBySiteKeyApiResponse,
      GetAdminSiteInsightsSitesSearchPagesBySiteKeyApiArg
    >({
      query:
        siteInsightsApiRequests.getAdminSiteInsightsSitesSearchPagesBySiteKey,
    }),
    getAdminSiteInsightsSitesSearchCountriesBySiteKey: build.query<
      GetAdminSiteInsightsSitesSearchCountriesBySiteKeyApiResponse,
      GetAdminSiteInsightsSitesSearchCountriesBySiteKeyApiArg
    >({
      query:
        siteInsightsApiRequests.getAdminSiteInsightsSitesSearchCountriesBySiteKey,
    }),
    getAdminSiteInsightsSitesSearchDevicesBySiteKey: build.query<
      GetAdminSiteInsightsSitesSearchDevicesBySiteKeyApiResponse,
      GetAdminSiteInsightsSitesSearchDevicesBySiteKeyApiArg
    >({
      query:
        siteInsightsApiRequests.getAdminSiteInsightsSitesSearchDevicesBySiteKey,
    }),
    getAdminSiteInsightsSitesSearchPageGroupsBySiteKey: build.query<
      GetAdminSiteInsightsSitesSearchPageGroupsBySiteKeyApiResponse,
      GetAdminSiteInsightsSitesSearchPageGroupsBySiteKeyApiArg
    >({
      query:
        siteInsightsApiRequests.getAdminSiteInsightsSitesSearchPageGroupsBySiteKey,
    }),
    getAdminSiteInsightsSitesSearchPageDetailBySiteKey: build.query<
      GetAdminSiteInsightsSitesSearchPageDetailBySiteKeyApiResponse,
      GetAdminSiteInsightsSitesSearchPageDetailBySiteKeyApiArg
    >({
      query:
        siteInsightsApiRequests.getAdminSiteInsightsSitesSearchPageDetailBySiteKey,
    }),
    getAdminSiteInsightsSitesSearchQueryDetailBySiteKey: build.query<
      GetAdminSiteInsightsSitesSearchQueryDetailBySiteKeyApiResponse,
      GetAdminSiteInsightsSitesSearchQueryDetailBySiteKeyApiArg
    >({
      query:
        siteInsightsApiRequests.getAdminSiteInsightsSitesSearchQueryDetailBySiteKey,
    }),
  }),
  overrideExisting: false,
});
export { injectedRtkApi as siteInsightsApi };
