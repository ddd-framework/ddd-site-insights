import assert from 'node:assert/strict';
import { test } from 'node:test';
import { formatCoverageNotes, formatWindowNote } from './coverageWording.ts';

const window = {
  from: '2026-10-17', to: '2026-11-13', days: 28, dayTimeZone: 'America/Los_Angeles', dataState: 'final',
  dataThroughDate: '2026-11-13', dataThroughDateSource: 'probe', windowClamped: false,
};

test('the window in provider days', () => {
  assert.equal(formatWindowNote(window), '2026-10-17 – 2026-11-13 (28 days, America/Los_Angeles days, final data through 2026-11-13)');
});

test('a full query list sorted by the module, with a partial comparison', () => {
  const codes = formatCoverageNotes({
    window: { ...window, windowClamped: true },
    comparisonWindow: { kind: 'previousYear', comparisonIsPartial: true, days: 118 },
    coverage: {
      fetchedRows: 5000, limitReached: true, sortScope: 'fetchedRows', anonymizedQueriesOmitted: true,
      responseAggregation: 'byProperty', counterpartsNotRequested: 3,
    },
  }).map(note => note.code);
  assert.deepEqual(codes, ['windowClamped', 'comparisonPartial', 'limitReached', 'sortedByModule', 'anonymizedQueriesOmitted', 'counterpartsNotRequested']);
});

test('errors, fresh data, a refused comparison, page aggregation', () => {
  const notes = formatCoverageNotes({
    error: { cause: 'quotaExceeded', message: 'Quota exceeded' },
    window: { ...window, dataState: 'all', dataThroughDateSource: 'fallback' },
    comparisonWindow: { kind: 'previousPeriod', comparisonUnavailableReason: 'outsideRetention' },
    coverage: { fetchedRows: 3, providerRowsExhausted: true, responseAggregation: 'byPage', joinedDimensionsMayLoseRows: true },
  });
  assert.deepEqual(notes.map(note => note.code), ['error', 'freshData', 'dataThroughFallback', 'comparisonUnavailable', 'providerRowsExhausted', 'joinedDimensionsMayLoseRows', 'pageAggregation']);
  assert.equal(notes[3].text, 'No comparison: it lies outside the days the provider keeps.');
});

test('nothing to qualify', () => {
  assert.deepEqual(formatCoverageNotes({ coverage: { fetchedRows: 25, limitReached: false, providerRowsExhausted: false, sortScope: 'provider' } }), []);
});
