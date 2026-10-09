'use strict';

const assert = require('assert');
const fs = require('fs');
const http = require('http');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const FIXTURE = path.join(__dirname, 'fixtures/admin-date-picker.html');
const DATE_TYPES = ['date', 'time', 'datetime-local'];
const VIEWPORTS = [
  { name: 'phone', width: 390, height: 720, isMobile: true, hasTouch: true },
  { name: 'desktop', width: 1280, height: 800, isMobile: false, hasTouch: false },
];

function startServer() {
  return new Promise((resolve, reject) => {
    const publicDir = path.join(ROOT, 'public');
    const server = http.createServer((req, res) => {
      const urlPath = decodeURIComponent((req.url || '/').split('?')[0]);
      let file;
      if (urlPath === '/' || urlPath === '/admin-date-picker.html') {
        file = FIXTURE;
      } else {
        file = path.join(publicDir, urlPath.replace(/^\//, ''));
      }
      const allowed = file === FIXTURE || file.startsWith(publicDir + path.sep);
      if (!allowed) {
        res.writeHead(403);
        res.end();
        return;
      }
      fs.readFile(file, (err, data) => {
        if (err) {
          res.writeHead(404);
          res.end('not found');
          return;
        }
        const ext = path.extname(file);
        const types = { '.css': 'text/css', '.html': 'text/html', '.js': 'text/javascript' };
        res.writeHead(200, { 'Content-Type': types[ext] || 'application/octet-stream' });
        res.end(data);
      });
    });
    server.listen(0, '127.0.0.1', () => resolve({ server, port: server.address().port }));
    server.on('error', reject);
  });
}

async function launch() {
  let puppeteer;
  try {
    puppeteer = require('puppeteer');
  } catch (err) {
    throw new Error('puppeteer is required for admin date picker browser tests');
  }
  return puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });
}

async function setTheme(page, dark) {
  await page.evaluate((isDark) => {
    document.documentElement.classList.toggle('admin-dark', isDark);
    document.body.classList.toggle('admin-dark', isDark);
  }, dark);
}

async function colorSchemes(page) {
  return page.evaluate((types) => {
    const out = {};
    types.forEach((type) => {
      const el = document.querySelector('input[type="' + type + '"]:not([readonly])');
      out[type] = el ? getComputedStyle(el).colorScheme : '';
    });
    return out;
  }, DATE_TYPES);
}

/**
 * Chrome does not report ::-webkit-calendar-picker-indicator via
 * getComputedStyle(), so read the declared filter from the dark stylesheet.
 * Restoring invert(0.85) must fail this check.
 */
async function darkIndicatorFilters(page) {
  return page.evaluate(() => {
    const out = [];
    for (let s = 0; s < document.styleSheets.length; s++) {
      let rules;
      try {
        rules = document.styleSheets[s].cssRules;
      } catch (err) {
        continue;
      }
      for (let i = 0; i < rules.length; i++) {
        const rule = rules[i];
        const sel = rule.selectorText || '';
        if (sel.indexOf('html.admin-dark') === -1) continue;
        if (sel.indexOf('calendar-picker-indicator') === -1) continue;
        out.push({
          selector: sel,
          filter: (rule.style && rule.style.filter) || '',
        });
      }
    }
    return out;
  });
}

async function activate(page, selector, pointerType) {
  return page.evaluate((sel, type) => {
    const el = document.querySelector(sel);
    if (!el) return { exists: false };
    let calls = 0;
    let threw = false;
    const original = el.showPicker;
    el.showPicker = function () {
      calls += 1;
    };
    const opts = { bubbles: true, cancelable: true, pointerType: type, pointerId: 1 };
    el.dispatchEvent(new PointerEvent('pointerdown', opts));
    el.dispatchEvent(new PointerEvent('pointerup', opts));
    el.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    if (original) el.showPicker = original;
    else delete el.showPicker;
    return {
      exists: true,
      calls: calls,
      threw: threw,
      readOnly: !!el.readOnly,
      disabled: !!el.disabled,
    };
  }, selector, pointerType);
}

async function activateShowPickerThrows(page, selector, pointerType) {
  return page.evaluate((sel, type) => {
    const el = document.querySelector(sel);
    let threwToCaller = false;
    let calls = 0;
    el.showPicker = function () {
      calls += 1;
      throw new Error('showPicker failed');
    };
    try {
      const opts = { bubbles: true, cancelable: true, pointerType: type, pointerId: 1 };
      el.dispatchEvent(new PointerEvent('pointerdown', opts));
      el.dispatchEvent(new PointerEvent('pointerup', opts));
      el.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    } catch (err) {
      threwToCaller = true;
    }
    return {
      threwToCaller: threwToCaller,
      calls: calls,
      notesStillThere: !!document.getElementById('notes'),
    };
  }, selector, pointerType);
}

