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

/** The numbers and names a note's text is built from, so a translated host words the note itself. */
export interface CoverageNoteValues {
  /** `error`: the cause and the message of the failed part */
  cause?: string;
  message?: string;
  /** `comparisonUnavailable`: outsideRetention or tooShortInsideRetention */
  reason?: string;
  /** `comparisonPartial`: days of the shortened comparison window */
  comparisonDays?: number;
  /** `limitReached`, `sortedByModule`: rows fetched */
  fetchedRows?: number;
  /** `counterpartsNotRequested` */
  counterpartsNotRequested?: number;
  /** `duplicateRowsDropped` */
  duplicateRowsDropped?: number;
}

export interface CoverageNote {
  code: CoverageNoteCode;
  /** The values in the text; empty for notes without numbers */
  values: CoverageNoteValues;
  /** English default wording */
  text: string;
}

/** The window as data next to its English line: what {@link formatWindowNote} words. */
export interface WindowNote {
  code: 'window';
  values: {
    from: string;
    to: string;
    days: number;
    dayTimeZone?: string;
    dataThroughDate?: string;
  };
  /** English default wording, as {@link formatWindowNote} returns it */
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

/** The window as `{code, values, text}`: a translated host words it from `values`. */
export function describeWindow(window: SearchWindow): WindowNote {
  return {
    code: 'window',
    values: {
      from: window.from ?? '',
      to: window.to ?? '',
      days: window.days ?? 0,
      ...(window.dayTimeZone ? { dayTimeZone: window.dayTimeZone } : {}),
      ...(window.dataThroughDate ? { dataThroughDate: window.dataThroughDate } : {}),
    },
    text: formatWindowNote(window),
  };
}

/**
 * Every limit an answer part states, in reading order: errors first, then the window, the comparison, the rows. An
 * empty list means the part has nothing to qualify (it never means "all rows": the provider keeps top rows only).
 */
export function formatCoverageNotes(part: CoveredAnswerPart): CoverageNote[] {
  const notes: CoverageNote[] = [];
  if (part.error) {
    notes.push({
      code: 'error',
      values: { cause: part.error.cause ?? '', message: part.error.message ?? '' },
      text: `This part failed (${part.error.cause}): ${part.error.message ?? ''}`.trim(),
    });
  }
  const window = part.window;
  if (window?.dataState === 'all') {
    notes.push({ code: 'freshData', values: {}, text: 'Includes fresh days that may still change.' });
  }
  if (window?.dataThroughDateSource === 'fallback') {
    notes.push({ code: 'dataThroughFallback', values: {}, text: 'The last final day is assumed (three days ago); the provider named none.' });
  }
  if (window?.windowClamped) {
    notes.push({ code: 'windowClamped', values: {}, text: 'The window starts at the earliest day the provider keeps.' });
  }
  const comparisonWindow = part.comparisonWindow;
  if (comparisonWindow?.comparisonUnavailableReason) {
    const reason =
      comparisonWindow.comparisonUnavailableReason === 'outsideRetention'
        ? 'it lies outside the days the provider keeps'
        : 'too little of it lies inside the days the provider keeps';
    notes.push({
      code: 'comparisonUnavailable',
      values: { reason: comparisonWindow.comparisonUnavailableReason },
      text: `No comparison: ${reason}.`,
    });
  } else if (comparisonWindow?.comparisonIsPartial) {
    notes.push({
      code: 'comparisonPartial',
      values: comparisonWindow.days !== undefined && comparisonWindow.days !== null ? { comparisonDays: comparisonWindow.days } : {},
      text: `The comparison is shortened to ${comparisonWindow.days ?? '?'} days; clicks and impressions are compared per day.`,
    });
  }
  const coverage = part.coverage;
  if (!coverage) return notes;
  // totals and a series of every day are complete by construction: nothing to say about their rows
  if (coverage.isCompleteByConstruction) {
    // no row note
  } else if (coverage.limitReached) {
    notes.push({ code: 'limitReached', values: { fetchedRows: coverage.fetchedRows ?? 0 }, text: `More rows may exist: ${coverage.fetchedRows ?? 0} rows were fetched.` });
  } else if (coverage.providerRowsExhausted && (coverage.fetchedRows ?? 0) > 0) {
    notes.push({ code: 'providerRowsExhausted', values: {}, text: 'The provider returned fewer rows than asked; it reports top rows only.' });
  }
  if (coverage.sortScope === 'fetchedRows') {
    notes.push({ code: 'sortedByModule', values: { fetchedRows: coverage.fetchedRows ?? 0 }, text: `Sorted within the ${coverage.fetchedRows ?? 0} rows fetched, not over all rows.` });
  }
  if (coverage.anonymizedQueriesOmitted) {
    notes.push({ code: 'anonymizedQueriesOmitted', values: {}, text: 'Anonymised queries are left out: these rows add up to less than the totals.' });
  }
  if (coverage.joinedDimensionsMayLoseRows) {
    notes.push({ code: 'joinedDimensionsMayLoseRows', values: {}, text: 'Query and page together: the provider drops rows.' });
  }
  if (coverage.responseAggregation === 'byPage') {
    notes.push({ code: 'pageAggregation', values: {}, text: 'Counted per page: higher than the site totals.' });
  }
  if ((coverage.counterpartsNotRequested ?? 0) > 0) {
    notes.push({
      code: 'counterpartsNotRequested',
      values: { counterpartsNotRequested: coverage.counterpartsNotRequested ?? 0 },
      text: `${coverage.counterpartsNotRequested} rows have no comparison (limits).`,
    });
  }
  if ((coverage.duplicateRowsDropped ?? 0) > 0) {
    notes.push({
      code: 'duplicateRowsDropped',
      values: { duplicateRowsDropped: coverage.duplicateRowsDropped ?? 0 },
      text: `${coverage.duplicateRowsDropped} rows repeated across pages were dropped.`,
    });
  }
  return notes;
}
