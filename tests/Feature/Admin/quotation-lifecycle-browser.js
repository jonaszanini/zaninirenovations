'use strict';

const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

const email = process.env.QH_LC_EMAIL;
const password = process.env.QH_LC_PASSWORD;
const base = process.env.QH_LC_BASE;
const outPath = process.env.QH_LC_OUT;
const shotsDir = process.env.QH_LC_SHOTS || path.join(process.cwd(), 'storage/app/qh-lifecycle-shots');
const sha = process.env.QH_LC_SHA || '';
const cases = JSON.parse(process.env.QH_LC_CASES || '[]');

function launchOpts() {
  const opts = {
    headless: true,
    protocolTimeout: 120000,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
  };
  if (process.env.PUPPETEER_EXECUTABLE_PATH) {
    opts.executablePath = process.env.PUPPETEER_EXECUTABLE_PATH;
  }
  return opts;
}

async function shotWithRetry(page, dest, fullPage) {
  let lastErr = null;
  for (let attempt = 0; attempt < 4; attempt++) {
    try {
      await page.screenshot({ path: dest, fullPage: !!fullPage });
      return dest;
    } catch (err) {
      lastErr = err;
      await new Promise((r) => setTimeout(r, 150 * (attempt + 1)));
    }
  }
  throw lastErr;
}

async function login(page) {
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
}

function inspectSaveControls() {
  const publish = document.querySelector('[data-qh-header] [data-qh-save-intent="publish"]');
  const draft = document.querySelector('[data-qh-header] [data-qh-save-intent="draft"]');
  const footerSave = document.querySelector('[data-qh-footer-save]');
  const amount = document.querySelector('[data-qh-footer-amount]');
  const pdf = document.querySelector('a[data-qh-view-pdf]');
  const pdfBtn = document.querySelector('[data-qh-view-pdf]');
  const visible = (el) => {
    if (!el) return '';
    const label = (el.innerText || '').replace(/\s+/g, ' ').trim();
    return label || (el.getAttribute('aria-label') || '').trim();
  };
  return {
    hasFooter: !!document.querySelector('[data-qh-footer]'),
    footerAmount: amount ? (amount.textContent || '').trim() : '',
    headerPublish: visible(publish),
    headerDraft: visible(draft),
    footerPublish: visible(footerSave),
    publishHotkey: !!(publish && publish.hasAttribute('data-hotkey-save')),
    draftHotkey: !!(draft && draft.hasAttribute('data-hotkey-save')),
    pdfHref: pdf ? pdf.getAttribute('href') : '',
    pdfTag: pdfBtn ? pdfBtn.tagName : '',
    pdfDisabled: !!(pdfBtn && pdfBtn.hasAttribute('disabled')),
    fieldValue: '',
  };
}

function fieldList(spec) {
  const fields = Array.isArray(spec.fields) ? spec.fields.slice() : [];
  if (spec.fieldSelector && spec.newValue != null) {
    fields.unshift({ selector: spec.fieldSelector, value: String(spec.newValue) });
  }
  return fields;
}

async function setField(page, selector, value) {
  await page.waitForSelector(selector, { timeout: 20000 });
  await page.$eval(selector, (el, next) => {
    el.disabled = false;
    el.removeAttribute('readonly');
    el.hidden = false;
    el.focus();
    el.value = next;
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
  }, String(value));
}

async function gotoEdit(page, href, waitSelector) {
  await page.goto(base + href, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('[data-qh-header]');
  await page.waitForSelector('[data-qh-footer-amount]');
  if (waitSelector) {
    await page.waitForSelector(waitSelector, { timeout: 20000 });
  }
}

async function clickAndWait(page, selector) {
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }).catch(() => null),
    page.click(selector),
  ]);
  await page.waitForSelector('[data-qh-header]', { timeout: 30000 });
}

