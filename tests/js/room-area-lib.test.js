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
almost(lib.parseLengthInput('3.810 mm', 'feet_decimal'), 0.00381 / FT, '3.810 mm is a decimal millimetre');
almost(lib.parseLengthInput('1.700 mm', 'meter'), 0.0017 / FT, '1.700 mm is a decimal millimetre');
almost(lib.parseLengthInput('10.000 mm', 'meter'), 0.01 / FT, '10.000 mm is a decimal millimetre');
almost(lib.parseLengthInput('3.810,5 mm', 'meter'), 3.8105 / FT, '3.810,5 mm is European thousands + decimal');
almost(lib.parseLengthInput('12.700 mm', 'feet_decimal'), 0.0127 / FT, '12.700 mm stays a decimal millimetre');
almost(lib.parseLengthInput('50.000 mm', 'meter'), 0.05 / FT, '50.000 mm stays a decimal millimetre');
assert.ok(lib.describeParsedLength(0.0015 / FT).indexOf('1.5 mm') !== -1, 'describe 1.500 mm');
almost(lib.parseLengthInput('12’ 6”', 'meter'), 12.5, 'iOS smart quotes 12’ 6”');

rejects('12abc', 'meter');
rejects('12abc', 'feet_inch_frac');
rejects('1e9', 'meter');
rejects('1e9', 'feet_decimal');
rejects('6 1/0"', 'feet_inch_frac');
rejects('0', 'feet_inch_frac');
rejects('', 'meter');
rejects('0.500 mm', 'meter'); // 0.5 mm is below 1/16"; 500 mm would parse
rejects('1.500 mm', 'meter'); // 1.5 mm decimal is below 1/16"; 1500 mm would have parsed

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

assert.strictEqual(lib.isAxisAlignedRectangle(rect), true, 'rect is a rectangle');
assert.strictEqual(lib.isAxisAlignedRectangle(l), false, 'L is not a rectangle');
assert.strictEqual(lib.overallDimensionText(rect, { width: 16, height: 12, areaLabel: '192 sq ft' }), '16 × 12 · 192 sq ft');
assert.strictEqual(lib.overallDimensionText(l, { width: 16, height: 12, areaLabel: '152 sq ft' }), 'Overall 16 × 12');

