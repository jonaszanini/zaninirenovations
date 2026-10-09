'use strict';

const assert = require('assert');
const fs = require('fs');
const http = require('http');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const FIXTURE = path.join(__dirname, 'fixtures/hotkey-save-pages.html');

function startServer() {
  return new Promise((resolve, reject) => {
    const publicDir = path.join(ROOT, 'public');
    const server = http.createServer((req, res) => {
      const urlPath = decodeURIComponent((req.url || '/').split('?')[0]);
      if (urlPath.startsWith('/save/')) {
        const chunks = [];
        req.on('data', (c) => chunks.push(c));
        req.on('end', () => {
          res.writeHead(200, { 'Content-Type': 'application/json' });
          res.end(JSON.stringify({ ok: true, path: urlPath, body: Buffer.concat(chunks).toString('utf8') }));
        });
        return;
      }
      let file;
      if (urlPath === '/' || urlPath === '/hotkey-save-pages.html') {
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
    throw new Error('puppeteer is required for admin hotkey save browser tests');
  }
  return puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });
}

async function pressSave(page) {
  await page.keyboard.down('Control');
  await page.keyboard.press('KeyS');
  await page.keyboard.up('Control');
}

async function resetSaveState(page) {
  await page.evaluate(() => {
    if (window.AdminHotkeySave && window.AdminHotkeySave.instance) {
      window.AdminHotkeySave.instance().reset();
    }
  });
}

async function showOnly(page, id) {
  await page.evaluate((keep) => {
    ['page-quotation', 'page-room-area', 'page-toast-layout'].forEach((pid) => {
      const el = document.getElementById(pid);
      if (el) el.hidden = pid !== keep;
    });
  }, id);
}

(async function main() {
  const { server, port } = await startServer();
  const browser = await launch();
  const base = 'http://127.0.0.1:' + port + '/';
  try {
    const page = await browser.newPage();
    const requests = [];
    page.on('request', (req) => {
      if (req.url().includes('/save/')) {
        requests.push({ url: req.url(), method: req.method(), postData: req.postData() });
      }
    });

    await page.setViewport({ width: 1280, height: 800 });
    await page.goto(base, { waitUntil: 'load' });
    await page.waitForFunction(() => !!(window.AdminHotkeySave && window.AdminToast));

    await page.focus('#sq-title');
    await page.keyboard.type(' extra');
    await pressSave(page);
    await page.waitForFunction(() => window.__saves && window.__saves.length === 1);
    await pressSave(page);
    await new Promise((resolve) => setTimeout(resolve, 250));
    assert.strictEqual(requests.filter((r) => r.url.includes('/save/quotation')).length, 1, 'quotation Ctrl+S after typing sends exactly one save');

    await page.waitForFunction(() => {
      const host = document.querySelector('[data-admin-toast-host]');
      return host && !host.hidden && host.textContent.indexOf('Saved') !== -1;
    });

    await showOnly(page, 'page-room-area');
    await resetSaveState(page);
    await page.evaluate(() => { window.__saves = []; });
    requests.length = 0;
    await page.waitForFunction(() => {
      const btn = document.getElementById('ra-save');
      return !!(btn && !btn.disabled && btn.offsetParent);
    });
    await page.click('#ra-room-name');
    await page.keyboard.type('Office');
    await pressSave(page);
    await page.waitForFunction(() => window.__saves && window.__saves.indexOf('room') !== -1);
    await pressSave(page);
    await new Promise((resolve) => setTimeout(resolve, 250));
    assert.strictEqual(requests.filter((r) => r.url.includes('/save/room')).length, 1, 'room area Ctrl+S after typing sends exactly one save');

    await showOnly(page, 'page-toast-layout');
    await page.evaluate(() => {
      window.AdminToast.close();
      window.AdminToast.saved();
    });
    const desktop = await page.evaluate(() => {
      const toast = document.querySelector('[data-admin-toast-host]');
      const save = document.getElementById('layout-save');
      const tr = toast.getBoundingClientRect();
      const sr = save.getBoundingClientRect();
      const overlap = !(tr.right < sr.left - 8 || tr.left > sr.right + 8 || tr.bottom < sr.top - 8 || tr.top > sr.bottom + 8);
      return { overlap, text: toast.textContent, placement: toast.getAttribute('data-placement') };
    });
    assert.strictEqual(desktop.overlap, false, 'desktop Saved toast does not cover the Save button');
    assert.ok(desktop.text.indexOf('Saved') !== -1, 'success toast says Saved');

    await page.setViewport({ width: 390, height: 720, isMobile: true, hasTouch: true });
    await page.focus('#layout-field');
    await page.evaluate(() => {
      window.AdminToast.close();
      window.AdminToast.validation([
        { el: document.getElementById('layout-field'), label: 'Name', message: 'is required' },
      ]);
    });
    const phone = await page.evaluate(() => {
      const toast = document.querySelector('[data-admin-toast-host]');
      const save = document.getElementById('layout-save');
      const field = document.getElementById('layout-field');
      const tr = toast.getBoundingClientRect();
      function hits(r) {
        return !(tr.right < r.left - 8 || tr.left > r.right + 8 || tr.bottom < r.top - 8 || tr.top > r.bottom + 8);
      }
      return {
        coversSave: hits(save.getBoundingClientRect()),
        coversField: hits(field.getBoundingClientRect()),
        hasLink: !!toast.querySelector('[data-admin-toast-field]'),
        invalid: field.classList.contains('admin-field-invalid'),
      };
    });
    assert.strictEqual(phone.coversSave, false, '390px validation toast does not cover Save');
    assert.strictEqual(phone.coversField, false, '390px validation toast does not cover the focused field');
    assert.ok(phone.hasLink, 'validation toast lists a jump link');
    assert.ok(phone.invalid, 'invalid field is highlighted');

    await page.evaluate(() => window.AdminToast.error('Server exploded'));
    const err = await page.evaluate(() => {
      const toast = document.querySelector('[data-admin-toast-host]');
      return {
        text: toast.textContent,
        hasClose: !!toast.querySelector('[data-admin-toast-close]'),
      };
    });
    assert.ok(err.text.indexOf('Server exploded') !== -1, 'server error toast shows the message');
    assert.ok(err.hasClose, 'server error toast can be closed');

    await page.evaluate(() => {
      document.documentElement.classList.add('admin-dark');
      window.AdminToast.saved();
    });
    const dark = await page.evaluate(() => {
      const card = document.querySelector('.admin-toast__card');
      const styles = getComputedStyle(card);
      return { bg: styles.backgroundColor, color: styles.color };
    });
    assert.ok(dark.bg, 'dark-mode toast has a background');

    console.log('admin-hotkey-save browser tests: ok');
  } finally {
    await browser.close();
    server.close();
  }
})().catch((err) => {
  console.error(err);
  process.exit(1);
});
