import { ValidationErrors } from "../Validation/ValidationErrors";
import { ExceptionDetails } from "./ExceptionDetails";

export type BadRequestException = {
  validationErrors?: ValidationErrors;
  /** Error message */
  error?: string | null;
  exceptionDetails?: ExceptionDetails;
};

export default BadRequestException;
