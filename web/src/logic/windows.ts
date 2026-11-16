/**
 * Pure window logic of the site insights screens, the same arithmetic as the PHP `SearchDays` / `SearchWindowsService`:
 * days are `YYYY-MM-DD` strings in the provider's day zone, computed on UTC dates (no local time zone involved), both
 * ends inclusive. Server-callable: no React, no DOM.
 */

/** A window preset of the API (`preset` query parameter). */
export type WindowPresetKey = '7d' | '28d' | '90d' | '6m' | '12m' | '16m';

export interface WindowPreset {
  key: WindowPresetKey;
  /** `days`: the last N days; `months`: the same day M months earlier plus one day */
  unit: 'days' | 'months';
  amount: number;
  /** Short English label; hosts translate by `key` */
  label: string;
}

/** The presets in the order a picker shows them. */
export const windowPresets: readonly WindowPreset[] = [
  { key: '7d', unit: 'days', amount: 7, label: 'Last 7 days' },
  { key: '28d', unit: 'days', amount: 28, label: 'Last 28 days' },
  { key: '90d', unit: 'days', amount: 90, label: 'Last 3 months' },
  { key: '6m', unit: 'months', amount: 6, label: 'Last 6 months' },
  { key: '12m', unit: 'months', amount: 12, label: 'Last 12 months' },
  { key: '16m', unit: 'months', amount: 16, label: 'Last 16 months' },
];

/** The default preset of the API. */
export const DEFAULT_WINDOW_PRESET: WindowPresetKey = '28d';

/** A window as the API returns it (`SearchWindow`) or as {@link computePresetWindow} builds it. */
export interface WindowDays {
  from: string;
  to: string;
  days: number;
  preset?: string | null;
  windowClamped?: boolean;
}

const DAY_PATTERN = /^(\d{4})-(\d{2})-(\d{2})$/;
const MILLISECONDS_PER_DAY = 86_400_000;

/** Whether `value` is an existing day written `YYYY-MM-DD`. */
export function isValidDay(value: string): boolean {
  const match = DAY_PATTERN.exec(value);
  if (!match) return false;
  const [, year, month, day] = match.map(Number);
  const date = new Date(Date.UTC(year, month - 1, day));
  return date.getUTCFullYear() === year && date.getUTCMonth() === month - 1 && date.getUTCDate() === day;
}

function parseDay(day: string): number {
  if (!isValidDay(day)) throw new RangeError(`Not a day written YYYY-MM-DD: ${day}`);
  const [, year, month, dayOfMonth] = DAY_PATTERN.exec(day)!.map(Number);
  return Date.UTC(year, month - 1, dayOfMonth);
}

function formatDay(timestamp: number): string {
  return new Date(timestamp).toISOString().slice(0, 10);
}

/** The day `days` days after `day` (negative: before). */
export function addDays(day: string, days: number): string {
  return formatDay(parseDay(day) + days * MILLISECONDS_PER_DAY);
}

/** The same day of the month `months` months earlier, clamped to the last day of a shorter month. */
export function subtractMonthsClamped(day: string, months: number): string {
  const date = new Date(parseDay(day));
  const targetMonthIndex = date.getUTCFullYear() * 12 + date.getUTCMonth() - months;
  const targetYear = Math.floor(targetMonthIndex / 12);
  const targetMonth = targetMonthIndex - targetYear * 12;
  const daysInTargetMonth = new Date(Date.UTC(targetYear, targetMonth + 1, 0)).getUTCDate();
  return formatDay(Date.UTC(targetYear, targetMonth, Math.min(date.getUTCDate(), daysInTargetMonth)));
}

/** Days from `from` to `to`, both included. */
export function daysInclusive(from: string, to: string): number {
  return Math.round((parseDay(to) - parseDay(from)) / MILLISECONDS_PER_DAY) + 1;
}

/**
 * The window of a preset ending on `lastDay` (the last final day, or today for fresh data), raised to `earliestDay`
 * when given — the window the API answers for the same preset.
 */
export function computePresetWindow(preset: WindowPresetKey, lastDay: string, earliestDay?: string): WindowDays {
  const windowPreset = windowPresets.find((candidate) => candidate.key === preset);
  if (!windowPreset) throw new RangeError(`Unknown preset: ${preset}`);
  let from =
    windowPreset.unit === 'days'
      ? addDays(lastDay, -(windowPreset.amount - 1))
      : addDays(subtractMonthsClamped(lastDay, windowPreset.amount), 1);
  let windowClamped = false;
  if (earliestDay !== undefined && from < earliestDay) {
    from = earliestDay;
    windowClamped = true;
  }
  return { from, to: lastDay, days: daysInclusive(from, lastDay), preset, windowClamped };
}

/**
 * A one-line label of a window, e.g. `Last 28 days · 2026-10-17 – 2026-11-13`; a clamped window says so
 * (`… (from the earliest day kept)`), explicit days show their count.
 */
export function buildWindowLabel(window: WindowDays): string {
  const windowPreset = windowPresets.find((candidate) => candidate.key === window.preset);
  const range = window.from === window.to ? window.from : `${window.from} – ${window.to}`;
  const name = windowPreset ? windowPreset.label : `${window.days} ${window.days === 1 ? 'day' : 'days'}`;
  return `${name} · ${range}${window.windowClamped ? ' (from the earliest day kept)' : ''}`;
}
