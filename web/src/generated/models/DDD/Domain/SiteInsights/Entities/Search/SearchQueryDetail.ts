import { SearchWindow } from "./SearchWindow";
import { SearchComparisonWindow } from "./SearchComparisonWindow";
import { SearchTotals } from "./SearchTotals";
import { SearchPerformanceRows } from "./SearchPerformanceRows";

export type SearchQueryDetail = {
  /** Key of the site */
  siteKey?: string;
  /** The query as asked (trimmed, exact match) */
  query?: string;
  window?: SearchWindow;
  comparisonWindow?: SearchComparisonWindow;
  totals?: SearchTotals;
  series?: SearchPerformanceRows;
  pages?: SearchPerformanceRows;
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default SearchQueryDetail;
