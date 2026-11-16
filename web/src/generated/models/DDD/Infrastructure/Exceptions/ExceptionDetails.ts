import { ExceptionDetail } from "./ExceptionDetail";

export type ExceptionDetails = {
  elements?: ExceptionDetail[];
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default ExceptionDetails;
