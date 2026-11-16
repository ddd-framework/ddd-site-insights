/**
 * Pure comparison formatting of the site insights screens: a row's or aggregate's change against its counterpart in
 * the comparison window, as the API states it. Server-callable: no React, no DOM.
 */
import type { SearchMetricsComparison } from '../generated/models/DDD/Domain/SiteInsights/Entities/Search/SearchMetricsComparison';

/** A metric of the API. */
export type SearchMetricName = 'clicks' | 'impressions' | 'ctr' | 'position';

/** The unit of a delta: per-day averages (partial comparison), percentage points (click rate), or none. */
export type DeltaUnit = 'perDay' | 'percentagePoints' | null;

/** The counterpart states of the API (`SearchMetricsComparison.counterpartState`). */
export type CounterpartState = 'observed' | 'notObserved' | 'notRequested' | 'failed';

export interface FormattedDelta {
  /** e.g. `+12`, `-3.5/day`, `+0.4 pp`, `-1.2`; for a missing counterpart the wording of its state */
  text: string;
  /** up / down / flat; `none` without an observed counterpart */
  direction: 'up' | 'down' | 'flat' | 'none';
  /** true when the change is good (a lower position is better), false when bad, null when flat or unknown */
  improvement: boolean | null;
  /** clicks and impressions of a partial comparison are per-day averages */
  perDay: boolean;
  counterpartState: CounterpartState;
  /**
   * The delta as a number in its unit (click rate already in percentage points), null without an observed
   * counterpart: a translated host formats `value` and `unit` itself instead of using `text`.
   */
  value: number | null;
  /** `perDay` (clicks or impressions of a partial comparison), `percentagePoints` (click rate) or null */
  unit: DeltaUnit;
}

export interface FormatDeltaOptions {
  /** BCP 47 locale of the numbers (default `en`) */
  locale?: string;
  /** Wording of the counterpart states that carry no number; hosts translate by state */
  stateTexts?: Partial<Record<Exclude<CounterpartState, 'observed'>, string>>;
}

/** Default wording of a counterpart without a number: never shown as zero. */
export const DEFAULT_COUNTERPART_STATE_TEXTS: Record<Exclude<CounterpartState, 'observed'>, string> = {
  notObserved: 'not reported in the comparison window',
  notRequested: 'no comparison',
  failed: 'comparison failed',
};

function readCounterpartState(comparison: SearchMetricsComparison | undefined | null): CounterpartState {
  const state = comparison?.counterpartState;
  return state === 'observed' || state === 'notObserved' || state === 'failed' ? state : 'notRequested';
}

/**
 * The change of one metric as the API computed it (never recomputed from rounded values): absolute clicks and
 * impressions for equal windows, per-day averages for a partial comparison, click rate in percentage points, position
 * as a difference of averages (negative = better). Without an observed counterpart the text names the state.
 */
export function formatDelta(
  comparison: SearchMetricsComparison | undefined | null,
  metric: SearchMetricName,
  options: FormatDeltaOptions = {},
): FormattedDelta {
  const counterpartState = readCounterpartState(comparison);
  const perDay = comparison?.perDay === true;
  if (counterpartState !== 'observed') {
    const stateTexts = { ...DEFAULT_COUNTERPART_STATE_TEXTS, ...options.stateTexts };
    return { text: stateTexts[counterpartState], direction: 'none', improvement: null, perDay, counterpartState, value: null, unit: null };
  }
  const locale = options.locale ?? 'en';
  let value: number | null | undefined;
  let unit: DeltaUnit = null;
  let text = '';
  const signed = (fractionDigits: number) =>
    new Intl.NumberFormat(locale, { signDisplay: 'exceptZero', maximumFractionDigits: fractionDigits });
  switch (metric) {
    case 'clicks':
    case 'impressions': {
      value = perDay
        ? metric === 'clicks' ? comparison?.clicksPerDayDelta : comparison?.impressionsPerDayDelta
        : metric === 'clicks' ? comparison?.clicksDelta : comparison?.impressionsDelta;
      if (value !== null && value !== undefined) text = signed(perDay ? 1 : 0).format(value) + (perDay ? '/day' : '');
      unit = perDay ? 'perDay' : null;
      break;
    }
    case 'ctr':
      value = comparison?.ctrDelta;
      if (value !== null && value !== undefined) {
        value = value * 100;
        text = `${signed(1).format(value)} pp`;
      }
      unit = 'percentagePoints';
      break;
    case 'position':
      value = comparison?.positionDelta;
      if (value !== null && value !== undefined) text = signed(1).format(value);
      break;
  }
  if (value === null || value === undefined) {
    return { text: '', direction: 'none', improvement: null, perDay, counterpartState, value: null, unit };
  }
  const direction = value > 0 ? 'up' : value < 0 ? 'down' : 'flat';
  const improvement = direction === 'flat' ? null : metric === 'position' ? value < 0 : value > 0;
  return { text, direction, improvement, perDay, counterpartState, value, unit };
}
