'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const flash = require('../../public/js/admin-quotation-flash.js');
const toast = require('../../public/js/admin-toast.js');

assert.strictEqual(
  flash.compact('Standard quotation SQ-2026-0407 saved and linked to this project.'),
  'Saved and linked to this project'
);
assert.strictEqual(flash.compact('Basement quotation BR-1 saved as a draft.'), 'Saved as a draft');
assert.strictEqual(flash.compact('Standard quotation saved as draft.'), 'Saved as a draft');
assert.strictEqual(flash.compact('Fence quotation FQ-9 updated (v3).'), 'Saved');
assert.strictEqual(flash.compact('Width is required', 'error'), 'Width is required');
assert.ok(String(flash.compact('saved and linked')).indexOf('—') === -1);
assert.ok(String(flash.compact('saved and linked')).indexOf('–') === -1);

assert.strictEqual(toast.QUOTE_MS, 6000);
assert.strictEqual(toast.SUCCESS_MS, 2500);

const css = fs.readFileSync(path.resolve(__dirname, '../../public/css/admin.css'), 'utf8');
assert.ok(css.indexOf('[data-qh-flash-toast]') !== -1, 'quotation toast CSS is shared');
assert.ok(css.indexOf('backdrop-filter') !== -1, 'toast uses backdrop blur');
assert.ok(css.indexOf('.qh-flash-status') !== -1, 'compact status CSS is shared');
assert.ok(/@media \(max-width: 700px\)\s*\{[^}]*\[data-qh-flash-toast\]/s.test(css), '390px toast is full width minus margins');

const dark = fs.readFileSync(path.resolve(__dirname, '../../public/css/admin-dark.css'), 'utf8');
assert.ok(dark.indexOf('html.admin-dark [data-qh-flash-toast] .admin-toast--success') !== -1);
assert.ok(dark.indexOf('html.admin-dark .qh-flash-status') !== -1);

console.log('admin-quotation-flash: ok');
