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

(cases.overrides || []).forEach(function (row) {
  const local = lib.formulaVariablesFromMetrics(Object.assign({}, cases.variables, row.variables || {}));
  const result = lib.evaluateFormula(row.formula, local);
  assert.strictEqual(result.ok, true, row.formula + ' override should succeed: ' + result.error);
  assert.ok(Math.abs(result.value - row.value) < 1e-9, row.formula + ' override => ' + result.value + ' expected ' + row.value);
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

const mixed = lib.formulaVariablesFromMetrics({
  area_sqft: 132,
  inside_area_sqft: 132,
  inside_corners: 4,
  wall_height_ft: null,
});
assert.strictEqual(mixed.inside_area_sqft, 132);
assert.strictEqual(mixed.inside_corners, 4);
assert.strictEqual(mixed.wall_height_ft, 8);

[
  { wall_height_ft: null, wall_height: null },
  { wall_height_ft: '', wall_height: '' },
  { wall_height_ft: 'abc', wall_height: 'abc' },
  { wall_height_ft: ' ', wall_height: ' ' },
  { wall_height_ft: '0x10', wall_height: '0x10' },
  { wall_height_ft: true, wall_height: true },
  { wall_height_ft: [7], wall_height: [7] },
].forEach(function (row) {
  const heightVars = lib.formulaVariablesFromMetrics(row);
  assert.strictEqual(heightVars.wall_height_ft, 8, JSON.stringify(row) + ' should fall back to 8');
  assert.strictEqual(heightVars.wall_height, 8, JSON.stringify(row) + ' alias should fall back to 8');
});

const nbspEight = lib.formulaVariablesFromMetrics({ wall_height_ft: '\u00A08', wall_height: '\u00A08' });
assert.strictEqual(nbspEight.wall_height_ft, 8, 'NBSP before 8 is 8');
const nbspNine = lib.formulaVariablesFromMetrics({ wall_height_ft: '\u00A09', wall_height: '\u00A09' });
assert.strictEqual(nbspNine.wall_height_ft, 9, 'NBSP before 9 is 9');

const drywall = lib.evaluateAssemblyLine(
  { formula: 'ceil(net_wall_sf * 1.12 / 32)', whole_units: true, unit: 'sheet' },
  cases.variables
);
assert.strictEqual(drywall.ok, true);
assert.strictEqual(drywall.qty, 11);

console.log('room-area formula tests: ok');
