'use strict';

const assert = require('assert');
const lib = require('../../public/js/room-area-lib.js');

const FT = lib.FEET_TO_METERS;
const delta = 0.0001;

function almost(actual, expected, msg) {
  assert.ok(Math.abs(actual - expected) < delta, msg || (actual + ' !~ ' + expected));
}

function rejects(raw, unit) {
  assert.throws(function () {
    lib.parseLengthInput(raw, unit || 'feet_inch_frac');
  }, Error);
}

// --- D2 parser cases from QA ---
almost(lib.parseLengthInput('3810mm', 'meter'), 3.81 / FT, '3810mm is millimetres');
almost(lib.parseLengthInput('3810 mm', 'feet_decimal'), 3.81 / FT, '3810 mm with space');
almost(lib.parseLengthInput('12ft 6in', 'meter'), 12.5, '12ft 6in without spaces around units');
almost(lib.parseLengthInput('12 ft 6 in', 'meter'), 12.5, '12 ft 6 in spaced');
almost(lib.parseLengthInput('5 m 20 cm', 'feet_inch_frac'), 5.2 / FT, '5 m 20 cm combined');
almost(lib.parseLengthInput('5m 20cm', 'inch'), 5.2 / FT, '5m 20cm no spaces');
almost(lib.parseLengthInput('3,81', 'meter'), 3.81 / FT, 'comma is a decimal point in metric');
almost(lib.parseLengthInput('3,81 m', 'feet_inch_frac'), 3.81 / FT, '3,81 m');
almost(lib.parseLengthInput('3,81 mm', 'meter'), 0.00381 / FT, '3,81 mm stays a decimal millimetre');
almost(lib.parseLengthInput('3.81 mm', 'meter'), 0.00381 / FT, '3.81 mm stays a decimal millimetre');
almost(lib.parseLengthInput('12.5', 'feet_decimal'), 12.5, 'decimal feet');
almost(lib.parseLengthInput("12' 6\"", 'meter'), 12.5, 'explicit feet-inch wins over metric');
almost(lib.parseLengthInput('150"', 'meter'), 12.5, '150" is inches');
almost(lib.parseLengthInput('150″', 'feet_inch_frac'), 12.5, 'unicode double-prime inches');
almost(lib.parseLengthInput('12′ 6″', 'meter'), 12.5, 'unicode prime feet-inches');
almost(lib.parseLengthInput('6½', 'inch'), 6.5 / 12, 'vulgar 6½ is 6.5 inches, not 61/2');
almost(lib.parseLengthInput('6½"', 'feet_inch_frac'), 6.5 / 12, '6½"');
almost(lib.parseLengthInput("12' ½\"", 'feet_inch_frac'), 12 + 0.5 / 12, "12' ½\"");
almost(lib.parseLengthInput('3,810 mm', 'meter'), 3.81 / FT, '3,810 mm thousands comma');
almost(lib.parseLengthInput('3.810 mm', 'feet_decimal'), 3.81 / FT, '3.810 mm thousands dot');
almost(lib.parseLengthInput('12’ 6”', 'meter'), 12.5, 'iOS smart quotes 12’ 6”');

rejects('12abc', 'meter');
rejects('12abc', 'feet_inch_frac');
rejects('1e9', 'meter');
rejects('1e9', 'feet_decimal');
rejects('6 1/0"', 'feet_inch_frac');
rejects('0', 'feet_inch_frac');
rejects('', 'meter');

// Bare integer in feet-inch-frac is inches
almost(lib.parseLengthInput('150', 'feet_inch_frac'), 12.5, 'bare 150 is inches');

// --- D1 wall height store feet / display metres ---
almost(lib.parseWallHeight('2.44', true).feet, 2.44 / FT, 'metric 2.44 stores as feet');
almost(lib.parseWallHeight('8', false).feet, 8, 'imperial 8 stays feet');
assert.ok(lib.parseWallHeight('8', true).warning, '8 in metric is out of range and warns');
assert.strictEqual(lib.parseWallHeight('8', true).feet, null, '8 in metric does not clamp to 20');
assert.ok(lib.parseWallHeight('junk', false).warning, 'junk wall height warns');
assert.strictEqual(lib.parseWallHeight('junk', false).feet, null, 'junk does not fall back to 8');
almost(lib.clampWallHeightFt(2.44 / FT), 8.01, '2.44 m clamps near 8 ft');
var shown = lib.wallHeightDisplay(8, true);
assert.ok(Math.abs(Number(shown) - 8 * FT) < 0.01, 'display 8 ft as metres: ' + shown);
assert.strictEqual(lib.wallHeightDisplay(8, false), '8');

// --- D5 templates ---
var l = lib.shapeFromKind('l', 16, 12);
almost(lib.polygonArea(l), 152, 'L 16×12 with 6×4 return is 152');
assert.strictEqual(l.length, 6);

var lSmall = lib.lFromOverall(16, 12);
almost(lib.polygonArea(lSmall), 152, 'lFromOverall 16×12 = 152');

var t = lib.shapeFromKind('t', 16, 12);
almost(lib.polygonArea(t), 16 * 4 + 6 * 8, 'T uses typed L as overall height');
var bboxH = Math.max.apply(null, t.map(function (p) { return p.y; }));
almost(bboxH, 12, 'T overall height is typed L');

var t10 = lib.tFromOverall(16, 10);
var t10h = Math.max.apply(null, t10.map(function (p) { return p.y; }));
almost(t10h, 10, 'T typed L=10 is overall height');

var rect = lib.shapeFromKind('rect', 12, 10);
almost(lib.polygonArea(rect), 120, 'rect 12×10');

// --- D3 alcove outward on U / L / T ---
function areaChange(shape, wall, w, d) {
  var before = lib.polygonArea(shape);
  var after = lib.polygonArea(lib.insertAlcove(shape, wall, w, d, true));
  return after - before;
}

var u = lib.uShape(16, 12, 8, 8);
almost(areaChange(u, 4, 4, 2), 8, 'U inner bottom alcove adds 8');
almost(areaChange(u, 3, 4, 2), 8, 'U inner right alcove adds 8');
almost(areaChange(u, 5, 4, 2), 8, 'U inner left alcove adds 8');

var lRoom = lib.lFromOverall(16, 12);
almost(areaChange(lRoom, 3, 4, 2), 7.2, 'L wall 3 alcove adds 7.2');

var tRoom = lib.tFromOverall(16, 12);
almost(areaChange(tRoom, 2, 4, 2), 8, 'T wall 2 alcove adds 8');
almost(areaChange(tRoom, 6, 4, 2), 8, 'T wall 6 alcove adds 8');

var r = lib.rectangle(12, 10);
almost(areaChange(r, 0, 4, 2), 8, 'rectangle alcove still adds 8');

console.log('room-area-lib tests: ' + process.uptime().toFixed(3) + 's ok');
