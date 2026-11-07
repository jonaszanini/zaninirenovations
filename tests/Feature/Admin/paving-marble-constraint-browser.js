'use strict';

const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

const email = process.env.PQ_MARBLE_EMAIL;
const password = process.env.PQ_MARBLE_PASSWORD;
const base = process.env.PQ_MARBLE_BASE;
const outPath = process.env.PQ_MARBLE_OUT;
const shotsDir = process.env.PQ_MARBLE_SHOTS || path.join(process.cwd(), 'storage/app/pq-marble-shots');
const expect = JSON.parse(process.env.PQ_MARBLE_EXPECT || '{}');
const createPath = expect.createPath || '/admin/quotations/paving/create';
const customScope = expect.customScope || 'CUSTOM PAVING SCOPE COPY';

function launchOpts() {
  const opts = {
    headless: true,
    protocolTimeout: 60000,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
  };
  if (process.env.PUPPETEER_EXECUTABLE_PATH) {
    opts.executablePath = process.env.PUPPETEER_EXECUTABLE_PATH;
  }
  return opts;
}

function moneyCents(text) {
  const n = parseFloat(String(text || '').replace(/[^0-9.]/g, ''));
  return Number.isFinite(n) ? Math.round(n * 100) : null;
}

async function shot(page, name) {
  const file = path.join(shotsDir, name + '.png');
  for (let attempt = 0; attempt < 4; attempt++) {
    try {
      await page.screenshot({ path: file, fullPage: false });
      return file;
    } catch (err) {
      if (attempt === 3) throw err;
      await new Promise((r) => setTimeout(r, 150 * (attempt + 1)));
    }
  }
  return file;
}

async function overflow(page) {
  return page.evaluate(() => {
    const doc = document.documentElement;
    const marble = document.getElementById('pq-marble-section');
    const box = marble ? marble.getBoundingClientRect() : null;
    return {
      innerWidth: window.innerWidth,
      scrollWidth: Math.max(doc.scrollWidth, document.body.scrollWidth),
      marbleRight: box ? box.right : 0,
    };
  });
}

async function layoutProbe(page) {
  return page.evaluate(() => {
    const sidebar = document.getElementById('admin-sidebar');
    const main = document.querySelector('.admin-main');
    const app = document.querySelector('.admin-app');
    const sb = sidebar ? sidebar.getBoundingClientRect() : null;
    const mn = main ? main.getBoundingClientRect() : null;
    const style = sidebar ? window.getComputedStyle(sidebar) : null;
    const overlaying = !!(sb && mn && sb.width > 40 && sb.right > mn.left + 8 && sb.left < mn.right - 8);
    return {
      innerWidth: window.innerWidth,
      navOpen: !!(app && app.classList.contains('admin-nav-open')),
      sidebarTransform: style ? String(style.transform || '') : '',
      sidebarLeft: sb ? sb.left : null,
      sidebarRight: sb ? sb.right : null,
      mainLeft: mn ? mn.left : null,
      overlaying,
    };
  });
}

async function readTotals(page) {
  return page.evaluate(() => {
    const footer = document.querySelector('[data-qh-footer-amount]');
    const totalRow = document.querySelector('.pq-row--total strong');
    const calcRow = document.querySelector('.pq-row--hl strong');
    const missing = document.querySelector('.pq-preview-status');
    const root = document.getElementById('pq-live-root');
    const area = document.querySelector('.pq-stone-area-in');
    const marbleArea = document.getElementById('pq-marble-area');
    function cents(text) {
      const n = parseFloat(String(text || '').replace(/[^0-9.]/g, ''));
      return isFinite(n) ? Math.round(n * 100) : null;
    }
    const footerText = footer ? String(footer.textContent || '').trim() : '';
    const totalText = totalRow ? String(totalRow.textContent || '').trim() : '';
    return {
      footer: footerText,
      totalQuote: totalText,
      calculated: calcRow ? String(calcRow.textContent || '').trim() : '',
      footerCents: cents(footerText),
      totalCents: cents(totalText),
      missing: !!missing,
      pending: !!(root && root.getAttribute('data-pq-preview-state') === 'loading')
        || !!(footer && footer.getAttribute('data-qh-footer-pending') === '1'),
      unavailable: !!(footer && footer.getAttribute('data-qh-footer-unavailable') === '1'),
      state: root ? String(root.getAttribute('data-pq-preview-state') || '') : '',
      grand: typeof window.__quoteGrandTotal === 'number' ? window.__quoteGrandTotal : null,
      grandUnavailable: !!window.__quoteGrandTotalUnavailable,
      stoneArea: area ? String(area.value || '') : '',
      marbleArea: marbleArea ? String(marbleArea.value || '') : '',
      innerWidth: window.innerWidth,
    };
  });
}

