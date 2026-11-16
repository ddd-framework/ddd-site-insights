import { SearchWindow } from "./SearchWindow";
import { SearchComparisonWindow } from "./SearchComparisonWindow";
import { SearchRowsCoverage } from "./SearchRowsCoverage";
import { SearchPartError } from "./SearchPartError";
import { SearchPerformanceRow } from "./SearchPerformanceRow";

export type SearchPerformanceRows = {
  /** Provider key, e.g. google-search-console */
  provider?: string;
  /** Dimensions the rows are grouped by */
  dimensions?: string[];
  window?: SearchWindow;
  comparisonWindow?: SearchComparisonWindow;
  coverage?: SearchRowsCoverage;
  error?: SearchPartError;
  elements?: SearchPerformanceRow[];
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default SearchPerformanceRows;
