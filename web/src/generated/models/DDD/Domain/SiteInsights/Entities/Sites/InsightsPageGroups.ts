import { InsightsPageGroup } from "./InsightsPageGroup";

export type InsightsPageGroups = {
  elements?: InsightsPageGroup[];
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default InsightsPageGroups;
