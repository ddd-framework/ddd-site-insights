import type { FetchArgs } from "@reduxjs/toolkit/query";
import { PostAdminSiteInsightsSitesAccessChecksBySiteKeyApiArg } from "./models/_endpoints/PostAdminSiteInsightsSitesAccessChecksBySiteKeyApiArg";

export const siteInsightsApiRequests = {
  getAdminSiteInsightsSites: (): string | FetchArgs => ({
    url: `/api/admin/siteInsights/sites`,
  }),
  postAdminSiteInsightsSitesAccessChecksBySiteKey: (
    queryArg: PostAdminSiteInsightsSitesAccessChecksBySiteKeyApiArg,
  ): string | FetchArgs => ({
    url: `/api/admin/siteInsights/sites/${encodeURIComponent(String(queryArg.siteKey))}/accessChecks`,
    method: "POST",
    params: {
      noCache: queryArg.noCache,
      outputFormat: queryArg.outputFormat,
    },
  }),
};
