export type SiteInsightsAccessCheck = {
  /** Key of the site */
  siteKey?: string;
  /** The capability checked
     Allowed Values:
    -   `keyReadable`
    -   `tokenObtained`
    -   `propertyListed`
    -   `permissionLevel`
    -   `searchAnalytics`
    -   `sitemaps`
    -   `urlInspection`
    -   `budgetCountersShared`
     */
  capability?:
    | "keyReadable"
    | "tokenObtained"
    | "propertyListed"
    | "permissionLevel"
    | "searchAnalytics"
    | "sitemaps"
    | "urlInspection"
    | "budgetCountersShared";
  /** ok, failed, or skipped because a prerequisite failed
     Allowed Values:
    -   `ok`
    -   `failed`
    -   `skipped`
     */
  state?: "ok" | "failed" | "skipped";
  /** A SiteInsightsErrorCause value; set when failed */
  cause?: string | null;
  /** What failed or what was found (e.g. the permission level), without secrets */
  detail?: string | null;
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default SiteInsightsAccessCheck;
