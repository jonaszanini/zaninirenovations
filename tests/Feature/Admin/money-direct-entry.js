/**
 * Types 4.75 into every visible .cp-money-input (selector sweep, not a
 * field-name list) and retypes existing values so they must stay the same.
 */
'use strict';

const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

const email = process.env.MONEY_EMAIL;
const password = process.env.MONEY_PASSWORD;
const base = process.env.MONEY_BASE;
const pages = JSON.parse(process.env.MONEY_PAGES || '[]');
const outPath = process.env.MONEY_OUT || path.join(process.cwd(), 'storage/app/money-direct-entry.json');

function parseMoney(value) {
  const n = parseFloat(String(value || '').replace(/,/g, ''));
  return isFinite(n) ? n : 0;
}

(async () => {
  const browser = await puppeteer.launch({
    headless: true,
    protocolTimeout: 30000,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
  });
  const page = await browser.newPage();
  page.setDefaultNavigationTimeout(30000);
  page.setDefaultTimeout(20000);

  async function go(url) {
    await page.goto(url, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('body');
  }

  async function login() {
    await go(base + '/admin/login');
    if (page.url().includes('/admin/login')) {
      await page.waitForSelector('input[name="email"]');
      await page.type('input[name="email"]', email);
      await page.type('input[name="password"]', password);
      await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
        page.click('button[type="submit"]'),
      ]);
    }
  }

  async function revealMoneyFields() {
    await page.evaluate(() => {
      document.querySelectorAll('details').forEach((d) => { d.open = true; });
      document.querySelectorAll('[data-cp-debris-include-yes]').forEach((el) => {
        if (!el.checked) {
          el.click();
        }
      });
    });
  }

  async function sweep() {
    await revealMoneyFields();
    const handles = await page.$$('.cp-money-input');
    const results = [];
    for (let i = 0; i < handles.length; i++) {
      const handle = handles[i];
      const meta = await page.evaluate((el) => {
        if (!el || el.disabled || el.readOnly) {
          return { skip: true, reason: 'disabled' };
        }
        if (el.classList.contains('is-locked')) {
          return { skip: true, reason: 'locked' };
        }
        const style = window.getComputedStyle(el);
        const rect = el.getBoundingClientRect();
        if (style.display === 'none' || style.visibility === 'hidden' || rect.width < 2 || rect.height < 2) {
          return { skip: true, reason: 'hidden' };
        }
        return {
          skip: false,
          name: el.name || el.id || '',
          entry: el.getAttribute('data-cp-money-entry') || '',
          before: el.value || '',
        };
      }, handle);
      if (meta.skip) {
        results.push(meta);
        continue;
      }
      const beforeNum = parseMoney(meta.before);
      const isExisting = meta.before.trim() !== '' && beforeNum > 0;
      await handle.click({ clickCount: 3 });
      await page.keyboard.press('Backspace');
      const typed = isExisting ? String(beforeNum) : '4.75';
      await page.keyboard.type(typed, { delay: 15 });
      await page.evaluate((el) => { el.blur(); }, handle);
      const after = await page.evaluate((el) => el.value || '', handle);
      const afterNum = parseMoney(after);
      const expected = isExisting ? beforeNum : 4.75;
      results.push({
        skip: false,
        name: meta.name,
        entry: meta.entry,
        existing: isExisting,
        typed,
        before: meta.before,
        after,
        afterNum,
        ok: Math.abs(afterNum - expected) < 0.0001 && Math.abs(afterNum - expected / 100) > 0.001,
        entryDirect: meta.entry === 'direct',
      });
    }
    return results;
  }

  const report = { pages: [], save: null };
  try {
    await login();
    for (const url of pages) {
      await go(base + url);
      if (page.url().includes('/admin/login')) {
        throw new Error('Still on login after opening ' + url);
      }
      const fields = await sweep();
      report.pages.push({
        url,
        status: 200,
        fields,
        typed: fields.filter((f) => !f.skip).length,
        failed: fields.filter((f) => !f.skip && !f.ok).length,
      });
    }

    await go(base + '/admin/quotations/flooring/price-configuration');
    await revealMoneyFields();
    const existingSnapshot = await page.evaluate(() => {
      return Array.from(document.querySelectorAll('input[name*="[installation_price_per_sqft]"]'))
        .filter((el) => (el.name || '').indexOf('new_flooring_types') === -1)
        .map((el) => ({ name: el.name, value: el.value }));
    });
    const addName = await page.$('input[name="new_flooring_types[0][name]"]');
    const addPrice = await page.$('input[name="new_flooring_types[0][installation_price_per_sqft]"]');
    if (!addName || !addPrice) {
      throw new Error('Add flooring type inputs missing');
    }
    await addName.click({ clickCount: 3 });
    await page.keyboard.type('Direct entry oak');
    await addPrice.click({ clickCount: 3 });
    await page.keyboard.press('Backspace');
    await page.keyboard.type('4.75', { delay: 15 });
    await page.evaluate((el) => { el.blur(); }, addPrice);
    const addShown = parseMoney(await page.evaluate((el) => el.value, addPrice));
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      page.evaluate(() => {
        var form = document.querySelector('form.admin-card');
        if (!form) throw new Error('Flooring config form missing');
        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit();
        } else {
          form.submit();
        }
      }),
    ]);
    report.save = {
      addShown,
      existingSnapshot,
      afterUrl: page.url(),
    };

    fs.mkdirSync(path.dirname(outPath), { recursive: true });
    fs.writeFileSync(outPath, JSON.stringify(report, null, 2));
    const failedPages = report.pages.filter((p) => p.failed > 0);
    const typedTotal = report.pages.reduce((n, p) => n + p.typed, 0);
    if (typedTotal < 8) {
      throw new Error('Selector sweep typed too few money fields: ' + typedTotal);
    }
    if (failedPages.length) {
      throw new Error('Sweep failed on ' + failedPages.map((p) => p.url).join(', '));
    }
    if (Math.abs(addShown - 4.75) > 0.0001) {
      throw new Error('Add flooring type showed ' + addShown + ' after typing 4.75');
    }
    await browser.close();
    process.exit(0);
  } catch (err) {
    try {
      fs.mkdirSync(path.dirname(outPath), { recursive: true });
      fs.writeFileSync(outPath, JSON.stringify({ error: String(err && err.message ? err.message : err), report }, null, 2));
    } catch (e) { /* ignore */ }
    console.error(err);
    await browser.close();
    process.exit(1);
  }
})();
