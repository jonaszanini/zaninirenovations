'use strict';

const assert = require('assert');
const layout = require('../../public/js/admin-basement-layout-import.js');

console.log('admin-basement-layout-import tests');

assert.ok(layout);
assert.strictEqual(typeof layout.proposedQuantities, 'function');
assert.strictEqual(typeof layout.wouldOverwrite, 'function');

const opt = {
  area_sqft: 500,
  ceiling_area_sqft: 500,
  ceiling_height_ft: 8,
  perimeter_lf: 90,
  wall_area_sqft: 720,
  net_wall_area_sqft: 720,
  inside_corners: 4,
  outside_corners: 0,
  polygon_count: 1,
  door_count: 1,
  window_count: 0,
  opening_count: 0,
  baseboard_lf: 87,
  casing_lf: 44,
  bulkhead_framing_lf: 16,
  bulkhead_lf: 16,
  bulkhead_drywall_sqft: 30,
  partition_lf: 12,
};

const proposed = layout.proposedQuantities(opt, 8);
assert.strictEqual(proposed.area_sqft, 500);
assert.strictEqual(proposed.flooring_sf, 500);
assert.strictEqual(proposed.perimeter_lf, 90);
assert.strictEqual(proposed.drywall_wall_sf, 750);
assert.strictEqual(proposed.paint_wall_sf, 750);
assert.strictEqual(proposed.bulkhead_lf, 16);
assert.strictEqual(proposed.partition_lf, 12);
assert.strictEqual(proposed.baseboard_lf, 87);
assert.strictEqual(proposed.door_qty, 1);
assert.strictEqual(proposed.framing_door_openings, 1);
assert.strictEqual(proposed.drywall_door_openings, 1);
assert.strictEqual(proposed.window_trim_qty, 0);
assert.strictEqual(proposed.casing_openings, 1);
assert.strictEqual(proposed.ceiling_sf, 500);
assert.strictEqual(proposed.drywall_ceiling_sf, 500);
assert.strictEqual(proposed.paint_ceiling_sf, 500);

const dropCeil = layout.proposedQuantities(Object.assign({}, opt, { ceiling_area_sqft: 500 }), 8, 'drop');
assert.strictEqual(dropCeil.paint_ceiling_sf, 0);
assert.strictEqual(dropCeil.drywall_ceiling_sf, 500);

const legacyDoor = layout.proposedQuantities({
  area_sqft: 240,
  perimeter_lf: 64,
  door_count: 1,
  ceiling_area_sqft: 240,
}, 8, 'drywall');
assert.strictEqual(legacyDoor.drywall_wall_sf, 512);
assert.strictEqual(legacyDoor.paint_wall_sf, 491);
assert.strictEqual(proposed.insulation_ceiling_sf, 500);
assert.strictEqual(proposed.paint_trim_lf, 131);
assert.strictEqual(proposed.drywall_opening_sf, 0);
assert.strictEqual(proposed.room_count, 1);
assert.strictEqual(proposed.ceiling_height_ft, 8);

layout.NEVER_OVERWRITE.forEach(function (key) {
  assert.ok(!Object.prototype.hasOwnProperty.call(proposed, key), 'must not map ' + key);
});

const fromFramingAlias = layout.proposedQuantities({
  area_sqft: 100,
  perimeter_lf: 40,
  bulkhead_framing_lf: 16,
}, 8);
assert.strictEqual(fromFramingAlias.bulkhead_lf, 16);
assert.strictEqual(fromFramingAlias.partition_lf, null, 'missing partition is unavailable, not zero');

const emptyForm = layout.currentQuantities(null);
assert.strictEqual(layout.formHasQuantities(emptyForm), false);
assert.strictEqual(layout.wouldOverwrite(emptyForm, proposed), false, 'D4: first import does not confirm');

const afterImport = Object.assign({}, proposed, { import_label: 'Living' });
assert.strictEqual(layout.wouldOverwrite(afterImport, proposed), false, 'D4: same layout re-select is a no-op');

const handEdited = Object.assign({}, afterImport, { area_sqft: 111 });
assert.strictEqual(layout.wouldOverwrite(handEdited, proposed), true, 'D4: hand-edited area confirms before overwrite');

const otherLayout = layout.proposedQuantities(Object.assign({}, opt, { area_sqft: 1200, perimeter_lf: 140 }), 8);
assert.strictEqual(layout.wouldOverwrite(afterImport, otherLayout), true, 'D4: different layout confirms');

const zeroCeiling = layout.proposedQuantities({ area_sqft: 1500, ceiling_area_sqft: 0 }, 8);
assert.strictEqual(layout.ceilingTakeoffAvailable({ area_sqft: 1500, ceiling_area_sqft: 0 }), false);
assert.strictEqual(zeroCeiling.ceiling_sf, 1500);
assert.strictEqual(zeroCeiling.drywall_ceiling_sf, 1500);
assert.strictEqual(zeroCeiling.paint_ceiling_sf, 1500);

