#!/usr/bin/env node
'use strict';

/**
 * Match changed paths against tests/ci/workflow-filters.json (same globs as dorny paths-filter).
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const FILTERS_PATH = path.join(__dirname, 'workflow-filters.json');

function globToRegExp(glob) {
  if (glob.startsWith('!')) {
    return null;
  }
  if (!glob.includes('*')) {
    return new RegExp('^' + glob.replace(/[.+^${}()|[\]\\]/g, '\\$&') + '$');
  }
  if (glob.endsWith('/**') && glob.split('**').length === 2) {
    const prefix = glob.slice(0, -3);
    const esc = prefix.replace(/[.+^${}()|[\]\\]/g, '\\$&');
    return new RegExp('^' + esc + '(?:/.*)?$');
  }
  const segments = glob.split('/');
  const reParts = segments.map((seg) => {
    if (seg === '**') {
      return '(?:.+/)?';
    }
    if (seg.includes('**')) {
      return seg
        .split('**')
        .map((part) => part.replace(/[.+^${}()|[\]\\]/g, '\\$&').replace(/\\\*/g, '[^/]*'))
        .join('.*');
    }
    if (seg.endsWith('**') && seg !== '**') {
      const prefix = seg.slice(0, -2).replace(/[.+^${}()|[\]\\]/g, '\\$&');
      return prefix + '.*';
    }
    let s = seg.replace(/[.+^${}()|[\]\\]/g, '\\$&');
    s = s.replace(/\\\*/g, '[^/]*');
    return s;
  });
  return new RegExp('^' + reParts.join('/') + '$');
}

function pathMatchesGlob(filePath, glob) {
  const re = globToRegExp(glob);
  if (!re) {
    return false;
  }
  return re.test(filePath);
}

function loadFilters() {
  return JSON.parse(fs.readFileSync(FILTERS_PATH, 'utf8'));
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

function matchAnyWorkflowFilter(filePath, filters) {
  for (const [name, patterns] of Object.entries(filters)) {
    if (name.startsWith('_') || name === 'code') {
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

function computeBroadRun(changedPaths, filters) {
  for (const p of changedPaths) {
    if (!p || isDocPath(p)) {
      continue;
    }
    if (!matchAnyWorkflowFilter(p, filters)) {
      return true;
    }
  }
  return false;
}

if (require.main === module) {
  const args = process.argv.slice(2);
  const filters = loadFilters();
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
  if (args[0] === '--aggregate' && args[1]) {
    const paths = fs.readFileSync(args[1], 'utf8').split('\n').filter(Boolean);
    console.log(JSON.stringify(aggregateFilterHits(paths, filters), null, 2));
    process.exit(0);
  }
  console.error('Usage: match-workflow-filters.mjs --path <file> [--expect-any]');
  console.error('       match-workflow-filters.mjs --aggregate <changed-files.txt>');
  process.exit(2);
}

module.exports = {
  loadFilters,
  pathMatchesGlob,
  matchFiltersForPath,
  aggregateFilterHits,
  envFromAggregate,
  computeBroadRun,
  matchAnyWorkflowFilter,
  isDocPath,
};
