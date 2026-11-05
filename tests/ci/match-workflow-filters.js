#!/usr/bin/env node
'use strict';

/**
 * Match changed paths against tests/ci/workflow-filters.json using picomatch
 * (same semantics as dorny/paths-filter / GitHub Actions).
 */
const fs = require('fs');
const path = require('path');
const picomatch = require('picomatch');

const FILTERS_PATH = path.join(__dirname, 'workflow-filters.json');

/** Filters that tag MySQL/SEO only — must not suppress fail-open for browser or unknown paths. */
const CLASSIFICATION_IGNORE = new Set(['seo']);

const BROWSER_FILTER_KEYS = [
  'browser_js',
  'browser_admin',
  'browser_openplan3d',
  'browser_quotation',
  'browser_client',
];

/** Map aggregate filter keys → compute-ci-flags env vars (OR with dorny outputs). */
const FILTER_ENV_BY_AGG = [
  ['FILTER_GLOBAL', 'global_test_deps'],
  ['FILTER_ADMIN_SHELL', 'admin_shell'],
  ['FILTER_SP', 'shared_prices_geometry'],
  ['FILTER_RA', 'room_area_live'],
  ['FILTER_BL', 'blog_live'],
  ['FILTER_MF', 'millwork_fuzz'],
  ['FILTER_BROWSER_JS', 'browser_js'],
  ['FILTER_BROWSER_ADMIN', 'browser_admin'],
  ['FILTER_BROWSER_OPENPLAN3D', 'browser_openplan3d'],
  ['FILTER_BROWSER_QUOTATION', 'browser_quotation'],
  ['FILTER_BROWSER_CLIENT', 'browser_client'],
  ['FILTER_ROUTES', 'routes_smoke'],
  ['FILTER_SEO', 'seo'],
];

function loadFilters() {
  return JSON.parse(fs.readFileSync(FILTERS_PATH, 'utf8'));
}

function compilePattern(glob) {
  if (typeof glob !== 'string' || glob.startsWith('!')) {
    return null;
  }
  return picomatch(glob, { dot: true });
}

function pathMatchesGlob(filePath, glob) {
  const fn = compilePattern(glob);
  if (!fn) {
    return false;
  }
  return fn(filePath);
}

function matchFiltersForPath(filePath, filters) {
  const hit = {};
  for (const [name, patterns] of Object.entries(filters)) {
    if (name.startsWith('_')) {
      continue;
    }
    hit[name] = patterns.some((p) => pathMatchesGlob(filePath, p));
  }
  return hit;
}

function matchAnyWorkflowFilter(filePath, filters, ignoreFilters = CLASSIFICATION_IGNORE) {
  for (const [name, patterns] of Object.entries(filters)) {
    if (name.startsWith('_') || name === 'code' || ignoreFilters.has(name)) {
      continue;
    }
    for (const p of patterns) {
      if (pathMatchesGlob(filePath, p)) {
        return true;
      }
    }
  }
  return false;
}

function aggregateFilterHits(changedPaths, filters) {
  const out = {};
  for (const name of Object.keys(filters)) {
    if (name.startsWith('_')) {
      continue;
    }
    out[name] = false;
  }
  for (const filePath of changedPaths) {
    const hits = matchFiltersForPath(filePath, filters);
    for (const [name, val] of Object.entries(hits)) {
      if (val) {
        out[name] = true;
      }
    }
  }
  return out;
}

function envFromAggregate(agg) {
  const env = {};
  env.FILTER_GLOBAL = agg.global_test_deps ? 'true' : 'false';
  env.FILTER_ADMIN_SHELL = agg.admin_shell ? 'true' : 'false';
  env.FILTER_SP = agg.shared_prices_geometry ? 'true' : 'false';
  env.FILTER_RA = agg.room_area_live ? 'true' : 'false';
  env.FILTER_BL = agg.blog_live ? 'true' : 'false';
  env.FILTER_MF = agg.millwork_fuzz ? 'true' : 'false';
  env.FILTER_BROWSER_JS = agg.browser_js ? 'true' : 'false';
  env.FILTER_BROWSER_ADMIN = agg.browser_admin ? 'true' : 'false';
  env.FILTER_BROWSER_OPENPLAN3D = agg.browser_openplan3d ? 'true' : 'false';
  env.FILTER_BROWSER_QUOTATION = agg.browser_quotation ? 'true' : 'false';
  env.FILTER_BROWSER_CLIENT = agg.browser_client ? 'true' : 'false';
  env.FILTER_ROUTES = agg.routes_smoke ? 'true' : 'false';
  env.FILTER_SEO = agg.seo ? 'true' : 'false';
  return env;
}