var jsonCalls = 0;
var okRes = {
  ok: true,
  status: 200,
  json: function () {
    jsonCalls += 1;
    return Promise.resolve({ ok: true, photo_count: 2 });
  }
};
lib.readFetchJson(okRes, 'fail').then(function (result) {
  assert.strictEqual(jsonCalls, 1, 'readFetchJson parses JSON once');
  assert.strictEqual(result.ok, true);
  assert.strictEqual(result.data.photo_count, 2);
  assert.strictEqual(typeof result.data.json, 'undefined');
  return lib.readFetchJson({ ok: true, status: 200 }, 'Could not delete project.').then(function (bad) {
    assert.strictEqual(bad.ok, false);
    assert.strictEqual(bad.data.message, 'Could not delete project.');
  });
}).then(function () {
  assert.strictEqual(lib.friendlyNetworkError(new Error('Failed to fetch'), 'Could not delete project. Check your connection and try again.'), 'Could not delete project. Check your connection and try again.');
  assert.strictEqual(lib.friendlyNetworkError({ message: 'Unexpected token < in JSON' }, 'Could not upload photos. Check your connection and try again.'), 'Could not upload photos. Check your connection and try again.');
  assert.strictEqual(lib.friendlyNetworkError({ status: 413, message: 'Payload Too Large' }, 'x'), 'That file is too large to upload.');
  var saved = 'Floor plan upload failed. The room itself was saved. Save again to retry.';
  var savedOnly = 'Floor plan upload failed. The room itself was saved.';
  assert.strictEqual(lib.planUploadErrorAfterRoomSaved({ status: 413, message: 'Payload Too Large' }), savedOnly + ' That file is too large.');
  assert.ok(lib.planUploadErrorAfterRoomSaved({ status: 413, message: 'Payload Too Large' }).indexOf('room itself was saved') !== -1);
  assert.ok(lib.planUploadErrorAfterRoomSaved({ status: 413, message: 'Payload Too Large' }).indexOf('Save again to retry') === -1);
  assert.ok(lib.planUploadErrorAfterRoomSaved({ status: 422, message: 'The background must not be greater than 10240 kilobytes.' }).indexOf('Save again to retry') === -1);
  assert.ok(lib.planUploadErrorAfterRoomSaved({ status: 422, message: 'The background must not be greater than 10240 kilobytes.' }).indexOf('room itself was saved') !== -1);
  assert.strictEqual(lib.planUploadErrorAfterRoomSaved({ status: 422, message: 'The background must be an image.' }), savedOnly + ' The background must be an image.');
  assert.ok(lib.planUploadErrorAfterRoomSaved({ status: 422, message: 'The background must be an image.' }).indexOf('room itself was saved') !== -1);
  assert.ok(lib.planUploadErrorAfterRoomSaved({ status: 422, message: 'The background must be an image.' }).indexOf('Save again to retry') === -1);
  assert.strictEqual(lib.planUploadErrorAfterRoomSaved({ status: 422, message: 'Unlock layers to change the floor plan.' }), 'Unlock layers to change the floor plan.');
  assert.ok(lib.planUploadErrorAfterRoomSaved({ status: 422, message: 'Unlock layers to change the floor plan.' }).indexOf('Save again') === -1);
  assert.strictEqual(lib.planUploadReason({ status: 422, message: 'Unlock layers to change the floor plan.' }), 'Unlock layers to change the floor plan.');
  assert.strictEqual(lib.planUploadReason({ status: 422, message: 'The background must not be greater than 10240 kilobytes.' }), 'The background must not be greater than 10240 kilobytes.');
  assert.ok(lib.friendlyPlanUploadError({ status: 422, message: 'Unlock layers to change the floor plan.' }).indexOf('Wrong file type') === -1);
  assert.strictEqual(lib.planUploadErrorAfterRoomSaved({ status: 500, message: 'Server Error' }), saved + ' The server had a problem.');
  assert.strictEqual(lib.planUploadErrorAfterRoomSaved(new Error('Failed to fetch')), saved + ' Check your connection and try again.');
  assert.strictEqual(lib.planUploadErrorAfterRoomSaved(new Error('Could not upload the floor plan. Check your connection and try again.')), saved + ' Check your connection and try again.');
  assert.strictEqual(lib.friendlyPlanUploadError({ status: 500, message: 'Server Error' }), 'Could not upload the floor plan. The server had a problem. Try again.');
  assert.ok(lib.friendlyPlanUploadError({ status: 500, message: 'Server Error' }).indexOf('Server Error') === -1);
  assert.strictEqual(lib.ceilingHeightFt(null, 8), 8);
  assert.strictEqual(lib.ceilingHeightFt(9, 8), 9);
  assert.strictEqual(lib.ceilingAreaSqFt(152), 152);
  assert.strictEqual(lib.netWallAreaSqFt(12, 8, lib.openingAreaSqFt(36, 80) + lib.openingAreaSqFt(36, 36) + lib.openingAreaSqFt(36, 84)), 46);
  assert.strictEqual(lib.netWallAreaSqFt(10.1, 7.75, 0), 78.28);
  assert.strictEqual(lib.roundTo(10.1 * 7.75, 2), 78.28);
  assert.strictEqual(lib.netWallAreaSqFt(4.3, 7.75, 30.1), 3.23);
  assert.strictEqual(lib.netWallAreaSqFt(2.04, 9, 18.03), 0.33);
  assert.strictEqual(lib.netWallAreaSqFt(10.1, 7.75, lib.openingAreaSqFt(33, 80)), 59.94);
  assert.notStrictEqual(lib.netWallAreaSqFt(10.1, 7.75, lib.roundTo(lib.openingAreaSqFt(33, 80), 2)), 59.94);
  assert.strictEqual(lib.parseWastePercent('').value, 10);
  assert.strictEqual(lib.parseWastePercent('15%').value, 15);
  assert.strictEqual(lib.parseWastePercent('7,5').value, 7.5);
  assert.strictEqual(lib.parseWastePercent(75).value, 75);
  assert.strictEqual(lib.parseWastePercent('75').value, 75);
  assert.strictEqual(lib.parseWastePercent('75').ok, true);
  assert.strictEqual(lib.parseWastePercent(150).ok, false);
  assert.strictEqual(lib.parseWastePercent('150').ok, false);
  assert.strictEqual(lib.parseWastePercent('abc').ok, false);
  assert.strictEqual(lib.parseWastePercent('1e3').ok, false);
  assert.strictEqual(lib.parseWastePercent('1E3').ok, false);
  assert.strictEqual(lib.netWallAreaSqFt(3, 8, 40), 0);
  assert.strictEqual(lib.normalizeBoxSqFt(0.1), 0);
  assert.strictEqual(lib.flooringBoxes(100, 10, 0.1), 0);
  assert.strictEqual(lib.flooringBoxes(100, 10, 20), 6);
  assert.strictEqual(lib.flooringBoxes(100, 10, 19), 6);
  assert.strictEqual(lib.flooringBoxes(20, 0, 20), 1);
  assert.strictEqual(lib.flooringBoxes(20.01, 0, 20), 2);
  assert.strictEqual(lib.flooringBoxes(100, 10, 22), 5);
  assert.strictEqual(lib.flooringBoxes(100, 10, 11), 10);
  assert.strictEqual(lib.flooringBoxes(50, 10, 5.5), 10);
  assert.strictEqual(lib.flooringBoxes(12.5, 12, 14), 1);
  assert.strictEqual(lib.roundTo(1.5, 0), 2);
  assert.strictEqual(lib.roundTo(2.5, 0), 3);
  assert.strictEqual(lib.casingLf(36, 80, 2), 32.67);
  assert.strictEqual(lib.casingLf(36, 36, 4, 'window'), 12);
  assert.strictEqual(lib.casingLf(36, 36, 3, 'window'), 9);
  assert.strictEqual(lib.casingLf(36, 36, 2, 'window'), 0);
  assert.strictEqual(lib.defaultCasingSides('door'), 2);
  assert.strictEqual(lib.defaultCasingSides('DOOR', 'exterior'), 1);
  assert.strictEqual(lib.defaultCasingSides('window'), 4);
  assert.strictEqual(lib.defaultCasingSides('opening'), 2);
  assert.strictEqual(lib.casingSidesForObject({ type: 'door', casing_sides: 1 }), 1);
  assert.strictEqual(lib.casingSidesForObject({ type: 'WINDOW' }), 4);
  assert.strictEqual(lib.casingSidesForObject({ type: 'window', casing_sides: '' }), 4);
  assert.strictEqual(lib.casingSidesForObject({ type: 'window', casing_sides: ' ' }), 4);
  assert.strictEqual(lib.casingSidesForObject({ type: 'door', casing_sides: ' ' }), 2);
  assert.strictEqual(lib.casingSidesForObject({ type: 'opening', height: 48, casing_sides: ' ' }), 0);
  assert.strictEqual(lib.casingSidesForObject({ type: 'opening' }), 2);
  assert.strictEqual(lib.casingSidesForObject({ type: 'opening', height: 48 }), 0);
  assert.strictEqual(lib.casingSidesForObject({ type: 'opening', height: 48, casing_sides: 2 }), 2);
  assert.strictEqual(lib.casingSidesForObject({ type: 'opening', casing_sides: 'nope' }), 2);
  assert.strictEqual(lib.casingSidesForObject({ type: 'opening', height: 48, casing_sides: 'nope' }), 2);
  assert.strictEqual(lib.casingSidesForObject({ type: 'door', casing_sides: 'abc' }), 2);
  assert.strictEqual(lib.objectHeightInches({ type: 'opening', height: 120 }, 8), 96);
  assert.strictEqual(lib.objectHeightInches({ type: 'opening', height: 48 }, 8), 84);
  assert.strictEqual(lib.objectHeightInches({ type: 'opening', height: 48 }, 9), 84);
  assert.strictEqual(lib.objectHeightInches({ type: 'opening' }, 8), 84);
  assert.strictEqual(lib.objectHeightInches({ type: 'door', height: 48 }, 8), 48);
  assert.strictEqual(lib.objectHeightInches({ type: 'window', height: 48 }, 8), 48);
  assert.strictEqual(lib.objectHeightInches({ type: 'opening', height: 60 }, 8), 60);
  assert.strictEqual(lib.totalCasingLf([{ type: 'door', width: 36, height: 120 }], 9) !== lib.totalCasingLf([{ type: 'door', width: 36, height: 120 }], 8), true);
  assert.strictEqual(lib.objectType({ type: 'DOOR' }), 'door');
  var perWallCanvas = {
    groups: [{ polygons: [{ id: 'p1', vertices: [{ x: 0, y: 0 }, { x: 144, y: 0 }, { x: 144, y: 120 }, { x: 0, y: 120 }] }] }],
    objects: [
      { type: 'Door', wall_id: 'wall_p1_0_1', width: 36, offset: 24 },
      { type: 'WINDOW', wall_id: 'wall_p1_1_2', width: 48, offset: 24, height: 36 },
      { type: 'Opening', wall_id: 'wall_p1_2_3', width: 36, offset: 24 }
    ]
  };
  var walls8 = lib.perWallNetAreas(perWallCanvas, 12, 8);
  var walls9 = lib.perWallNetAreas(perWallCanvas, 12, 9);
  assert.strictEqual(walls8.length, 4);
  assert.strictEqual(walls8[0].net_area_sqft, 76);
  assert.strictEqual(walls8[1].net_area_sqft, 68);
  assert.strictEqual(walls8[2].net_area_sqft, 75);
  assert.strictEqual(walls8[3].net_area_sqft, 80);
  assert.strictEqual(walls9[0].net_area_sqft, 88);
  assert.strictEqual(walls9[2].net_area_sqft, 87);
  var overlap = lib.unionOpeningAreaSqFt([
    { type: 'door', width: 36, offset: 12, height: 80 },
    { type: 'door', width: 36, offset: 36, height: 80 }
  ], 8);
  assert.ok(Math.abs(overlap - 33.33) < 0.02);
  assert.notStrictEqual(overlap, 40);
  var sillUnion = lib.unionOpeningAreaSqFt([
    { type: 'window', width: 36, height: 24, offset: 0, sill_height: 0 },
    { type: 'window', width: 36, height: 24, offset: 0, sill_height: 36 }
  ], 8);
  assert.ok(Math.abs(sillUnion - 12) < 0.02);
  assert.notStrictEqual(sillUnion, 6);
  var topClamp = lib.unionOpeningAreaSqFt([
    { type: 'window', width: 36, height: 60, offset: 0, sill_height: 60 }
  ], 8);
  assert.ok(Math.abs(topClamp - 9) < 0.02);
  assert.notStrictEqual(topClamp, 15);
  var clamped = lib.clampOpeningOnWall(200, 36, 120);
  assert.strictEqual(clamped.offset, 84);
  assert.strictEqual(clamped.width, 36);
  var wide = lib.unionOpeningAreaSqFt([{ type: 'door', width: 200, offset: 0, height: 80 }], 8, 120);
  assert.ok(Math.abs(wide - lib.openingAreaSqFt(120, 80)) < 0.02);
  var pennyWall = lib.perWallNetAreas({
    groups: [{ polygons: [{ id: 'p1', vertices: [{ x: 0, y: 0 }, { x: 121.2, y: 0 }, { x: 121.2, y: 93 }, { x: 0, y: 93 }] }] }],
    objects: [{ type: 'door', wall_id: 'wall_p1_0_1', width: 33, offset: 12, height: 80 }]
  }, 12, 7.75);
  assert.strictEqual(pennyWall[0].opening_area_sqft, 18.33);
  assert.strictEqual(pennyWall[0].net_area_sqft, 59.94);
  var openWalls = lib.perWallNetAreas({
    groups: [{ polygons: [{ id: 'open1', closed: false, vertices: [{ x: 0, y: 0 }, { x: 144, y: 0 }, { x: 144, y: 120 }] }] }],
    objects: []
  }, 12, 8);
  assert.strictEqual(openWalls.length, 2);
  var bowtieVerts = [{ x: 0, y: 0 }, { x: 120, y: 120 }, { x: 120, y: 0 }, { x: 0, y: 120 }];
  var bowtie = lib.perWallNetAreas({
    groups: [{ polygons: [{ id: 'bow', vertices: bowtieVerts }] }],
    objects: []
  }, 12, 8);
  assert.strictEqual(bowtie.length, 0);
  var bowtieCorners = lib.cornerCounts(bowtieVerts);
  assert.strictEqual(bowtieCorners.inside, 0);
  assert.strictEqual(bowtieCorners.outside, 0);
  console.log('room-area-lib tests: ' + process.uptime().toFixed(3) + 's ok');
}).catch(function (err) {
  console.error(err);
  process.exit(1);
});
