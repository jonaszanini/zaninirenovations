'use strict';

const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

const email = process.env.BR_CLIENT_PROVIDES_EMAIL;
const password = process.env.BR_CLIENT_PROVIDES_PASSWORD;
const base = process.env.BR_CLIENT_PROVIDES_BASE;
const outPath = process.env.BR_CLIENT_PROVIDES_OUT;
const shotsDir = process.env.BR_CLIENT_PROVIDES_SHOTS || path.join(process.cwd(), 'storage/app/qa-screenshots');
const editPath = process.env.BR_CLIENT_PROVIDES_EDIT_PATH || '';
const expectGrandDrop = Number(process.env.BR_EXPECT_GRAND_DROP || '4331.85');
const expectGrandBefore = Number(process.env.BR_EXPECT_GRAND_BEFORE || '0');

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
  tol = tol == null ? 0.05 : tol;
  return Math.abs(a - b) <= tol;
}

async function waitGrand(page) {
  await page.waitForFunction(() => {
    const el = document.getElementById('br-toolbar-grand-amount');
    if (!el) return false;
    const n = parseFloat(String(el.textContent || '').replace(/[^\d.-]/g, ''));
    return isFinite(n) && n > 0;
  }, { timeout: 45000 });
}

async function readGrand(page) {
  return parseMoney(await page.$eval('#br-toolbar-grand-amount', (el) => el.textContent));
}

async function readFlooringSectionSubtotal(page) {
  return page.evaluate(() => {
    const wrap = document.querySelector('[data-br-section-subtotal="flooring"]');
    if (!wrap || wrap.hidden) return 0;
    const amt = wrap.querySelector('[data-br-section-subtotal-amount]');
    return parseFloat(String(amt?.textContent || '').replace(/[^\d.-]/g, '')) || 0;
  });
}

async function floorMatTotal(page) {
  return page.evaluate(() => {
    let sum = 0;
    document.querySelectorAll('#br-mat-rows tr[data-br-mat-key]').forEach((row) => {
      const key = row.getAttribute('data-br-mat-key') || '';
      if (!key) return;
      const text = row.querySelector('.cp-col-amount')?.textContent || '';
      const n = parseFloat(String(text).replace(/[^\d.-]/g, ''));
      if (row.textContent.includes('client provided')) {
        sum += 0;
      } else if (isFinite(n)) {
        sum += n;
      }
    });
    return sum;
  });
}

