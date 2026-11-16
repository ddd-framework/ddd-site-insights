import { GetAdminSiteInsightsSitesApiResponse } from "./models/_endpoints/GetAdminSiteInsightsSitesApiResponse";
import { GetAdminSiteInsightsSitesApiArg } from "./models/_endpoints/GetAdminSiteInsightsSitesApiArg";
import { PostAdminSiteInsightsSitesAccessChecksBySiteKeyApiResponse } from "./models/_endpoints/PostAdminSiteInsightsSitesAccessChecksBySiteKeyApiResponse";
import { PostAdminSiteInsightsSitesAccessChecksBySiteKeyApiArg } from "./models/_endpoints/PostAdminSiteInsightsSitesAccessChecksBySiteKeyApiArg";
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
  }),
  overrideExisting: false,
});
export { injectedRtkApi as siteInsightsApi };
