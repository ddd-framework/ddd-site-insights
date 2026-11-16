import assert from 'node:assert/strict';
import { test } from 'node:test';
import { describeWindow, formatCoverageNotes, formatWindowNote } from './coverageWording.ts';

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

test('values next to the code, for a translated host', () => {
  const notes = formatCoverageNotes({
    comparisonWindow: { kind: 'previousYear', comparisonIsPartial: true, days: 118 },
    coverage: { fetchedRows: 5000, limitReached: true, sortScope: 'fetchedRows', counterpartsNotRequested: 3, duplicateRowsDropped: 2 },
  });
  const valuesByCode = Object.fromEntries(notes.map(note => [note.code, note.values]));
  assert.deepEqual(valuesByCode.comparisonPartial, { comparisonDays: 118 });
  assert.deepEqual(valuesByCode.limitReached, { fetchedRows: 5000 });
  assert.deepEqual(valuesByCode.sortedByModule, { fetchedRows: 5000 });
  assert.deepEqual(valuesByCode.counterpartsNotRequested, { counterpartsNotRequested: 3 });
  assert.deepEqual(valuesByCode.duplicateRowsDropped, { duplicateRowsDropped: 2 });
  assert.ok(notes.every(note => typeof note.text === 'string' && note.text !== ''), 'the English text stays');
  const errorNote = formatCoverageNotes({ error: { cause: 'quotaExceeded', message: 'Quota exceeded' } })[0];
  assert.deepEqual(errorNote.values, { cause: 'quotaExceeded', message: 'Quota exceeded' });
});

test('the window as data', () => {
  const windowNote = describeWindow(window);
  assert.equal(windowNote.code, 'window');
  assert.deepEqual(windowNote.values, {
    from: '2026-10-17', to: '2026-11-13', days: 28, dayTimeZone: 'America/Los_Angeles', dataThroughDate: '2026-11-13',
  });
  assert.equal(windowNote.text, formatWindowNote(window));
});

test('a part complete by construction gets no row note', () => {
  for (const coverage of [
    { fetchedRows: 1, isCompleteByConstruction: true, limitReached: false, providerRowsExhausted: false },
    // rc.7 answers: complete parts carried providerRowsExhausted true
    { fetchedRows: 28, isCompleteByConstruction: true, limitReached: false, providerRowsExhausted: true },
  ]) {
    const codes = formatCoverageNotes({ coverage }).map(note => note.code);
    assert.ok(!codes.includes('providerRowsExhausted') && !codes.includes('limitReached'), JSON.stringify(codes));
  }
  assert.deepEqual(
    formatCoverageNotes({ coverage: { fetchedRows: 3, providerRowsExhausted: true } }).map(note => note.code),
    ['providerRowsExhausted'],
  );
});