(async () => {
  const report = { ok: false };
  const browser = await puppeteer.launch(launchOpts());
  try {
    const page = await browser.newPage();
    await login(page);
    await page.goto(base + editPath, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('[data-br-step="review"]');
    await waitGrand(page);

    const review = await page.$('[data-br-step="review"]');
    if (review) {
      const open = await page.evaluate((el) => el.hasAttribute('open'), review);
      if (!open) {
        await page.evaluate((el) => el.setAttribute('open', ''), review);
      }
    }

    await page.waitForSelector('input[name="client_provides_sections[]"][value="flooring"]');
    const flooringCb = await page.$('input[name="client_provides_sections[]"][value="flooring"]');
    report.flooringWasChecked = await page.evaluate((el) => el.checked, flooringCb);

    const grandBefore = await readGrand(page);
    report.before = { grand: grandBefore };
    if (expectGrandBefore > 0 && !moneyClose(grandBefore, expectGrandBefore, 2)) {
      report.grandBeforeMismatch = { ui: grandBefore, expected: expectGrandBefore };
    }

  if (!report.flooringWasChecked) {
      await flooringCb.click();
      await page.waitForFunction((before, drop) => {
        const el = document.getElementById('br-toolbar-grand-amount');
        const n = parseFloat(String(el?.textContent || '').replace(/[^\d.-]/g, ''));
        return isFinite(n) && n < before - drop + 50;
      }, { timeout: 60000 }, grandBefore, expectGrandDrop);
    } else {
      await flooringCb.click();
      await page.waitForFunction((before) => {
        const el = document.getElementById('br-toolbar-grand-amount');
        const n = parseFloat(String(el?.textContent || '').replace(/[^\d.-]/g, ''));
        return isFinite(n) && n > before;
      }, { timeout: 60000 }, grandBefore);
      await flooringCb.click();
      await page.waitForFunction((before, drop) => {
        const el = document.getElementById('br-toolbar-grand-amount');
        const n = parseFloat(String(el?.textContent || '').replace(/[^\d.-]/g, ''));
        return isFinite(n) && n < before - drop + 50;
      }, { timeout: 60000 }, grandBefore, expectGrandDrop);
    }

    await new Promise((r) => setTimeout(r, 1500));
    const flooringStillChecked = await page.evaluate(() => {
      const cb = document.querySelector('input[name="client_provides_sections[]"][value="flooring"]');
      return !!(cb && cb.checked);
    });
    report.flooringStillChecked = flooringStillChecked;
    const grandAfter = await readGrand(page);
    report.after = { grand: grandAfter };
    report.grandDrop = grandBefore - grandAfter;

    const clientProvidedMarker = await page.evaluate(() => {
      return Array.from(document.querySelectorAll('#br-mat-rows tr')).some((row) => {
        const text = row.textContent.toLowerCase();
        return text.includes('client provided')
          && (text.includes('lvp') || text.includes('underlayment') || text.includes('flooring') || text.includes('transition'));
      });
    });
    report.clientProvidedMarker = clientProvidedMarker;

    const reconOk = await page.evaluate(() => {
      const rows = {};
      document.querySelectorAll('[data-br-recon-key]').forEach((row) => {
        const key = row.getAttribute('data-br-recon-key') || '';
        const span = row.querySelector('span:last-child');
        const text = span ? String(span.textContent || '').trim() : '';
        const n = parseFloat(text.replace(/[^\d.-]/g, ''));
        if (key) rows[key] = isFinite(n) ? n : 0;
      });
      const inHouse = rows.sections_subtotal_sum || 0;
      const extra = rows.extra_materials || 0;
      const fee = rows.contractor_fee || 0;
      const subtotal = rows.subtotal_pre_hst || 0;
      const assembled = Math.round((inHouse + extra + fee) * 100) / 100;
      if (Math.abs(assembled - subtotal) > 0.05) {
        return false;
      }
      const elec = rows.electrical_tax_in || 0;
      const plumb = rows.plumbing_tax_in || 0;
      const additional = rows.additional_costs_tax_in || 0;
      const hst = rows.hst || 0;
      const grand = rows.grand_total || 0;
      const grandAssembled = Math.round((subtotal + hst + elec + plumb + additional) * 100) / 100;
      return Math.abs(grandAssembled - grand) <= 0.05;
    });
    report.reconciliationAddUp = reconOk;

    report.grandDropOk = moneyClose(report.grandDrop, expectGrandDrop, 3);
    const grandBeforeOk = !report.grandBeforeMismatch;
    report.ok = report.grandDropOk && clientProvidedMarker && reconOk && grandBeforeOk && flooringStillChecked;

    const shotBase = path.join(shotsDir, 'basement-client-provides-review');
    await page.setViewport({ width: 1280, height: 900 });
    const reviewEl = await page.$('[data-br-step="review"]');
    if (reviewEl) {
      await reviewEl.screenshot({ path: shotBase + '-1280.png' });
    }
    await page.setViewport({ width: 390, height: 900 });
    if (reviewEl) {
      await reviewEl.screenshot({ path: shotBase + '-390.png' });
    }
  } finally {
    await browser.close();
  }

  if (outPath) {
    fs.mkdirSync(path.dirname(outPath), { recursive: true });
    fs.writeFileSync(outPath, JSON.stringify(report, null, 2));
  }
  if (!report.ok) {
    console.error(JSON.stringify(report, null, 2));
    process.exit(1);
  }
})();