const flaggedUnavailable = layout.proposedQuantities({
  area_sqft: 1500,
  ceiling_area_sqft: 0,
  ceiling_area_available: false
}, 8);
assert.strictEqual(flaggedUnavailable.ceiling_sf, 1500);

const measuredCeilingZero = layout.proposedQuantities({
  area_sqft: 0,
  ceiling_area_sqft: 0,
  ceiling_area_available: true,
  excluded_area_sqft: 1500,
  polygon_count: 1
}, 8);
assert.strictEqual(measuredCeilingZero.ceiling_sf, 0);
assert.strictEqual(measuredCeilingZero.flooring_sf, 0);

const excludedZero = layout.proposedQuantities({
  area_sqft: 0,
  ceiling_area_sqft: 0,
  excluded_area_sqft: 80,
  polygon_count: 1
}, 8);
assert.strictEqual(excludedZero.ceiling_sf, 0);

const unavailableNoFloor = layout.proposedQuantities({ perimeter_lf: 90, ceiling_area_sqft: 0 }, 8);
assert.strictEqual(unavailableNoFloor.ceiling_sf, null);
assert.strictEqual(unavailableNoFloor.drywall_ceiling_sf, null);
assert.strictEqual(
  layout.wouldOverwrite(Object.assign({}, unavailableNoFloor, {
    ceiling_sf: 1500,
    drywall_ceiling_sf: 118,
    import_label: 'Manual'
  }), unavailableNoFloor),
  false,
  'unavailable ceiling must not wipe a hand-typed ceiling'
);

const leftoverDrywall = {
  area_sqft: 1500,
  ceiling_sf: 1500,
  drywall_ceiling_sf: 118,
  drywall_wall_sf: 3767.65,
  import_label: 'Basement'
};
const importedCeiling = layout.proposedQuantities({ area_sqft: 1500, ceiling_area_sqft: 1500 }, 8);
assert.strictEqual(importedCeiling.ceiling_sf, 1500);
assert.strictEqual(importedCeiling.drywall_ceiling_sf, 1500);
const leftoverHangOnly = Object.assign({}, importedCeiling, {
  drywall_ceiling_sf: 118,
  import_label: 'Basement'
});
assert.strictEqual(
  layout.wouldOverwrite(leftoverHangOnly, importedCeiling),
  false,
  'import must not treat a saved hang field as a form overwrite; Ceiling area already matches'
);
assert.ok(layout.SKIP_FORM_KEYS.drywall_ceiling_sf);
assert.strictEqual(
  layout.wouldOverwrite(leftoverDrywall, measuredCeilingZero),
  true,
  'measured zero ceiling overwrites a leftover hang after confirm'
);

const missingPartition = layout.proposedQuantities({ area_sqft: 500, perimeter_lf: 90, wall_area_sqft: 720 }, 8);
assert.strictEqual(missingPartition.partition_lf, null);
const currentWithManualPartition = Object.assign({}, missingPartition, {
  partition_lf: 18,
  import_label: 'Living'
});
assert.strictEqual(
  layout.wouldOverwrite(currentWithManualPartition, missingPartition),
  false,
  'unavailable partition must not wipe a hand-edited value'
);

const measuredZero = layout.proposedQuantities({
  area_sqft: 80,
  partition_lf: 0,
  bulkhead_lf: 0,
}, 8);
assert.strictEqual(measuredZero.partition_lf, 0);
assert.strictEqual(
  layout.wouldOverwrite({ partition_lf: 18, area_sqft: 80, import_label: 'Living' }, measuredZero),
  true,
  'measured zero overwrites a hand-edited value after confirm'
);

const finishes = layout.proposedQuantities({
  area_sqft: 100,
  wall_area_sqft: 900,
  partition_lf: 40,
  baseboard_lf: 90,
  finish_drywall_sqft: 210,
  finish_insulation_sqft: 180,
  finish_stud_lf: 12.5,
  finish_baseboard_lf: 70,
  finish_casing_lf: 18,
}, 8);
assert.strictEqual(finishes.drywall_wall_sf, 210);
assert.strictEqual(finishes.paint_wall_sf, 210);
assert.strictEqual(finishes.insulation_wall_sf, 180);
assert.strictEqual(finishes.partition_lf, 12.5);
assert.strictEqual(finishes.baseboard_lf, 70);
assert.strictEqual(finishes.paint_trim_lf, 88);

const mixedPaint = layout.proposedQuantities({
  area_sqft: 500,
  finish_drywall_sqft: 720,
  finish_paint_sqft: 208,
  bulkhead_drywall_sqft: 30,
  bulkhead_lf: 16,
  ceiling_area_sqft: 500
}, 8);
assert.strictEqual(mixedPaint.drywall_wall_sf, 750);
assert.strictEqual(mixedPaint.paint_wall_sf, 208);
assert.strictEqual(mixedPaint.paint_ceiling_sf, 500);
assert.strictEqual(mixedPaint.bulkhead_lf, 16);

const zeroPaint = layout.proposedQuantities({
  area_sqft: 80,
  finish_drywall_sqft: 640,
  finish_paint_sqft: 0,
  bulkhead_drywall_sqft: 12
}, 8);
assert.strictEqual(zeroPaint.paint_wall_sf, 0);
assert.strictEqual(zeroPaint.drywall_wall_sf, 652);

