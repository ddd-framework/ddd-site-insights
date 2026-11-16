import { SearchWindow } from "./SearchWindow";
import { SearchComparisonWindow } from "./SearchComparisonWindow";
import { SearchTotals } from "./SearchTotals";
import { SearchPerformanceRows } from "./SearchPerformanceRows";

export type SearchPerformanceOverview = {
  /** Key of the site */
  siteKey?: string;
  window?: SearchWindow;
  comparisonWindow?: SearchComparisonWindow;
  /** The last day with final data (provider days) */
  dataThroughDate?: string;
  /** probe or fallback */
  dataThroughDateSource?: string;
  totals?: SearchTotals;
  series?: SearchPerformanceRows;
  topQueries?: SearchPerformanceRows;
  topPages?: SearchPerformanceRows;
  countries?: SearchPerformanceRows;
  devices?: SearchPerformanceRows;
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default SearchPerformanceOverview;