async function waitSettled(page, expectedMoney) {
  const wantCents = expectedMoney != null && expectedMoney !== ''
    ? moneyCents(expectedMoney)
    : null;
  await page.waitForFunction((want) => {
    const missing = document.querySelector('.pq-preview-status');
    if (missing) return false;
    const root = document.getElementById('pq-live-root');
    if (root && root.getAttribute('data-pq-preview-state') !== 'ready') return false;
    const footer = document.querySelector('[data-qh-footer-amount]');
    const totalRow = document.querySelector('.pq-row--total strong');
    if (!footer || !totalRow) return false;
    if (footer.getAttribute('data-qh-footer-unavailable') === '1') return false;
    if (typeof window.__quoteGrandTotal !== 'number') return false;
    function cents(text) {
      const n = parseFloat(String(text || '').replace(/[^0-9.]/g, ''));
      return isFinite(n) ? Math.round(n * 100) : null;
    }
    const f = cents(footer.textContent);
    const t = cents(totalRow.textContent);
    if (f == null || t == null || f !== t || f <= 0) return false;
    if (want != null && f !== want) return false;
    return true;
  }, { timeout: 25000 }, wantCents);
  let last = null;
  for (let i = 0; i < 8; i++) {
    await new Promise((r) => setTimeout(r, 350));
    const cur = await readTotals(page);
    const stable = cur.footerCents === last
      && cur.footerCents === cur.totalCents
      && cur.state === 'ready'
      && !cur.missing
      && (wantCents == null || cur.footerCents === wantCents);
    if (stable) return cur;
    last = cur.footerCents;
  }
  const got = await readTotals(page);
  if (wantCents != null && got.footerCents !== wantCents) {
    throw new Error('footer did not settle at ' + expectedMoney + ', got ' + JSON.stringify(got));
  }
  if (got.footerCents !== got.totalCents) {
    throw new Error('footer/summary mismatch ' + JSON.stringify(got));
  }
  return got;
}

async function captureWidth(page, width, name, expectedMoney) {
  await page.setViewport({ width, height: 900, deviceScaleFactor: 1 });
  await new Promise((r) => setTimeout(r, 200));
  const settled = await waitSettled(page, expectedMoney);
  await page.evaluate(() => {
    const live = document.getElementById('pq-live-root');
    if (live) live.scrollIntoView({ block: 'nearest' });
  });
  const overflowRow = await overflow(page);
  const shotPath = await shot(page, name);
  return {
    settled,
    overflow: overflowRow,
    shot: shotPath,
    totals: await readTotals(page),
  };
}

async function tryPublish(page) {
  const before = page.url();
  await page.click('#pq-btn-publish');
  await new Promise((r) => setTimeout(r, 500));
  return {
    urlUnchanged: page.url() === before,
    url: page.url(),
    state: await marbleState(page),
  };
}

async function marbleState(page) {
  return page.evaluate(() => {
    const form = document.getElementById('pq-main-form');
    const enabled = document.getElementById('pq-marble-enabled');
    const fields = document.getElementById('pq-marble-fields');
    const area = document.getElementById('pq-marble-area');
    const typeSel = document.getElementById('pq-marble-type');
    const depth = document.getElementById('pq-marble-depth');
    const invalid = form ? form.querySelector(':invalid') : null;
    return {
      marbleChecked: !!(enabled && enabled.checked),
      fieldsHidden: !!(fields && fields.hidden),
      fieldsDisplay: fields ? window.getComputedStyle(fields).display : '',
      areaDisabled: !!(area && area.disabled),
      areaRequired: !!(area && area.required),
      areaMin: area ? String(area.getAttribute('min') || '') : '',
      areaValue: area ? String(area.value || '') : '',
      areaValid: area ? area.checkValidity() : false,
      typeDisabled: !!(typeSel && typeSel.disabled),
      depthDisabled: !!(depth && depth.disabled),
      formValid: form ? form.checkValidity() : false,
      firstInvalid: invalid
        ? {
            name: invalid.name || '',
            id: invalid.id || '',
            message: invalid.validationMessage || '',
            disabled: !!invalid.disabled,
            min: String(invalid.getAttribute('min') || ''),
            value: String(invalid.value || ''),
          }
        : null,
    };
  });
}

