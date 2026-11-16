import { SearchMetricsComparison } from "./SearchMetricsComparison";
import { SearchRowsCoverage } from "./SearchRowsCoverage";
import { SearchPartError } from "./SearchPartError";

export type SearchTotals = {
  metrics?: SearchMetricsComparison;
  coverage?: SearchRowsCoverage;
  comparisonCoverage?: SearchRowsCoverage;
  error?: SearchPartError;
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default SearchTotals;
