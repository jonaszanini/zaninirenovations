#!/usr/bin/env node
'use strict';

/**
 * Ensure workflow-filters.json patterns appear in tests.yml (drift guard).
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const yaml = fs.readFileSync(path.join(ROOT, '.github/workflows/tests.yml'), 'utf8');
const filters = JSON.parse(fs.readFileSync(path.join(__dirname, 'workflow-filters.json'), 'utf8'));

let failed = 0;
for (const [name, patterns] of Object.entries(filters)) {
  if (name.startsWith('_')) {
    continue;
  }
  for (const pattern of patterns) {
    if (!yaml.includes(pattern)) {
      console.error(`MISSING in tests.yml [${name}]: ${pattern}`);
      failed += 1;
    }
  }
}

if (failed) {
  process.exit(1);
}
console.log('verify-workflow-filters: ok');
