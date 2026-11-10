'use strict';

const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

const email = process.env.BR_SUBTOTAL_EMAIL;
const password = process.env.BR_SUBTOTAL_PASSWORD;
const base = process.env.BR_SUBTOTAL_BASE;
const outPath = process.env.BR_SUBTOTAL_OUT;
const shotsDir = process.env.BR_SUBTOTAL_SHOTS || path.join(process.cwd(), 'storage/app/qa-screenshots');
const createPath = process.env.BR_SUBTOTAL_CREATE_PATH || '/admin/quotations/basement/create';
const importQuery = process.env.BR_SUBTOTAL_IMPORT_LAYOUT_Q || '';
const layoutsPath = process.env.BR_SUBTOTAL_LAYOUTS_PATH || '';

const LITERAL_WALL_SF = Number(process.env.BR_LITERAL_WALL_SF || 512);
const LITERAL_DRYWALL_WALL_SF = Number(process.env.BR_LITERAL_DRYWALL_WALL_SF || 512);
const LITERAL_CEILING_SF = Number(process.env.BR_LITERAL_CEILING_SF || 240);
const LITERAL_PAINT_CEILING_SF = Number(process.env.BR_LITERAL_PAINT_CEILING_SF || 240);
const EXPECT_PAINTING_SECTION = parseMoney(process.env.BR_EXPECT_PAINTING_SECTION || '');
const EXPECT_FLOORING_SECTION = parseMoney(process.env.BR_EXPECT_FLOORING_SECTION || '');
const EXPECT_COMMUTE_ADDED = parseMoney(process.env.BR_EXPECT_COMMUTE_ADDED || '80');
const EXPECT_DEBRIS_ADDED = parseMoney(process.env.BR_EXPECT_DEBRIS_ADDED || '150');

function launchOpts() {
  const opts = {
    headless: true,
    protocolTimeout: 90000,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
  };
  if (process.env.PUPPETEER_EXECUTABLE_PATH) {
    opts.executablePath = process.env.PUPPETEER_EXECUTABLE_PATH;
  }
  return opts;
}

async function login(page) {
  await page.goto(base + '/admin/login', { waitUntil: 'domcontentloaded' });
  if (!page.url().includes('/admin/login')) return;
  await page.waitForSelector('input[name="email"]');
  await page.type('input[name="email"]', email);
  await page.type('input[name="password"]', password);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.click('button[type="submit"]'),
  ]);
}

function parseMoney(text) {
  const n = parseFloat(String(text || '').replace(/[^\d.-]/g, ''));
  return isFinite(n) ? Math.round(n * 100) / 100 : 0;
}

function moneyClose(a, b, tol) {
  tol = tol == null ? 0.02 : tol;
  return Math.abs(a - b) <= tol;
}

async function waitSectionSubtotalAmount(page, section, expected) {
  await page.waitForFunction((sec, exp) => {
    const wrap = document.querySelector(`[data-br-section-subtotal="${sec}"]`);
    if (!wrap || wrap.hidden) return false;
    const amt = wrap.querySelector('[data-br-section-subtotal-amount]');
    const n = parseFloat(String(amt?.textContent || '').replace(/[^\d.-]/g, ''));
    return isFinite(n) && Math.abs(Math.round(n * 100) / 100 - exp) <= 0.01;
  }, { timeout: 60000 }, section, expected);
}

async function waitGrandPositive(page) {
  await page.waitForFunction(() => {
    const el = document.getElementById('br-toolbar-grand-amount');
    if (!el) return false;
    const n = parseFloat(String(el.textContent || '').replace(/[^\d.-]/g, ''));
    return isFinite(n) && n > 0;
  }, { timeout: 45000 });
}

async function gotoStep(page, key) {
  const btn = await page.$(`[data-br-goto="${key}"]`);
  if (!btn) throw new Error('Missing wizard progress control for ' + key);
  await btn.click();
  await page.waitForFunction((step) => {
    const el = document.querySelector(`[data-br-step="${step}"]`);
    return el && el.classList.contains('is-active');
  }, { timeout: 15000 }, key);
  await new Promise((r) => setTimeout(r, 350));
}

async function readSectionSubtotal(page, section) {
  return page.evaluate((sec) => {
    const wrap = document.querySelector(`[data-br-section-subtotal="${sec}"]`);
    if (!wrap || wrap.hidden) return { hidden: true, text: '', amount: 0 };
    const amt = wrap.querySelector('[data-br-section-subtotal-amount]');
    const text = amt ? String(amt.textContent || '').trim() : '';
    const n = parseFloat(text.replace(/[^\d.-]/g, ''));
    return { hidden: false, text: text, amount: isFinite(n) ? n : 0 };
  }, section);
}

