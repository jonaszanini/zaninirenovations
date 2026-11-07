'use strict';

const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

const email = process.env.QH_CEILING_EMAIL;
const password = process.env.QH_CEILING_PASSWORD;
const base = process.env.QH_CEILING_BASE;
const outPath = process.env.QH_CEILING_OUT;
const shotsDir = process.env.QH_CEILING_SHOTS || path.join(process.cwd(), 'storage/app/qa-screenshots');
const cases = JSON.parse(process.env.QH_CEILING_CASES || '[]');

function launchOpts() {
  const opts = {
    headless: true,
    protocolTimeout: 45000,
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

async function waitSettled(page, expected) {
  const want = String(expected || '').replace(/\s+/g, '');
  await page.waitForFunction((exp) => {
    const el = document.querySelector('[data-qh-footer-amount]');
    if (!el) return false;
    return String(el.textContent || '').replace(/\s+/g, '') === exp;
  }, { timeout: 25000 }, want);
  let last = '';
  for (let i = 0; i < 8; i++) {
    await new Promise((r) => setTimeout(r, 350));
    const cur = await page.$eval('[data-qh-footer-amount]', (el) => String(el.textContent || '').replace(/\s+/g, ''));
    if (cur === want && cur === last) {
      return cur;
    }
    last = cur;
  }
  const got = await page.$eval('[data-qh-footer-amount]', (el) => String(el.textContent || '').replace(/\s+/g, ''));
  if (got !== want) {
    throw new Error('footer did not settle at ' + want + ', got ' + got);
  }
  return got;
}

async function shot(page, name) {
  const dest = path.join(shotsDir, name);
  let ok = false;
  for (let attempt = 0; attempt < 4 && !ok; attempt++) {
    try {
      await page.screenshot({ path: dest, fullPage: false });
      ok = true;
    } catch (err) {
      if (attempt === 3) throw err;
      await new Promise((r) => setTimeout(r, 150 * (attempt + 1)));
    }
  }
  return dest;
}

async function readEditor(page) {
  return page.evaluate(() => {
    const footer = document.querySelector('[data-qh-footer-amount]');
    const hang = document.querySelector('[data-br-labor-key="dw_hang"]');
    const paint = document.querySelector('[data-br-labor-key="paint_ceiling"]');
    const flag = document.getElementById('br-ceiling-hang-unified');
    const notice = document.querySelector('[data-br-legacy-ceiling-notice]');
    const overflow = document.documentElement.scrollWidth > window.innerWidth + 2;
    return {
      footer: footer ? String(footer.textContent || '').trim() : '',
      hang: hang ? String(hang.textContent || '').replace(/\s+/g, ' ').trim() : '',
      paint: paint ? String(paint.textContent || '').replace(/\s+/g, ' ').trim() : '',
      flag: flag ? String(flag.value || '') : '',
      hasNotice: !!(notice && !notice.hidden),
      overflow,
      split: document.getElementById('br-main-form')
        ? document.getElementById('br-main-form').getAttribute('data-br-split-ceiling')
        : null,
    };
  });
}

async function saveQuote(page) {
  const invalid = await page.evaluate(() => {
    const form = document.getElementById('br-main-form');
    if (!form) return ['missing form'];
    return Array.from(form.querySelectorAll(':invalid')).map((el) => {
      return (el.name || el.id || el.tagName) + '=' + JSON.stringify(el.value) + ' ' + (el.validationMessage || '');
    });
  });
  if (invalid.length) {
    throw new Error('form invalid: ' + invalid.join('; '));
  }
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 25000 }),
    page.evaluate(() => {
      const pub = document.getElementById('br-btn-publish');
      if (pub) {
        pub.click();
        return;
      }
      const form = document.getElementById('br-main-form');
      if (form && typeof HTMLFormElement.prototype.submit === 'function') {
        HTMLFormElement.prototype.submit.call(form);
      }
    }),
  ]);
}

(async () => {
  fs.mkdirSync(shotsDir, { recursive: true });
  const browser = await puppeteer.launch(launchOpts());
  const page = await browser.newPage();
  page.setDefaultNavigationTimeout(30000);
  page.setDefaultTimeout(30000);
  await login(page);

  const report = { cases: {} };
  for (const spec of cases) {
    const row = { widths: {} };
    await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
    await page.goto(base + spec.editPath, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('[data-qh-footer-amount]');
    const before = await waitSettled(page, spec.expectedMoney);
    const editorBefore = await readEditor(page);
    row.before = Object.assign({ settled: before }, editorBefore);
    row.widths['1280'] = {
      shot: await shot(page, spec.key + '-1280.png'),
      overflow: editorBefore.overflow,
      footer: editorBefore.footer,
    };

    await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 1 });
    await new Promise((r) => setTimeout(r, 200));
    const mobile = await waitSettled(page, spec.expectedMoney);
    const editorMobile = await readEditor(page);
    row.widths['390'] = {
      shot: await shot(page, spec.key + '-390.png'),
      overflow: editorMobile.overflow,
      footer: editorMobile.footer,
      settled: mobile,
    };

    await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
    await saveQuote(page);
    await page.waitForSelector('[data-qh-footer-amount]');
    const after = await waitSettled(page, spec.expectedMoney);
    row.after = Object.assign({ settled: after }, await readEditor(page));

    if (spec.pdfPath) {
      row.pdf = await page.evaluate(async (url) => {
        const r = await fetch(url, { credentials: 'same-origin' });
        const buf = new Uint8Array(await r.arrayBuffer());
        const magic = String.fromCharCode(buf[0] || 0, buf[1] || 0, buf[2] || 0, buf[3] || 0);
        return {
          status: r.status,
          contentType: r.headers.get('content-type') || '',
          pdfMagic: magic === '%PDF',
          bytes: buf.length,
        };
      }, base + spec.pdfPath);
    }
    report.cases[spec.key] = row;
  }

  fs.mkdirSync(path.dirname(outPath), { recursive: true });
  fs.writeFileSync(outPath, JSON.stringify(report, null, 2));
  await browser.close();
  process.stdout.write(JSON.stringify(report));
})().catch((err) => {
  console.error(err);
  process.exit(2);
});
