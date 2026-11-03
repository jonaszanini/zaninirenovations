'use strict';

const assert = require('assert');
const { execFileSync } = require('child_process');
const path = require('path');

const HELPER = path.join(__dirname, 'openplan3d-inch-drag-helper.js');

function runMode(mode) {
  const out = execFileSync(process.execPath, [HELPER, mode], { encoding: 'utf8', timeout: 180000 }).trim();
  const line = out.split('\n').filter(Boolean).pop();
  const result = JSON.parse(line);
  assert.strictEqual(result.ok, true, `${mode}: ${result.error || 'failed'}`);
  return result;
}

for (const ortho of ['ortho0', 'ortho45', 'ortho90']) {
  const r = runMode(ortho);
  assert.ok(r.lengthIn > 0, ortho + ' must report lengthIn');
}

console.log('openplan3d-kr-ortho-drag: ok');