async function readReconciliation(page) {
  return page.evaluate(() => {
    const out = {};
    document.querySelectorAll('[data-br-recon-key]').forEach((row) => {
      const key = row.getAttribute('data-br-recon-key') || '';
      const span = row.querySelector('span:last-child');
      const text = span ? String(span.textContent || '').trim() : '';
      const n = parseFloat(text.replace(/[^\d.-]/g, ''));
      out[key] = isFinite(n) ? n : 0;
    });
    return out;
  });
}

async function waitReconciliationGrand(page) {
  await page.waitForFunction(() => {
    const row = document.querySelector('[data-br-recon-key="grand_total"]');
    if (!row) return false;
    const n = parseFloat(String(row.textContent || '').replace(/[^\d.-]/g, ''));
    return isFinite(n) && n > 0;
  }, { timeout: 45000 });
}

async function typeField(page, selector, value) {
  await page.waitForSelector(selector, { timeout: 20000 });
  await page.evaluate((sel, val) => {
    const el = document.querySelector(sel);
    if (!el) throw new Error('Missing field ' + sel);
    el.value = String(val);
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
  }, selector, value);
}

async function importLayoutViaUi(page, query) {
  await gotoStep(page, 'overview');
  await page.waitForSelector('#br-layout-import-input', { timeout: 20000 });
  const input = await page.$('#br-layout-import-input');
  if (!input) throw new Error('Missing #br-layout-import-input');
  await input.click({ clickCount: 3 });
  await page.keyboard.press('Backspace');
  await input.type(query, { delay: 40 });
  let picked = false;
  try {
    await page.waitForFunction(() => {
      const list = document.getElementById('br-layout-import-list');
      if (!list) return false;
      const opts = list.querySelectorAll('.admin-search-select__option:not(.admin-search-select__option--action)');
      return opts.length > 0;
    }, { timeout: 20000 });
    picked = await page.evaluate(() => {
      const list = document.getElementById('br-layout-import-list');
      const opt = list
        ? list.querySelector('.admin-search-select__option:not(.admin-search-select__option--action)')
        : null;
      if (!opt) return false;
      opt.click();
      return true;
    });
  } catch (err) {
    picked = false;
  }
  if (!picked && layoutsPath) {
    picked = await page.evaluate(async (q, pathPrefix) => {
      const res = await fetch(pathPrefix + '?q=' + encodeURIComponent(q), { credentials: 'same-origin' });
      const rows = await res.json();
      const opt = Array.isArray(rows) ? rows[0] : null;
      if (!opt) return false;
      const picker = document.querySelector('[data-br-layout-picker]');
      if (!picker) return false;
      const input = document.getElementById('br-layout-import-input');
      if (input) {
        input.dispatchEvent(new Event('input', { bubbles: true }));
      }
      picker.dispatchEvent(new CustomEvent('admin-search-select:change', {
        bubbles: true,
        detail: { option: opt },
      }));
      return true;
    }, query, base + layoutsPath);
  }
  if (!picked) throw new Error('Layout search returned no options for ' + query);
  await new Promise((r) => setTimeout(r, 400));
  const dialogVisible = await page.evaluate(() => {
    const dlg = document.getElementById('admin-dialog');
    return !!(dlg && !dlg.hidden);
  });
  if (dialogVisible) {
    await page.waitForSelector('#admin-dialog .admin-btn--primary', { timeout: 10000 });
    await page.click('#admin-dialog .admin-btn--primary');
    await page.waitForFunction(() => {
      const dlg = document.getElementById('admin-dialog');
      return !dlg || dlg.hidden;
    }, { timeout: 15000 });
  }
  await new Promise((r) => setTimeout(r, 700));
}

async function shot(page, name) {
  const dest = path.join(shotsDir, name);
  fs.mkdirSync(path.dirname(dest), { recursive: true });
  await page.screenshot({ path: dest, fullPage: false });
  return dest;
}

async function scrollReconciliationIntoView(page) {
  await page.evaluate(() => {
    const el = document.querySelector('[data-br-reconciliation="1"]');
    if (el) {
      el.scrollIntoView({ block: 'center' });
    }
  });
  await new Promise((r) => setTimeout(r, 300));
}

