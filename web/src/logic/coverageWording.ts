/**
 * Pure wording of what an answer of the site insights API covers: the window in provider days, data state and last
 * final day, a clamped or partial comparison, truncated and anonymised rows, aggregation. A screen shows these next to
 * the numbers they limit; an agent reads the same fields from the JSON. Server-callable: no React, no DOM.
 */
import type { SearchComparisonWindow } from '../generated/models/DDD/Domain/SiteInsights/Entities/Search/SearchComparisonWindow';
import type { SearchPartError } from '../generated/models/DDD/Domain/SiteInsights/Entities/Search/SearchPartError';
import type { SearchRowsCoverage } from '../generated/models/DDD/Domain/SiteInsights/Entities/Search/SearchRowsCoverage';
import type { SearchWindow } from '../generated/models/DDD/Domain/SiteInsights/Entities/Search/SearchWindow';

/** Codes of the coverage notes; hosts translate by code. */
export type CoverageNoteCode =
  | 'error'
  | 'freshData'
  | 'dataThroughFallback'
  | 'windowClamped'
  | 'comparisonUnavailable'
  | 'comparisonPartial'
  | 'limitReached'
  | 'providerRowsExhausted'
  | 'sortedByModule'
  | 'anonymizedQueriesOmitted'
  | 'joinedDimensionsMayLoseRows'
  | 'pageAggregation'
  | 'counterpartsNotRequested'
  | 'duplicateRowsDropped';

export interface CoverageNote {
  code: CoverageNoteCode;
  /** English default wording */
  text: string;
}

/** A part of an answer: a row list (`SearchPerformanceRows`), totals (`SearchTotals`) or anything with these fields. */
export interface CoveredAnswerPart {
  coverage?: SearchRowsCoverage | null;
  window?: SearchWindow | null;
  comparisonWindow?: SearchComparisonWindow | null;
  error?: SearchPartError | null;
}

/**
 * The window as one line in provider days, e.g. `2026-10-17 – 2026-11-13 (28 days, America/Los_Angeles days, final
 * data through 2026-11-13)`.
 */
export function formatWindowNote(window: SearchWindow): string {
  const range = window.from === window.to ? `${window.from}` : `${window.from} – ${window.to}`;
  const days = window.days === 1 ? '1 day' : `${window.days} days`;
  const zone = window.dayTimeZone ? `, ${window.dayTimeZone} days` : '';
  const through = window.dataThroughDate ? `, final data through ${window.dataThroughDate}` : '';
  return `${range} (${days}${zone}${through})`;
}

/**
 * Every limit an answer part states, in reading order: errors first, then the window, the comparison, the rows. An
 * empty list means the part has nothing to qualify (it never means "all rows": the provider keeps top rows only).
 */
export function formatCoverageNotes(part: CoveredAnswerPart): CoverageNote[] {
  const notes: CoverageNote[] = [];
  if (part.error) {
    notes.push({ code: 'error', text: `This part failed (${part.error.cause}): ${part.error.message ?? ''}`.trim() });
  }
  const window = part.window;
  if (window?.dataState === 'all') {
    notes.push({ code: 'freshData', text: 'Includes fresh days that may still change.' });
  }
  if (window?.dataThroughDateSource === 'fallback') {
    notes.push({ code: 'dataThroughFallback', text: 'The last final day is assumed (three days ago); the provider named none.' });
  }
  if (window?.windowClamped) {
    notes.push({ code: 'windowClamped', text: 'The window starts at the earliest day the provider keeps.' });
  }
  const comparisonWindow = part.comparisonWindow;
  if (comparisonWindow?.comparisonUnavailableReason) {
    const reason =
      comparisonWindow.comparisonUnavailableReason === 'outsideRetention'
        ? 'it lies outside the days the provider keeps'
        : 'too little of it lies inside the days the provider keeps';
    notes.push({ code: 'comparisonUnavailable', text: `No comparison: ${reason}.` });
  } else if (comparisonWindow?.comparisonIsPartial) {
    notes.push({
      code: 'comparisonPartial',
      text: `The comparison is shortened to ${comparisonWindow.days ?? '?'} days; clicks and impressions are compared per day.`,
    });
  }
  const coverage = part.coverage;
  if (!coverage) return notes;
  if (coverage.limitReached) {
    notes.push({ code: 'limitReached', text: `More rows may exist: ${coverage.fetchedRows ?? 0} rows were fetched.` });
  } else if (coverage.providerRowsExhausted && (coverage.fetchedRows ?? 0) > 0) {
    notes.push({ code: 'providerRowsExhausted', text: 'The provider returned fewer rows than asked; it reports top rows only.' });
  }
  if (coverage.sortScope === 'fetchedRows') {
    notes.push({ code: 'sortedByModule', text: `Sorted within the ${coverage.fetchedRows ?? 0} rows fetched, not over all rows.` });
  }
  if (coverage.anonymizedQueriesOmitted) {
    notes.push({ code: 'anonymizedQueriesOmitted', text: 'Anonymised queries are left out: these rows add up to less than the totals.' });
  }
  if (coverage.joinedDimensionsMayLoseRows) {
    notes.push({ code: 'joinedDimensionsMayLoseRows', text: 'Query and page together: the provider drops rows.' });
  }
  if (coverage.responseAggregation === 'byPage') {
    notes.push({ code: 'pageAggregation', text: 'Counted per page: higher than the site totals.' });
  }
  if ((coverage.counterpartsNotRequested ?? 0) > 0) {
    notes.push({ code: 'counterpartsNotRequested', text: `${coverage.counterpartsNotRequested} rows have no comparison (limits).` });
  }
  if ((coverage.duplicateRowsDropped ?? 0) > 0) {
    notes.push({ code: 'duplicateRowsDropped', text: `${coverage.duplicateRowsDropped} rows repeated across pages were dropped.` });
  }
  return notes;
}
