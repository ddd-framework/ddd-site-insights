import { IndexingCoverage } from "./IndexingCoverage";
import { UrlIndexInspection } from "./UrlIndexInspection";

export type UrlIndexInspections = {
  /** Key of the site */
  siteKey?: string;
  /** Provider key, e.g. google-search-console */
  provider?: string;
  /** The property the URLs were inspected in */
  property?: string;
  /** Language of the provider's texts, as asked (e.g. en-US) */
  languageCode?: string | null;
  /** URLs given more than once and inspected once */
  duplicateUrlsDropped?: number;
  /** shared, perPod or notCounted: how the inspection counters are kept (see the module skill) */
  inspectionCounterScope?: string;
  coverage?: IndexingCoverage;
  elements?: UrlIndexInspection[];
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default UrlIndexInspections;
