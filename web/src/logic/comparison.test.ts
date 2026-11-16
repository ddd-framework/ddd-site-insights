import assert from 'node:assert/strict';
import { test } from 'node:test';
import { formatDelta } from './comparison.ts';

const observed = {
  counterpartState: 'observed',
  clicksDelta: 12,
  impressionsDelta: -340,
  ctrDelta: 0.0042,
  positionDelta: -1.25,
  perDay: false,
};

test('observed counterparts: absolute deltas, click rate in points, a lower position is better', () => {
  assert.deepEqual(formatDelta(observed, 'clicks'), { text: '+12', direction: 'up', improvement: true, perDay: false, counterpartState: 'observed' });
  assert.equal(formatDelta(observed, 'impressions').text, '-340');
  assert.equal(formatDelta(observed, 'impressions').improvement, false);
  assert.equal(formatDelta(observed, 'ctr').text, '+0.4 pp');
  const position = formatDelta(observed, 'position');
  assert.equal(position.text, '-1.3');
  assert.equal(position.direction, 'down');
  assert.equal(position.improvement, true);
});

test('a partial comparison compares per day', () => {
  const partial = { counterpartState: 'observed', clicksPerDayDelta: 2.54, impressionsPerDayDelta: 0, perDay: true };
  assert.equal(formatDelta(partial, 'clicks').text, '+2.5/day');
  assert.equal(formatDelta(partial, 'impressions').direction, 'flat');
  assert.equal(formatDelta(partial, 'impressions').improvement, null);
});

test('a missing counterpart is never zero', () => {
  assert.deepEqual(formatDelta({ counterpartState: 'notObserved' }, 'clicks'), {
    text: 'not reported in the comparison window', direction: 'none', improvement: null, perDay: false, counterpartState: 'notObserved',
  });
  assert.equal(formatDelta(undefined, 'clicks').counterpartState, 'notRequested');
  assert.equal(formatDelta({ counterpartState: 'failed' }, 'clicks', { stateTexts: { failed: 'Vergleich fehlgeschlagen' } }).text, 'Vergleich fehlgeschlagen');
});

test('locale', () => {
  assert.equal(formatDelta({ ...observed, clicksDelta: 1234 }, 'clicks', { locale: 'de' }).text, '+1.234');
});
