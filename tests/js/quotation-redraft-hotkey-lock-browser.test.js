'use strict';

const assert = require('assert');
const fs = require('fs');
const http = require('http');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const FIXTURE = path.join(__dirname, 'fixtures/quotation-redraft-hotkey-lock.html');

function startServer() {
  return new Promise((resolve, reject) => {
    const publicDir = path.join(ROOT, 'public');
    const server = http.createServer((req, res) => {
      const urlPath = decodeURIComponent((req.url || '/').split('?')[0]);
      let file;
      if (urlPath === '/' || urlPath === '/quotation-redraft-hotkey-lock.html') {
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
    throw new Error('puppeteer is required for re-draft hotkey lock browser tests');
  }
  return puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });
}

async function installSubmitProbe(page) {
  await page.evaluate(() => {
    window.__submits = [];
    window.__posts = [];
    const record = function (form, via) {
      const data = {};
      new FormData(form).forEach((value, key) => {
        data[key] = String(value);
      });
      window.__submits.push({ via, data });
    };
    HTMLFormElement.prototype.submit = function () {
      record(this, 'submit');
    };
    document.addEventListener('submit', function (e) {
      e.preventDefault();
      record(e.target, 'requestSubmit');
    }, true);
  });
}

async function openPage(page, base, order) {
  const qs = order === 'hotkey-first' ? '?order=hotkey-first' : '';
  await page.goto(base + '/quotation-redraft-hotkey-lock.html' + qs, { waitUntil: 'domcontentloaded' });
  await page.waitForFunction(() => !!(window.AdminDialog && window.AdminHotkeySave && window.AdminQuoteDraftValidation));
  await installSubmitProbe(page);
}

async function pressSave(page) {
  await page.keyboard.down('Control');
  await page.keyboard.press('KeyS');
  await page.keyboard.up('Control');
}

async function cancelDialog(page) {
  await page.waitForSelector('#admin-dialog:not([hidden])');
  await page.waitForFunction(() => !!document.querySelector('#admin-dialog .admin-dialog-actions .admin-btn--ghost'));
  await page.click('#admin-dialog .admin-dialog-actions .admin-btn--ghost');
  await page.waitForFunction(() => {
    const dialog = document.getElementById('admin-dialog');
    return !dialog || dialog.hidden;
  });
}

async function assertReleased(page, label) {
  const state = await page.evaluate(() => ({
    inflight: !!(window.AdminHotkeySave && window.AdminHotkeySave.instance && window.AdminHotkeySave.instance().isInflight()),
    submits: (window.__submits || []).length,
    dialogHidden: document.getElementById('admin-dialog').hidden,
  }));
  assert.strictEqual(state.dialogHidden, true, label + ': dialog closed');
  assert.strictEqual(state.submits, 0, label + ': cancel must not POST');
  assert.strictEqual(state.inflight, false, label + ': save lock must release immediately');
}

(async () => {
  const { server, port } = await startServer();
  const browser = await launch();
  const base = 'http://127.0.0.1:' + port;
  const page = await browser.newPage();
  try {
    for (const order of ['leave-first', 'hotkey-first']) {
      await openPage(page, base, order);
      await pressSave(page);
      await cancelDialog(page);
      await assertReleased(page, order + ' Ctrl+S cancel');

      await openPage(page, base, order);
      await page.click('#st-btn-draft');
      await cancelDialog(page);
      await assertReleased(page, order + ' manual Save as draft cancel');
    }
  } finally {
    await browser.close();
    server.close();
  }
  console.log('quotation-redraft-hotkey-lock browser tests: ok');
})().catch((err) => {
  console.error(err);
  process.exit(1);
});
