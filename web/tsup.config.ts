import { defineConfig } from 'tsup';

// One entry for now: `.` (generated types and pure window logic, server-callable, no React, no stylesheet). The
// screens become a second entry `./screens` with the 'use client' banner. Dependencies and peers stay external;
// `scripts/check-package.mjs` verifies the entry after every build.
export default defineConfig({
  entry: {
    index: 'src/index.ts',
  },
  format: ['esm'],
  dts: true,
  sourcemap: true,
  splitting: true,
  clean: false,
  target: 'es2022',
  platform: 'neutral',
  minify: false,
});
