// Runtime import of entry `.` in Node: pure functions only, no React and no stylesheet are loaded.
const main = await import('@mgamadeus/ddd-site-insights');

const expected = ['windowPresets', 'computePresetWindow', 'buildWindowLabel', 'addDays', 'subtractMonthsClamped', 'daysInclusive', 'isValidDay', 'DEFAULT_WINDOW_PRESET'];
const missing = expected.filter(name => !(name in main));
if (missing.length > 0) {
  console.error(`missing exports: ${missing.join(', ')}`);
  process.exit(1);
}
const window = main.computePresetWindow('28d', '2026-11-13');
if (window.from !== '2026-10-17' || window.days !== 28) {
  console.error(`unexpected window ${JSON.stringify(window)}`);
  process.exit(1);
}
console.log(`ok      main entry exports ${Object.keys(main).length} names`);
