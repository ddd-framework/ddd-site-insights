export type SearchClickRateBaseline = {
  /** Lowest position of the bucket (inclusive, on the rounded-down position) */
  positionFrom?: number;
  /** Upper bound of the bucket (exclusive) */
  positionTo?: number;
  /** device or allDevices */
  baselineScope?: string;
  /** Σ clicks ÷ Σ impressions of the bucket's other rows */
  clickRate?: number;
  /** Other rows of the bucket the baseline is computed from */
  rowCount?: number;
  /** The row's click rate must be below ratio × clickRate */
  ratio?: number;
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default SearchClickRateBaseline;
