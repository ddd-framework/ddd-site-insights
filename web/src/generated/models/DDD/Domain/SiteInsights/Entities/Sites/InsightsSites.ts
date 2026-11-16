import { InsightsSite } from "./InsightsSite";

export type InsightsSites = {
  elements?: InsightsSite[];
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default InsightsSites;