(async () => {
  fs.mkdirSync(shotsDir, { recursive: true });
  fs.writeFileSync(path.join(shotsDir, 'PROVENANCE.json'), JSON.stringify({
    sha,
    capturedAt: new Date().toISOString(),
    note: 'Lifecycle screenshots: draft/publish, qty edit, reopen footer, 1280 and 390, all non-painting types.',
  }, null, 2));

  const browser = await puppeteer.launch(launchOpts());
  const page = await browser.newPage();
  page.setDefaultNavigationTimeout(45000);
  page.setDefaultTimeout(30000);
  await login(page);

  const report = { sha, types: {} };

  for (const spec of cases) {
    const fields = fieldList(spec);
    const primarySelector = fields[0] ? fields[0].selector : spec.fieldSelector;
    const row = { type: spec.type, widths: {} };
    await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
    await gotoEdit(page, spec.editHref, primarySelector);

    const before = await page.evaluate(inspectSaveControls);
    if (primarySelector) {
      before.fieldValue = await page.$eval(primarySelector, (el) => String(el.value || ''));
    }
    row.draftOrPublished = spec.expectDraft ? 'draft' : 'published';
    row.controls1280 = before;
    row.widths['1280'] = {
      shot: await shotWithRetry(page, path.join(shotsDir, spec.type + '-1280-full.png'), true),
      viewportShot: await shotWithRetry(page, path.join(shotsDir, spec.type + '-1280-viewport.png'), false),
      footerAmount: before.footerAmount,
      headerPublish: before.headerPublish,
      headerDraft: before.headerDraft,
    };

    await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 1 });
    await gotoEdit(page, spec.editHref, primarySelector);
    const mobile = await page.evaluate(inspectSaveControls);
    row.widths['390'] = {
      shot: await shotWithRetry(page, path.join(shotsDir, spec.type + '-390-full.png'), true),
      viewportShot: await shotWithRetry(page, path.join(shotsDir, spec.type + '-390-viewport.png'), false),
      footerAmount: mobile.footerAmount,
      headerPublish: mobile.headerPublish,
      headerDraft: mobile.headerDraft,
    };

    await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
    await gotoEdit(page, spec.editHref, primarySelector);

    if (spec.expectDraft) {
      if (!before.draftHotkey || before.publishHotkey) {
        throw new Error(spec.type + ' draft Ctrl+S must target Save as draft');
      }
      await clickAndWait(page, '[data-qh-header] [data-qh-save-intent="publish"]');
      row.publishedFromDraft = true;
      await gotoEdit(page, spec.editHref, primarySelector);
    }

    for (const field of fields) {
      await setField(page, field.selector, field.value);
    }
    if (fields.length) {
      row.typedValue = await page.$eval(fields[0].selector, (el) => String(el.value || ''));
      await clickAndWait(page, '[data-qh-footer-save]');
      row.savedViaFooter = true;
    }

    await gotoEdit(page, spec.editHref, primarySelector);
    const after = await page.evaluate(inspectSaveControls);
    if (primarySelector) {
      after.fieldValue = await page.$eval(primarySelector, (el) => String(el.value || ''));
    }
    row.reopen = after;
    row.widths['1280'].reopenShot = await shotWithRetry(
      page,
      path.join(shotsDir, spec.type + '-1280-reopen.png'),
      true
    );
    await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 1 });
    await gotoEdit(page, spec.editHref, primarySelector);
    row.widths['390'].reopenShot = await shotWithRetry(
      page,
      path.join(shotsDir, spec.type + '-390-reopen.png'),
      true
    );
    await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });

    if (after.pdfHref) {
      const pdfUrl = /^https?:\/\//i.test(after.pdfHref) ? after.pdfHref : (base + after.pdfHref);
      row.pdfUrl = pdfUrl;
      const pdfPage = await browser.newPage();
      const pdfResp = await pdfPage.goto(pdfUrl, { waitUntil: 'networkidle0' });
      row.pdfStatus = pdfResp ? pdfResp.status() : 0;
      row.pdfBytes = pdfResp ? (await pdfResp.buffer()).length : 0;
      await pdfPage.close();
    }

    report.types[spec.type] = row;
  }

  await browser.close();
  fs.writeFileSync(outPath, JSON.stringify(report, null, 2));
})().catch((err) => {
  fs.writeFileSync(outPath, JSON.stringify({ sha, error: String(err && err.stack || err) }, null, 2));
  console.error(err);
  process.exit(1);
});
