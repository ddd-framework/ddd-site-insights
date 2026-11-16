import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

// The two POSTs that only read (each counts URL inspections against the provider's daily quota) must invalidate no
// tag in the generated enhance step, or every query on screen refetches after each call.
const enhancedSource = readFileSync(new URL('../generated/siteInsightsApiEnhanced.ts', import.meta.url), 'utf8');

for (const endpointKey of ['postAdminSiteInsightsSitesAccessChecksBySiteKey', 'postAdminSiteInsightsSitesIndexingInspectionsBySiteKey']) {
  test(`${endpointKey} invalidates no tag`, () => {
    const endpointBlock = enhancedSource.match(new RegExp(`${endpointKey}: \\{([^}]*)\\}`));
    assert.ok(endpointBlock, `${endpointKey} is in the generated enhance step`);
    assert.match(endpointBlock[1], /invalidatesTags: \[\]/);
  });
}
