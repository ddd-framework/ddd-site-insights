export type SearchPositionBucket = {
  /** Lowest position (inclusive, on the rounded-down position) */
  positionFrom?: number;
  /** Upper bound (exclusive) */
  positionTo?: number;
  /** desktop, mobile, tablet or all */
  device?: string;
  /** Rows of the bucket with at least the minimum impressions */
  rowCount?: number;
  /** Σ clicks ÷ Σ impressions of those rows; unset without rows */
  clickRate?: number | null;
  /** Whether the bucket can judge rows: at least minRowsPerBucket rows and a click rate of at least
    minBaselineClickRate (each row is then judged against the bucket without its own row) */
  hasBaseline?: boolean;
  /** Why the bucket has no baseline: tooFewRows or belowMinBaselineClickRate */
  noBaselineReason?: string | null;
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default SearchPositionBucket;
