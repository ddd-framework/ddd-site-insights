export type ExceptionDetail = {
  /** Exception message */
  message?: string | null;
  /** Exception detail, can be a payload of different types */
  details?: any[] | null;
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default ExceptionDetail;
