const fs = require('fs');

// 1. Edit tests/js/openplan3d-editor-browser.test.js
const tPath = 'tests/js/openplan3d-editor-browser.test.js';
let tContent = fs.readFileSync(tPath, 'utf8');
const oldTarget = "banner.id = 'conflict-banner-test';";
const newTarget = "banner.id = 'conflict-banner-test';\n      banner.setAttribute('role', 'alert');";
if (tContent.includes('\r\n')) {
  tContent = tContent.replace(oldTarget, oldTarget + "\r\n      banner.setAttribute('role', 'alert');");
} else {
  tContent = tContent.replace(oldTarget, newTarget);
}
fs.writeFileSync(tPath, tContent);
console.log('Updated browser test.');

// 2. Edit public/js/openplan3d-editor-bridge.js
const bPath = 'public/js/openplan3d-editor-bridge.js';
let bContent = fs.readFileSync(bPath, 'utf8');
const oldSelector = "var banners = document.querySelectorAll('div[role=\"alert\"], [data-save-conflict-banner], #conflict-banner-test, .bg-red-50, .bg-red-800, [class*=\"bg-red-\"]');";
const newSelector = "var banners = document.querySelectorAll('div[role=\"alert\"], [data-save-conflict-banner]');";
if (!bContent.includes(oldSelector)) {
  console.error('Bridge selector not found!');
} else {
  bContent = bContent.replace(oldSelector, newSelector);
  fs.writeFileSync(bPath, bContent);
  console.log('Updated bridge selector.');
}
