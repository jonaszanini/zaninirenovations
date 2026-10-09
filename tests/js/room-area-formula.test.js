'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const lib = require('../../public/js/room-area-lib.js');

const cases = JSON.parse(fs.readFileSync(path.join(__dirname, 'fixtures/room-area-formula-cases.json'), 'utf8'));
const vars = lib.formulaVariablesFromMetrics(cases.variables);

const libSrc = fs.readFileSync(path.join(__dirname, '../../public/js/room-area-lib.js'), 'utf8');
assert.ok(!/\beval\s*\(/.test(libSrc), 'room-area-lib.js must not call eval');
assert.ok(!/new Function\s*\(/.test(libSrc), 'room-area-lib.js must not use new Function');

cases.ok.forEach(function (row) {
  const result = lib.evaluateFormula(row.formula, vars);
  assert.strictEqual(result.ok, true, row.formula + ' should succeed: ' + result.error);
  assert.ok(Math.abs(result.value - row.value) < 1e-9, row.formula + ' => ' + result.value + ' expected ' + row.value);
});

cases.round.forEach(function (row) {
  const qty = lib.applyFormulaRounding(row.value, row.whole);
  assert.ok(Math.abs(qty - row.qty) < 1e-9, 'round ' + row.value + ' whole=' + row.whole + ' => ' + qty + ' expected ' + row.qty);
});

cases.errors.forEach(function (row) {
  const result = lib.evaluateFormula(row.formula, vars);
  assert.strictEqual(result.ok, false, JSON.stringify(row.formula) + ' should fail');
  assert.ok(
    String(result.error || '').indexOf(row.contains) !== -1,
    JSON.stringify(row.formula) + ' error "' + result.error + '" should contain "' + row.contains + '"'
  );
});

const drywall = lib.evaluateAssemblyLine(
  { formula: 'ceil(net_wall_sf * 1.1 / 32)', whole_units: true, unit: 'sheet' },
  cases.variables
);
assert.strictEqual(drywall.ok, true);
assert.strictEqual(drywall.qty, 10);

console.log('room-area formula tests: ok');
