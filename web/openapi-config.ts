import { defineConfig } from '@mgamadeus/ddd-web/codegen';

// The source is the schema the PHP schema test writes (schema/site-insights.openapi.json, committed). Models,
// argument and response types are generated with ddd-web-sdk; the endpoints file is bound to a placeholder base api
// (`src/api/codegenBaseApi.ts`, never exported) and its request builders go into a module of their own
// (`siteInsightsApiRequests.ts`), which `createSiteInsightsApi` rebases and injects into a host's api.
export default defineConfig({
  modelsDir: './src/generated/models',
  models: { naming: 'document', index: false },
  endpoints: { naming: 'path', requestBuilders: true },
  apis: [
    {
      schemaFile: './schema/site-insights.openapi.json',
      apiFile: './src/api/codegenBaseApi.ts',
      apiImport: 'codegenBaseApi',
      outputFile: './src/generated/siteInsightsApi.ts',
      exportName: 'siteInsightsApi',
      audience: 'admin',
      hooks: false,
      // the two POSTs only read (they are POST because each call counts URL inspections against the provider's daily
      // quota): mutations that invalidate nothing, so a call never refetches the queries on screen
      sideEffects: [
        { method: 'POST', path: /\/indexing\/inspections$/, sideEffects: false, endpointType: 'mutation' },
        { method: 'POST', path: /\/accessChecks$/, sideEffects: false, endpointType: 'mutation' },
      ],
    },
  ],
});
