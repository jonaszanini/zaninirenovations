'use strict';

const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

const email = process.env.BR_BATH_PROVIDES_EMAIL;
const password = process.env.BR_BATH_PROVIDES_PASSWORD;
const base = process.env.BR_BATH_PROVIDES_BASE;
const outPath = process.env.BR_BATH_PROVIDES_OUT;
const editPath = process.env.BR_BATH_PROVIDES_EDIT_PATH || '';
const expectJson = process.env.BR_BATH_EXPECT_DROPS || '{}';

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
  tol = tol == null ? 1.5 : tol;
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

const BATH_ITEMS = ['vanity', 'toilet', 'shower_frame', 'tub'];
let expectDrops = {};
try {
  expectDrops = JSON.parse(expectJson);
} catch (e) {
  expectDrops = {};
}

(async () => {
  const report = { ok: false, items: {} };
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

    const bathOn = await page.evaluate(() => {
      const el = document.querySelector('input[type="checkbox"][name="include_bathroom"]');
      return !!(el && el.checked);
    });
    report.bathroomOn = bathOn;
    if (!bathOn) {
      report.error = 'include_bathroom checkbox not checked';
      throw new Error('bathroom off');
    }

    const enabledState = await page.evaluate((keys) => {
      const out = {};
      keys.forEach((key) => {
        const cb = document.querySelector(`input[name="client_provides_bath_items[]"][value="${key}"]`);
        out[key] = {
          found: !!cb,
          disabled: !!(cb && cb.disabled),
          checked: !!(cb && cb.checked),
        };
      });
      return out;
    }, BATH_ITEMS);
    report.enabledState = enabledState;

    let allEnabled = true;
    BATH_ITEMS.forEach((key) => {
      const row = enabledState[key] || {};
      if (!row.found || row.disabled) {
        allEnabled = false;
      }
    });
    report.allItemsEnabled = allEnabled;

    for (const key of BATH_ITEMS) {
      const sel = `input[name="client_provides_bath_items[]"][value="${key}"]`;
      await page.waitForSelector(sel);
      const cb = await page.$(sel);
      const wasChecked = await page.evaluate((el) => el.checked, cb);
      if (wasChecked) {
        await cb.click();
        await new Promise((r) => setTimeout(r, 400));
      }

      const grandBefore = await readGrand(page);
      await cb.click();
      const expectDrop = Number(expectDrops[key] || 0);
      if (expectDrop > 1) {
        await page.waitForFunction((before, drop) => {
          const el = document.getElementById('br-toolbar-grand-amount');
          const n = parseFloat(String(el?.textContent || '').replace(/[^\d.-]/g, ''));
          return isFinite(n) && before - n >= drop - 2;
        }, { timeout: 60000 }, grandBefore, expectDrop);
      } else {
        await new Promise((r) => setTimeout(r, 800));
      }

      await new Promise((r) => setTimeout(r, 2000));
      const stillChecked = await page.evaluate((selector) => {
        const el = document.querySelector(selector);
        return !!(el && el.checked && !el.disabled);
      }, sel);
      const grandAfter = await readGrand(page);
      const drop = grandBefore - grandAfter;
      report.items[key] = {
        grandBefore,
        grandAfter,
        drop,
        expectDrop,
        dropOk: expectDrop > 0 ? moneyClose(drop, expectDrop, 2.5) : drop > 0,
        stillChecked,
      };
    }

    const itemResults = Object.values(report.items);
    const ticksOk = itemResults.every((r) => r.stillChecked);
    const dropsOk = itemResults.every((r) => r.dropOk);
    report.ok = bathOn && allEnabled && ticksOk && dropsOk;
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