async function fillBasics(page) {
  await page.waitForSelector('#pq-client');
  await page.click('#pq-client', { clickCount: 3 });
  await page.type('#pq-client', 'Ordinary Paving Browser');
  await page.evaluate((html) => {
    const ta = document.getElementById('pq-work-description');
    if (ta) ta.value = html;
    if (window.tinymce && typeof window.tinymce.get === 'function') {
      const ed = window.tinymce.get('pq-work-description');
      if (ed && typeof ed.setContent === 'function') {
        ed.setContent('<p>' + html + '</p>');
      }
    }
  }, customScope);
  await page.waitForSelector('.pq-stone-area-in');
  await page.click('.pq-stone-area-in', { clickCount: 3 });
  await page.type('.pq-stone-area-in', '80');
  const commuteNo = await page.$('[data-cp-commute-include-no]');
  if (commuteNo) await commuteNo.click();
  const debrisNo = await page.$('[data-cp-debris-include-no]');
  if (debrisNo) await debrisNo.click();
  const pay = await page.$('input[name="payment_installments"][value="3"]');
  if (pay) await pay.click();
  const reply = await page.$('input[name="ask_reply_to_accept"][value="0"]');
  if (reply) await reply.click();
}

async function fetchPdf(page) {
  const href = await page.evaluate(() => {
    const a = document.querySelector('a[data-qh-view-pdf][href]');
    return a ? a.getAttribute('href') : '';
  });
  if (!href) {
    return { status: 0, contentType: '', pdfMagic: false, bytes: 0, path: '' };
  }
  const url = href.indexOf('http') === 0 ? href : base + href;
  const meta = await page.evaluate(async (pdfUrl) => {
    const r = await fetch(pdfUrl, { credentials: 'same-origin' });
    const buf = new Uint8Array(await r.arrayBuffer());
    const magic = String.fromCharCode(buf[0] || 0, buf[1] || 0, buf[2] || 0, buf[3] || 0);
    let binary = '';
    const chunk = 0x8000;
    for (let i = 0; i < buf.length; i += chunk) {
      binary += String.fromCharCode.apply(null, buf.subarray(i, Math.min(i + chunk, buf.length)));
    }
    return {
      status: r.status,
      contentType: r.headers.get('content-type') || '',
      pdfMagic: magic === '%PDF',
      bytes: buf.length,
      b64: btoa(binary),
    };
  }, url);
  const dest = path.join(shotsDir, 'paving-ordinary-no-marble.pdf');
  fs.writeFileSync(dest, Buffer.from(meta.b64, 'base64'));
  return {
    status: meta.status,
    contentType: meta.contentType,
    pdfMagic: meta.pdfMagic,
    bytes: meta.bytes,
    path: dest,
    href,
  };
}

