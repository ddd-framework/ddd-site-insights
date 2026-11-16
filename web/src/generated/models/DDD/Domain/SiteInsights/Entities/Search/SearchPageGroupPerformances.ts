import { SearchWindow } from "./SearchWindow";
import { SearchComparisonWindow } from "./SearchComparisonWindow";
import { SearchPageGroupPerformance } from "./SearchPageGroupPerformance";

export type SearchPageGroupPerformances = {
  /** Key of the site */
  siteKey?: string;
  window?: SearchWindow;
  comparisonWindow?: SearchComparisonWindow;
  /** Always true: a page can belong to several groups, so group numbers must not be added up */
  groupsMayOverlap?: boolean;
  /** byPage: each group counts per page, which is higher than the property totals */
  aggregation?: string;
  /** Configured groups beyond budgets.maxPageGroups that were not asked */
  pageGroupsLeftOut?: number;
  elements?: SearchPageGroupPerformance[];
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default SearchPageGroupPerformances;
