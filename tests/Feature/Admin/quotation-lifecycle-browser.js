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
    protocolTimeout: 180000,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
  };
  if (process.env.PUPPETEER_EXECUTABLE_PATH) {
    opts.executablePath = process.env.PUPPETEER_EXECUTABLE_PATH;
  }
  return opts;
}

function viewportFor(width) {
  return Number(width) === 390
    ? { width: 390, height: 844, deviceScaleFactor: 1 }
    : { width: 1280, height: 900, deviceScaleFactor: 1 };
}

async function shotWithRetry(page, dest, opts) {
  let lastErr = null;
  for (let attempt = 0; attempt < 4; attempt++) {
    try {
      await page.screenshot(Object.assign({ path: dest }, opts || {}));
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

function controlState(el) {
  if (!el) {
    return { exists: false };
  }
  const style = window.getComputedStyle(el);
  const rect = el.getBoundingClientRect();
  const ariaHidden = el.closest('[hidden], [aria-hidden="true"]');
  const hidden = !!(
    el.hidden
    || style.display === 'none'
    || style.visibility === 'hidden'
    || (ariaHidden && ariaHidden !== document.body)
  );
  return {
    exists: true,
    tag: el.tagName,
    type: el.type || '',
    disabled: !!el.disabled,
    readOnly: !!el.readOnly,
    hidden,
    pointerEvents: style.pointerEvents,
    width: rect.width,
    height: rect.height,
  };
}

async function clickPoint(page, selector, label) {
  await page.waitForSelector(selector, { timeout: 20000, visible: true });
  const handle = await page.$(selector);
  if (!handle) {
    throw new Error((label || selector) + ' is missing');
  }
  const state = await handle.evaluate(controlState);
  if (state.disabled || state.hidden || state.readOnly || state.width < 2 || state.height < 2 || state.pointerEvents === 'none') {
    throw new Error((label || selector) + ' is not an accessible control: ' + JSON.stringify(state));
  }
  let lastHit = null;
  for (let attempt = 0; attempt < 8; attempt++) {
    await handle.evaluate((el) => el.scrollIntoView({ block: 'center', inline: 'nearest' }));
    const loc = await handle.evaluate((el) => {
      const r = el.getBoundingClientRect();
      const x = r.left + Math.max(8, r.width / 2);
      const y = r.top + Math.max(6, r.height / 2);
      const footer = document.querySelector('[data-qh-footer]');
      const footerTop = footer ? footer.getBoundingClientRect().top : window.innerHeight;
      return { x, y, footerTop, vh: window.innerHeight, top: r.top, bottom: r.bottom };
    });
    if (loc.bottom > loc.footerTop - 8) {
      await page.evaluate((delta) => window.scrollBy(0, delta), loc.bottom - loc.footerTop + 24);
    } else if (loc.top < 64) {
      await page.evaluate((delta) => window.scrollBy(0, delta), loc.top - 80);
    }
    const hit = await page.evaluate((sel, x, y) => {
      const el = document.querySelector(sel);
      const top = document.elementFromPoint(x, y);
      const ok = !!(top && el && (top === el || el.contains(top) || (el.labels && [].some.call(el.labels, (l) => l.contains(top)))));
      return {
        ok,
        x,
        y,
        hit: top ? ((top.id || '') + ' ' + top.tagName + ' ' + String(top.className || '').slice(0, 60)) : 'none',
      };
    }, selector, loc.x, loc.y);
    lastHit = hit;
    if (hit.ok) {
      await page.mouse.click(hit.x, hit.y);
      return handle;
    }
    await page.evaluate((dir) => window.scrollBy(0, dir), attempt % 2 === 0 ? -140 : 140);
  }
  throw new Error((label || selector) + ' is covered at the click point: ' + JSON.stringify(lastHit));
}

async function clickAccessible(page, selector, label) {
  await clickPoint(page, selector, label);
}

async function typeAccessible(page, selector, value, label) {
  const handle = await clickPoint(page, selector, label || selector);
  const tag = await handle.evaluate((el) => el.tagName);
  if (tag === 'SELECT') {
    await page.select(selector, String(value));
    return;
  }
  const focused = await page.evaluate((sel) => {
    const el = document.querySelector(sel);
    return !!(el && (document.activeElement === el || (el.labels && [].some.call(el.labels, (l) => l.contains(document.activeElement)))));
  }, selector);
  if (!focused) {
    throw new Error((label || selector) + ' click did not focus the control (covered or not editable)');
  }
  const current = await page.$eval(selector, (el) => String(el.value || ''));
  for (let i = 0; i < current.length + 4; i++) {
    await page.keyboard.press('Backspace');
    await page.keyboard.press('Delete');
  }
  await page.keyboard.type(String(value), { delay: 25 });
  await page.keyboard.press('Tab');
  const got = await page.$eval(selector, (el) => String(el.value || ''));
  const wanted = String(value);
  if (got !== wanted && parseFloat(got) !== parseFloat(wanted)) {
    throw new Error((label || selector) + ' typed "' + wanted + '" but the control value is "' + got + '"');
  }
}

async function revealThenType(page, spec, field) {
  const clicks = Array.isArray(spec.revealClicks) ? spec.revealClicks : [];
  for (const sel of clicks) {
    await clickAccessible(page, sel, spec.type + ' reveal ' + sel);
    await new Promise((r) => setTimeout(r, 250));
  }
  if (spec.openDetails) {
    const isOpen = await page.$eval(spec.openDetails, (el) => !!el.open).catch(() => true);
    if (!isOpen) {
      await clickAccessible(page, spec.openDetails + ' summary', spec.type + ' open ' + spec.openDetails);
      await new Promise((r) => setTimeout(r, 200));
    }
  }
  await typeAccessible(page, field.selector, field.value, spec.type + ' ' + field.selector);
}

async function readFooterSnap(page) {
  return page.evaluate(() => {
    const a = document.querySelector('[data-qh-footer-amount]');
    const root = document.getElementById('pq-live-root');
    const text = a ? String(a.textContent || '').trim() : '';
    const t = text.replace(/\s+/g, '');
    const pending = !!(a && (
      a.getAttribute('data-qh-footer-pending') === '1'
      || a.getAttribute('data-qh-footer-unavailable') === '1'
      || a.getAttribute('aria-busy') === 'true'
    ));
    const money = /CA\$[\d,]+\.\d{2}/.test(t);
    const st = root ? String(root.getAttribute('data-pq-preview-state') || '') : '';
    const previewOk = !root || st === 'ready';
    const livePending = !!window.__quoteGrandTotalPending || !!window.__quoteGrandTotalUnavailable;
    return {
      ok: !!(a && !pending && money && previewOk && !livePending),
      text,
      pending: a ? a.getAttribute('data-qh-footer-pending') : null,
      preview: root ? st : null,
    };
  });
}

async function waitOneRenderCycle(page) {
  await page.evaluate(() => new Promise((resolve) => {
    requestAnimationFrame(() => requestAnimationFrame(resolve));
  }));
}

async function waitFooterSettled(page) {
  const deadline = Date.now() + 25000;
  while (Date.now() < deadline) {
    try {
      await page.waitForFunction(() => {
        const a = document.querySelector('[data-qh-footer-amount]');
        if (!a) return false;
        const root = document.getElementById('pq-live-root');
        if (root) {
          const st = root.getAttribute('data-pq-preview-state') || '';
          if (st === 'idle' || st === 'loading') return false;
          if (st !== 'ready') return false;
        }
        if (a.getAttribute('data-qh-footer-pending') === '1') return false;
        if (a.getAttribute('data-qh-footer-unavailable') === '1') return false;
        if (a.getAttribute('aria-busy') === 'true') return false;
        if (window.__quoteGrandTotalPending) return false;
        if (window.__quoteGrandTotalUnavailable) return false;
        const t = (a.textContent || '').replace(/\s+/g, '');
        return /CA\$[\d,]+\.\d{2}/.test(t);
      }, { timeout: Math.max(250, deadline - Date.now()) });
    } catch (err) {
      break;
    }
    await waitOneRenderCycle(page);
    const snap = await readFooterSnap(page);
    if (snap.ok) return;
  }
  throw new Error('latest preview did not settle: ' + JSON.stringify(await readFooterSnap(page)));
}

async function gotoEdit(page, href) {
  await page.goto(base + href, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('[data-qh-header]');
  await page.waitForSelector('[data-qh-footer-amount]');
  await waitFooterSettled(page);
}

async function clickAndWait(page, selector, label) {
  const nav = page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }).catch(() => null);
  await clickPoint(page, selector, label || selector);
  await nav;
  await page.waitForSelector('[data-qh-header]', { timeout: 30000 });
}

async function shotPair(page, spec, kind) {
  const prefix = path.join(shotsDir, spec.type + '-' + spec.viewport + '-' + kind);
  return {
    viewportShot: await shotWithRetry(page, prefix + '-viewport.png', { fullPage: false }),
    fullShot: await shotWithRetry(page, prefix + '-full.png', { fullPage: true }),
  };
}

(async () => {
  fs.mkdirSync(shotsDir, { recursive: true });
  fs.writeFileSync(path.join(shotsDir, 'PROVENANCE.json'), JSON.stringify({
    sha,
    capturedAt: new Date().toISOString(),
    note: 'Viewport shots are the workflow evidence (controls + sticky total). Full-page shots are supplementary. Each type ran draft/publish/edit/reopen at both 1280 and 390. No force-unlock of hidden/disabled/readonly fields.',
  }, null, 2));

  const browser = await puppeteer.launch(launchOpts());
  const page = await browser.newPage();
  page.setDefaultNavigationTimeout(45000);
  page.setDefaultTimeout(30000);
  await login(page);

  const report = { sha, types: {} };

  for (const spec of cases) {
    const width = String(spec.viewport || 1280);
    const fields = Array.isArray(spec.fields) ? spec.fields : [];
    const primary = fields[0] ? fields[0].selector : null;
    const key = spec.type + '@' + width;
    const row = {
      type: spec.type,
      viewport: width,
      seed: spec.seed || '',
      widths: {},
    };
    await page.setViewport(viewportFor(width));
    await gotoEdit(page, spec.editHref);

    const before = await page.evaluate(inspectSaveControls);
    row.draftOrPublished = spec.expectDraft ? 'draft' : 'published';
    row.controls = before;
    const beforeShots = await shotPair(page, spec, 'before');
    row.widths[width] = {
      viewportShot: beforeShots.viewportShot,
      fullShot: beforeShots.fullShot,
      footerAmount: before.footerAmount,
      headerPublish: before.headerPublish,
      headerDraft: before.headerDraft,
    };

    if (spec.expectDraft) {
      if (!before.draftHotkey || before.publishHotkey) {
        throw new Error(spec.type + ' @' + width + ' draft Ctrl+S must target Save as draft');
      }
      await clickAndWait(page, '[data-qh-header] [data-qh-save-intent="publish"]', spec.type + ' header Publish');
      row.publishedFromDraft = true;
      await gotoEdit(page, spec.editHref);
    }

    for (const field of fields) {
      await revealThenType(page, spec, field);
    }
    if (fields.length) {
      row.typedValue = await page.$eval(fields[0].selector, (el) => String(el.value || ''));
      await clickAndWait(page, '[data-qh-footer-save]', spec.type + ' footer Publish');
      row.savedViaFooter = true;
    }

    await gotoEdit(page, spec.editHref);
    if (primary && Array.isArray(spec.revealClicks)) {
      for (const sel of spec.revealClicks) {
        const handle = await page.$(sel);
        if (handle) {
          await handle.evaluate((el) => el.scrollIntoView({ block: 'center', inline: 'nearest' }));
          await handle.click().catch(() => null);
          await new Promise((r) => setTimeout(r, 150));
        }
      }
    }
    if (primary) {
      const fieldHandle = await page.$(primary);
      if (fieldHandle) {
        await fieldHandle.evaluate((el) => el.scrollIntoView({ block: 'center', inline: 'nearest' }));
      }
    }
    await waitFooterSettled(page);
    const after = await page.evaluate(inspectSaveControls);
    if (primary) {
      after.fieldValue = await page.$eval(primary, (el) => String(el.value || ''));
    }
    row.reopen = after;
    const reopenShots = await shotPair(page, spec, 'reopen');
    row.widths[width].reopenViewportShot = reopenShots.viewportShot;
    row.widths[width].reopenFullShot = reopenShots.fullShot;
    row.widths[width].reopenFooterAmount = after.footerAmount;

    if (after.pdfHref) {
      const pdfUrl = /^https?:\/\//i.test(after.pdfHref) ? after.pdfHref : (base + after.pdfHref);
      row.pdfUrl = pdfUrl;
      const pdfPage = await browser.newPage();
      const pdfResp = await pdfPage.goto(pdfUrl, { waitUntil: 'networkidle0' });
      row.pdfStatus = pdfResp ? pdfResp.status() : 0;
      row.pdfBytes = pdfResp ? (await pdfResp.buffer()).length : 0;
      await pdfPage.close();
    }

    report.types[key] = row;
  }

  await browser.close();
  fs.writeFileSync(outPath, JSON.stringify(report, null, 2));
})().catch((err) => {
  fs.writeFileSync(outPath, JSON.stringify({ sha, error: String(err && err.stack || err) }, null, 2));
  console.error(err);
  process.exit(1);
});
