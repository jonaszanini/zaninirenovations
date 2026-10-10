'use strict';

const assert = require('assert');
const fs = require('fs');
const http = require('http');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const FIXTURE = path.join(__dirname, 'fixtures/quotation-redraft-confirm.html');

const EDITORS = [
  {
    name: 'request-submit',
    types: ['standard', 'fence', 'flooring', 'stairs', 'paving', 'low_deck', 'tile'],
  },
  {
    name: 'wizard',
    types: ['basement', 'millwork', 'shed', 'trimming'],
  },
  {
    name: 'painting',
    types: ['painting'],
  },
  {
    name: 'submit-button',
    types: ['native-submit-draft'],
  },
];

function startServer() {
  return new Promise((resolve, reject) => {
    const publicDir = path.join(ROOT, 'public');
    const server = http.createServer((req, res) => {
      const urlPath = decodeURIComponent((req.url || '/').split('?')[0]);
      let file;
      if (urlPath === '/' || urlPath === '/quotation-redraft-confirm.html') {
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
    throw new Error('puppeteer is required for quotation re-draft confirm browser tests');
  }
  return puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });
}

async function installSubmitProbe(page) {
  await page.evaluate(() => {
    window.__submits = [];
    window.__pageErrors = [];
    const record = function (form, via) {
      const data = {};
      new FormData(form).forEach((value, key) => {
        data[key] = String(value);
      });
      window.__submits.push({ via, data, action: form.getAttribute('action') });
    };
    window.__dialogEvents = [];
    ['admin-dialog:cancel', 'admin-hotkey-save:abort'].forEach((name) => {
      document.addEventListener(name, () => {
        window.__dialogEvents.push(name);
      });
    });
    HTMLFormElement.prototype.submit = function () {
      record(this, 'submit');
    };
    document.addEventListener('submit', function (e) {
      e.preventDefault();
      record(e.target, 'requestSubmit');
    }, true);
  });
  page.removeAllListeners('pageerror');
  page.on('pageerror', (err) => {
    page.evaluate((msg) => {
      window.__pageErrors.push(msg);
    }, String(err)).catch(() => {});
  });
}

async function attachEditorScript(page, family) {
  await page.evaluate((family) => {
    const form = document.getElementById('quote-form');
    const draftBtn = document.getElementById('st-btn-draft');
    const intent = document.getElementById('save-intent');
    if (!form || !draftBtn || !intent) {
      throw new Error('fixture form/draft controls missing');
    }
    if (family === 'request-submit') {
      draftBtn.addEventListener('click', function () {
        intent.value = 'draft';
        if (typeof form.requestSubmit === 'function') form.requestSubmit();
        else form.submit();
      });
      return;
    }
    if (family === 'wizard') {
      draftBtn.addEventListener('click', function (e) {
        e.preventDefault();
        intent.value = 'draft';
        HTMLFormElement.prototype.submit.call(form);
      });
      return;
    }
    if (family === 'painting') {
      let savingAsDraft = false;
      draftBtn.addEventListener('click', function () {
        savingAsDraft = true;
        intent.value = 'draft';
        form.submit();
      });
      form.addEventListener('submit', function () {
        if (!savingAsDraft) intent.value = 'publish';
        savingAsDraft = false;
      });
      return;
    }
    if (family === 'submit-button') {
      // Native type=submit click already POSTs. Do not attach a second saver.
      return;
    }
  }, family);
}

async function openEditor(page, base, family) {
  const errors = [];
  const onError = (err) => errors.push(String(err));
  page.on('pageerror', onError);
  const qs = family === 'submit-button' ? '?draft=submit' : '';
  await page.goto(base + '/quotation-redraft-confirm.html' + qs, { waitUntil: 'domcontentloaded' });
  page.off('pageerror', onError);
  assert.deepStrictEqual(errors, [], family + ' fixture must load without page errors');
  await installSubmitProbe(page);
  await attachEditorScript(page, family);
}

async function confirmRedraft(page) {
  await page.click('#st-btn-draft');
  await page.waitForSelector('#admin-dialog:not([hidden])');
  await page.waitForFunction(() => {
    return !!document.querySelector('#admin-dialog .admin-dialog-actions .admin-btn--danger, #admin-dialog .admin-dialog-actions .admin-btn--primary');
  });
  await page.click('#admin-dialog .admin-dialog-actions .admin-btn--danger, #admin-dialog .admin-dialog-actions .admin-btn--primary');
  await page.waitForFunction(() => (window.__submits || []).length > 0 || (window.__pageErrors || []).length > 0);
}

async function cancelRedraft(page) {
  await page.click('#st-btn-draft');
  await page.waitForSelector('#admin-dialog:not([hidden])');
  await page.waitForFunction(() => {
    return !!document.querySelector('#admin-dialog .admin-dialog-actions .admin-btn--ghost');
  });
  await page.click('#admin-dialog .admin-dialog-actions .admin-btn--ghost');
  await page.waitForFunction(() => {
    const dialog = document.getElementById('admin-dialog');
    return !dialog || dialog.hidden;
  });
}

(async () => {
  const { server, port } = await startServer();
  const browser = await launch();
  const base = 'http://127.0.0.1:' + port;
  const page = await browser.newPage();
  try {
    for (const editor of EDITORS) {
      await openEditor(page, base, editor.name);
      await confirmRedraft(page);
      const confirmed = await page.evaluate(() => ({
        submits: window.__submits,
        errors: window.__pageErrors,
        events: window.__dialogEvents,
        draftType: (document.getElementById('st-btn-draft') || {}).type || '',
      }));
      assert.deepStrictEqual(confirmed.errors, [], editor.name + ' confirm must not throw');
      assert.strictEqual(confirmed.submits.length, 1, editor.name + ' confirm must POST once (' + editor.types.join(', ') + ')');
      assert.strictEqual(confirmed.submits[0].data.save_intent, 'draft', editor.name + ' confirm must send draft intent');
      assert.strictEqual(confirmed.submits[0].data.is_draft, '1', editor.name + ' confirm must set is_draft');
      assert.deepStrictEqual(confirmed.events, [], editor.name + ' confirm must not emit cancel or abort');
      if (editor.name === 'submit-button') {
        assert.strictEqual(confirmed.draftType, 'submit', 'submit-button fixture must use type=submit');
      }

      await openEditor(page, base, editor.name);
      await cancelRedraft(page);
      const cancelled = await page.evaluate(() => ({
        submits: window.__submits,
        errors: window.__pageErrors,
        events: window.__dialogEvents,
        dialogHidden: document.getElementById('admin-dialog').hidden,
      }));
      assert.deepStrictEqual(cancelled.errors, [], editor.name + ' cancel must not throw');
      assert.strictEqual(cancelled.submits.length, 0, editor.name + ' cancel must send nothing');
      assert.strictEqual(cancelled.dialogHidden, true, editor.name + ' cancel must close the dialog');
      assert.ok(
        cancelled.events.indexOf('admin-dialog:cancel') !== -1,
        editor.name + ' cancel must emit admin-dialog:cancel'
      );
      assert.ok(
        cancelled.events.indexOf('admin-hotkey-save:abort') !== -1,
        editor.name + ' cancel must emit admin-hotkey-save:abort'
      );
    }
  } finally {
    await browser.close();
    server.close();
  }
  console.log('quotation-redraft-confirm browser tests: ok');
})().catch((err) => {
  console.error(err);
  process.exit(1);
});
