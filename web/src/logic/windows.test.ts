import assert from 'node:assert/strict';
import { test } from 'node:test';
import { addDays, buildWindowLabel, computePresetWindow, daysInclusive, isValidDay, subtractMonthsClamped, windowPresets } from './windows.ts';

// the example table of the PHP window arithmetic: last final day 2026-11-13, earliest day 2025-07-19
const LAST_FINAL_DAY = '2026-11-13';
const EARLIEST_DAY = '2025-07-19';

test('presets end on the last day and match the PHP windows', () => {
  assert.deepEqual(computePresetWindow('7d', LAST_FINAL_DAY, EARLIEST_DAY), { from: '2026-11-07', to: LAST_FINAL_DAY, days: 7, preset: '7d', windowClamped: false });
  assert.equal(computePresetWindow('28d', LAST_FINAL_DAY, EARLIEST_DAY).from, '2026-10-17');
  assert.equal(computePresetWindow('90d', LAST_FINAL_DAY, EARLIEST_DAY).from, '2026-08-16');
  assert.equal(computePresetWindow('6m', LAST_FINAL_DAY, EARLIEST_DAY).from, '2026-05-14');
  assert.deepEqual(computePresetWindow('12m', LAST_FINAL_DAY, EARLIEST_DAY), { from: '2025-11-14', to: LAST_FINAL_DAY, days: 365, preset: '12m', windowClamped: false });
  assert.deepEqual(computePresetWindow('16m', LAST_FINAL_DAY, EARLIEST_DAY), { from: EARLIEST_DAY, to: LAST_FINAL_DAY, days: 483, preset: '16m', windowClamped: true });
  assert.equal(computePresetWindow('16m', LAST_FINAL_DAY).from, '2025-07-14');
  assert.equal(computePresetWindow('6m', '2026-08-31').from, '2026-03-01');
  assert.deepEqual(computePresetWindow('7d', '2028-03-01'), { from: '2028-02-24', to: '2028-03-01', days: 7, preset: '7d', windowClamped: false });
});

test('day arithmetic on UTC days', () => {
  assert.equal(addDays('2026-11-16', -3), '2026-11-13');
  assert.equal(addDays('2028-02-28', 1), '2028-02-29');
  assert.equal(subtractMonthsClamped('2026-08-31', 6), '2026-02-28');
  assert.equal(subtractMonthsClamped('2026-11-16', 16), '2025-07-16');
  assert.equal(daysInclusive('2026-11-13', '2026-11-13'), 1);
  assert.equal(isValidDay('2028-02-29'), true);
  assert.equal(isValidDay('2026-02-29'), false);
  assert.equal(isValidDay('2026-2-1'), false);
  assert.throws(() => addDays('2026-02-30', 1), RangeError);
});

test('labels', () => {
  assert.equal(buildWindowLabel(computePresetWindow('28d', LAST_FINAL_DAY)), 'Last 28 days · 2026-10-17 – 2026-11-13');
  assert.equal(buildWindowLabel(computePresetWindow('16m', LAST_FINAL_DAY, EARLIEST_DAY)), 'Last 16 months · 2025-07-19 – 2026-11-13 (from the earliest day kept)');
  assert.equal(buildWindowLabel({ from: '2026-11-01', to: '2026-11-01', days: 1 }), '1 day · 2026-11-01');
  assert.deepEqual(windowPresets.map((windowPreset) => windowPreset.key), ['7d', '28d', '90d', '6m', '12m', '16m']);
});
