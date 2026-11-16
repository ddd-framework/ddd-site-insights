import { InsightsPageGroup } from "../Sites/InsightsPageGroup";
import { SearchWindow } from "./SearchWindow";
import { SearchComparisonWindow } from "./SearchComparisonWindow";
import { SearchTotals } from "./SearchTotals";
import { SearchPerformanceRows } from "./SearchPerformanceRows";

export type SearchPageDetail = {
  /** Key of the site */
  siteKey?: string;
  /** The page URL as asked (trimmed) */
  page?: string;
  pageGroup?: InsightsPageGroup;
  window?: SearchWindow;
  comparisonWindow?: SearchComparisonWindow;
  totals?: SearchTotals;
  series?: SearchPerformanceRows;
  queries?: SearchPerformanceRows;
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default SearchPageDetail;
