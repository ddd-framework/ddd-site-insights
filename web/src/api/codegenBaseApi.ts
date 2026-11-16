// Placeholder base api for code generation only: the generated endpoints file injects into it. It is never exported
// from the package; a host's api receives the endpoints through the package's factory.
import { createApi, fetchBaseQuery } from '@reduxjs/toolkit/query';

export const codegenBaseApi = createApi({
  reducerPath: 'siteInsightsCodegen',
  baseQuery: fetchBaseQuery({ baseUrl: '' }),
  tagTypes: ['SiteInsights'],
  endpoints: () => ({}),
});
