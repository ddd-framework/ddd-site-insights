import { InsightsPageGroup } from "../Sites/InsightsPageGroup";
import { SearchTotals } from "./SearchTotals";

export type SearchPageGroupPerformance = {
  pageGroup?: InsightsPageGroup;
  /** The RE2 expression the group was asked with (page includingRegex) */
  pageExpression?: string;
  totals?: SearchTotals;
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default SearchPageGroupPerformance;
