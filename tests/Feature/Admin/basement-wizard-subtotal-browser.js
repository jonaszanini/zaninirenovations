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
const importPath = process.env.BR_SUBTOTAL_IMPORT_PATH || '';
const importQuery = process.env.BR_SUBTOTAL_IMPORT_LAYOUT_Q || '';
const layoutsPath = process.env.BR_SUBTOTAL_LAYOUTS_PATH || '';

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
  return isFinite(n) ? n : 0;
}

async function waitGrandPositive(page) {
  await page.waitForFunction(() => {
    const el = document.getElementById('br-toolbar-grand-amount');
  if (!el) return false;
    const n = parseFloat(String(el.textContent || '').replace(/[^\d.-]/g, ''));
    return isFinite(n) && n > 0;
  }, { timeout: 30000 });
}

async function fetchPreview(page) {
  return page.evaluate(async () => {
    const cfg = window.BR_QUOTATION || {};
    const form = document.getElementById('br-main-form');
    if (!form || !cfg.previewUrl) return null;
    const tokenEl = document.querySelector('meta[name="csrf-token"]');
    const token = tokenEl ? tokenEl.getAttribute('content') : '';
    const fd = new FormData(form);
    form.querySelectorAll('input.admin-checkbox__input[name]').forEach(function (cb) {
      const name = cb.name || '';
      if (!name || name.indexOf('[') !== -1) return;
      fd.set(name, cb.checked ? (cb.value || '1') : '0');
    });
    form.querySelectorAll('.cp-money-input[name]').forEach(function (input) {
      fd.set(input.name, (input.value || '').replace(/,/g, ''));
    });
    fd.delete('_method');
    const res = await fetch(cfg.previewUrl, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      body: fd,
      credentials: 'same-origin',
    });
    if (!res.ok) return null;
    return res.json();
  });
}

function sectionTotalsFromRows(preview) {
  const totals = {};
  (preview.labor || []).forEach((row) => {
    const sec = row.section || '';
    if (!sec) return;
    totals[sec] = (totals[sec] || 0) + Number(row.amount || 0);
  });
  (preview.materials || []).forEach((row) => {
    const sec = row.section || '';
    if (!sec || sec === 'extra') return;
    totals[sec] = (totals[sec] || 0) + Number(row.amount || 0);
  });
  const trades = (preview.summary && preview.summary.trades) || {};
  totals.electrical = (totals.electrical || 0) + Number((trades.electrical && trades.electrical.pre_hst) || 0);
  totals.plumbing = (totals.plumbing || 0) + Number((trades.plumbing && trades.plumbing.pre_hst) || 0);
  Object.keys(totals).forEach((k) => {
    totals[k] = Math.round(totals[k] * 100) / 100;
  });
  return totals;
}

async function openStep(page, key) {
  await page.evaluate((step) => {
    const el = document.querySelector('[data-br-step="' + step + '"]');
    if (el) {
      el.removeAttribute('hidden');
      el.open = true;
      el.classList.add('is-active');
    }
  }, key);
  await new Promise((r) => setTimeout(r, 150));
}

async function shot(page, name) {
  const dest = path.join(shotsDir, name);
  await page.screenshot({ path: dest, fullPage: false });
  return dest;
}

