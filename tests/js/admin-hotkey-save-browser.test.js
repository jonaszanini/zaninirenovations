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
      if (urlPath.startsWith('/save/') || /calculate|\/preview/.test(urlPath)) {
        const chunks = [];
        req.on('data', (c) => chunks.push(c));
        req.on('end', () => {
          const isPreview = /calculate|\/preview/.test(urlPath);
          res.writeHead(200, { 'Content-Type': 'application/json' });
          res.end(JSON.stringify({
            ok: true,
            preview: isPreview,
            path: urlPath,
            body: Buffer.concat(chunks).toString('utf8'),
          }));
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
    ['page-quotation', 'page-room-area', 'page-toast-layout', 'page-vehicles', 'page-email', 'page-preview', 'page-cancelled', 'page-tinymce'].forEach((pid) => {
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
      if (req.url().includes('/save/') || req.url().includes('calculate') || req.url().includes('/preview')) {
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
      const bg = styles.backgroundColor;
      const m = bg.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([0-9.]+))?\)/);
      const alpha = m && m[4] != null ? Number(m[4]) : 1;
      return { bg, color: styles.color, alpha, opacity: Number(styles.opacity) };
    });
    assert.ok(dark.bg, 'dark-mode toast has a background');
    assert.ok(dark.alpha >= 0.99, 'dark-mode toast background is opaque');
    assert.ok(dark.opacity >= 0.99, 'dark-mode toast card opacity is solid');

    await page.setViewport({ width: 1280, height: 800 });
    await showOnly(page, 'page-toast-layout');
    await page.evaluate(() => {
      window.AdminToast.close();
      window.AdminToast.saved();
    });
    const sticky = await page.evaluate(() => {
      const toast = document.querySelector('[data-admin-toast-host]');
      const bar = document.getElementById('br-sticky-total');
      const next = document.getElementById('br-next');
      const tr = toast.getBoundingClientRect();
      function hits(r) {
        return !(tr.right < r.left - 8 || tr.left > r.right + 8 || tr.bottom < r.top - 8 || tr.top > r.bottom + 8);
      }
      return {
        coversBar: hits(bar.getBoundingClientRect()),
        coversNext: hits(next.getBoundingClientRect()),
      };
    });
    assert.strictEqual(sticky.coversBar, false, 'toast stays off the sticky totals');
    assert.strictEqual(sticky.coversNext, false, 'toast stays off Next');

    await showOnly(page, 'page-vehicles');
    await resetSaveState(page);
    await page.evaluate(() => { window.__saves = []; });
    requests.length = 0;
    await page.focus('#company-contact-phone');
    await pressSave(page);
    await page.waitForFunction(() => window.__saves && window.__saves.indexOf('contact') !== -1);
    assert.strictEqual(requests.filter((r) => r.url.includes('/save/contact')).length, 1, 'focused business contact form is saved');
    assert.strictEqual(requests.filter((r) => r.url.includes('/save/home')).length, 0, 'home address is not saved while contact is focused');

    await showOnly(page, 'page-email');
    await resetSaveState(page);
    await page.evaluate(() => { window.__saves = []; });
    requests.length = 0;
    await page.focus('#cp-send-client-email');
    await pressSave(page);
    await page.waitForFunction(() => window.__saves && window.__saves.indexOf('project') !== -1);
    const emailBody = requests.find((r) => r.url.includes('/save/project'));
    assert.ok(emailBody, 'project create save fired');
    assert.ok(!/send_client_email=1/.test(emailBody.postData || ''), 'Ctrl+S does not post send_client_email=1');
    assert.ok(/_hotkey_save=1/.test(emailBody.postData || ''), 'Ctrl+S posts _hotkey_save=1');

    await showOnly(page, 'page-preview');
    await resetSaveState(page);
    await page.evaluate(() => {
      window.__saves = [];
      window.__fetches = [];
      if (window.AdminToast) window.AdminToast.close();
    });
    requests.length = 0;
    await page.focus('#br-rooms');
    await pressSave(page);
    await page.waitForFunction(() => window.__saves && window.__saves.indexOf('basement') !== -1);
    await page.waitForFunction(() => {
      const host = document.querySelector('[data-admin-toast-host]');
      return host && !host.hidden && host.textContent.indexOf('Saved') !== -1;
    });
    const previewState = await page.evaluate(() => ({
      fetches: window.__fetches.slice(),
      saves: window.__saves.slice(),
      toast: (document.querySelector('[data-admin-toast-host]') || {}).textContent || '',
    }));
    assert.ok(previewState.fetches.indexOf('calculate') !== -1, 'preview still runs');
    assert.strictEqual(previewState.saves.filter((s) => s === 'basement').length, 1, 'one real basement save');
    assert.ok(previewState.toast.indexOf('Saved') !== -1, 'Saved toast is from the real save');

    await showOnly(page, 'page-tinymce');
    await resetSaveState(page);
    await page.evaluate(() => { window.__saves = []; });
    await page.evaluate(() => {
      const inst = window.AdminHotkeySave.instance();
      inst.bind();
      if (window.tinymce && window.tinymce.activeEditor && !window.tinymce.activeEditor.__adminHotkeyBound) {
        const editor = window.tinymce.activeEditor;
        editor.__adminHotkeyBound = true;
        editor.on('keydown', function (evt) { inst.handleKeydown(evt); });
      }
    });
    const tinyHandled = await page.evaluate(() => {
      const ev = {
        key: 's',
        code: 'KeyS',
        ctrlKey: true,
        metaKey: false,
        altKey: false,
        shiftKey: false,
        repeat: false,
        preventDefault() { this.prevented = true; },
        stopPropagation() {},
      };
      window.AdminHotkeySave.instance().handleKeydown(ev);
      return { prevented: ev.prevented, saves: window.__saves.slice() };
    });
    assert.strictEqual(tinyHandled.prevented, true, 'TinyMCE Ctrl+S prevents the browser dialog');
    assert.ok(tinyHandled.saves.indexOf('contract') !== -1, 'TinyMCE Ctrl+S clicks the draft save');
    const tinyHooked = await page.evaluate(() => {
      const inst = window.AdminHotkeySave.instance();
      inst.bindTiny();
      return typeof window.tinymce._addEditor === 'function';
    });
    assert.ok(tinyHooked, 'bindTiny registers the TinyMCE AddEditor hook');

    await showOnly(page, 'page-email');
    await resetSaveState(page);
    const n1 = await page.evaluate(() => {
      const form = document.getElementById('cp-create-project-form');
      const name = document.createElement('input');
      name.id = 'cp-client-name';
      name.name = 'client_name';
      name.required = true;
      name.value = '';
      form.insertBefore(name, form.firstChild);
      const box = document.getElementById('cp-send-client-email');
      box.checked = true;
      const ev = {
        key: 's', code: 'KeyS', ctrlKey: true, metaKey: false, altKey: false, shiftKey: false, repeat: false,
        preventDefault() {},
        stopPropagation() {},
      };
      window.AdminHotkeySave.instance().handleKeydown(ev);
      return {
        checked: box.checked,
        hotkeyField: !!form.querySelector('[name="_hotkey_save"]'),
      };
    });
    assert.strictEqual(n1.checked, true, 'failed Ctrl+S leaves the email box ticked');
    assert.strictEqual(n1.hotkeyField, false, 'failed Ctrl+S does not persist _hotkey_save');

    const bfcache = await page.evaluate(() => {
      const form = document.getElementById('cp-create-project-form');
      const name = document.getElementById('cp-client-name');
      if (name) name.remove();
      const box = document.getElementById('cp-send-client-email');
      box.checked = true;
      const inst = window.AdminHotkeySave.instance();
      inst.reset();
      inst.handleKeydown({
        key: 's', code: 'KeyS', ctrlKey: true, metaKey: false, altKey: false, shiftKey: false, repeat: false,
        preventDefault() {},
        stopPropagation() {},
      });
      const afterSave = { checked: box.checked, hotkey: !!form.querySelector('[name="_hotkey_save"]') };
      inst.onPageShow({ persisted: true });
      return {
        afterSave,
        checked: box.checked,
        hotkey: !!form.querySelector('[name="_hotkey_save"]'),
        expect: sessionStorage.getItem('admin-save-expect'),
      };
    });
    assert.strictEqual(bfcache.afterSave.checked, false, 'successful Ctrl+S unchecks email');
    assert.strictEqual(bfcache.checked, true, 'pageshow restores the email box');
    assert.strictEqual(bfcache.hotkey, false, 'pageshow removes _hotkey_save');
    assert.strictEqual(bfcache.expect, null, 'pageshow clears the expect flag');

    await showOnly(page, 'page-cancelled');
    await resetSaveState(page);
    const cancelled = await page.evaluate(() => {
      return new Promise((resolve) => {
        const btn = document.getElementById('date-save');
        const inst = window.AdminHotkeySave.instance();
        btn.click();
        setTimeout(() => {
          const locked = inst.isInflight();
          const ev = {
            target: btn,
            prevented: false,
            stopped: false,
            preventDefault() { this.prevented = true; },
            stopImmediatePropagation() { this.stopped = true; },
          };
          inst.onMarkedClick(ev);
          resolve({ locked: locked, swallowed: ev.prevented });
        }, 80);
      });
    });
    assert.strictEqual(cancelled.locked, false, 'cancelled submit releases the lock');
    assert.strictEqual(cancelled.swallowed, false, 'later Save clicks are not swallowed');

    await page.evaluate(() => {
      document.getElementById('admin-search-palette').hidden = false;
      document.getElementById('asp-input').focus();
    });
    await resetSaveState(page);
    await page.evaluate(() => { window.__saves = []; });
    await pressSave(page);
    await new Promise((resolve) => setTimeout(resolve, 150));
    const paletteSaves = await page.evaluate(() => window.__saves.slice());
    assert.deepStrictEqual(paletteSaves, [], 'search palette Ctrl+S does not save the page');
    await page.evaluate(() => { document.getElementById('admin-search-palette').hidden = true; });

    async function assertToastMissesChrome(width, height) {
      await page.setViewport({ width, height });
      await showOnly(page, 'page-toast-layout');
      await page.evaluate(() => {
        window.AdminToast.close();
        window.AdminToast.validation([
          { el: document.getElementById('layout-field'), label: 'Name', message: 'is required' },
        ]);
      });
      const hit = await page.evaluate(() => {
        const toast = document.querySelector('[data-admin-toast-host]');
        const bar = document.getElementById('br-sticky-total');
        const nav = document.getElementById('br-step-nav');
        const next = document.getElementById('br-next');
        const tr = toast.getBoundingClientRect();
        function hits(el) {
          const r = el.getBoundingClientRect();
          return !(tr.right < r.left - 8 || tr.left > r.right + 8 || tr.bottom < r.top - 8 || tr.top > r.bottom + 8);
        }
        return { bar: hits(bar), nav: hits(nav), next: hits(next), placement: toast.getAttribute('data-placement') };
      });
      assert.strictEqual(hit.bar, false, width + ' toast does not cover sticky totals');
      assert.strictEqual(hit.nav, false, width + ' toast does not cover step nav');
      assert.strictEqual(hit.next, false, width + ' toast does not cover Next');
    }
    await assertToastMissesChrome(390, 720);
    await assertToastMissesChrome(1024, 800);
    await assertToastMissesChrome(1280, 800);

    console.log('admin-hotkey-save browser tests: ok');
  } finally {
    await browser.close();
    server.close();
  }
})().catch((err) => {
  console.error(err);
  process.exit(1);
});
