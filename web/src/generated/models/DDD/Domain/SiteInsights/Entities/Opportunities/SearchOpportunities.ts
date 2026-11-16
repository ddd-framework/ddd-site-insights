import { SearchWindow } from "../Search/SearchWindow";
import { SearchComparisonWindow } from "../Search/SearchComparisonWindow";
import { SearchOpportunityKindResults } from "./SearchOpportunityKindResults";
import { SearchOpportunity } from "./SearchOpportunity";

export type SearchOpportunities = {
  /** Key of the site */
  siteKey?: string;
  window?: SearchWindow;
  comparisonWindow?: SearchComparisonWindow;
  kinds?: SearchOpportunityKindResults;
  /** Provider calls of all kinds together (cache hits not counted) */
  providerCalls?: number;
  /** The calls the operation may plan (budgets.callsPerOperation.opportunities) */
  callsAllowed?: number;
  elements?: SearchOpportunity[];
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default SearchOpportunities;
