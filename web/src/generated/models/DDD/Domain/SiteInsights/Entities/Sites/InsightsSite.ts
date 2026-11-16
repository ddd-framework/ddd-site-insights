import { InsightsPageGroups } from "./InsightsPageGroups";
import { InsightsProviderCapabilities } from "./InsightsProviderCapabilities";

export type InsightsSite = {
  /** Key of the site (`^[a-z0-9][a-z0-9-]{0,62}$`), used in URLs and commands */
  key?: string;
  /** Label shown in the admin */
  label?: string;
  /** Provider key, e.g. google-search-console */
  provider?: string;
  /** Search Console property: `https://example.test/` (URL prefix) or `sc-domain:example.test` */
  property?: string;
  /** Name of the credentials entry that reads the property; a name, never a secret */
  credentialsEntryName?: string;
  /** Revision of the credentials entry (part of every cache identity) */
  credentialsRevision?: number;
  /** RE2 patterns of brand queries */
  brandQueryPatterns?: string[];
  pageGroups?: InsightsPageGroups;
  /** Most URLs one inspection request may name (budgets.maxInspectionUrls) */
  maxInspectionUrls?: number;
  capabilities?: InsightsProviderCapabilities;
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default InsightsSite;