(async function main() {
  const browser = await puppeteer.launch(launchOpts());
  const page = await browser.newPage();
  await login(page);
  const report = { subtotals: {}, importPaint: {} };

  await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
  await page.goto(base + createPath, { waitUntil: 'networkidle2', timeout: 45000 });
  await page.waitForSelector('#br-main-form', { timeout: 45000 });
  await page.evaluate(() => {
    const name = document.querySelector('[name="client_name"]');
    if (name) {
      name.value = 'Subtotal Browser Client';
      name.dispatchEvent(new Event('input', { bubbles: true }));
    }
    const area = document.getElementById('br-area');
    if (area) {
      area.value = '500';
      area.dispatchEvent(new Event('input', { bubbles: true }));
    }
  });
  await waitGrandPositive(page);
  await new Promise((r) => setTimeout(r, 400));

  const previewBefore = await fetchPreview(page);
  if (!previewBefore) throw new Error('preview fetch failed');
  const manualTotals = sectionTotalsFromRows(previewBefore);
  const apiSections = (previewBefore.summary && previewBefore.summary.section_subtotals_pre_hst) || {};
  const sectionKeys = Object.keys(apiSections);
  const mismatches = [];
  sectionKeys.forEach((key) => {
    const api = Number(apiSections[key] || 0);
    const manual = Number(manualTotals[key] || 0);
    if (Math.abs(api - manual) > 0.02) {
      mismatches.push({ key, api, manual });
    }
  });
  report.subtotals.apiMatchesManual = mismatches.length === 0 ? mismatches : mismatches;

  let sectionSum = 0;
  sectionKeys.forEach((key) => {
    sectionSum += Number(apiSections[key] || 0);
  });
  const feePre = Number((previewBefore.summary && previewBefore.summary.contractor_fee_pre_hst) || 0);
  const subtotalPre = Number((previewBefore.summary && previewBefore.summary.subtotal_pre_hst) || 0);
  report.subtotals.sectionSum = Math.round(sectionSum * 100) / 100;
  report.subtotals.feePre = feePre;
  report.subtotals.subtotalPre = subtotalPre;
  report.subtotals.sumMatchesSubtotal = Math.abs(sectionSum + feePre - subtotalPre) < 0.05;

  const framingBefore = Number(apiSections.framing || 0);
  await page.evaluate(() => {
    const el = document.getElementById('br-partition');
    if (el) {
      el.value = '25';
      el.dispatchEvent(new Event('input', { bubbles: true }));
    }
  });
  {
    await waitGrandPositive(page);
    await new Promise((r) => setTimeout(r, 500));
    const previewAfter = await fetchPreview(page);
    const afterSections = (previewAfter.summary && previewAfter.summary.section_subtotals_pre_hst) || {};
    const framingAfter = Number(afterSections.framing || 0);
    report.subtotals.framingBefore = framingBefore;
    report.subtotals.framingAfter = framingAfter;
    report.subtotals.framingLiveUpdated = framingAfter > framingBefore + 0.01;
  }

  const domFlooring = await page.evaluate(() => {
    const wrap = document.querySelector('[data-br-section-subtotal="flooring"]');
    if (!wrap || wrap.hidden) return { hidden: true, amount: 0 };
    const amt = wrap.querySelector('[data-br-section-subtotal-amount]');
    const text = amt ? String(amt.textContent || '') : '';
    const n = parseFloat(text.replace(/[^\d.-]/g, ''));
    return { hidden: false, amount: isFinite(n) ? n : 0 };
  });
  report.subtotals.domFlooring = domFlooring;
  report.subtotals.domMatchesApi = Math.abs(domFlooring.amount - Number(apiSections.flooring || 0)) < 0.02;

  await openStep(page, 'flooring');
  report.subtotals.shotFlooring1280 = await shot(page, 'basement-section-subtotal-flooring-1280.png');
  await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 1 });
  await new Promise((r) => setTimeout(r, 200));
  await openStep(page, 'flooring');
  report.subtotals.shotFlooring390 = await shot(page, 'basement-section-subtotal-flooring-390.png');

  if (importPath && layoutsPath) {
    await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
    await page.goto(base + importPath, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#br-main-form');
    const layoutsUrl = base + layoutsPath + '?q=' + encodeURIComponent(importQuery);
    const opt = await page.evaluate(async (url) => {
      const res = await fetch(url, { credentials: 'same-origin' });
      const rows = await res.json();
      return Array.isArray(rows) ? rows[0] : null;
    }, layoutsUrl);
    if (!opt) throw new Error('layout search returned no option for paint import');
    await page.evaluate((option) => {
      const picker = document.querySelector('[data-br-layout-picker]');
      if (!picker) throw new Error('missing layout picker');
      picker.dispatchEvent(new CustomEvent('admin-search-select:change', {
        bubbles: true,
        detail: { option: option },
      }));
    }, opt);
    await new Promise((r) => setTimeout(r, 600));
    const paintWall = await page.$eval('#br-p-wall', (el) => parseFloat(el.value || '0'));
    report.importPaint.wallSf = paintWall;
    await openStep(page, 'painting');
    report.importPaint.shot1280 = await shot(page, 'basement-painting-after-import-1280.png');
    await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 1 });
    await new Promise((r) => setTimeout(r, 200));
    await openStep(page, 'painting');
    report.importPaint.shot390 = await shot(page, 'basement-painting-after-import-390.png');
    report.importPaint.ok = paintWall > 0;
  }

  report.ok = report.subtotals.sumMatchesSubtotal
    && report.subtotals.apiMatchesManual.length === 0
    && report.subtotals.domMatchesApi
  && report.subtotals.framingLiveUpdated
    && (!importPath || report.importPaint.ok);

  fs.mkdirSync(path.dirname(outPath), { recursive: true });
  fs.writeFileSync(outPath, JSON.stringify(report, null, 2));
  await browser.close();
  process.stdout.write(JSON.stringify(report));
})().catch((err) => {
  console.error(err);
  process.exit(2);
});
