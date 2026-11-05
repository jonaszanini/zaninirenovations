#!/usr/bin/env node
'use strict';

/**
 * Every tests/js *browser* spec must appear in a tests/ci/js-browser-*.txt manifest.
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const jsDir = path.join(ROOT, 'tests/js');
const ciDir = __dirname;

function listBrowserSpecs() {
  const out = [];
  for (const name of fs.readdirSync(jsDir)) {
    if (!/browser/i.test(name)) {
      continue;
    }
    if (!/\.(test\.js|test\.mjs)$/.test(name)) {
      continue;
    }
    out.push(`tests/js/${name}`);
  }
  for (const sub of fs.readdirSync(jsDir, { withFileTypes: true })) {
    if (!sub.isDirectory()) {
      continue;
    }
    for (const name of fs.readdirSync(path.join(jsDir, sub.name))) {
      if (!/browser/i.test(name)) {
        continue;
      }
      if (!/\.(test\.js|test\.mjs)$/.test(name)) {
        continue;
      }
      out.push(`tests/js/${sub.name}/${name}`);
    }
  }
  return [...new Set(out)].sort();
}

function readManifests() {
  const listed = new Set();
  for (const name of fs.readdirSync(ciDir)) {
    if (!name.startsWith('js-browser-') || !name.endsWith('.txt')) {
      continue;
    }
    const lines = fs.readFileSync(path.join(ciDir, name), 'utf8').split('\n');
    for (const line of lines) {
      const t = line.trim();
      if (t && !t.startsWith('#')) {
        listed.add(t);
      }
    }
  }
  return listed;
}

const specs = listBrowserSpecs();
const listed = readManifests();
let failed = 0;
for (const spec of specs) {
  if (!listed.has(spec)) {
    console.error(`MISSING from js-browser-*.txt: ${spec}`);
    failed += 1;
  }
}

for (const entry of listed) {
  if (!entry.includes('browser') && !entry.endsWith('.test.mjs')) {
    continue;
  }
  const abs = path.join(ROOT, entry);
  if (!fs.existsSync(abs)) {
    console.error(`Manifest lists missing file: ${entry}`);
    failed += 1;
  }
}

if (failed) {
  process.exit(1);
}
console.log(`verify-browser-manifest: ok (${specs.length} browser specs listed)`);
