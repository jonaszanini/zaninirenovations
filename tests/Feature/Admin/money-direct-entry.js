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
const invoiceUrl = process.env.MONEY_INVOICE_URL || '';
const outPath = process.env.MONEY_OUT || path.join(process.cwd(), 'storage/app/money-direct-entry.json');
const PREVIEW_FIRE_MS = 280;

function parseMoney(value) {
  const n = parseFloat(String(value || '').replace(/,/g, ''));
  return isFinite(n) ? n : 0;
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
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

  async function invoiceDiscountPreview() {
    if (!invoiceUrl) {
      throw new Error('MONEY_INVOICE_URL is required for the discount preview');
    }
    await go(base + invoiceUrl);
    await page.waitForFunction(() => window.cpMoneyMask && typeof window.cpMoneyMask.parse === 'function');
    await page.waitForSelector('#cp-invoice-discount-amt');
    const amt = await page.$('#cp-invoice-discount-amt');
    await amt.click({ clickCount: 3 });
    await page.keyboard.press('Backspace');
    await page.keyboard.type('130', { delay: 15 });
    await page.keyboard.press('Tab');
    return page.evaluate(() => {
      const text = (id) => {
        const el = document.getElementById(id);
        return el ? String(el.textContent || '').trim() : '';
      };
      return {
        field: document.getElementById('cp-invoice-discount-amt').value,
        net: text('cp-invoice-net-subtotal'),
        hst: text('cp-invoice-hst'),
        grand: text('cp-invoice-grand'),
      };
    });
  }

  async function behaviorChecks() {
    return page.evaluate(() => {
      if (!window.cpMoneyMask || typeof window.cpMoneyMask.wire !== 'function') {
        throw new Error('cpMoneyMask missing');
      }
      function probe(raw, opts) {
        opts = opts || {};
        const form = document.createElement('form');
        const input = document.createElement('input');
        input.className = 'cp-money-input';
        input.setAttribute('data-cp-money-places', '2');
        if (opts.direct) {
          input.setAttribute('data-cp-money-entry', 'direct');
        }
        form.appendChild(input);
        document.body.appendChild(form);
        window.cpMoneyMask.wire(input);
        input.focus();
        input.value = raw;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        if (!opts.skipBlur) {
          input.blur();
          input.dispatchEvent(new FocusEvent('blur', { bubbles: true }));
        }
        const submitted = form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        const result = {
          value: input.value,
          num: parseFloat(String(input.value || '').replace(/,/g, '')),
          invalid: input.classList.contains('is-invalid') || input.validity.customError,
          submitAllowed: submitted,
        };
        form.remove();
        return result;
      }

      const untagged = probe('4');
      const comma = probe('4,75');
      const ambiguous = probe('1.234,56');
      const negative = probe('-5');
      const rounded = probe('4.756');
      const loneDot = probe('.', { skipBlur: true });

      return {
        untaggedFour: untagged.num,
        untaggedShown: untagged.value,
        commaDecimal: comma.num,
        commaShown: comma.value,
        ambiguousInvalid: ambiguous.invalid,
        ambiguousShown: ambiguous.value,
        ambiguousValue: isFinite(ambiguous.num) ? ambiguous.num : 0,
        negativeInvalid: negative.invalid,
        negativeShown: negative.value,
        negativeValue: isFinite(negative.num) ? negative.num : 0,
        rounded: rounded.num,
        loneDotBlocked: loneDot.invalid && !loneDot.submitAllowed,
      };
    });
  }

  async function revealPavingLabourRates() {
    await revealMoneyFields();
    await page.evaluate(() => {
      const include = document.getElementById('pq-helper-include');
      if (include && !include.checked) {
        include.click();
      }
    });
    await page.waitForFunction(() => {
      const el = document.getElementById('pq-helper');
      if (!el) return false;
      const wrap = document.getElementById('pq-helper-wrap');
      if (wrap && wrap.hidden) return false;
      const style = window.getComputedStyle(el);
      const rect = el.getBoundingClientRect();
      return style.display !== 'none' && style.visibility !== 'hidden' && rect.width >= 2 && rect.height >= 2;
    }, { timeout: 10000 });
  }

  async function typeIntoFocusedSlowly(handle, typed) {
    await handle.evaluate((el) => el.scrollIntoView({ block: 'center', inline: 'nearest' }));
    await handle.click({ clickCount: 3 });
    await page.keyboard.press('Backspace');
    const snapshots = [];
    let expected = '';
    for (const ch of typed) {
      expected += ch;
      await page.keyboard.type(ch);
      await sleep(PREVIEW_FIRE_MS);
      const state = await page.evaluate((el) => ({
        value: el.value || '',
        focused: document.activeElement === el,
      }), handle);
      snapshots.push({ typedSoFar: expected, value: state.value, focused: state.focused });
    }
    const posted = await page.evaluate((el) => {
      if (!el.form) return null;
      const value = new FormData(el.form).get(el.name);
      return value == null ? null : String(value);
    }, handle);
    const name = await page.evaluate((el) => el.name || el.id || '', handle);
    const focusedValue = snapshots.length ? snapshots[snapshots.length - 1].value : '';
    return {
      name,
      typed,
      focusedValue,
      posted,
      snapshots,
      ok: snapshots.every((s) => s.value === s.typedSoFar && s.focused) && posted === typed,
    };
  }

  async function labourRateKeypressSave() {
    await go(base + '/admin/quotations/paving/create');
    await revealPavingLabourRates();
    const owner = await page.$('#pq-owner');
    const helper = await page.$('#pq-helper');
    if (!owner || !helper) {
      throw new Error('Paving labour rate fields missing');
    }
    const fields = [
      Object.assign({ selector: '#pq-owner' }, await typeIntoFocusedSlowly(owner, '65')),
      Object.assign({ selector: '#pq-helper' }, await typeIntoFocusedSlowly(helper, '35')),
      Object.assign({ selector: '#pq-owner' }, await typeIntoFocusedSlowly(owner, '120.50')),
    ];
    await page.evaluate(() => {
      const ownerEl = document.getElementById('pq-owner');
      const helperEl = document.getElementById('pq-helper');
      if (ownerEl) ownerEl.blur();
      if (helperEl) helperEl.blur();
      const area = document.querySelector('input[name="stone_entries[0][area_sq_ft]"]');
      if (area && !(area.value || '').trim()) {
        area.value = '168';
        area.dispatchEvent(new Event('input', { bubbles: true }));
      }
    });
    let posted = '';
    const onRequest = (req) => {
      const method = String(req.method() || '').toUpperCase();
      if (method === 'POST' || method === 'PUT') {
        posted = req.postData() || posted;
      }
    };
    page.on('request', onRequest);
    try {
      await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded' }).catch(() => null),
        page.click('#pq-btn-draft'),
      ]);
    } finally {
      page.off('request', onRequest);
    }
    return { fields, afterUrl: page.url(), posted, failed: fields.filter((f) => !f.ok).length };
  }

  async function pavingRateMaskChecks() {
    await go(base + '/admin/quotations/paving/create');
    await page.waitForSelector('#pq-owner');
    return page.evaluate(() => {
      function probe(raw) {
        const input = document.getElementById('pq-owner');
        input.focus();
        input.value = raw;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.blur();
        input.dispatchEvent(new FocusEvent('blur', { bubbles: true }));
        const submitted = input.form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        return {
          value: input.value,
          invalid: input.classList.contains('is-invalid') || input.validity.customError,
          message: String(input.validationMessage || ''),
          submitAllowed: submitted,
        };
      }
      return {
        comma: probe('4,75'),
        ambiguous: probe('1.234,56'),
      };
    });
  }

  async function fenceGateLowerNotice() {
    await go(base + '/admin/quotations/fence/create');
    await page.waitForSelector('#fq-gates');
    await page.click('#fq-add-side');
    const lengths = await page.$$('.fq-side-length');
    if (lengths.length < 2) {
      throw new Error('Fence needs two runs to lower Gates');
    }
    for (const handle of lengths) {
      await handle.click({ clickCount: 3 });
      await page.keyboard.type('20');
    }
    await page.evaluate(() => {
      const gates = document.getElementById('fq-gates');
      gates.value = '2';
      gates.dispatchEvent(new Event('input', { bubbles: true }));
      gates.dispatchEvent(new Event('change', { bubbles: true }));
    });
    const boxes = await page.$$('.fq-side-gate-here');
    for (const handle of boxes) {
      const checked = await page.evaluate((el) => el.checked, handle);
      if (!checked) {
        await handle.click();
      }
    }
    return page.evaluate(() => {
      const gates = document.getElementById('fq-gates');
      const beforeTicks = document.querySelectorAll('.fq-side-gate-here:checked').length;
      gates.value = '1';
      gates.dispatchEvent(new Event('input', { bubbles: true }));
      gates.dispatchEvent(new Event('change', { bubbles: true }));
      const warn = document.getElementById('fq-gate-warn');
      return {
        beforeTicks,
        afterTicks: document.querySelectorAll('.fq-side-gate-here:checked').length,
        notice: warn ? String(warn.textContent || '') : '',
        noticeHidden: !!(warn && warn.classList.contains('is-hidden')),
      };
    });
  }

  const report = { pages: [], save: null, invoicePreview: null, behaviors: null, labourRateKeypress: null, pavingRateMask: null, fenceGateNotice: null };
  try {
    await login();
    report.invoicePreview = await invoiceDiscountPreview();
    report.behaviors = await behaviorChecks();
    report.labourRateKeypress = await labourRateKeypressSave();
    report.pavingRateMask = await pavingRateMaskChecks();
    report.fenceGateNotice = await fenceGateLowerNotice();
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
    const preview = report.invoicePreview || {};
    if (preview.field !== '130.00' || preview.net !== 'CA$870.00' || preview.hst !== 'CA$113.10' || preview.grand !== 'CA$983.10') {
      throw new Error('Invoice discount preview mismatch: ' + JSON.stringify(preview));
    }
    const b = report.behaviors || {};
    if (Math.abs((b.untaggedFour || 0) - 4) > 0.0001) {
      throw new Error('JS default reverted (untagged 4 → ' + b.untaggedShown + ')');
    }
    if (Math.abs((b.commaDecimal || 0) - 4.75) > 0.0001) {
      throw new Error('Comma decimal 4,75 became ' + b.commaShown);
    }
    if (!b.ambiguousInvalid || String(b.ambiguousShown) === '1.23') {
      throw new Error('Ambiguous 1.234,56 was not rejected: ' + b.ambiguousShown);
    }
    if (!b.negativeInvalid || Math.abs((b.negativeValue || 0) - 5) < 0.0001) {
      throw new Error('Negative -5 was silently flipped: ' + b.negativeShown);
    }
    if (Math.abs((b.rounded || 0) - 4.76) > 0.0001) {
      throw new Error('4.756 rounded to ' + b.rounded + ' instead of 4.76');
    }
    if (!b.loneDotBlocked) {
      throw new Error('Lone "." was submittable');
    }
    const labour = report.labourRateKeypress || {};
    if ((labour.failed || 0) > 0) {
      throw new Error('Paving labour rate rewritten while typing: ' + JSON.stringify(labour.fields));
    }
    const rates = report.pavingRateMask || {};
    if (Math.abs(parseMoney((rates.comma && rates.comma.value) || '') - 4.75) > 0.0001 || (rates.comma && rates.comma.invalid)) {
      throw new Error('Paving 4,75 mask failed: ' + JSON.stringify(rates.comma));
    }
    if (!rates.ambiguous || !rates.ambiguous.invalid || String(rates.ambiguous.value) === '1.23') {
      throw new Error('Paving 1.234,56 was not rejected: ' + JSON.stringify(rates.ambiguous));
    }
    const fenceNotice = report.fenceGateNotice || {};
    if ((fenceNotice.afterTicks || 0) !== 1 || fenceNotice.noticeHidden || !String(fenceNotice.notice || '').toLowerCase().includes('cleared')) {
      throw new Error('Fence gate-lower notice failed: ' + JSON.stringify(fenceNotice));
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
