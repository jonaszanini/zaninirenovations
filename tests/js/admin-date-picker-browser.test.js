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

async function indicatorVisibility(page) {
  return page.evaluate(() => {
    const el = document.querySelector('input[type="date"]');
    if (!el) return null;
    const styles = getComputedStyle(el, '::-webkit-calendar-picker-indicator');
    return {
      opacity: styles.opacity,
      filter: styles.filter,
      cursor: styles.cursor,
    };
  });
}

async function clickCallsShowPicker(page, selector) {
  return page.evaluate((sel) => {
    const el = document.querySelector(sel);
    if (!el) return { exists: false };
    let calls = 0;
    const original = el.showPicker;
    el.showPicker = function () {
      calls += 1;
    };
    el.click();
    if (original) el.showPicker = original;
    else delete el.showPicker;
    return {
      exists: true,
      calls: calls,
      readOnly: !!el.readOnly,
      disabled: !!el.disabled,
    };
  }, selector);
}

async function typingStillWorks(page) {
  return page.evaluate(() => {
    const date = document.querySelector('#due-date');
    const notes = document.querySelector('#notes');
    const keydown = new KeyboardEvent('keydown', { key: '1', bubbles: true, cancelable: true });
    date.dispatchEvent(keydown);
    notes.focus();
    notes.value = '';
    const input = new InputEvent('input', { bubbles: true, cancelable: true, data: 'ok' });
    notes.value = 'typed-ok';
    notes.dispatchEvent(input);
    return {
      dateKeyBlocked: keydown.defaultPrevented,
      dateReadOnly: !!date.readOnly,
      dateDisabled: !!date.disabled,
      notesValue: notes.value,
    };
  });
}

(async function main() {
  const { server, port } = await startServer();
  const browser = await launch();
  const base = 'http://127.0.0.1:' + port + '/';
  try {
    const page = await browser.newPage();
    await page.goto(base, { waitUntil: 'load' });
    await page.waitForFunction(() => !!(window.AdminDatePicker && window.AdminDatePicker.bind));

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

        const icon = await indicatorVisibility(page);
        assert.ok(icon, label + ' has a date indicator');
        assert.ok(parseFloat(icon.opacity) >= 0.99, label + ' calendar icon opacity is ' + icon.opacity);
        if (dark) {
          assert.ok(!/invert/i.test(icon.filter || ''), label + ' dark icon must not use invert, got ' + icon.filter);
        }

        for (const type of DATE_TYPES) {
          const result = await clickCallsShowPicker(page, 'input[type="' + type + '"]:not([readonly])');
          assert.strictEqual(result.calls, 1, label + ' click on ' + type + ' calls showPicker');
        }

        const locked = await clickCallsShowPicker(page, '#locked-date');
        assert.strictEqual(locked.calls, 0, label + ' readonly date does not call showPicker');

        const typing = await typingStillWorks(page);
        assert.strictEqual(typing.dateKeyBlocked, false, label + ' date keydown is not prevented');
        assert.strictEqual(typing.dateReadOnly, false, label + ' date stays editable');
        assert.strictEqual(typing.notesValue, 'typed-ok', label + ' typing into a nearby field still works');
      }
    }

    await page.focus('#notes');
    await page.keyboard.type(' hello');
    const notesAfterType = await page.$eval('#notes', (el) => el.value);
    assert.ok(notesAfterType.indexOf('hello') !== -1, 'real keyboard typing reaches a text field');
  } finally {
    await browser.close();
    server.close();
  }
  console.log('admin-date-picker browser tests: ok');
})().catch((err) => {
  console.error(err);
  process.exit(1);
});
