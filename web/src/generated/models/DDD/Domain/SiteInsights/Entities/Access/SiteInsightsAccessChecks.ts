import { SiteInsightsAccessCheck } from "./SiteInsightsAccessCheck";

export type SiteInsightsAccessChecks = {
  elements?: SiteInsightsAccessCheck[];
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default SiteInsightsAccessChecks;
