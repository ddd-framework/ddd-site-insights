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
  /** Whether the bucket has at least minRowsPerBucket rows (a baseline of its own) */
  hasBaseline?: boolean;
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default SearchPositionBucket;
