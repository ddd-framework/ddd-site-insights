export type SearchPartError = {
  /** A SiteInsightsErrorCause value */
  cause?: string;
  /** What failed, at most 160 characters; never a key, token or e-mail address */
  message?: string;
  /** The provider's HTTP status, when it answered */
  providerStatus?: number | null;
  /** Whether asking again later may succeed */
  retryable?: boolean;
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default SearchPartError;
