export type InsightsProviderCapabilities = {
  /** Provider key, e.g. google-search-console */
  provider?: string;
  /** Dimensions rows can be grouped by */
  dimensions?: string[];
  /** Filter operators */
  filterOperators?: string[];
  /** Metrics of every row */
  metrics?: string[];
  /** Search types */
  searchTypes?: string[];
  /** Data states (final: settled days only; all: fresh days that may still change) */
  dataStates?: string[];
  /** Aggregations */
  aggregations?: string[];
  /** Time zone the provider counts its days in */
  dayTimeZone?: string;
  /** Months of data the provider keeps */
  retentionMonths?: number;
  /** Most rows one call may ask for */
  maxRowsPerCall?: number;
  /** Whether hourly data is available (not used in version 1) */
  hasHourlyData?: boolean;
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default InsightsProviderCapabilities;