async function enableCommuteAndDebris(page) {
  await page.evaluate(() => {
    const commuteYes = document.querySelector('[data-cp-commute-include-yes]');
    const debrisYes = document.querySelector('[data-cp-debris-include-yes]');
    if (commuteYes) commuteYes.click();
    if (debrisYes) debrisYes.click();
  });
  await page.evaluate((commuteAmt, debrisAmt) => {
    const hidden = document.querySelector('[data-cp-commute-round-trip-hidden]');
    if (hidden) {
      hidden.value = String(commuteAmt);
    }
    const card = document.querySelector('[data-cp-commute-expense]');
    const roundTripEl = card?.querySelector('[data-cp-commute-round-trip]');
    if (roundTripEl) {
      roundTripEl.textContent = 'CA$' + Number(commuteAmt).toFixed(2);
    }
    const percent = document.querySelector('[data-cp-commute-percent]');
    const trips = document.querySelector('[data-cp-commute-trips]');
    if (percent) {
      percent.value = '100';
      percent.dispatchEvent(new Event('input', { bubbles: true }));
    }
    if (trips) {
      trips.value = '1';
      trips.dispatchEvent(new Event('input', { bubbles: true }));
    }
    document.dispatchEvent(new CustomEvent('commute-expense:change', {
      detail: { included: true, amount: commuteAmt, roundTrip: commuteAmt, percent: 100, trips: 1 },
    }));
    const debrisInput = document.querySelector('[data-cp-debris-amount]');
    if (debrisInput) {
      debrisInput.value = String(debrisAmt);
      debrisInput.dispatchEvent(new Event('input', { bubbles: true }));
    }
    document.dispatchEvent(new CustomEvent('debris-removal:change', {
      detail: { included: true, amount: debrisAmt },
    }));
  }, EXPECT_COMMUTE_ADDED, EXPECT_DEBRIS_ADDED);
  await new Promise((r) => setTimeout(r, 500));
}

function shotFileNonEmpty(filePath) {
  const stat = fs.statSync(filePath);
  return stat.size > 8000;
}

