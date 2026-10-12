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
    window.__dialogEvents = [];
    ['admin-dialog:cancel', 'admin-hotkey-save:abort'].forEach((name) => {
      document.addEventListener(name, () => {
        window.__dialogEvents.push(name);
      });
    });
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

async function openPage(page, base, order, opts) {
  const params = [];
  if (order === 'hotkey-first') params.push('order=hotkey-first');
  if (opts && opts.draftType === 'submit') params.push('draft=submit');
  const qs = params.length ? '?' + params.join('&') : '';
  await page.goto(base + '/quotation-redraft-hotkey-lock.html' + qs, { waitUntil: 'domcontentloaded' });
  await page.waitForFunction(() => !!(window.AdminDialog && window.AdminHotkeySave && window.AdminQuoteDraftValidation));
  await installSubmitProbe(page);
}

async function pressSave(page) {
  await page.keyboard.down('Control');
  await page.keyboard.press('KeyS');
  await page.keyboard.up('Control');
}

async function acceptDialog(page) {
  await page.waitForSelector('#admin-dialog:not([hidden])');
  await page.waitForFunction(() => {
    return !!document.querySelector('#admin-dialog .admin-dialog-actions .admin-btn--danger, #admin-dialog .admin-dialog-actions .admin-btn--primary');
  });
  await page.click('#admin-dialog .admin-dialog-actions .admin-btn--danger, #admin-dialog .admin-dialog-actions .admin-btn--primary');
  await page.waitForFunction(() => (window.__submits || []).length > 0);
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
    events: window.__dialogEvents || [],
    dialogHidden: document.getElementById('admin-dialog').hidden,
  }));
  assert.strictEqual(state.dialogHidden, true, label + ': dialog closed');
  assert.strictEqual(state.submits, 0, label + ': cancel must not POST');
  assert.strictEqual(state.inflight, false, label + ': save lock must release immediately');
  assert.ok(state.events.indexOf('admin-dialog:cancel') !== -1, label + ': cancel must emit admin-dialog:cancel');
  assert.ok(state.events.indexOf('admin-hotkey-save:abort') !== -1, label + ': cancel must emit admin-hotkey-save:abort');
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

      await openPage(page, base, order);
      await pressSave(page);
      await acceptDialog(page);
      const accepted = await page.evaluate(() => ({
        submits: window.__submits,
        events: window.__dialogEvents,
        inflight: !!(window.AdminHotkeySave && window.AdminHotkeySave.instance && window.AdminHotkeySave.instance().isInflight()),
      }));
      assert.strictEqual(accepted.submits.length, 1, order + ' Ctrl+S accept must POST once');
      assert.strictEqual(accepted.submits[0].data.save_intent, 'draft', order + ' Ctrl+S accept must send draft intent');
      assert.strictEqual(accepted.submits[0].data.is_draft, '1', order + ' Ctrl+S accept must set is_draft');
      assert.deepStrictEqual(accepted.events, [], order + ' Ctrl+S accept must not emit cancel or abort');
    }

    await openPage(page, base, 'leave-first', { draftType: 'submit' });
    const submitType = await page.evaluate(() => (document.getElementById('st-btn-draft') || {}).type || '');
    assert.strictEqual(submitType, 'submit', 'submit-type fixture must use type=submit');
    await pressSave(page);
    await acceptDialog(page);
    const submitAccepted = await page.evaluate(() => ({
      submits: window.__submits,
      events: window.__dialogEvents,
    }));
    assert.strictEqual(submitAccepted.submits.length, 1, 'submit-type draft: fallback must not POST twice when the click already saved');
    assert.strictEqual(submitAccepted.submits[0].data.save_intent, 'draft', 'submit-type draft accept must send draft intent');
    assert.deepStrictEqual(submitAccepted.events, [], 'submit-type confirm must not emit cancel or abort');
  } finally {
    await browser.close();
    server.close();
  }
  console.log('quotation-redraft-hotkey-lock browser tests: ok');
})().catch((err) => {
  console.error(err);
  process.exit(1);
});
