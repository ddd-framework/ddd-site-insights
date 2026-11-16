export type SearchRowsCoverage = {
  /** Rows the module fetched for this list */
  fetchedRows?: number;
  /** Provider calls made (cache hits not counted) */
  providerCalls?: number;
  /** Whether every part was served from the cache */
  fromCache?: boolean;
  /** When the provider answered (the oldest part) */
  fetchedAt?: string | null;
  /** Rows asked per call */
  rowLimitPerCall?: number;
  /** The module stopped (limit, ceiling or budget) while the last page was full */
  limitReached?: boolean;
  /** The last page returned fewer rows than asked (not "all rows": the provider keeps top rows only) */
  providerRowsExhausted?: boolean;
  /** provider (the provider sorted) or fetchedRows (the module sorted the rows it fetched) */
  sortScope?: string;
  /** Rows dropped across pages because their identity was already seen */
  duplicateRowsDropped?: number;
  /** Aggregation asked: auto, byPage or byProperty */
  requestedAggregation?: string;
  /** Aggregation answered: byPage or byProperty */
  responseAggregation?: string;
  /** Whether the provider named its aggregation */
  responseAggregationKnown?: boolean;
  /** Grouped or filtered by query: rare queries are missing, sums are below the totals */
  anonymizedQueriesOmitted?: boolean;
  /** Query × page: the provider drops rows in joint reports */
  joinedDimensionsMayLoseRows?: boolean;
  /** Counterpart calls made for a comparison */
  counterpartChunks?: number;
  /** Rows whose counterpart was not asked (limits) */
  counterpartsNotRequested?: number;
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default SearchRowsCoverage;
