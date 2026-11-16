import { SearchMetrics } from "../Search/SearchMetrics";
import { SearchMetricsComparison } from "../Search/SearchMetricsComparison";
import { SearchClickRateBaseline } from "./SearchClickRateBaseline";
import { SearchPerformanceRows } from "../Search/SearchPerformanceRows";

export type SearchOpportunity = {
  /** The rule that found it */
  kind?: string;
  /** query, page or queryPage */
  subjectType?: string;
  /** The query */
  query?: string | null;
  /** The page */
  page?: string | null;
  /** The device (weakClickRateForPosition) */
  device?: string | null;
  metrics?: SearchMetrics;
  comparison?: SearchMetricsComparison;
  baseline?: SearchClickRateBaseline;
  evidenceRows?: SearchPerformanceRows;
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default SearchOpportunity;
