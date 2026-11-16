export type SearchOpportunityThresholds = {
  /** Days of the window the thresholds are scaled to */
  windowDays?: number;
  /** Lowest position (strikingDistanceQuery) */
  positionFrom?: number | null;
  /** Highest position (strikingDistanceQuery) */
  positionTo?: number | null;
  /** Fewest impressions of a row, scaled */
  minImpressions?: number | null;
  /** Whether brand queries are left out */
  excludeBrand?: boolean | null;
  /** Fewest rows per position bucket (weakClickRateForPosition) */
  minRowsPerBucket?: number | null;
  /** Lowest baseline click rate that judges a row (weakClickRateForPosition) */
  minBaselineClickRate?: number | null;
  /** Share of the bucket's click rate below which a row is a hit (weakClickRateForPosition) */
  ratio?: number | null;
  /** Whether a thin device bucket takes the baseline of all devices (weakClickRateForPosition) */
  fallbackToAllDevices?: boolean | null;
  /** Top queries asked (severalPagesForQuery) */
  topQueries?: number | null;
  /** Fewest impressions per page, scaled (severalPagesForQuery) */
  minImpressionsPerPage?: number | null;
  /** Smallest share of the query's impressions per page (severalPagesForQuery) */
  minShare?: number | null;
  /** Fewest pages (severalPagesForQuery) */
  minPages?: number | null;
  /** Top keys per period (rising and declining) */
  candidatesPerPeriod?: number | null;
  /** Fewest clicks in either period, scaled (rising and declining) */
  minClicksEitherPeriod?: number | null;
  /** Smallest change of clicks, scaled (rising and declining) */
  minAbsoluteDelta?: number | null;
  /** Smallest relative change (rising and declining) */
  minRelativeDelta?: number | null;
  /** Most results */
  maxResults?: number | null;
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default SearchOpportunityThresholds;
