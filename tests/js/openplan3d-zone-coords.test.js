'use strict';

const assert = require('assert');
const path = require('path');
const { execFileSync } = require('child_process');

const HELPER = path.join(__dirname, 'openplan3d-inch-drag-helper.js');

function runZoneCorners() {
  const out = execFileSync(process.execPath, [HELPER, 'zone-corners'], { encoding: 'utf8', timeout: 180000 }).trim();
  const line = out.split('\n').filter(Boolean).pop();
  const result = JSON.parse(line);
  assert.strictEqual(result.ok, true, result.error || 'zone-corners failed');
  return result;
}

const result = runZoneCorners();
assert.ok(Array.isArray(result.cases), 'must report view cases');
assert.strictEqual(result.cases.length, 3, 'default, zoomed, and panned views');
console.log('openplan3d-zone-coords: ok');
