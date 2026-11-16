import { SearchRowsCoverage } from "./SearchRowsCoverage";

export type SearchMetrics = {
  /** Clicks */
  clicks?: number;
  /** Impressions */
  impressions?: number;
  /** Click rate 0–1; 0 without impressions */
  ctr?: number;
  /** Average position (1 = top); unset when the provider or search type has none */
  position?: number | null;
  /** byProperty or byPage, as the provider answered (SearchRowsCoverage::$responseAggregation) */
  aggregation?: string;
  /** false: the provider returned no row for this question (zeros are not observations) */
  hasData?: boolean;
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default SearchMetrics;
