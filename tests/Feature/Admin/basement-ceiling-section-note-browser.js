'use strict';

const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

const email = process.env.BR_CEILING_NOTE_EMAIL;
const password = process.env.BR_CEILING_NOTE_PASSWORD;
const base = process.env.BR_CEILING_NOTE_BASE;
const outPath = process.env.BR_CEILING_NOTE_OUT;
const shotsDir = process.env.BR_CEILING_NOTE_SHOTS || path.join(process.cwd(), 'storage/app/qa-screenshots');
const createPath = process.env.BR_CEILING_NOTE_CREATE_PATH || '/admin/quotations/basement/create';
const EXPECT_CEILING_SECTION = parseMoney(process.env.BR_EXPECT_CEILING_SECTION || '0');
const EXPECT_DRYWALL_SECTION = parseMoney(process.env.BR_EXPECT_DRYWALL_SECTION || '0');

const NOTE_COPY = 'Hang, tape and mud for this ceiling are priced in Drywall / Mud / Tape / Sand.';

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

function parseMoney(text) {
  const n = parseFloat(String(text || '').replace(/[^\d.-]/g, ''));
  return isFinite(n) ? Math.round(n * 100) / 100 : 0;
}

function moneyClose(a, b, tol) {
  tol = tol == null ? 0.02 : tol;
  return Math.abs(a - b) <= tol;
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

async function waitGrandPositive(page) {
  await page.waitForFunction(() => {
    const el = document.getElementById('br-toolbar-grand-amount');
    if (!el) return false;
    const n = parseFloat(String(el.textContent || '').replace(/[^\d.-]/g, ''));
    return isFinite(n) && n > 0;
  }, { timeout: 45000 });
}

async function setCeilingType(page, value) {
  await page.evaluate((v) => {
    const el = document.getElementById('br-ceil-type');
    if (!el) throw new Error('Missing #br-ceil-type');
    el.value = v;
    el.dispatchEvent(new Event('change', { bubbles: true }));
  }, value);
  await new Promise((r) => setTimeout(r, 650));
}

async function readCeilingUi(page) {
  return page.evaluate(() => {
    const note = document.getElementById('br-ceiling-drywall-priced-note');
    const wrap = document.querySelector('[data-br-section-subtotal="ceiling"]');
    const amt = wrap ? wrap.querySelector('[data-br-section-subtotal-amount]') : null;
    const text = amt ? String(amt.textContent || '').trim() : '';
    const n = parseFloat(text.replace(/[^\d.-]/g, ''));
    return {
      noteHidden: !note || note.hidden,
      noteText: note ? String(note.textContent || '').replace(/\s+/g, ' ').trim() : '',
      subtotalHidden: !wrap || wrap.hidden,
      subtotalText: text,
      subtotalAmount: isFinite(n) ? n : 0,
      ceilingType: (document.getElementById('br-ceil-type') || {}).value || '',
    };
  });
}

async function shot(page, name) {
  const dest = path.join(shotsDir, name);
  fs.mkdirSync(path.dirname(dest), { recursive: true });
  await page.screenshot({ path: dest, fullPage: false });
  return dest;
}

(async function main() {
  fs.mkdirSync(shotsDir, { recursive: true });
  const browser = await puppeteer.launch(launchOpts());
  const page = await browser.newPage();
  page.setDefaultTimeout(30000);
  await login(page);

  const report = { ok: false, types: {}, screenshots: {} };

  await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
  await page.goto(base + createPath, { waitUntil: 'networkidle2', timeout: 60000 });
  await page.waitForSelector('#br-main-form', { timeout: 45000 });
  await typeField(page, '[name="client_name"]', 'Ceiling note browser client');
  await typeField(page, '#br-area', '800');
  await waitGrandPositive(page);
  await gotoStep(page, 'ceiling');
  await waitGrandPositive(page);
  await new Promise((r) => setTimeout(r, 500));

  const baselineDrywall = await readCeilingUi(page);
  report.baselineDrywall = baselineDrywall;
  report.baselineDrywall.literalOk = moneyClose(baselineDrywall.subtotalAmount, EXPECT_CEILING_SECTION, 0.01);
  report.baselineDrywall.noteVisible = !baselineDrywall.noteHidden;
  report.baselineDrywall.noteCopyOk = baselineDrywall.noteText === NOTE_COPY;

  report.screenshots.desktop1280 = await shot(page, 'basement-ceiling-drywall-note-1280.png');
  await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 1 });
  await new Promise((r) => setTimeout(r, 250));
  await gotoStep(page, 'ceiling');
  report.screenshots.mobile390 = await shot(page, 'basement-ceiling-drywall-note-390.png');
  await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
  await gotoStep(page, 'ceiling');

  const typeChecks = [
    { value: 'drywall', noteVisible: true },
    { value: 'paint', noteVisible: true },
    { value: 'drop', noteVisible: false },
    { value: 'none', noteVisible: false },
  ];

  for (const spec of typeChecks) {
    await setCeilingType(page, spec.value);
    await waitGrandPositive(page);
    const row = await readCeilingUi(page);
    row.noteVisibleOk = row.noteHidden !== spec.noteVisible;
    row.subtotalLiteralOk = spec.value === 'drywall'
      ? moneyClose(row.subtotalAmount, EXPECT_CEILING_SECTION, 0.01)
      : true;
    report.types[spec.value] = row;
  }

  await setCeilingType(page, 'drywall');
  await waitGrandPositive(page);
  const restored = await readCeilingUi(page);
  report.subtotalRestoredAfterCycle = moneyClose(
    restored.subtotalAmount,
    baselineDrywall.subtotalAmount,
    0.01
  );

  const drywallStep = await page.evaluate(() => {
    const wrap = document.querySelector('[data-br-section-subtotal="drywall"]');
    const amt = wrap ? wrap.querySelector('[data-br-section-subtotal-amount]') : null;
    const text = amt ? String(amt.textContent || '').trim() : '';
    const n = parseFloat(text.replace(/[^\d.-]/g, ''));
    return { amount: isFinite(n) ? n : 0, text };
  });
  report.drywallSection = drywallStep;
  report.drywallSection.literalOk = moneyClose(drywallStep.amount, EXPECT_DRYWALL_SECTION, 0.01);
  report.drywallSectionHasHang = drywallStep.amount > 0.01;

  report.ok = report.baselineDrywall.noteVisible
    && report.baselineDrywall.noteCopyOk
    && report.baselineDrywall.literalOk
    && report.subtotalRestoredAfterCycle
    && report.drywallSection.literalOk
    && report.drywallSectionHasHang
    && typeChecks.every((spec) => {
      const row = report.types[spec.value] || {};
      return row.noteVisibleOk === true;
    });

  await browser.close();
  fs.writeFileSync(outPath, JSON.stringify(report, null, 2));
  if (!report.ok) {
    console.error(JSON.stringify(report, null, 2));
    process.exit(1);
  }
})().catch((err) => {
  console.error(err);
  process.exit(1);
});
