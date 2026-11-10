'use strict';

const fs = require('fs');
const puppeteer = require('puppeteer');

const email = process.env.BR_ALLOW_RESET_EMAIL;
const password = process.env.BR_ALLOW_RESET_PASSWORD;
const base = process.env.BR_ALLOW_RESET_BASE;
const outPath = process.env.BR_ALLOW_RESET_OUT;
const editPath = process.env.BR_ALLOW_RESET_EDIT_PATH || '';
const expectPermit = process.env.BR_ALLOW_RESET_PERMIT_SNIPPET || 'CA$600.00';

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

(async () => {
  const report = { ok: false };
  const browser = await puppeteer.launch(launchOpts());
  try {
    const page = await browser.newPage();
    await login(page);
    await page.goto(base + editPath, { waitUntil: 'domcontentloaded' });
    await page.waitForFunction(() => {
      const el = document.getElementById('br-toolbar-grand-amount');
      const n = parseFloat(String(el?.textContent || '').replace(/[^\d.-]/g, ''));
      return isFinite(n) && n > 0;
    }, { timeout: 45000 });
    const scopeStep = await page.$('[data-br-step="client_scope"]');
    if (scopeStep) {
      const open = await page.evaluate((el) => el.hasAttribute('open'), scopeStep);
      if (!open) {
        await page.evaluate((el) => el.setAttribute('open', ''), scopeStep);
      }
    }
    await page.waitForSelector('#br-scope-allowances');
    await new Promise((r) => setTimeout(r, 1500));
    await page.waitForSelector('#br-scope-allowances-stale');

    const beforeText = await page.$eval('#br-scope-allowances', (el) => el.value || '');
    report.hadStaleBanner = await page.$('#br-scope-allowances-stale') !== null;
    report.beforeMissingPermit = !beforeText.includes(expectPermit);

    await page.click('#br-scope-allowances-reset');
    await new Promise((r) => setTimeout(r, 2000));
    await page.waitForFunction(() => !document.getElementById('br-scope-allowances-stale'), { timeout: 60000 });

    const afterText = await page.$eval('#br-scope-allowances', (el) => el.value || '');
    report.afterHasPermit = afterText.includes('City building permit') && afterText.includes(expectPermit);
    report.staleCleared = (await page.$('#br-scope-allowances-stale')) === null;
    report.ok = report.hadStaleBanner && report.beforeMissingPermit && report.afterHasPermit && report.staleCleared;
  } catch (err) {
    report.error = String(err && err.message ? err.message : err);
  } finally {
    await browser.close();
    if (outPath) {
      fs.writeFileSync(outPath, JSON.stringify(report, null, 2));
    }
    process.exit(report.ok ? 0 : 1);
  }
})();
