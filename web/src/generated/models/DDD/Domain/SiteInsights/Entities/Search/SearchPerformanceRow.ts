import { SearchWindow } from "./SearchWindow";
import { SearchMetrics } from "./SearchMetrics";
import { SearchMetricsComparison } from "./SearchMetricsComparison";

export type SearchPerformanceRow = {
  /** Day (provider days, SearchWindow::$dayTimeZone); set when grouped by date */
  date?: string | null;
  /** Search query; set when grouped by query */
  query?: string | null;
  /** Page URL as the provider reports it; set when grouped by page */
  page?: string | null;
  /** Country, ISO 3166-1 alpha-3 lower case; set when grouped by country */
  country?: string | null;
  /** desktop, mobile, tablet; set when grouped by device */
  device?: string | null;
  /** Search appearance; set when grouped by it */
  searchAppearance?: string | null;
  metrics?: SearchMetrics;
  comparison?: SearchMetricsComparison;
  /** Series only: true for a day the provider reported nothing for (filled with zeros) */
  isFilledDay?: boolean | null;
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default SearchPerformanceRow;
