import { SearchPartError } from "../Search/SearchPartError";
import { SearchOpportunityThresholds } from "./SearchOpportunityThresholds";
import { SearchRowsCoverage } from "../Search/SearchRowsCoverage";
import { SearchPositionBuckets } from "./SearchPositionBuckets";
import { SearchPerformanceRows } from "../Search/SearchPerformanceRows";

export type SearchOpportunityKindResult = {
  /** The kind */
  kind?: string;
  /** Results of the kind in `elements` of the set */
  resultCount?: number;
  /** Why the kind has no result: noRows, belowThresholds, windowTooShort, noComparison, thinBaseline, sourceFailed */
  emptyReason?: string | null;
  error?: SearchPartError;
  /** Results come from part of the source only (some calls failed, see `error`) */
  isPartial?: boolean;
  thresholds?: SearchOpportunityThresholds;
  sourceCoverage?: SearchRowsCoverage;
  /** Provider calls this kind added (sources shared with an earlier kind are counted there) */
  providerCalls?: number;
  /** What a result of this kind does not prove */
  note?: string;
  positionBuckets?: SearchPositionBuckets;
  observedInOnePeriodOnly?: SearchPerformanceRows;
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default SearchOpportunityKindResult;
