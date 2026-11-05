'use strict';

const assert = require('assert');
const layout = require('../../public/js/admin-basement-layout-import.js');

console.log('admin-basement-layout-import tests');

assert.ok(layout);
assert.strictEqual(typeof layout.proposedQuantities, 'function');
assert.strictEqual(typeof layout.wouldOverwrite, 'function');

const opt = {
  area_sqft: 500,
  perimeter_lf: 90,
  wall_area_sqft: 720,
  inside_corners: 4,
  outside_corners: 0,
  door_count: 1,
  window_count: 0,
  opening_count: 0,
  baseboard_lf: 87,
  bulkhead_framing_lf: 16,
  bulkhead_lf: 16,
  partition_lf: 12,
};

const proposed = layout.proposedQuantities(opt, 8);
assert.strictEqual(proposed.area_sqft, 500);
assert.strictEqual(proposed.perimeter_lf, 90);
assert.strictEqual(proposed.wall_area_sqft, 720);
assert.strictEqual(proposed.bulkhead_lf, 16);
assert.strictEqual(proposed.partition_lf, 12);
assert.strictEqual(proposed.baseboard_lf, 87);

const fromFramingAlias = layout.proposedQuantities({
  area_sqft: 100,
  perimeter_lf: 40,
  bulkhead_framing_lf: 16,
}, 8);
assert.strictEqual(fromFramingAlias.bulkhead_lf, 16);
assert.strictEqual(fromFramingAlias.partition_lf, 0);

const emptyForm = {
  area_sqft: 0,
  perimeter_lf: 0,
  wall_area_sqft: 0,
  ceiling_sf: 0,
  inside_corners: 0,
  outside_corners: 0,
  door_count: 0,
  window_count: 0,
  opening_count: 0,
  bulkhead_lf: 0,
  partition_lf: 0,
  baseboard_lf: 0,
  import_label: '',
};
assert.strictEqual(layout.formHasQuantities(emptyForm), false);
assert.strictEqual(layout.wouldOverwrite(emptyForm, proposed), false, 'D4: first import does not confirm');

const afterImport = Object.assign({}, proposed, { import_label: 'Living' });
assert.strictEqual(layout.wouldOverwrite(afterImport, proposed), false, 'D4: same layout re-select is a no-op');

const handEdited = Object.assign({}, afterImport, { area_sqft: 111 });
assert.strictEqual(layout.wouldOverwrite(handEdited, proposed), true, 'D4: hand-edited area confirms before overwrite');

const otherLayout = layout.proposedQuantities(Object.assign({}, opt, { area_sqft: 1200, perimeter_lf: 140 }), 8);
assert.strictEqual(layout.wouldOverwrite(afterImport, otherLayout), true, 'D4: different layout confirms');

assert.ok(layout.CONFIRM_MESSAGE.indexOf('differ') !== -1);
assert.ok(layout.CONFIRM_MESSAGE.indexOf('Replace') !== -1);

console.log('admin-basement-layout-import: ok');