(async function main() {
  const { server, port } = await startServer();
  const browser = await launch();
  const base = 'http://127.0.0.1:' + port + '/';
  try {
    const page = await browser.newPage();
    await page.goto(base, { waitUntil: 'load' });
    await page.waitForFunction(() => !!(window.AdminDatePicker && window.AdminDatePicker.bind));

    const filters = await darkIndicatorFilters(page);
    assert.ok(filters.length > 0, 'dark stylesheet declares calendar-picker-indicator rules');
    filters.forEach((rule) => {
      assert.ok(!/invert/i.test(rule.filter), 'dark indicator filter must not invert: ' + rule.selector + ' => ' + rule.filter);
    });
    assert.ok(
      filters.some((rule) => String(rule.filter).toLowerCase() === 'none'),
      'dark indicator must declare filter: none so invert(0.85) cannot sneak back'
    );

    for (const viewport of VIEWPORTS) {
      await page.setViewport({
        width: viewport.width,
        height: viewport.height,
        isMobile: viewport.isMobile,
        hasTouch: viewport.hasTouch,
      });

      for (const dark of [false, true]) {
        const label = viewport.name + ' ' + (dark ? 'dark' : 'light');
        await setTheme(page, dark);
        const schemes = await colorSchemes(page);
        DATE_TYPES.forEach((type) => {
          const scheme = String(schemes[type] || '');
          if (dark) {
            assert.ok(/dark/i.test(scheme), label + ' ' + type + ' color-scheme should be dark, got ' + scheme);
          } else {
            assert.ok(!/dark/i.test(scheme), label + ' ' + type + ' color-scheme should not be dark, got ' + scheme);
          }
        });

        for (const type of DATE_TYPES) {
          const sel = 'input[type="' + type + '"]:not([readonly])';
          const touch = await activate(page, sel, 'touch');
          assert.strictEqual(touch.calls, 1, label + ' touch tap on ' + type + ' opens the picker');
          const pen = await activate(page, sel, 'pen');
          assert.strictEqual(pen.calls, 1, label + ' pen tap on ' + type + ' opens the picker');
          const mouse = await activate(page, sel, 'mouse');
          assert.strictEqual(mouse.calls, 0, label + ' mouse click on ' + type + ' does not open the picker');
        }

        const lockedTouch = await activate(page, '#locked-date', 'touch');
        assert.strictEqual(lockedTouch.calls, 0, label + ' readonly date does not open the picker');
      }
    }

    await setTheme(page, false);
    await page.setViewport({ width: 1280, height: 800, isMobile: false, hasTouch: false });

    const mouseThenType = await page.evaluate(() => {
      const el = document.querySelector('#due-date');
      let calls = 0;
      el.showPicker = function () { calls += 1; };
      const opts = { bubbles: true, cancelable: true, pointerType: 'mouse', pointerId: 1 };
      el.value = '';
      el.dispatchEvent(new PointerEvent('pointerdown', opts));
      el.dispatchEvent(new PointerEvent('pointerup', opts));
      el.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
      el.focus();
      return { calls: calls };
    });
    assert.strictEqual(mouseThenType.calls, 0, 'mouse click does not call showPicker');
    await page.focus('#due-date');
    await page.keyboard.type('10152026');
    const typedDate = await page.$eval('#due-date', (el) => el.value);
    assert.strictEqual(typedDate, '2026-10-15', 'mouse click then typing saves the date value, got ' + typedDate);

    const tabResult = await page.evaluate(() => {
      const date = document.querySelector('#due-date');
      let calls = 0;
      date.showPicker = function () { calls += 1; };
      document.getElementById('client-name').focus();
      return { start: document.activeElement && document.activeElement.id };
    });
    assert.strictEqual(tabResult.start, 'client-name');
    await page.keyboard.press('Tab');
    const afterTab = await page.evaluate(() => {
      const date = document.querySelector('#due-date');
      return {
        active: document.activeElement && document.activeElement.id,
        // Re-read calls from a fresh spy around Tab:
      };
    });
    assert.strictEqual(afterTab.active, 'due-date', 'Tab moves focus to the date field');

    const tabDidNotOpen = await page.evaluate(() => {
      const date = document.querySelector('#due-date');
      let calls = 0;
      date.showPicker = function () { calls += 1; };
      document.getElementById('client-name').focus();
      date.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true }));
      date.dispatchEvent(new FocusEvent('focus', { bubbles: true }));
      return calls;
    });
    assert.strictEqual(tabDidNotOpen, 0, 'Tab / focus does not open the picker');

    const boom = await activateShowPickerThrows(page, '#due-date', 'touch');
    assert.strictEqual(boom.calls, 1, 'throwing showPicker was still invoked on touch');
    assert.strictEqual(boom.threwToCaller, false, 'a throwing showPicker does not break the page');
    assert.strictEqual(boom.notesStillThere, true, 'DOM remains usable after showPicker throws');
  } finally {
    await browser.close();
    server.close();
  }
  console.log('admin-date-picker browser tests: ok');
})().catch((err) => {
  console.error(err);
  process.exit(1);
});
