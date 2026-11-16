export type SearchComparisonWindow = {
  /** previousPeriod or previousYear (52 weeks, weekdays aligned) */
  kind?: string;
  /** Whether the comparison was shortened to the provider's retention (deltas per day) */
  comparisonIsPartial?: boolean;
  /** outsideRetention or tooShortInsideRetention; unset when the comparison is available */
  comparisonUnavailableReason?: string | null;
  /** First day, inclusive */
  from?: string;
  /** Last day, inclusive */
  to?: string;
  /** Days of the window, both ends included */
  days?: number;
  /** The preset the window was built from (7d, 28d, 90d, 6m, 12m, 16m); unset for explicit days */
  preset?: string | null;
  /** Whether `from` was raised to the earliest day the provider keeps */
  windowClamped?: boolean;
  /** The provider's day zone, e.g. America/Los_Angeles */
  dayTimeZone?: string;
  /** final (settled days) or all (fresh days that may still change) */
  dataState?: string;
  /** Search type, e.g. web */
  searchType?: string;
  /** The last day with final data */
  dataThroughDate?: string | null;
  /** probe (read from the provider) or fallback (today − 3 in the provider's zone) */
  dataThroughDateSource?: string | null;
  /** The first incomplete day (dataState all) */
  firstIncompleteDate?: string | null;
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default SearchComparisonWindow;
