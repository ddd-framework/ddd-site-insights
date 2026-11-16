import { GetAdminSiteInsightsSitesApiResponse } from "./models/_endpoints/GetAdminSiteInsightsSitesApiResponse";
import { GetAdminSiteInsightsSitesApiArg } from "./models/_endpoints/GetAdminSiteInsightsSitesApiArg";
import { GetAdminSiteInsightsSitesAccessChecksBySiteKeyApiResponse } from "./models/_endpoints/GetAdminSiteInsightsSitesAccessChecksBySiteKeyApiResponse";
import { GetAdminSiteInsightsSitesAccessChecksBySiteKeyApiArg } from "./models/_endpoints/GetAdminSiteInsightsSitesAccessChecksBySiteKeyApiArg";
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
    getAdminSiteInsightsSitesAccessChecksBySiteKey: build.query<
      GetAdminSiteInsightsSitesAccessChecksBySiteKeyApiResponse,
      GetAdminSiteInsightsSitesAccessChecksBySiteKeyApiArg
    >({
      query:
        siteInsightsApiRequests.getAdminSiteInsightsSitesAccessChecksBySiteKey,
    }),
  }),
  overrideExisting: false,
});
export { injectedRtkApi as siteInsightsApi };
