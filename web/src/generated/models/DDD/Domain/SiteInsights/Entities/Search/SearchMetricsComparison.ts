import { SearchMetrics } from "./SearchMetrics";
import { SearchPartError } from "./SearchPartError";

export type SearchMetricsComparison = {
  current?: SearchMetrics;
  previous?: SearchMetrics;
  /** observed, notObserved, notRequested or failed */
  counterpartState?: string;
  /** Clicks current − previous (equal window lengths) */
  clicksDelta?: number | null;
  /** Impressions current − previous (equal window lengths) */
  impressionsDelta?: number | null;
  /** Per-day clicks current − previous (partial comparison) */
  clicksPerDayDelta?: number | null;
  /** Per-day impressions current − previous (partial comparison) */
  impressionsPerDayDelta?: number | null;
  /** Click rate current − previous */
  ctrDelta?: number | null;
  /** Position current − previous (negative = better) */
  positionDelta?: number | null;
  /** Whether the click and impression deltas are per-day averages */
  perDay?: boolean;
  /** Series only: the day of the comparison window this day is compared with (aligned by offset) */
  previousDate?: string | null;
  error?: SearchPartError;
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default SearchMetricsComparison;