(async () => {
  fs.mkdirSync(shotsDir, { recursive: true });
  const browser = await puppeteer.launch(launchOpts());
  const page = await browser.newPage();
  page.setDefaultNavigationTimeout(30000);
  page.setDefaultTimeout(30000);

  await page.goto(base + '/admin/login', { waitUntil: 'domcontentloaded' });
  if (page.url().includes('/admin/login')) {
    await page.waitForSelector('input[name="email"]');
    await page.type('input[name="email"]', email);
    await page.type('input[name="password"]', password);
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      page.click('button[type="submit"]'),
    ]);
  }

  await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
  await page.goto(base + createPath, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('#pq-main-form');
  await page.waitForSelector('#pq-marble-area');

  const report = { shots: {}, overflow: {}, expected: expect };

  await fillBasics(page);
  report.off = await marbleState(page);
  report.offCreate1280 = await captureWidth(page, 1280, 'paving_ordinary_no_marble_1280', expect.offMoney);
  report.offCreate390 = await captureWidth(page, 390, 'paving_ordinary_no_marble_390', expect.offMoney);
  report.shots['off-1280'] = report.offCreate1280.shot;
  report.shots['off-390'] = report.offCreate390.shot;
  report.overflow['off-1280'] = report.offCreate1280.overflow;
  report.overflow['off-390'] = report.offCreate390.overflow;

  await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
  await page.click('#pq-marble-enabled');
  await page.waitForFunction(() => {
    const root = document.getElementById('pq-live-root');
    const footer = document.querySelector('[data-qh-footer-amount]');
    return root
      && root.getAttribute('data-pq-preview-state') === 'missing'
      && footer
      && footer.getAttribute('data-qh-footer-unavailable') === '1'
      && typeof window.__quoteGrandTotal !== 'number';
  }, { timeout: 15000 });
  report.enabledZero = await marbleState(page);
  report.enabledZeroTotals = await readTotals(page);
  report.blockedPublishZero = await tryPublish(page);

  await page.select('#pq-marble-type', await page.$eval('#pq-marble-type option:not([value=""])', (opt) => opt.value));
  await page.click('#pq-marble-area', { clickCount: 3 });
  await page.type('#pq-marble-area', '0.05');
  report.enabledTooSmall = await marbleState(page);
  report.blockedPublishTooSmall = await tryPublish(page);

  await page.click('#pq-marble-area', { clickCount: 3 });
  await page.type('#pq-marble-area', '50');
  report.enabledValid = await marbleState(page);
  report.onCreate1280 = await captureWidth(page, 1280, 'paving_enabled_marble_1280', expect.onMoney);
  report.onCreate390 = await captureWidth(page, 390, 'paving_enabled_marble_390', expect.onMoney);
  report.shots['on-1280'] = report.onCreate1280.shot;
  report.shots['on-390'] = report.onCreate390.shot;
  report.overflow['on-1280'] = report.onCreate1280.overflow;
  report.overflow['on-390'] = report.onCreate390.overflow;

  await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
  await page.click('#pq-marble-enabled');
  await new Promise((r) => setTimeout(r, 80));
  report.offAfterToggle = await marbleState(page);
  report.offAfterSettled = await waitSettled(page, expect.offMoney);
  report.overflow['offAfter-390'] = await (async () => {
    await page.setViewport({ width: 390, height: 900, deviceScaleFactor: 1 });
    await waitSettled(page, expect.offMoney);
    const row = await overflow(page);
    await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
    return row;
  })();

  await waitSettled(page, expect.offMoney);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.click('#pq-btn-publish'),
  ]);
  report.afterSaveUrl = page.url();
  report.afterSaveMarbleChecked = await page.$eval('#pq-marble-enabled', (el) => !!el.checked);
  report.afterSaveAreaDisabled = await page.$eval('#pq-marble-area', (el) => !!el.disabled);
  report.afterSaveIsDraft = await page.$eval('#pq-main-form', (el) => el.getAttribute('data-quotation-is-draft') || '');
  report.afterSave1280 = await captureWidth(page, 1280, 'paving_published_reopen_1280', expect.offMoney);
  report.afterSave390 = await captureWidth(page, 390, 'paving_published_reopen_390', expect.offMoney);
  report.afterSave390.mode = 'resize';
  report.afterSave390.layout = await layoutProbe(page);
  report.shots['reopen-1280'] = report.afterSave1280.shot;
  report.shots['reopen-390'] = report.afterSave390.shot;

  const fresh = await browser.newPage();
  fresh.setDefaultNavigationTimeout(30000);
  fresh.setDefaultTimeout(30000);
  await fresh.setViewport({ width: 390, height: 900, deviceScaleFactor: 1 });
  await fresh.goto(page.url(), { waitUntil: 'domcontentloaded' });
  await fresh.waitForSelector('#pq-main-form');
  report.fresh390 = await captureWidth(fresh, 390, 'paving_ordinary_fresh_390', expect.offMoney);
  report.fresh390.mode = 'fresh-load';
  report.fresh390.layout = await layoutProbe(fresh);
  report.shots['fresh-390'] = report.fresh390.shot;
  await fresh.close();

  report.pdf = await fetchPdf(page);

  fs.writeFileSync(outPath, JSON.stringify(report, null, 2));
  await browser.close();
})().catch((err) => {
  console.error(err && err.stack ? err.stack : String(err));
  process.exit(1);
});
