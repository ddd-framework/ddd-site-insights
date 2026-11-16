export type SearchTransportFailure = {
  /** timeout or endedBeforeTimeout */
  kind?: string;
  /** How long the failed call round took */
  elapsedMilliseconds?: number;
  /** The timeout of the call (budgets.batchTimeoutSeconds) */
  timeoutSeconds?: number;
  /** The fully qualified class name of the object */
  objectType?: string | null;
};

export default SearchTransportFailure;
