export type InsightsPageGroup = {
  /** Key of the group within its site */
  key?: string;
  /** Label shown in the admin */
  label?: string;
  /** RE2 patterns on the URL path (no lookarounds, no backreferences) */
  pathPatterns?: string[];
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default InsightsPageGroup;
