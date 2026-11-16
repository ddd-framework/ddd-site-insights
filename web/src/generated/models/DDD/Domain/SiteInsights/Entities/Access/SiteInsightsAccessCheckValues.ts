export type SiteInsightsAccessCheckValues = {
  /** `rowsRead`: rows the one-row Search Analytics call returned */
  rows?: number | null;
  /** `sitemapsListed`: sitemap files in the list */
  files?: number | null;
  /** `inspectionVerdict`: the verdict of the inspected root URL (e.g. PASS), or unknown */
  verdict?: string | null;
  /** `permissionLevel`: the provider's permission level name, or unknown */
  level?: string | null;
  /** `propertyNotListed`: properties the credentials entry lists */
  listedProperties?: number | null;
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default SiteInsightsAccessCheckValues;
