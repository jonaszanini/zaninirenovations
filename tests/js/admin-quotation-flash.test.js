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
assert.ok(css.indexOf('[data-qh-editor-toast]') !== -1, 'quotation toast CSS targets the existing AdminToast host');
assert.ok(css.indexOf('qh-admin-toast-slide') !== -1, 'toast slides in from the right');
assert.ok(css.indexOf('prefers-reduced-motion') !== -1, 'reduced motion uses a fade');
assert.ok(css.indexOf('qh-admin-toast-fade') !== -1, 'reduced motion fade keyframes');
assert.ok(css.indexOf('backdrop-filter') !== -1, 'toast uses backdrop blur');
assert.ok(css.indexOf('.qh-flash-status') !== -1, 'compact status CSS is shared');
assert.ok(css.indexOf('min(320px, calc(100vw - 5.5rem))') !== -1, '390px toast stays a right chip, not a full-width bar');
assert.ok(!/@media \(max-width: 700px\)\s*\{[^}]*\[data-qh-editor-toast\][^}]*max-width:\s*none/s.test(css), 'quotation toast does not stretch full width on phones');

const dark = fs.readFileSync(path.resolve(__dirname, '../../public/css/admin-dark.css'), 'utf8');
assert.ok(dark.indexOf('html.admin-dark [data-qh-editor-toast] .admin-toast--success') !== -1);
assert.ok(dark.indexOf('html.admin-dark .qh-flash-status') !== -1);

const toastJs = fs.readFileSync(path.resolve(__dirname, '../../public/js/admin-toast.js'), 'utf8');
assert.ok(toastJs.indexOf("applyPlacement(node, 'top-right')") !== -1);
assert.ok(toastJs.indexOf('data-qh-editor-toast') !== -1);
assert.ok(toastJs.indexOf('is-entering') !== -1);

const partial = fs.readFileSync(
  path.resolve(__dirname, '../../resources/views/admin/partials/quotation_flash_toast.blade.php'),
  'utf8'
);
assert.ok(partial.indexOf('data-admin-toast-host') !== -1, 'reuses the existing AdminToast host');
assert.ok(partial.indexOf('admin-toast__card') === -1, 'does not render a second toast card');

console.log('admin-quotation-flash: ok');
