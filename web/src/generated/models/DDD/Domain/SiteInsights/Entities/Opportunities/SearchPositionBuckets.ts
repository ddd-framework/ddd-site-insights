import { SearchPositionBucket } from "./SearchPositionBucket";

export type SearchPositionBuckets = {
  elements?: SearchPositionBucket[];
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default SearchPositionBuckets;
