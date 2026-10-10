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
const negStandardUrl = process.env.MONEY_NEG_STANDARD_URL || '';
const negHourlyUrl = process.env.MONEY_NEG_HOURLY_URL || '';
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
        input.setAttribute('data-cp-money-places', String(opts.places || 2));
        if (opts.direct) {
          input.setAttribute('data-cp-money-entry', 'direct');
        }
        if (opts.allowNegative) {
          input.setAttribute('data-cp-money-allow-negative', '');
        }
        if (opts.stored) {
          input.value = raw;
        }
        form.appendChild(input);
        document.body.appendChild(form);
        window.cpMoneyMask.wire(input);
        if (!opts.stored) {
          input.focus();
          input.value = raw;
          input.dispatchEvent(new Event('input', { bubbles: true }));
          if (!opts.skipBlur) {
            input.blur();
            input.dispatchEvent(new FocusEvent('blur', { bubbles: true }));
          }
        }
        const submitted = form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        const result = {
          value: input.value,
          num: parseFloat(String(input.value || '').replace(/,/g, '')),
          invalid: input.classList.contains('is-invalid') || input.validity.customError,
          message: String(input.validationMessage || ''),
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
      const halfUp = probe('1.005');
      const loneDot = probe('.', { skipBlur: true });
      const gasComma = probe('1,459', { places: 3 });
      const storedNeg = probe('-5.00', { stored: true });
      const allowStored = probe('-200.00', { stored: true, allowNegative: true });
      const allowTyped = probe('-200', { allowNegative: true });
      const oneDashTwo = probe('1-2', { allowNegative: true });
      const negHalf = window.cpMoneyMask.round(-30.485, 2);

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
        halfUp: halfUp.num,
        halfUpShown: halfUp.value,
        loneDotBlocked: loneDot.invalid && !loneDot.submitAllowed,
        gasCommaInvalid: gasComma.invalid,
        gasCommaShown: gasComma.value,
        gasCommaNum: isFinite(gasComma.num) ? gasComma.num : 0,
        storedNegativeBlank: storedNeg.value === '',
        storedNegativeShown: storedNeg.value,
        storedNegativeSubmitAllowed: storedNeg.submitAllowed,
        storedNegativeInvalid: storedNeg.invalid,
        allowNegativeShown: allowStored.value,
        allowNegativeNum: isFinite(allowStored.num) ? allowStored.num : 0,
        allowNegativeSubmitAllowed: allowStored.submitAllowed,
        allowNegativeInvalid: allowStored.invalid,
        allowNegativeTypedShown: allowTyped.value,
        allowNegativeTypedNum: isFinite(allowTyped.num) ? allowTyped.num : 0,
        allowNegativeTypedInvalid: allowTyped.invalid,
        oneDashTwoInvalid: oneDashTwo.invalid,
        oneDashTwoMessage: oneDashTwo.message,
        oneDashTwoShown: oneDashTwo.value,
        negHalfRound: negHalf,
      };
    });
  }

  async function mountKeypressField(id, places, allowNegative) {
    await page.evaluate((fieldId, fieldPlaces, allowNeg) => {
      const existing = document.getElementById(fieldId);
      if (existing && existing.closest('form')) {
        existing.closest('form').remove();
      }
      const form = document.createElement('form');
      const input = document.createElement('input');
      input.id = fieldId;
      input.className = 'cp-money-input admin-input';
      input.setAttribute('data-cp-money-places', String(fieldPlaces));
      input.setAttribute('data-cp-money-entry', 'direct');
      if (allowNeg) {
        input.setAttribute('data-cp-money-allow-negative', '');
      }
      input.style.width = '220px';
      input.style.height = '44px';
      form.appendChild(input);
      document.body.appendChild(form);
      window.cpMoneyMask.wire(input);
    }, id, places, !!allowNegative);
  }

  async function readKeypressField(id) {
    return page.evaluate((fieldId) => {
      const el = document.getElementById(fieldId);
      return {
        value: el ? el.value : '',
        invalid: !!(el && (el.classList.contains('is-invalid') || el.validity.customError)),
        message: el ? String(el.validationMessage || '') : '',
      };
    }, id);
  }

  async function keypressChecks() {
    await mountKeypressField('cp-kp-minus', 2);
    await page.click('#cp-kp-minus');
    await page.keyboard.type('-5', { delay: 20 });
    const typedMinus = await readKeypressField('cp-kp-minus');

    await mountKeypressField('cp-kp-gas', 3);
    await page.click('#cp-kp-gas');
    await page.keyboard.type('1,459', { delay: 20 });
    await page.keyboard.press('Tab');
    const gasComma = await readKeypressField('cp-kp-gas');

    await mountKeypressField('cp-kp-round', 2);
    await page.click('#cp-kp-round');
    await page.keyboard.type('1.005', { delay: 20 });
    await page.keyboard.press('Tab');
    const halfUp = await readKeypressField('cp-kp-round');

    await mountKeypressField('cp-kp-allow-neg', 2, true);
    await page.click('#cp-kp-allow-neg');
    await page.keyboard.type('-200', { delay: 20 });
    await page.keyboard.press('Tab');
    const allowMinus = await readKeypressField('cp-kp-allow-neg');

    await mountKeypressField('cp-kp-mixed', 2);
    await page.click('#cp-kp-mixed');
    await page.keyboard.type('1.234,56', { delay: 20 });
    const mixedTyped = await readKeypressField('cp-kp-mixed');
    await page.keyboard.press('Tab');
    const mixedBlur = await readKeypressField('cp-kp-mixed');
    const mixedSubmit = await page.evaluate(() => {
      const el = document.getElementById('cp-kp-mixed');
      return !!(el && el.form && el.form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
    });

    await mountKeypressField('cp-kp-us', 2);
    await page.click('#cp-kp-us');
    await page.keyboard.type('1,234.56', { delay: 20 });
    await page.keyboard.press('Tab');
    const usThousands = await readKeypressField('cp-kp-us');

    await mountKeypressField('cp-kp-us-long', 2);
    await page.click('#cp-kp-us-long');
    await page.keyboard.type('1,234,567.89', { delay: 20 });
    await page.keyboard.press('Tab');
    const usMillions = await readKeypressField('cp-kp-us-long');

    await mountKeypressField('cp-kp-us-int', 2);
    await page.click('#cp-kp-us-int');
    await page.keyboard.type('12,345,678', { delay: 20 });
    await page.keyboard.press('Tab');
    const usInteger = await readKeypressField('cp-kp-us-int');

    await mountKeypressField('cp-kp-ambig-commas', 2);
    await page.click('#cp-kp-ambig-commas');
    await page.keyboard.type('1,2,3', { delay: 20 });
    const ambigCommasTyped = await readKeypressField('cp-kp-ambig-commas');
    await page.keyboard.press('Tab');
    const ambigCommasBlur = await readKeypressField('cp-kp-ambig-commas');
    const ambigCommasSubmit = await page.evaluate(() => {
      const el = document.getElementById('cp-kp-ambig-commas');
      return !!(el && el.form && el.form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
    });

    return {
      typedMinus, gasComma, halfUp, allowMinus, mixedTyped, mixedBlur, mixedSubmit, usThousands,
      usMillions, usInteger, ambigCommasTyped, ambigCommasBlur, ambigCommasSubmit,
    };
  }

  async function resaveInvoiceUntouched(url) {
    if (!url) {
      throw new Error('Negative invoice URL missing');
    }
    await go(base + url);
    await page.waitForSelector('#cp-invoice-form');
    const before = await page.evaluate(() => {
      return Array.from(document.querySelectorAll('#cp-invoice-items .cp-quotation-row')).map((tr) => {
        const el = tr.querySelector('.cp-quotation-row-amount');
        const kind = tr.querySelector('select.cp-invoice-line-kind, input[name*="[line_kind]"]');
        return {
          name: el ? el.name || '' : '',
          value: el ? el.value || '' : '',
          kind: kind ? kind.value || '' : '',
          allowNegative: !!(el && el.hasAttribute('data-cp-money-allow-negative')),
          invalid: !!(el && (el.classList.contains('is-invalid') || el.validity.customError)),
        };
      });
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
        page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
        page.click('[data-cp-invoice-intent="draft"]'),
      ]);
    } finally {
      page.off('request', onRequest);
    }
    return { before, afterUrl: page.url(), posted };
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
        negative: probe('-5'),
        ambiguous: probe('1.234,56'),
        halfUp: probe('1.005'),
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
    const afterLower = await page.evaluate(() => {
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
    const gatesHandle = await page.$('#fq-gates');
    await gatesHandle.click({ clickCount: 3 });
    await page.keyboard.press('Backspace');
    await page.keyboard.type('3');
    await page.keyboard.press('Tab');
    const afterRetype = await page.evaluate(() => {
      const warn = document.getElementById('fq-gate-warn');
      return {
        gates: document.getElementById('fq-gates').value,
        ticks: document.querySelectorAll('.fq-side-gate-here:checked').length,
        notice: warn ? String(warn.textContent || '') : '',
        noticeHidden: !!(warn && warn.classList.contains('is-hidden')),
      };
    });
    return Object.assign({}, afterLower, { afterRetype });
  }

  const report = { pages: [], save: null, invoicePreview: null, behaviors: null, keypress: null, negativeResave: null, labourRateKeypress: null, pavingRateMask: null, fenceGateNotice: null };
  try {
    await login();
    report.invoicePreview = await invoiceDiscountPreview();
    report.behaviors = await behaviorChecks();
    report.keypress = await keypressChecks();
    report.labourRateKeypress = await labourRateKeypressSave();
    report.pavingRateMask = await pavingRateMaskChecks();
    report.fenceGateNotice = await fenceGateLowerNotice();
    report.negativeResave = {
      standard: await resaveInvoiceUntouched(negStandardUrl),
      hourly: await resaveInvoiceUntouched(negHourlyUrl),
    };
    await go(base + negHourlyUrl);
    await page.waitForSelector('#cp-invoice-form');
    await page.click('#cp-invoice-add-row');
    const hourlyMaterialsNegative = await page.evaluate(() => {
      const rows = Array.from(document.querySelectorAll('#cp-invoice-items tr.cp-quotation-row'));
      const tr = rows[rows.length - 1];
      const kind = tr.querySelector('select.cp-invoice-line-kind');
      const amt = tr.querySelector('.cp-quotation-row-amount');
      kind.value = 'materials';
      kind.dispatchEvent(new Event('change', { bubbles: true }));
      amt.focus();
      amt.value = '-50';
      amt.dispatchEvent(new Event('input', { bubbles: true }));
      amt.blur();
      amt.dispatchEvent(new FocusEvent('blur', { bubbles: true }));
      const form = document.getElementById('cp-invoice-form');
      const submitted = form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
      const materials = {
        allowNegative: amt.hasAttribute('data-cp-money-allow-negative'),
        value: amt.value,
        invalid: amt.classList.contains('is-invalid') || amt.validity.customError,
        message: String(amt.validationMessage || ''),
        submitAllowed: submitted,
      };
      kind.value = 'labour';
      kind.dispatchEvent(new Event('change', { bubbles: true }));
      return {
        ...materials,
        afterKindChange: {
          allowNegative: amt.hasAttribute('data-cp-money-allow-negative'),
          value: amt.value,
          invalid: amt.classList.contains('is-invalid') || amt.validity.customError,
          message: String(amt.validationMessage || ''),
        },
      };
    });
    report.hourlyMaterialsNegative = hourlyMaterialsNegative;
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
    if (Math.abs((b.halfUp || 0) - 1.01) > 0.0001) {
      throw new Error('1.005 rounded to ' + b.halfUpShown + ' instead of 1.01');
    }
    if (!b.loneDotBlocked) {
      throw new Error('Lone "." was submittable');
    }
    if (!b.gasCommaInvalid || String(b.gasCommaShown) === '1,459.000' || String(b.gasCommaShown) === '1459.000') {
      throw new Error('1,459 on a 3-place field was read as ' + b.gasCommaShown);
    }
    if (b.storedNegativeBlank || String(b.storedNegativeShown) === '') {
      throw new Error('Stored negative was blanked: ' + b.storedNegativeShown);
    }
    if (!b.storedNegativeInvalid || b.storedNegativeSubmitAllowed) {
      throw new Error('Stored negative on a default field must stay visible and block submit: ' + JSON.stringify({
        shown: b.storedNegativeShown,
        submit: b.storedNegativeSubmitAllowed,
        invalid: b.storedNegativeInvalid,
      }));
    }
    if (Math.abs((b.allowNegativeNum || 0) + 200) > 0.0001 || b.allowNegativeInvalid || !b.allowNegativeSubmitAllowed) {
      throw new Error('Allow-negative stored -200.00 failed: ' + JSON.stringify({
        shown: b.allowNegativeShown,
        num: b.allowNegativeNum,
        submit: b.allowNegativeSubmitAllowed,
        invalid: b.allowNegativeInvalid,
      }));
    }
    if (Math.abs((b.allowNegativeTypedNum || 0) + 200) > 0.0001 || b.allowNegativeTypedInvalid) {
      throw new Error('Allow-negative typed -200 failed: ' + JSON.stringify({
        shown: b.allowNegativeTypedShown,
        num: b.allowNegativeTypedNum,
        invalid: b.allowNegativeTypedInvalid,
      }));
    }
    if (!b.oneDashTwoInvalid || String(b.oneDashTwoMessage || '').toLowerCase().indexOf('valid amount') === -1) {
      throw new Error('1-2 on an allowed field should say Enter a valid amount: ' + JSON.stringify({
        shown: b.oneDashTwoShown,
        message: b.oneDashTwoMessage,
        invalid: b.oneDashTwoInvalid,
      }));
    }
    if (Math.abs((b.negHalfRound || 0) + 30.49) > 0.0001) {
      throw new Error('Negative half rounding was ' + b.negHalfRound + ' instead of -30.49');
    }
    const kp = report.keypress || {};
    if (!kp.typedMinus || !kp.typedMinus.invalid || String(kp.typedMinus.value).indexOf('-') === -1) {
      throw new Error('Typed -5 did not show the inline negative error: ' + JSON.stringify(kp.typedMinus));
    }
    if (!kp.allowMinus || kp.allowMinus.invalid || Math.abs(parseFloat(String(kp.allowMinus.value || '').replace(/,/g, '')) + 200) > 0.0001) {
      throw new Error('Allow-negative keypress -200 failed: ' + JSON.stringify(kp.allowMinus));
    }
    if (!kp.gasComma || !kp.gasComma.invalid || String(kp.gasComma.value) === '1,459.000' || String(kp.gasComma.value) === '1459.000') {
      throw new Error('Keypress 1,459 on 3-place was not flagged: ' + JSON.stringify(kp.gasComma));
    }
    if (!kp.halfUp || Math.abs(parseFloat(String(kp.halfUp.value || '').replace(/,/g, '')) - 1.01) > 0.0001) {
      throw new Error('Keypress 1.005 did not round to 1.01: ' + JSON.stringify(kp.halfUp));
    }
    if (!kp.mixedTyped || String(kp.mixedTyped.value) !== '1.234,56') {
      throw new Error('Keypress 1.234,56 dropped the comma: ' + JSON.stringify(kp.mixedTyped));
    }
    if (!kp.mixedBlur || !kp.mixedBlur.invalid || String(kp.mixedBlur.value) !== '1.234,56' || String(kp.mixedBlur.value) === '1.23') {
      throw new Error('Typed 1.234,56 was rewritten on blur: ' + JSON.stringify(kp.mixedBlur));
    }
    if (kp.mixedSubmit) {
      throw new Error('Typed 1.234,56 was submittable');
    }
    if (!kp.usThousands || kp.usThousands.invalid || Math.abs(parseFloat(String(kp.usThousands.value || '').replace(/,/g, '')) - 1234.56) > 0.0001) {
      throw new Error('Keypress 1,234.56 failed: ' + JSON.stringify(kp.usThousands));
    }
    if (!kp.usMillions || kp.usMillions.invalid || Math.abs(parseFloat(String(kp.usMillions.value || '').replace(/,/g, '')) - 1234567.89) > 0.0001) {
      throw new Error('Keypress 1,234,567.89 failed: ' + JSON.stringify(kp.usMillions));
    }
    if (!kp.usInteger || kp.usInteger.invalid || Math.abs(parseFloat(String(kp.usInteger.value || '').replace(/,/g, '')) - 12345678) > 0.0001) {
      throw new Error('Keypress 12,345,678 failed: ' + JSON.stringify(kp.usInteger));
    }
    if (!kp.ambigCommasTyped || String(kp.ambigCommasTyped.value) !== '1,2,3') {
      throw new Error('Keypress 1,2,3 dropped a comma: ' + JSON.stringify(kp.ambigCommasTyped));
    }
    if (!kp.ambigCommasBlur || !kp.ambigCommasBlur.invalid || String(kp.ambigCommasBlur.value) !== '1,2,3' || String(kp.ambigCommasBlur.value) === '1.23') {
      throw new Error('Typed 1,2,3 was rewritten or silently saved: ' + JSON.stringify(kp.ambigCommasBlur));
    }
    if (kp.ambigCommasSubmit) {
      throw new Error('Typed 1,2,3 was submittable');
    }
    const labour = report.labourRateKeypress || {};
    if ((labour.failed || 0) > 0) {
      throw new Error('Paving labour rate rewritten while typing: ' + JSON.stringify(labour.fields));
    }
    const rates = report.pavingRateMask || {};
    if (Math.abs(parseMoney((rates.comma && rates.comma.value) || '') - 4.75) > 0.0001 || (rates.comma && rates.comma.invalid)) {
      throw new Error('Paving 4,75 mask failed: ' + JSON.stringify(rates.comma));
    }
    if (!rates.negative || !rates.negative.invalid || String(rates.negative.value || '').indexOf('-') === -1) {
      throw new Error('Paving -5 mask failed: ' + JSON.stringify(rates.negative));
    }
    if (!rates.ambiguous || !rates.ambiguous.invalid || String(rates.ambiguous.value) === '1.23') {
      throw new Error('Paving 1.234,56 was not rejected: ' + JSON.stringify(rates.ambiguous));
    }
    if (!rates.halfUp || Math.abs(parseMoney((rates.halfUp && rates.halfUp.value) || '') - 1.01) > 0.0001) {
      throw new Error('Paving 1.005 did not round to 1.01: ' + JSON.stringify(rates.halfUp));
    }
    const fenceNotice = report.fenceGateNotice || {};
    if ((fenceNotice.afterTicks || 0) !== 1 || fenceNotice.noticeHidden || !String(fenceNotice.notice || '').toLowerCase().includes('cleared')) {
      throw new Error('Fence gate-lower notice failed: ' + JSON.stringify(fenceNotice));
    }
    const afterRetype = fenceNotice.afterRetype || {};
    if (String(afterRetype.gates || '') !== '3' || (afterRetype.ticks || 0) !== 2 || String(afterRetype.notice || '').toLowerCase().includes('cleared')) {
      throw new Error('Fence Gates Backspace+3 did not restore ticks: ' + JSON.stringify(afterRetype));
    }
    const neg = report.negativeResave || {};
    ['standard', 'hourly'].forEach((kind) => {
      const row = neg[kind] || {};
      const fields = row.before || [];
      const credit = fields.find((f) => String(f.value || '').indexOf('-200') !== -1);
      if (!credit || credit.invalid || !credit.allowNegative) {
        throw new Error('Untouched ' + kind + ' -200 line was not ready to save: ' + JSON.stringify(row));
      }
      if (!String(row.afterUrl || '').includes('/invoices/')) {
        throw new Error('Untouched ' + kind + ' invoice did not re-save: ' + row.afterUrl);
      }
      if (!String(row.posted || '').includes('-200.00') && !String(row.posted || '').includes('%2D200.00')) {
        throw new Error('Page JS did not post -200.00 for ' + kind + ': ' + String(row.posted || '').slice(0, 400));
      }
    });
    const mats = report.hourlyMaterialsNegative || {};
    if (mats.allowNegative || !mats.invalid || mats.submitAllowed || String(mats.value || '').indexOf('-') === -1) {
      throw new Error('Hourly materials -50 must stay visible, error, and block submit: ' + JSON.stringify(mats));
    }
    const afterKind = mats.afterKindChange || {};
    if (!afterKind.allowNegative || afterKind.invalid || String(afterKind.value || '').indexOf('-') === -1) {
      throw new Error('Changing materials to labour must clear the stale minus error: ' + JSON.stringify(afterKind));
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
