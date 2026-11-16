export type SiteInsightsInspectionsPostRequestDto = {
  /** URLs of the site's property, 1 to budgets.maxInspectionUrls (default 20; more are refused with 400), each at most 2048 characters */
  urls: string[];
  /** Language of the provider's texts, e.g. en or en-US */
  languageCode?: string | null;
};

export default SiteInsightsInspectionsPostRequestDto;
