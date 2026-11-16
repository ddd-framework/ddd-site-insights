export type GetAdminSiteInsightsSitesSearchCountriesBySiteKeyApiArg = {
  /** Only queries containing this text (anonymised queries are never matched) */
  queryContains?: string;
  /** Only this query */
  queryEquals?: string;
  /** Only pages whose URL contains this text */
  pageContains?: string;
  /** Only this page URL */
  pageEquals?: string;
  /** Only pages of this configured page group (key) */
  pageGroup?: string;
  /** Only this country (ISO 3166-1 alpha-3, lower case, e.g. deu) */
  country?: string;
  /** Only desktop, mobile or tablet
    Allowed Values:
    -   `desktop`
    -   `mobile`
    -   `tablet`
     */
  device?: "desktop" | "mobile" | "tablet";
  /** Rows, 1 to 1000 (default 50) */
  limit?: number;
  /** First row, 0 to 24999 (default 0) */
  offset?: number;
  /** clicks (default), impressions, ctr or position
    Allowed Values:
    -   `clicks`
    -   `impressions`
    -   `ctr`
    -   `position`
     */
  sortBy?: "clicks" | "impressions" | "ctr" | "position";
  /** desc or asc (default desc, asc for position)
    Allowed Values:
    -   `desc`
    -   `asc`
     */
  sortDirection?: "desc" | "asc";
  /** 7d, 28d (default), 90d, 6m, 12m or 16m, ending on the last day with data; ignored with from and to
    Allowed Values:
    -   `7d`
    -   `28d`
    -   `90d`
    -   `6m`
    -   `12m`
    -   `16m`
     */
  preset?: "7d" | "28d" | "90d" | "6m" | "12m" | "16m";
  /** First day (Y-m-d, a day of the provider's day zone); with to instead of the preset */
  from?: string;
  /** Last day (Y-m-d, a day of the provider's day zone); at most the last day with data of the data state */
  to?: string;
  /** none (default), previousPeriod or previousYear (52 weeks earlier, weekdays aligned)
    Allowed Values:
    -   `none`
    -   `previousPeriod`
    -   `previousYear`
     */
  compare?: "none" | "previousPeriod" | "previousYear";
  /** web (default), image, video, news, discover or googleNews
    Allowed Values:
    -   `web`
    -   `image`
    -   `video`
    -   `news`
    -   `discover`
    -   `googleNews`
     */
  searchType?: "web" | "image" | "video" | "news" | "discover" | "googleNews";
  /** final (default: settled days only) or all (fresh days that may still change)
    Allowed Values:
    -   `final`
    -   `all`
     */
  dataState?: "final" | "all";
  /** Key of the site (config SiteInsights.sites) */
  siteKey: string;
  /** Common request parameter — see "Shared request parameters". */
  noCache?: boolean;
  /** Common request parameter — see "Shared request parameters". */
  outputFormat?: string;
};

export default GetAdminSiteInsightsSitesSearchCountriesBySiteKeyApiArg;
