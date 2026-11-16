import { SitemapContentCount } from "./SitemapContentCount";

export type SitemapContentCounts = {
  elements?: SitemapContentCount[];
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default SitemapContentCounts;
