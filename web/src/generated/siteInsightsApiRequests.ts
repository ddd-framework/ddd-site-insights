import type { FetchArgs } from "@reduxjs/toolkit/query";
import { PostAdminSiteInsightsSitesAccessChecksBySiteKeyApiArg } from "./models/_endpoints/PostAdminSiteInsightsSitesAccessChecksBySiteKeyApiArg";
import { GetAdminSiteInsightsSitesSearchOverviewBySiteKeyApiArg } from "./models/_endpoints/GetAdminSiteInsightsSitesSearchOverviewBySiteKeyApiArg";
import { GetAdminSiteInsightsSitesSearchQueriesBySiteKeyApiArg } from "./models/_endpoints/GetAdminSiteInsightsSitesSearchQueriesBySiteKeyApiArg";
import { GetAdminSiteInsightsSitesSearchPagesBySiteKeyApiArg } from "./models/_endpoints/GetAdminSiteInsightsSitesSearchPagesBySiteKeyApiArg";
import { GetAdminSiteInsightsSitesSearchCountriesBySiteKeyApiArg } from "./models/_endpoints/GetAdminSiteInsightsSitesSearchCountriesBySiteKeyApiArg";
import { GetAdminSiteInsightsSitesSearchDevicesBySiteKeyApiArg } from "./models/_endpoints/GetAdminSiteInsightsSitesSearchDevicesBySiteKeyApiArg";
import { GetAdminSiteInsightsSitesSearchPageGroupsBySiteKeyApiArg } from "./models/_endpoints/GetAdminSiteInsightsSitesSearchPageGroupsBySiteKeyApiArg";
import { GetAdminSiteInsightsSitesSearchPageDetailBySiteKeyApiArg } from "./models/_endpoints/GetAdminSiteInsightsSitesSearchPageDetailBySiteKeyApiArg";
import { GetAdminSiteInsightsSitesSearchQueryDetailBySiteKeyApiArg } from "./models/_endpoints/GetAdminSiteInsightsSitesSearchQueryDetailBySiteKeyApiArg";
import { GetAdminSiteInsightsSitesIndexingSitemapsBySiteKeyApiArg } from "./models/_endpoints/GetAdminSiteInsightsSitesIndexingSitemapsBySiteKeyApiArg";
import { PostAdminSiteInsightsSitesIndexingInspectionsBySiteKeyApiArg } from "./models/_endpoints/PostAdminSiteInsightsSitesIndexingInspectionsBySiteKeyApiArg";

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
  getAdminSiteInsightsSitesSearchOverviewBySiteKey: (
    queryArg: GetAdminSiteInsightsSitesSearchOverviewBySiteKeyApiArg,
  ): string | FetchArgs => ({
    url: `/api/admin/siteInsights/sites/${encodeURIComponent(String(queryArg.siteKey))}/search/overview`,
    params: {
      preset: queryArg.preset,
      from: queryArg["from"],
      to: queryArg.to,
      compare: queryArg.compare,
      searchType: queryArg.searchType,
      dataState: queryArg.dataState,
      noCache: queryArg.noCache,
      outputFormat: queryArg.outputFormat,
    },
  }),
  getAdminSiteInsightsSitesSearchQueriesBySiteKey: (
    queryArg: GetAdminSiteInsightsSitesSearchQueriesBySiteKeyApiArg,
  ): string | FetchArgs => ({
    url: `/api/admin/siteInsights/sites/${encodeURIComponent(String(queryArg.siteKey))}/search/queries`,
    params: {
      queryContains: queryArg.queryContains,
      queryEquals: queryArg.queryEquals,
      pageContains: queryArg.pageContains,
      pageEquals: queryArg.pageEquals,
      pageGroup: queryArg.pageGroup,
      country: queryArg.country,
      device: queryArg.device,
      limit: queryArg.limit,
      offset: queryArg.offset,
      sortBy: queryArg.sortBy,
      sortDirection: queryArg.sortDirection,
      preset: queryArg.preset,
      from: queryArg["from"],
      to: queryArg.to,
      compare: queryArg.compare,
      searchType: queryArg.searchType,
      dataState: queryArg.dataState,
      noCache: queryArg.noCache,
      outputFormat: queryArg.outputFormat,
    },
  }),
  getAdminSiteInsightsSitesSearchPagesBySiteKey: (
    queryArg: GetAdminSiteInsightsSitesSearchPagesBySiteKeyApiArg,
  ): string | FetchArgs => ({
    url: `/api/admin/siteInsights/sites/${encodeURIComponent(String(queryArg.siteKey))}/search/pages`,
    params: {
      queryContains: queryArg.queryContains,
      queryEquals: queryArg.queryEquals,
      pageContains: queryArg.pageContains,
      pageEquals: queryArg.pageEquals,
      pageGroup: queryArg.pageGroup,
      country: queryArg.country,
      device: queryArg.device,
      limit: queryArg.limit,
      offset: queryArg.offset,
      sortBy: queryArg.sortBy,
      sortDirection: queryArg.sortDirection,
      preset: queryArg.preset,
      from: queryArg["from"],
      to: queryArg.to,
      compare: queryArg.compare,
      searchType: queryArg.searchType,
      dataState: queryArg.dataState,
      noCache: queryArg.noCache,
      outputFormat: queryArg.outputFormat,
    },
  }),
  getAdminSiteInsightsSitesSearchCountriesBySiteKey: (
    queryArg: GetAdminSiteInsightsSitesSearchCountriesBySiteKeyApiArg,
  ): string | FetchArgs => ({
    url: `/api/admin/siteInsights/sites/${encodeURIComponent(String(queryArg.siteKey))}/search/countries`,
    params: {
      queryContains: queryArg.queryContains,
      queryEquals: queryArg.queryEquals,
      pageContains: queryArg.pageContains,
      pageEquals: queryArg.pageEquals,
      pageGroup: queryArg.pageGroup,
      country: queryArg.country,
      device: queryArg.device,
      limit: queryArg.limit,
      offset: queryArg.offset,
      sortBy: queryArg.sortBy,
      sortDirection: queryArg.sortDirection,
      preset: queryArg.preset,
      from: queryArg["from"],
      to: queryArg.to,
      compare: queryArg.compare,
      searchType: queryArg.searchType,
      dataState: queryArg.dataState,
      noCache: queryArg.noCache,
      outputFormat: queryArg.outputFormat,
    },
  }),
  getAdminSiteInsightsSitesSearchDevicesBySiteKey: (
    queryArg: GetAdminSiteInsightsSitesSearchDevicesBySiteKeyApiArg,
  ): string | FetchArgs => ({
    url: `/api/admin/siteInsights/sites/${encodeURIComponent(String(queryArg.siteKey))}/search/devices`,
    params: {
      queryContains: queryArg.queryContains,
      queryEquals: queryArg.queryEquals,
      pageContains: queryArg.pageContains,
      pageEquals: queryArg.pageEquals,
      pageGroup: queryArg.pageGroup,
      country: queryArg.country,
      device: queryArg.device,
      limit: queryArg.limit,
      offset: queryArg.offset,
      sortBy: queryArg.sortBy,
      sortDirection: queryArg.sortDirection,
      preset: queryArg.preset,
      from: queryArg["from"],
      to: queryArg.to,
      compare: queryArg.compare,
      searchType: queryArg.searchType,
      dataState: queryArg.dataState,
      noCache: queryArg.noCache,
      outputFormat: queryArg.outputFormat,
    },
  }),
  getAdminSiteInsightsSitesSearchPageGroupsBySiteKey: (
    queryArg: GetAdminSiteInsightsSitesSearchPageGroupsBySiteKeyApiArg,
  ): string | FetchArgs => ({
    url: `/api/admin/siteInsights/sites/${encodeURIComponent(String(queryArg.siteKey))}/search/pageGroups`,
    params: {
      preset: queryArg.preset,
      from: queryArg["from"],
      to: queryArg.to,
      compare: queryArg.compare,
      searchType: queryArg.searchType,
      dataState: queryArg.dataState,
      noCache: queryArg.noCache,
      outputFormat: queryArg.outputFormat,
    },
  }),
  getAdminSiteInsightsSitesSearchPageDetailBySiteKey: (
    queryArg: GetAdminSiteInsightsSitesSearchPageDetailBySiteKeyApiArg,
  ): string | FetchArgs => ({
    url: `/api/admin/siteInsights/sites/${encodeURIComponent(String(queryArg.siteKey))}/search/pageDetail`,
    params: {
      url: queryArg.url,
      preset: queryArg.preset,
      from: queryArg["from"],
      to: queryArg.to,
      compare: queryArg.compare,
      searchType: queryArg.searchType,
      dataState: queryArg.dataState,
      noCache: queryArg.noCache,
      outputFormat: queryArg.outputFormat,
    },
  }),
  getAdminSiteInsightsSitesSearchQueryDetailBySiteKey: (
    queryArg: GetAdminSiteInsightsSitesSearchQueryDetailBySiteKeyApiArg,
  ): string | FetchArgs => ({
    url: `/api/admin/siteInsights/sites/${encodeURIComponent(String(queryArg.siteKey))}/search/queryDetail`,
    params: {
      query: queryArg.query,
      preset: queryArg.preset,
      from: queryArg["from"],
      to: queryArg.to,
      compare: queryArg.compare,
      searchType: queryArg.searchType,
      dataState: queryArg.dataState,
      noCache: queryArg.noCache,
      outputFormat: queryArg.outputFormat,
    },
  }),
  getAdminSiteInsightsSitesIndexingSitemapsBySiteKey: (
    queryArg: GetAdminSiteInsightsSitesIndexingSitemapsBySiteKeyApiArg,
  ): string | FetchArgs => ({
    url: `/api/admin/siteInsights/sites/${encodeURIComponent(String(queryArg.siteKey))}/indexing/sitemaps`,
    params: {
      noCache: queryArg.noCache,
      outputFormat: queryArg.outputFormat,
    },
  }),
  postAdminSiteInsightsSitesIndexingInspectionsBySiteKey: (
    queryArg: PostAdminSiteInsightsSitesIndexingInspectionsBySiteKeyApiArg,
  ): string | FetchArgs => ({
    url: `/api/admin/siteInsights/sites/${encodeURIComponent(String(queryArg.siteKey))}/indexing/inspections`,
    method: "POST",
    body: queryArg.dddPresentationApiAdminSiteInsightsDtosSiteInsightsInspectionsPostRequestDto,
    params: {
      noCache: queryArg.noCache,
      outputFormat: queryArg.outputFormat,
    },
  }),
};
