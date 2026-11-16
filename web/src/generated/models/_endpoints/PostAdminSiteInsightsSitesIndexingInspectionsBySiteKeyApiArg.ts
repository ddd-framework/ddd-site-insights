import { SiteInsightsInspectionsPostRequestDto } from "../DDD/Presentation/Api/Admin/SiteInsights/Dtos/SiteInsightsInspectionsPostRequestDto";

export type PostAdminSiteInsightsSitesIndexingInspectionsBySiteKeyApiArg = {
  /** Key of the site (config SiteInsights.sites) */
  siteKey: string;
  /** Common request parameter — see "Shared request parameters". */
  noCache?: boolean;
  /** Common request parameter — see "Shared request parameters". */
  outputFormat?: string;
  dddPresentationApiAdminSiteInsightsDtosSiteInsightsInspectionsPostRequestDto: SiteInsightsInspectionsPostRequestDto;
};

export default PostAdminSiteInsightsSitesIndexingInspectionsBySiteKeyApiArg;
