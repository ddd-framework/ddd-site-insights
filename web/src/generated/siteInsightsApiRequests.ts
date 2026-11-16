import type { FetchArgs } from "@reduxjs/toolkit/query";
import { GetAdminSiteInsightsSitesAccessChecksBySiteKeyApiArg } from "./models/_endpoints/GetAdminSiteInsightsSitesAccessChecksBySiteKeyApiArg";

export const siteInsightsApiRequests = {
  getAdminSiteInsightsSites: (): string | FetchArgs => ({
    url: `/api/admin/siteInsights/sites`,
  }),
  getAdminSiteInsightsSitesAccessChecksBySiteKey: (
    queryArg: GetAdminSiteInsightsSitesAccessChecksBySiteKeyApiArg,
  ): string | FetchArgs => ({
    url: `/api/admin/siteInsights/sites/${encodeURIComponent(String(queryArg.siteKey))}/accessChecks`,
    params: {
      noCache: queryArg.noCache,
      outputFormat: queryArg.outputFormat,
    },
  }),
};