(async function main() {
  fs.mkdirSync(shotsDir, { recursive: true });
  const browser = await puppeteer.launch(launchOpts());
  const page = await browser.newPage();
  page.setDefaultTimeout(30000);
  await login(page);
  const report = { geometry: {}, create: {}, importPaint: {}, reconciliation: {}, ok: false };

  await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
  await page.goto(base + createPath, { waitUntil: 'networkidle2', timeout: 60000 });
  await page.waitForSelector('#br-main-form', { timeout: 45000 });

  await typeField(page, '[name="client_name"]', 'Subtotal Browser Client');

  if (importQuery) {
    await importLayoutViaUi(page, importQuery);
    report.importPaint = report.importPaint || {};
    report.importPaint.fields = await page.evaluate(() => ({
      paintWall: parseFloat((document.getElementById('br-p-wall') || {}).value || '0'),
      paintCeil: parseFloat((document.getElementById('br-p-ceil') || {}).value || '0'),
      drywallWall: parseFloat((document.getElementById('br-dw-wall') || {}).value || '0'),
    }));
    report.importPaint.wallSfOk = moneyClose(report.importPaint.fields.paintWall, LITERAL_WALL_SF, 0.5)
      && moneyClose(report.importPaint.fields.drywallWall, LITERAL_DRYWALL_WALL_SF, 0.5);
    report.importPaint.ceilingSfOk = moneyClose(report.importPaint.fields.paintCeil, LITERAL_PAINT_CEILING_SF, 0.5);
    await gotoStep(page, 'painting');
    await waitGrandPositive(page);
    if (EXPECT_PAINTING_SECTION > 0) {
      await waitSectionSubtotalAmount(page, 'painting', EXPECT_PAINTING_SECTION);
    }
    report.importPaint.paintingSection = await readSectionSubtotal(page, 'painting');
    if (EXPECT_PAINTING_SECTION > 0) {
      report.importPaint.paintingSectionLiteralOk = moneyClose(
        report.importPaint.paintingSection.amount,
        EXPECT_PAINTING_SECTION,
        0.01
      );
    }
    report.importPaint.shot1280 = await shot(page, 'basement-painting-after-import-1280.png');
    await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 1 });
    await new Promise((r) => setTimeout(r, 250));
    await gotoStep(page, 'painting');
    report.importPaint.shot390 = await shot(page, 'basement-painting-after-import-390.png');
    await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
    await gotoStep(page, 'overview');
  }

  await typeField(page, '#br-area', '800');
  await typeField(page, '#br-fl-sf', '800');
  await waitGrandPositive(page);
  await new Promise((r) => setTimeout(r, 600));

  await gotoStep(page, 'flooring');
  const flooringBefore = await readSectionSubtotal(page, 'flooring');
  report.create.flooringBefore = flooringBefore;
  await gotoStep(page, 'framing');
  const framingBefore = await readSectionSubtotal(page, 'framing');
  await typeField(page, '#br-partition', '25');
  await waitGrandPositive(page);
  await new Promise((r) => setTimeout(r, 600));
  const framingAfter = await readSectionSubtotal(page, 'framing');
  report.create.framingBefore = framingBefore.amount;
  report.create.framingAfter = framingAfter.amount;
  report.create.framingLiveUpdated = framingAfter.amount > framingBefore.amount + 0.01;

  await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
  await gotoStep(page, 'flooring');
  if (EXPECT_FLOORING_SECTION > 0) {
    await waitSectionSubtotalAmount(page, 'flooring', EXPECT_FLOORING_SECTION);
  }
  const flooringDesktop = await readSectionSubtotal(page, 'flooring');
  report.create.flooringSection = flooringDesktop;
  if (EXPECT_FLOORING_SECTION > 0) {
    report.create.flooringSectionLiteralOk = moneyClose(flooringDesktop.amount, EXPECT_FLOORING_SECTION, 0.01);
  }
  report.create.shotFlooring1280 = await shot(page, 'basement-section-subtotal-flooring-1280.png');
  report.create.shotFlooring1280NonEmpty = shotFileNonEmpty(report.create.shotFlooring1280);

  await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 1 });
  await new Promise((r) => setTimeout(r, 250));
  await gotoStep(page, 'flooring');
  const mobileFlooring = await readSectionSubtotal(page, 'flooring');
  report.create.mobileFlooringVisible = !mobileFlooring.hidden && mobileFlooring.amount > 0;
  report.create.shotFlooring390 = await shot(page, 'basement-section-subtotal-flooring-390.png');
  report.create.shotFlooring390NonEmpty = shotFileNonEmpty(report.create.shotFlooring390);

  await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
  await new Promise((r) => setTimeout(r, 200));
  await gotoStep(page, 'overview');
  await enableCommuteAndDebris(page);
  await waitGrandPositive(page);

  await gotoStep(page, 'review');
  await waitReconciliationGrand(page);
  const recon = await readReconciliation(page);
  report.reconciliation = recon;
  const toolbarGrand = await page.$eval('#br-toolbar-grand-amount', (el) => {
    const n = parseFloat(String(el.textContent || '').replace(/[^\d.-]/g, ''));
    return isFinite(n) ? Math.round(n * 100) / 100 : 0;
  });
  report.reconciliation.toolbarGrand = toolbarGrand;
  report.reconciliation.grandMatchesToolbar = moneyClose(recon.grand_total || 0, toolbarGrand);
  await scrollReconciliationIntoView(page);
  report.importPaint = report.importPaint || {};
  report.importPaint.shotReconciliation1280 = await shot(page, 'basement-reconciliation-1280.png');
  report.importPaint.shotReconciliation1280NonEmpty = shotFileNonEmpty(
    report.importPaint.shotReconciliation1280
  );
  report.importPaint.reconVisible1280 = await page.evaluate(() => {
    const wrap = document.querySelector('[data-br-reconciliation="1"]');
    if (!wrap) return false;
    const rect = wrap.getBoundingClientRect();
    return rect.height > 20 && rect.width > 40;
  });

  await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 1 });
  await new Promise((r) => setTimeout(r, 250));
  await gotoStep(page, 'review');
  await waitReconciliationGrand(page);
  await scrollReconciliationIntoView(page);
  report.create.shotReconciliation390 = await shot(page, 'basement-reconciliation-mobile-390.png');
  report.create.shotReconciliation390NonEmpty = shotFileNonEmpty(report.create.shotReconciliation390);
  report.create.reconVisible390 = await page.evaluate(() => {
    const wrap = document.querySelector('[data-br-reconciliation="1"]');
    if (!wrap) return false;
    const rect = wrap.getBoundingClientRect();
    return rect.height > 20 && rect.width > 20;
  });

  report.ok = report.create.framingLiveUpdated
    && report.reconciliation.grandMatchesToolbar
    && report.create.shotFlooring1280NonEmpty
    && report.create.shotFlooring390NonEmpty
    && report.importPaint.shotReconciliation1280NonEmpty
    && report.create.shotReconciliation390NonEmpty
    && report.importPaint.reconVisible1280
    && report.create.reconVisible390
    && (!importQuery || (report.importPaint.wallSfOk && report.importPaint.ceilingSfOk
      && (EXPECT_PAINTING_SECTION <= 0 || report.importPaint.paintingSectionLiteralOk)))
    && (EXPECT_FLOORING_SECTION <= 0 || report.create.flooringSectionLiteralOk);

  fs.mkdirSync(path.dirname(outPath), { recursive: true });
  fs.writeFileSync(outPath, JSON.stringify(report, null, 2));
  await browser.close();
  process.stdout.write(JSON.stringify(report));
})().catch((err) => {
  console.error(err);
  process.exit(2);
});
