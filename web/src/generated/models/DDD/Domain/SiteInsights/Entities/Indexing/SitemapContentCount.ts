export type SitemapContentCount = {
  /** The provider's content type (web, image, video, news, …) */
  type?: string;
  /** URLs of this type submitted in the file */
  submitted?: number;
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default SitemapContentCount;
