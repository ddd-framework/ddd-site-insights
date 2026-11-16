import { ValidationResult } from "./ValidationResult";

export type ValidationError = {
  /** Complete object path to the property where validation failed */
  jsonPath?: string;
  elements?: ValidationResult[];
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default ValidationError;
