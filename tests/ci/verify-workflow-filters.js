#!/usr/bin/env node
'use strict';

/**
 * Parse dorny filter blocks from tests.yml and compare to workflow-filters.json.
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const yamlPath = path.join(ROOT, '.github/workflows/tests.yml');
const jsonPath = path.join(__dirname, 'workflow-filters.json');

function parseYamlFilters(yaml) {
  const start = yaml.indexOf('filters: |');
  if (start < 0) {
    throw new Error('filters: | block not found in tests.yml');
  }
  const block = yaml.slice(start);
  const end = block.search(/\n\s{6,}[a-z_]+:\s*\n\s+env:/);
  const section = end > 0 ? block.slice(0, end) : block.split('\n      - id: flags')[0];
  const lines = section.split('\n');
  const filters = {};
  let current = null;
  for (const line of lines) {
    const nameMatch = line.match(/^\s{12}([a-z0-9_]+):\s*$/);
    if (nameMatch) {
      current = nameMatch[1];
      filters[current] = [];
      continue;
    }
    const patMatch = line.match(/^\s+- '(.+)'\s*$/);
    if (patMatch && current) {
      filters[current].push(patMatch[1]);
    }
  }
  delete filters.code;
  return filters;
}

const yaml = fs.readFileSync(yamlPath, 'utf8');
const fromYaml = parseYamlFilters(yaml);
const fromJson = JSON.parse(fs.readFileSync(jsonPath, 'utf8'));

let failed = 0;
const yamlKeys = Object.keys(fromYaml).sort();
const jsonKeys = Object.keys(fromJson).filter((k) => !k.startsWith('_')).sort();
if (yamlKeys.join(',') !== jsonKeys.join(',')) {
  console.error('Filter key mismatch');
  console.error('yaml only:', yamlKeys.filter((k) => !jsonKeys.includes(k)));
  console.error('json only:', jsonKeys.filter((k) => !yamlKeys.includes(k)));
  failed += 1;
}

for (const key of jsonKeys) {
  const yp = (fromYaml[key] || []).slice().sort();
  const jp = (fromJson[key] || []).slice().sort();
  if (yp.join('\n') !== jp.join('\n')) {
    console.error(`Pattern list mismatch for filter "${key}"`);
    for (const p of jp) {
      if (!yp.includes(p)) {
        console.error(`  missing in yaml: ${p}`);
        failed += 1;
      }
    }
    for (const p of yp) {
      if (!jp.includes(p)) {
        console.error(`  missing in json: ${p}`);
        failed += 1;
      }
    }
  }
}

if (failed) {
  process.exit(1);
}
console.log('verify-workflow-filters: structural ok');
