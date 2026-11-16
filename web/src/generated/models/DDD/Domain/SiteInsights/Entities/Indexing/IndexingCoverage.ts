export type IndexingCoverage = {
  /** Parts asked: 1 for the sitemaps list, the distinct URLs of an inspection request */
  requestedParts?: number;
  /** Parts answered from the cache (no provider call, no quota counted) */
  partsFromCache?: number;
  /** Parts that carry an error instead of an answer */
  failedParts?: number;
  /** Provider calls made (retries included, cache hits not counted) */
  providerCalls?: number;
  /** Calls counted against the provider's daily URL inspection quota by this request (0 for sitemaps) */
  inspectionsCounted?: number;
  /** Whether every answered part came from the cache */
  fromCache?: boolean;
  /** When the provider answered (the oldest answered part) */
  fetchedAt?: string | null;
  /** How long an answer is served from the cache (cache.sitemapsTtlSeconds or cache.inspectionTtlSeconds) */
  cacheLifetimeSeconds?: number;
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default IndexingCoverage;