function isDocPath(p) {
  return p.endsWith('.md') || p.startsWith('docs/') || p.startsWith('lang/');
}

function pathIsClassifiedForBroadRun(filePath, filters) {
  if (matchAnyWorkflowFilter(filePath, filters, CLASSIFICATION_IGNORE)) {
    return true;
  }
  const hits = matchFiltersForPath(filePath, filters);
  if (hits.seo) {
    return true;
  }
  return false;
}

function computeBroadRun(changedPaths, filters) {
  for (const p of changedPaths) {
    if (!p || isDocPath(p)) {
      continue;
    }
    if (!pathIsClassifiedForBroadRun(p, filters)) {
      return true;
    }
  }
  return false;
}

/** Admin (or other) Blade with no browser-domain filter → run all browser suites. */
function computeBrowserFailOpen(changedPaths, filters) {
  for (const p of changedPaths) {
    if (!p || isDocPath(p)) {
      continue;
    }
    if (!p.startsWith('resources/views/admin/')) {
      continue;
    }
    const hits = matchFiltersForPath(p, filters);
    const browserHit = BROWSER_FILTER_KEYS.some((k) => hits[k]);
    if (!browserHit) {
      return true;
    }
  }
  return false;
}

function readChangedPathsFile(filePath) {
  return fs.readFileSync(filePath, 'utf8').split('\n').filter(Boolean);
}

function analyzeChangedFiles(changedPaths, filters) {
  const aggregate = aggregateFilterHits(changedPaths, filters);
  return {
    broadRun: computeBroadRun(changedPaths, filters),
    browserFailOpen: computeBrowserFailOpen(changedPaths, filters),
    aggregate,
  };
}

function emitAnalyzeChangedStdout(analysis) {
  console.log(`broad_run=${analysis.broadRun}`);
  console.log(`browser_fail_open=${analysis.browserFailOpen}`);
  for (const [envKey, aggKey] of FILTER_ENV_BY_AGG) {
    if (analysis.aggregate[aggKey]) {
      console.log(`${envKey}=true`);
    }
  }
}
if (require.main === module) {
  const args = process.argv.slice(2);
  const filters = loadFilters();
  if (args[0] === '--analyze-changed' && args[1]) {
    const paths = readChangedPathsFile(args[1]);
    emitAnalyzeChangedStdout(analyzeChangedFiles(paths, filters));
    process.exit(0);
  }
  if (args[0] === '--path' && args[1]) {
    const p = args[1];
    if (args[2] === '--expect-any') {
      process.exit(matchAnyWorkflowFilter(p, filters) ? 0 : 1);
    }
    console.log(JSON.stringify(matchFiltersForPath(p, filters), null, 2));
    process.exit(0);
  }
  if (args[0] === '--broad-file' && args[1]) {
    const paths = fs.readFileSync(args[1], 'utf8').split('\n').filter(Boolean);
    process.exit(computeBroadRun(paths, filters) ? 0 : 1);
  }
  if (args[0] === '--browser-fail-open-file' && args[1]) {
    const paths = fs.readFileSync(args[1], 'utf8').split('\n').filter(Boolean);
    process.exit(computeBrowserFailOpen(paths, filters) ? 0 : 1);
  }
  if (args[0] === '--aggregate' && args[1]) {
    const paths = fs.readFileSync(args[1], 'utf8').split('\n').filter(Boolean);
    console.log(JSON.stringify(aggregateFilterHits(paths, filters), null, 2));
    process.exit(0);
  }
  console.error('Usage: match-workflow-filters.js --path <file> [--expect-any]');
  console.error('       match-workflow-filters.js --broad-file <changed-files.txt>');
  console.error('       match-workflow-filters.js --analyze-changed <changed-files.txt>');
  process.exit(2);
}

module.exports = {
  loadFilters,
  pathMatchesGlob,
  compilePattern,
  matchFiltersForPath,
  aggregateFilterHits,
  envFromAggregate,
  pathIsClassifiedForBroadRun,
  computeBroadRun,
  computeBrowserFailOpen,
  analyzeChangedFiles,
  emitAnalyzeChangedStdout,
  matchAnyWorkflowFilter,
  isDocPath,
  CLASSIFICATION_IGNORE,
  BROWSER_FILTER_KEYS,
  FILTER_ENV_BY_AGG,
};
