import { ValidationError } from "./ValidationError";

export type ValidationErrors = {
  elements?: ValidationError[];
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default ValidationErrors;
