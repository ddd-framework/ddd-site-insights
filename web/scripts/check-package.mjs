#!/usr/bin/env node
// Checks a package directory (this directory after `pnpm build`, or an extracted tarball's `package/`):
//   1. every `exports` target exists
//   2. the main entry (`.`) reaches no React, no Redux runtime, no stylesheet and no Tailwind through its static
//      imports (server-callable)
//   3. no test file and no codegen placeholder is in `dist`
//
// Usage: node scripts/check-package.mjs <packageDir>
import * as fs from 'node:fs';
import * as path from 'node:path';

const packageDir = path.resolve(process.argv[2] ?? '.');
const manifest = JSON.parse(fs.readFileSync(path.join(packageDir, 'package.json'), 'utf-8'));
const problems = [];

/** Every string leaf of an `exports` value (conditions and subpaths flattened). */
const targets = value =>
  typeof value === 'string' ? [value] : value && typeof value === 'object' ? Object.values(value).flatMap(targets) : [];

for (const target of targets(manifest.exports ?? {})) {
  const exists = fs.existsSync(path.join(packageDir, target));
  console.log(`${exists ? 'ok     ' : 'MISSING'} exports ${target}`);
  if (!exists) problems.push(`missing exports target ${target}`);
}

const IMPORT_PATTERN = /(?:^|[\s;])(?:import|export)\s*(?:[^'"()]*?\sfrom\s*)?['"]([^'"]+)['"]/g;

/** Specifiers of the static imports and re-exports of a built file. */
const staticSpecifiers = file =>
  [...fs.readFileSync(file, 'utf-8').matchAll(IMPORT_PATTERN)].map(match => match[1]);

/** Everything a built entry loads statically: its own files (followed) and the package specifiers it names. */
const loadGraph = entry => {
  const files = new Set();
  const packages = new Set();
  const queue = [path.join(packageDir, entry)];
  while (queue.length > 0) {
    const file = queue.pop();
    if (files.has(file) || !fs.existsSync(file)) continue;
    files.add(file);
    for (const specifier of staticSpecifiers(file)) {
      if (specifier.startsWith('.')) queue.push(path.resolve(path.dirname(file), specifier));
      else packages.add(specifier);
    }
  }
  return { files, packages };
};

const mainEntry = manifest.exports?.['.']?.import;
if (mainEntry && fs.existsSync(path.join(packageDir, mainEntry))) {
  const main = loadGraph(mainEntry);
  const forbidden = [...main.packages].filter(specifier =>
    /^(react|react-dom|react-redux|tailwindcss|@reduxjs\/toolkit)(\/|$)/.test(specifier) || specifier.endsWith('.css'),
  );
  console.log(`${forbidden.length === 0 ? 'ok     ' : 'FAILED '} main entry loads no React, Redux runtime, Tailwind or stylesheet`);
  if (forbidden.length > 0) problems.push(`the main entry loads ${forbidden.join(', ')}`);
  const cssFiles = [...main.files].filter(file => file.endsWith('.css'));
  if (cssFiles.length > 0) problems.push(`the main entry loads stylesheets: ${cssFiles.join(', ')}`);
}

const distDir = path.join(packageDir, 'dist');
if (fs.existsSync(distDir)) {
  const distFiles = fs.readdirSync(distDir, { recursive: true }).map(String);
  const testFiles = distFiles.filter(file => /\.test\./.test(file));
  console.log(`${testFiles.length === 0 ? 'ok     ' : 'FAILED '} no test files in dist`);
  if (testFiles.length > 0) problems.push(`test files in dist: ${testFiles.join(', ')}`);
  const placeholderFiles = distFiles.filter(file => file.endsWith('.js') && fs.readFileSync(path.join(distDir, file), 'utf-8').includes('siteInsightsCodegen'));
  console.log(`${placeholderFiles.length === 0 ? 'ok     ' : 'FAILED '} the codegen base api is not bundled`);
  if (placeholderFiles.length > 0) problems.push(`codegen placeholder in ${placeholderFiles.join(', ')}`);
}

if (problems.length > 0) {
  for (const problem of problems) console.error(`${packageDir}: ${problem}`);
  process.exit(1);
}
