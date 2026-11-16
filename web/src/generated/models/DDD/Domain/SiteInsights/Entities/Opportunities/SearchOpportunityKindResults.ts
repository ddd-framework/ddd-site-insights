import { SearchOpportunityKindResult } from "./SearchOpportunityKindResult";

export type SearchOpportunityKindResults = {
  elements?: SearchOpportunityKindResult[];
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default SearchOpportunityKindResults;
