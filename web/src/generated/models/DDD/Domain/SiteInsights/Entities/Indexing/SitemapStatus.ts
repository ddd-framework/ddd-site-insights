import { SitemapContentCounts } from "./SitemapContentCounts";

export type SitemapStatus = {
  /** URL of the sitemap file */
  path?: string;
  /** The provider's type of the file (sitemap, rssFeed, atomFeed, urlList, …) */
  type?: string | null;
  /** Whether the file is a sitemap index */
  isIndex?: boolean;
  /** Whether the provider has not processed the file yet */
  isPending?: boolean;
  /** When the file was last submitted (unset when it was found, not submitted) */
  lastSubmitted?: string | null;
  /** When the provider last downloaded the file */
  lastDownloaded?: string | null;
  /** Warnings the provider reported for the file */
  warnings?: number;
  /** Errors the provider reported for the file */
  errors?: number;
  /** URLs submitted in the file, over every content type */
  submitted?: number;
  submittedByType?: SitemapContentCounts;
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default SitemapStatus;
