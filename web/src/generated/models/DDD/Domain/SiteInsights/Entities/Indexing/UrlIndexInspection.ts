import { SearchPartError } from "../Search/SearchPartError";

export type UrlIndexInspection = {
  /** The URL as asked */
  url?: string;
  /** PASS, PARTIAL, FAIL, NEUTRAL or VERDICT_UNSPECIFIED */
  verdict?: string | null;
  /** The provider's coverage state text, e.g. "Submitted and indexed" */
  coverageState?: string | null;
  /** INDEXING_ALLOWED, BLOCKED_BY_META_TAG, BLOCKED_BY_HTTP_HEADER, BLOCKED_BY_ROBOTS_TXT, … */
  indexingState?: string | null;
  /** ALLOWED, DISALLOWED or ROBOTS_TXT_STATE_UNSPECIFIED */
  robotsTxtState?: string | null;
  /** SUCCESSFUL, SOFT_404, BLOCKED_ROBOTS_TXT, NOT_FOUND, ACCESS_DENIED, SERVER_ERROR, … */
  pageFetchState?: string | null;
  /** When the provider last crawled the URL */
  lastCrawlTime?: string | null;
  /** MOBILE or DESKTOP: the user agent of the last crawl */
  crawledAs?: string | null;
  /** The canonical the provider chose */
  googleCanonical?: string | null;
  /** The canonical the page declares */
  userCanonical?: string | null;
  /** Sitemap files that list the URL, as the provider knows them */
  sitemaps?: string[];
  /** URLs the provider knows linking to the URL */
  referringUrls?: string[];
  /** Link to the inspection in Search Console */
  inspectionResultLink?: string | null;
  /** When the provider answered this inspection */
  inspectedAt?: string | null;
  /** Whether the answer came from the cache (no inspection was counted for it) */
  fromCache?: boolean;
  error?: SearchPartError;
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default UrlIndexInspection;
