# @mgamadeus/ddd-site-insights

Frontend of the site insights module for backends built with [mgamadeus/ddd](https://github.com/ddd-framework/ddd)
(Composer package [`mgamadeus/ddd-site-insights`](https://github.com/ddd-framework/ddd-site-insights), same version).

Entry `.` holds the TypeScript types generated from the module's API and pure window logic. It loads no React and no
stylesheet and can run on a server. The API factory and the screens follow in later versions.

## Install

```bash
npm install @mgamadeus/ddd-site-insights
```

ESM only. Peer dependencies: `@mgamadeus/ddd-web` `>=0.13.0 <1`, `@reduxjs/toolkit` `^2.6.0`, `react` `^19.0.0`,
`react-redux` `^9.0.0`.

## Window logic

```ts
import { buildWindowLabel, computePresetWindow, windowPresets } from '@mgamadeus/ddd-site-insights';

const window = computePresetWindow('28d', '2026-11-13', '2025-07-19');
// { from: '2026-10-17', to: '2026-11-13', days: 28, preset: '28d', windowClamped: false }
buildWindowLabel(window); // 'Last 28 days · 2026-10-17 – 2026-11-13'
```

Days are `YYYY-MM-DD` in the provider's day zone; the arithmetic matches the backend's.

## Generated types

`src/generated/` is generated from `schema/site-insights.openapi.json`, which the PHP schema test writes. After an API
change: `SITE_INSIGHTS_WRITE_SCHEMA=1 vendor/bin/phpunit tests/Schema` in the repository root, then `pnpm generate`.
CI fails when the two drift (`check:generated`).

## License

MIT