const missingPaint = layout.proposedQuantities({
  area_sqft: 80,
  finish_drywall_sqft: 640,
  bulkhead_drywall_sqft: 12
}, 8);
assert.strictEqual(missingPaint.paint_wall_sf, 652);
assert.strictEqual(
  layout.wouldOverwrite(Object.assign({}, missingPaint, {
    paint_wall_sf: 400,
    import_label: 'Manual'
  }), missingPaint),
  true,
  'drywall-fallback wall paint overwrites a hand-typed value after confirm'
);
assert.strictEqual(
  layout.wouldOverwrite({ paint_wall_sf: 400, import_label: 'Manual' }, zeroPaint),
  true,
  'measured zero paint overwrites a hand-typed value after confirm'
);

assert.ok(layout.CONFIRM_MESSAGE.indexOf('differ') !== -1);
assert.ok(layout.CONFIRM_MESSAGE.indexOf('Replace') !== -1);

const mixedLegacyOpt = {
  finish_paint_sqft: 0,
  paint_walls_marked: 0,
  paint_walls_explicit_off: 1,
  paint_walls_unassigned: 2,
  paint_bulkheads_unassigned: 1
};
const mixedProposed = layout.proposedQuantities(mixedLegacyOpt, 8);
assert.strictEqual(mixedProposed.paint_wall_sf, 0);
const currentPaint400 = { paint_wall_sf: 400, import_label: 'Manual', area_sqft: 80 };
assert.strictEqual(layout.needsImportConfirm(currentPaint400, mixedProposed, mixedLegacyOpt), true);
const confirmMsg = layout.confirmImportMessage(currentPaint400, mixedProposed, mixedLegacyOpt);
assert.ok(confirmMsg.indexOf('1 wall(s) marked Paint') !== -1 || confirmMsg.indexOf('0 wall(s) marked Paint') !== -1, confirmMsg);
assert.ok(confirmMsg.indexOf('2 wall(s) with no Paint choice') !== -1, confirmMsg);
assert.ok(confirmMsg.indexOf('Imported wall paint: 0 sq ft') !== -1, confirmMsg);
assert.ok(confirmMsg.indexOf('current wall paint of 400 sq ft') !== -1, confirmMsg);

const untouchedLegacy = layout.proposedQuantities({
  area_sqft: 80,
  finish_drywall_sqft: 640,
  paint_walls_unassigned: 3
}, 8);
assert.strictEqual(untouchedLegacy.paint_wall_sf, 640);
assert.strictEqual(
  layout.needsImportConfirm(Object.assign({}, untouchedLegacy, { paint_wall_sf: 400, import_label: 'Manual' }), untouchedLegacy, {
    paint_walls_unassigned: 3
  }),
  true,
  'drywall-fallback legacy paint confirms before replacing 400'
);
assert.ok(layout.FIELD_IDS.flooring_sf === 'br-fl-sf');
assert.ok(layout.FIELD_IDS.paint_wall_sf === 'br-p-wall');
assert.ok(layout.LW_KEYS.flooring_sf);

const statusWithCeiling = layout.importStatusText(proposed, { name: 'Living' }, { heightFt: 8, measuredNet: false });
assert.ok(statusWithCeiling.indexOf('500 sq ft ceiling') !== -1, statusWithCeiling);
assert.ok(statusWithCeiling.indexOf('500 sq ft floor') !== -1, statusWithCeiling);
assert.ok(statusWithCeiling.indexOf('wall drywall') !== -1, statusWithCeiling);

const emptyLayoutMetrics = {
  area_sqft: 0.0,
  perimeter_lf: 0.0,
  polygon_count: 0,
  ceiling_area_sqft: 0.0,
  excluded_area_sqft: 0.0
};
assert.strictEqual(layout.ceilingTakeoffAvailable(emptyLayoutMetrics), false);
const emptyProposed = layout.proposedQuantities(emptyLayoutMetrics, 8);
assert.strictEqual(emptyProposed.ceiling_sf, null);
assert.strictEqual(emptyProposed.drywall_ceiling_sf, null);
assert.strictEqual(
  layout.wouldOverwrite(Object.assign({}, emptyProposed, {
    ceiling_sf: 1500,
    drywall_ceiling_sf: 118,
    import_label: 'Manual'
  }), emptyProposed),
  false,
  'emptyLayoutMetrics placeholder zero must not wipe a hand-typed ceiling'
);

const noCeilingProposed = layout.proposedQuantities({ perimeter_lf: 90 }, 8);
assert.strictEqual(noCeilingProposed.ceiling_sf, null);
const statusWithoutCeiling = layout.importStatusText(noCeilingProposed, { name: 'Walls only' }, { heightFt: 8 });
assert.ok(statusWithoutCeiling.indexOf('ceiling') === -1, statusWithoutCeiling);
assert.ok(statusWithoutCeiling.indexOf('Walls only') !== -1, statusWithoutCeiling);

console.log('admin-basement-layout-import: ok');
