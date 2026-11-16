import { IndexingCoverage } from "./IndexingCoverage";
import { SearchPartError } from "../Search/SearchPartError";
import { SitemapStatus } from "./SitemapStatus";

export type SitemapStatuses = {
  /** Key of the site */
  siteKey?: string;
  /** Provider key, e.g. google-search-console */
  provider?: string;
  /** The property the files belong to */
  property?: string;
  coverage?: IndexingCoverage;
  error?: SearchPartError;
  elements?: SitemapStatus[];
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default SitemapStatuses;
