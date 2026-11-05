#!/usr/bin/env node
'use strict';

/**
 * Guard: picomatch workflow filters must classify every tracked repo file
 * (except docs) as matching at least one filter OR intentionally unknown.
 */
const { execFileSync } = require('child_process');
const path = require('path');
const { loadFilters, matchAnyWorkflowFilter, isDocPath } = require('./match-workflow-filters.js');

const ROOT = path.resolve(__dirname, '../..');

const files = execFileSync('git', ['ls-files'], { cwd: ROOT, encoding: 'utf8' })
  .split('\n')
  .filter(Boolean);

const filters = loadFilters();
let unknown = 0;
const samples = [];

for (const file of files) {
  if (isDocPath(file)) {
    continue;
  }
  if (!matchAnyWorkflowFilter(file, filters)) {
    unknown += 1;
    if (samples.length < 5) {
      samples.push(file);
    }
  }
}

// Unknown paths are OK for fail-open; we only fail on pathMatchesGlob internal errors.
// Spot-check patterns Tester called out:
const checks = [
  ['app/Services/RoomAreaFinishMath.php', 'app/**/RoomArea*.php', true],
  ['tests/js/admin-hotkey-save-browser.test.js', 'tests/js/admin-*', true],
  ['public/js/admin-x/y.js', 'public/js/admin-**', false],
  ['resources/views/admin/dashboard/index.blade.php', 'seo', false],
];

const { pathMatchesGlob, matchFiltersForPath } = require('./match-workflow-filters.js');
let failed = 0;
for (const [file, glob, expect] of checks) {
  let got;
  if (glob === 'seo') {
    got = matchFiltersForPath(file, filters).seo;
  } else {
    got = pathMatchesGlob(file, glob);
  }
  if (got !== expect) {
    console.error(`check failed ${file} vs ${glob}: expected ${expect}, got ${got}`);
    failed += 1;
  }
}

if (failed) {
  process.exit(1);
}
console.log(
  `picomatch-tracked-files-audit: ok (${files.length} tracked, ${unknown} unclassified non-doc paths for fail-open)`,
);
if (samples.length) {
  console.log('sample unclassified:', samples.join(', '));
}
