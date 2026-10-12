'use strict';

const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

const email = process.env.MW_EMAIL;
const password = process.env.MW_PASSWORD;
const base = process.env.MW_BASE;
const createUrl = process.env.MW_CREATE || '/admin/quotations/millwork/create';
const outPath = process.env.MW_OUT || path.join(process.cwd(), 'storage/app/millwork-create-browser.json');

function money(text) {
  const n = Number(String(text || '').replace(/[^0-9.-]/g, ''));
  return Number.isFinite(n) ? n : 0;
}

(async () => {
  const report = {
    sheet: '',
    mats: [],
    labourBefore: 0,
    labourAfter: 0,
    added: 0,
    grandBefore: 0,
    grandAfter: 0,
    ctrlSPosts: 0,
    ctrlSToast: '',
    ctrlSUrl: '',
    ctrlSIntent: '',
    ctrlSEmailed: false,
    draftPosts: 0,
    draftToast: '',
    draftUrl: '',
    publishToast: '',
    publishFocused: '',
    publishInView: false,
    publishCovered: false,
    publishStayedOnCreate: false,
    overlaps: {},
    overflow: {},
  };
  const browser = await puppeteer.launch({
    headless: true,
    protocolTimeout: 60000,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
  });
  const page = await browser.newPage();
  page.setDefaultNavigationTimeout(30000);
  page.setDefaultTimeout(20000);
  page.on('dialog', async (dialog) => {
    await dialog.accept().catch(() => {});
  });

  async function go(url) {
    await page.goto(url, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('body');
  }

  async function login() {
    await go(base + '/admin/login');
    if (!page.url().includes('/admin/login')) return;
    await page.waitForSelector('input[name="email"]');
    await page.type('input[name="email"]', email);
    await page.type('input[name="password"]', password);
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      page.click('button[type="submit"]'),
    ]);
    if (page.url().includes('/login')) {
      const info = await page.evaluate(() => (document.body.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 400));
      throw new Error('Login stayed on ' + page.url() + ' ' + info);
    }
  }

  async function openCreate() {
    await go(base + createUrl);
    try {
      await page.waitForSelector('#mw-main-form', { timeout: 20000 });
    } catch (err) {
      const info = await page.evaluate(() => ({
        url: location.href,
        title: document.title,
        text: (document.body && document.body.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 500),
      }));
      throw new Error('Create page missing #mw-main-form at ' + info.url + ' title=' + info.title + ' text=' + info.text);
    }
    await page.waitForFunction(() => window.AdminHotkeySave && document.getElementById('mw-btn-draft'));
  }

  async function setDim(name, value) {
    await page.evaluate((field, val) => {
      const el = document.querySelector('[name="' + field + '"]');
      if (!el) throw new Error('Missing ' + field);
      el.focus();
      el.value = val;
      el.dispatchEvent(new Event('input', { bubbles: true }));
      el.dispatchEvent(new Event('change', { bubbles: true }));
    }, name, value);
  }

  async function waitStableMoney(id) {
    let last = '';
    let steady = 0;
    for (let i = 0; i < 30; i++) {
      const cur = await page.$eval(id, (el) => (el.textContent || '').trim());
      if (cur === last && money(cur) > 0) {
        steady += 1;
        if (steady >= 2) return cur;
      } else {
        steady = 0;
        last = cur;
      }
      await new Promise((r) => setTimeout(r, 350));
    }
    return last;
  }

  function postedField(body, name) {
    if (!body) return '';
    const url = new RegExp('(?:^|&)' + name + '=([^&]*)').exec(body);
    if (url) return decodeURIComponent(url[1].replace(/\+/g, ' '));
    const multi = new RegExp('name="' + name + '"\\s*\\r?\\n\\r?\\n([^\\r\\n]*)').exec(body);
    return multi ? multi[1] : '';
  }

  function watchStorePosts(bucket) {
    const seen = new Set();
    const onRequest = (req) => {
      if (req.method() !== 'POST') return;
      let pathname = '';
      try { pathname = new URL(req.url()).pathname; } catch (err) { return; }
      if (!/\/admin\/quotations\/millwork\/?$/.test(pathname)) return;
      const key = req.url() + '|' + (req.postData() || '');
      if (seen.has(key)) return;
      seen.add(key);
      bucket.count += 1;
      const body = req.postData() || '';
      bucket.intent = postedField(body, 'save_intent');
      bucket.emailed = ['send_client_email', 'notify_client', 'email_client'].some((name) => postedField(body, name) === '1');
      bucket.once = postedField(body, '_submit_once') !== '';
    };
    page.on('request', onRequest);
    return () => page.off('request', onRequest);
  }

  try {
    await login();
    await openCreate();

    report.sheet = await page.$eval('#mw-sheet option:checked', (el) => (el.textContent || '').trim());
    report.shelfHeightVisible = await page.evaluate(() => {
      const el = document.querySelector('[name="pieces[0][height]"]');
      if (!el) return false;
      const style = getComputedStyle(el);
      const rect = el.getBoundingClientRect();
      return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 2 && rect.height > 2;
    });
    report.backVisible = await page.evaluate(() => {
      const el = document.querySelector('[name="pieces[0][include_back]"]');
      if (!el) return false;
      const row = el.closest('.mw-piece__flags, .cp-checkbox-row, label') || el;
      const style = getComputedStyle(row);
      return style.display !== 'none' && style.visibility !== 'hidden';
    });

    await page.click('#mw-client-name', { clickCount: 3 });
    await page.keyboard.type('Material stability');
    await setDim('pieces[0][sets][0][length]', '36');
    await setDim('pieces[0][sets][0][depth]', '12');
    report.mats.push(await waitStableMoney('#mw-bar-mat'));

    for (let step = 0; step < 3; step++) {
      const advanced = await page.evaluate((target) => {
        const btn = document.getElementById('mw-next');
        if (btn) btn.click();
        const status = document.getElementById('mw-step-status');
        const errors = document.getElementById('mw-step-errors');
        return {
          status: status ? status.textContent.trim() : '',
          errors: errors ? (errors.innerText || '').replace(/\s+/g, ' ').trim() : '',
          wanted: target + ' of',
        };
      }, step + 2);
      if (advanced.status.indexOf(advanced.wanted) !== 0) {
        throw new Error('Next did not reach step ' + (step + 2) + ': ' + JSON.stringify(advanced));
      }
      report.mats.push(await waitStableMoney('#mw-bar-mat'));
    }

    report.grandBefore = money(await page.$eval('#mw-bar-total', (el) => el.textContent));
    report.labourBefore = money(await page.$eval('#mw-bar-labor', (el) => el.textContent));
    const previewDone = page.waitForResponse((res) => /millwork-calculate|millwork\/preview/.test(res.url()) && res.request().method() === 'POST', { timeout: 15000 });
    await page.evaluate(() => {
      const box = document.getElementById('q-building-include');
      if (!box) throw new Error('Missing building labour checkbox');
      box.checked = true;
      box.dispatchEvent(new Event('input', { bubbles: true }));
      box.dispatchEvent(new Event('change', { bubbles: true }));
    });
    await previewDone;
    await page.waitForFunction((before) => {
      const total = document.getElementById('mw-bar-total');
      const added = document.getElementById('q-building-added-amount');
      if (!total || !added) return false;
      const grand = Number(String(total.textContent || '').replace(/[^0-9.-]/g, ''));
      const extra = Number(String(added.textContent || '').replace(/[^0-9.-]/g, ''));
      return extra > 0 && Math.abs((grand - before) - extra) < 0.06;
    }, { timeout: 10000 }, report.grandBefore);
    const afterMat = await waitStableMoney('#mw-bar-mat');
    report.mats.push(afterMat);
    report.added = money(await page.$eval('#q-building-added-amount', (el) => el.textContent));
    report.grandAfter = money(await page.$eval('#mw-bar-total', (el) => el.textContent));
    report.labourAfter = money(await page.$eval('#mw-bar-labor', (el) => el.textContent));

    const widths = [390, 768, 1024, 1280];
    for (const width of widths) {
      await page.setViewport({ width, height: 800, deviceScaleFactor: 1 });
      await new Promise((r) => setTimeout(r, 200));
      const hit = await page.evaluate(async () => {
        document.querySelectorAll('#mw-main-form .br-acc').forEach((el) => {
          el.hidden = false;
          el.setAttribute('open', '');
        });
        const bar = document.getElementById('mw-totals-bar');
        if (!bar) return { covered: ['missing totals bar'], overflow: true };
        const fields = Array.from(document.querySelectorAll('#mw-main-form input, #mw-main-form select, #mw-main-form textarea')).filter((el) => {
          if (el.disabled || el.type === 'hidden' || bar.contains(el)) return false;
          const style = getComputedStyle(el);
          if (style.display === 'none' || style.visibility === 'hidden') return false;
          return true;
        });
        const covered = [];
        for (const el of fields) {
          el.scrollIntoView({ block: 'center', inline: 'nearest' });
          await new Promise((resolve) => requestAnimationFrame(resolve));
          const a = el.getBoundingClientRect();
          const b = bar.getBoundingClientRect();
          if (a.width < 2 || a.height < 2 || b.width < 2 || b.height < 2) continue;
          const overlap = a.left < b.right - 2 && a.right > b.left + 2 && a.top < b.bottom - 2 && a.bottom > b.top + 2;
          if (overlap) covered.push(el.name || el.id || el.tagName);
          if (covered.length >= 5) break;
        }
        return {
          covered,
          overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 8,
        };
      });
      report.overlaps[String(width)] = hit.covered;
      report.overflow[String(width)] = hit.overflow;
    }

    await page.setViewport({ width: 1280, height: 800, deviceScaleFactor: 1 });
    await openCreate();
    await page.click('#mw-client-name', { clickCount: 3 });
    await page.keyboard.type('CtrlS browser marker');
    const ctrl = { count: 0, intent: '', emailed: false, once: false, statuses: [] };
    const stopCtrl = watchStorePosts(ctrl);
    page.on('response', async (res) => {
      const url = res.url();
      if (!/millwork/.test(url)) return;
      if (res.request().method() !== 'POST' && !/\/edit/.test(url)) return;
      ctrl.statuses.push(res.request().method() + ' ' + res.status() + ' ' + url.replace(base, ''));
      if (res.request().resourceType() === 'document' && /\/edit/.test(url)) {
        try { ctrl.firstHtmlHasDraft = /saved as draft/i.test(await res.text()); } catch (err) { ctrl.firstHtmlHasDraft = String(err); }
      }
    });
    await page.evaluate(() => {
      window.__mwKeys = [];
      document.addEventListener('keydown', (e) => {
        if (e.key === 's' || e.key === 'S' || e.code === 'KeyS') {
          window.__mwKeys.push({ key: e.key, code: e.code, ctrl: !!e.ctrlKey, prevented: e.defaultPrevented });
        }
      }, true);
    });
    const ctrlNav = page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 20000 }).catch(() => null);
    const cdp = await page.target().createCDPSession();
    await cdp.send('Input.dispatchKeyEvent', {
      type: 'keyDown', modifiers: 2, key: 's', code: 'KeyS', windowsVirtualKeyCode: 83, nativeVirtualKeyCode: 83,
    });
    await cdp.send('Input.dispatchKeyEvent', {
      type: 'keyUp', modifiers: 2, key: 's', code: 'KeyS', windowsVirtualKeyCode: 83, nativeVirtualKeyCode: 83,
    });
    await ctrlNav;
    try {
      await page.waitForSelector('.admin-toast--success, .admin-flash--success', { timeout: 10000 });
    } catch (err) {
      const diag = await page.evaluate(() => ({
        url: location.href,
        posts: 0,
        flash: (document.getElementById('admin-toast-flash') || {}).textContent || '',
        banner: !!document.querySelector('.admin-flash--success'),
        toast: (document.querySelector('[data-admin-toast-host]') || {}).innerText || '',
        hasDraft: /saved as draft/i.test(document.documentElement.innerHTML),
      }));
      diag.posts = ctrl.count;
      diag.statuses = ctrl.statuses;
      diag.firstHtmlHasDraft = ctrl.firstHtmlHasDraft;
      throw new Error('Ctrl+S did not save. ' + JSON.stringify(diag));
    }
    report.ctrlSPosts = ctrl.count;
    report.ctrlSIntent = ctrl.intent;
    report.ctrlSEmailed = ctrl.emailed;
    report.ctrlSOnce = ctrl.once;
    report.ctrlSUrl = page.url();
    report.ctrlSToast = await page.evaluate(() => {
      const toast = document.querySelector('.admin-toast--success .admin-toast__title, .admin-toast--success .admin-toast__message, .admin-flash--success');
      return toast ? (toast.textContent || '').replace(/\s+/g, ' ').trim() : '';
    });
    stopCtrl();

    await openCreate();
    await page.click('#mw-client-name', { clickCount: 3 });
    await page.keyboard.type('Draft button browser marker');
    const draft = { count: 0, intent: '', emailed: false, once: false };
    const stopDraft = watchStorePosts(draft);
    const draftNav = page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 20000 }).catch(() => null);
    await page.click('#mw-btn-draft');
    await draftNav;
    await page.waitForSelector('.admin-toast--success, .admin-flash--success', { timeout: 10000 });
    report.draftPosts = draft.count;
    report.draftIntent = draft.intent;
    report.draftEmailed = draft.emailed;
    report.draftUrl = page.url();
    report.draftToast = await page.evaluate(() => {
      const toast = document.querySelector('.admin-toast--success, .admin-flash--success');
      return toast ? (toast.textContent || '').replace(/\s+/g, ' ').trim() : '';
    });
    stopDraft();

    await openCreate();
    await page.click('#mw-client-name', { clickCount: 3 });
    await page.keyboard.type('Publish still required');
    const publishNav = page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 4000 }).catch(() => null);
    await page.click('#mw-btn-publish');
    await publishNav;
    await page.waitForSelector('.admin-toast--error', { timeout: 8000 });
    await page.waitForFunction(() => {
      const el = document.activeElement;
      if (!el || el === document.body) return false;
      const rect = el.getBoundingClientRect();
      return rect.width > 2 && rect.top >= -2 && rect.bottom <= window.innerHeight + 2;
    }, { timeout: 8000 }).catch(() => {});
    const publishState = await page.evaluate(() => {
      const toast = document.querySelector('.admin-toast--error');
      const el = document.activeElement;
      const bar = document.getElementById('mw-totals-bar');
      const rect = el && el.getBoundingClientRect ? el.getBoundingClientRect() : null;
      const barRect = bar ? bar.getBoundingClientRect() : null;
      let covered = false;
      if (rect && barRect && rect.width > 2 && barRect.height > 2) {
        covered = rect.left < barRect.right - 2 && rect.right > barRect.left + 2 && rect.top < barRect.bottom - 2 && rect.bottom > barRect.top + 2;
      }
      const inView = !!(rect && rect.width > 2 && rect.top >= -2 && rect.bottom <= window.innerHeight + 2);
      return {
        toast: toast ? (toast.textContent || '').replace(/\s+/g, ' ').trim() : '',
        focused: el ? (el.name || el.id || el.tagName) : '',
        inView,
        covered,
        url: location.pathname,
      };
    });
    report.publishToast = publishState.toast;
    report.publishFocused = publishState.focused;
    report.publishInView = publishState.inView;
    report.publishCovered = publishState.covered;
    report.publishStayedOnCreate = /\/millwork\/create\/?$/.test(publishState.url);

    fs.mkdirSync(path.dirname(outPath), { recursive: true });
    fs.writeFileSync(outPath, JSON.stringify(report, null, 2));

    const mats = report.mats.map(money);
    if (mats.length < 4 || mats.some((n) => n <= 0)) throw new Error('Material price never appeared: ' + report.mats.join(' | '));
    if (mats.some((n) => Math.abs(n - mats[0]) > 0.02)) throw new Error('Material price changed across steps: ' + report.mats.join(' | '));
    if (report.added <= 0) throw new Error('Additional labour added amount stayed at zero');
    if (Math.abs((report.grandAfter - report.grandBefore) - report.added) > 0.05) {
      throw new Error('Grand total delta ' + (report.grandAfter - report.grandBefore) + ' is not the added amount ' + report.added);
    }
    if (!/3\s*\/\s*4|0\.75/.test(report.sheet)) throw new Error('New quote did not preselect a 3/4 sheet: ' + report.sheet);
    if (!report.shelfHeightVisible) throw new Error('Simple shelf height is not visible');
    if (report.ctrlSPosts !== 1 || report.ctrlSIntent !== 'draft' || report.ctrlSEmailed) {
      throw new Error('Ctrl+S did not save one draft without email: ' + JSON.stringify({ posts: report.ctrlSPosts, intent: report.ctrlSIntent, emailed: report.ctrlSEmailed }));
    }
    if (!/draft/i.test(report.ctrlSToast)) throw new Error('Ctrl+S toast was not the draft confirmation: ' + report.ctrlSToast);
    if (!/millwork\/\d+/.test(report.ctrlSUrl)) throw new Error('Ctrl+S did not leave the create page: ' + report.ctrlSUrl);
    if (report.draftPosts !== 1 || report.draftIntent !== 'draft' || report.draftEmailed || !/draft/i.test(report.draftToast)) {
      throw new Error('Save as draft failed: ' + JSON.stringify(report.draftToast) + ' posts=' + report.draftPosts);
    }
    if (!report.publishStayedOnCreate || !/Please fix/i.test(report.publishToast)) {
      throw new Error('Save quotation did not show a validation toast: ' + report.publishToast);
    }
    if (!report.publishFocused || !report.publishInView || report.publishCovered) {
      throw new Error('Validation did not focus a visible field: ' + JSON.stringify(publishStateSafe(report)));
    }
    Object.keys(report.overlaps).forEach((width) => {
      if (report.overlaps[width].length) throw new Error('Totals bar covers a field at ' + width + 'px: ' + report.overlaps[width].join(', '));
    });
  } catch (err) {
    try {
      fs.mkdirSync(path.dirname(outPath), { recursive: true });
      report.error = err && err.stack ? err.stack : String(err);
      fs.writeFileSync(outPath, JSON.stringify(report, null, 2));
    } catch (writeErr) { /* ignore */ }
    throw err;
  } finally {
    await browser.close();
  }
})().catch((err) => {
  console.error(err && err.stack ? err.stack : err);
  process.exit(1);
});

function publishStateSafe(report) {
  return {
    focused: report.publishFocused,
    inView: report.publishInView,
    covered: report.publishCovered,
  };
}
