'use strict';

const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

const email = process.env.QH_FLASH_EMAIL;
const password = process.env.QH_FLASH_PASSWORD;
const base = process.env.QH_FLASH_BASE;
const outPath = process.env.QH_FLASH_OUT;
const shotsDir = process.env.QH_FLASH_SHOTS || path.join(process.cwd(), 'storage/app/qa-screenshots');
const cases = JSON.parse(process.env.QH_FLASH_CASES || '[]');

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

async function shot(page, name) {
  fs.mkdirSync(shotsDir, { recursive: true });
  const dest = path.join(shotsDir, name);
  await page.screenshot({ path: dest, fullPage: false });
  return dest;
}

async function showFlash(page, message) {
  await page.waitForFunction(() => !!(window.AdminToast && window.AdminQuotationFlash && document.querySelector('[data-admin-toast-host]')));
  await page.evaluate((text) => {
    window.AdminToast.show({ type: 'success', title: text, sticky: false });
    if (window.AdminQuotationFlash && window.AdminQuotationFlash.sync) {
      window.AdminQuotationFlash.sync('success', text);
    }
  }, message);
  await page.waitForSelector('[data-admin-toast-host]:not([hidden]) .admin-toast--success');
  await page.waitForSelector('[data-qh-flash-status]:not([hidden])');
  await page.evaluate(() => new Promise((resolve) => {
    const toast = document.querySelector('[data-qh-editor-toast]') || document.querySelector('[data-admin-toast-host]');
    if (!toast) {
      resolve();
      return;
    }
    const done = function () { resolve(); };
    toast.addEventListener('animationend', done, { once: true });
    setTimeout(done, 700);
  }));
}

async function metrics(page) {
  return page.evaluate(() => {
    const toast = document.querySelector('[data-qh-editor-toast]') || document.querySelector('[data-admin-toast-host]');
    const card = toast && toast.querySelector('.admin-toast__card');
    const closeBtn = toast && toast.querySelector('[data-admin-toast-close]');
    const status = document.querySelector('[data-qh-flash-status]');
    const header = document.querySelector('[data-qh-header]');
    const footer = document.querySelector('.qh-sticky-footer, .br-sticky-total, #br-sticky-total');
    const menu = document.getElementById('admin-menu-toggle');
    const tr = toast ? toast.getBoundingClientRect() : null;
    const fr = footer ? footer.getBoundingClientRect() : null;
    const mr = menu && getComputedStyle(menu).display !== 'none' ? menu.getBoundingClientRect() : null;
    const cr = closeBtn ? closeBtn.getBoundingClientRect() : null;
    const banner = document.querySelector('.admin-flash--success, [data-admin-flash="success"]');
    const styles = card ? getComputedStyle(card) : null;
    const overlap = function (a, b) {
      if (!a || !b || a.width < 1 || b.width < 1) return false;
      return !(a.right < b.left + 1 || a.left > b.right - 1 || a.bottom < b.top + 1 || a.top > b.bottom - 1);
    };
    return {
      toastVisible: !!(toast && !toast.hidden),
      toastText: toast ? String(toast.textContent || '').replace(/\s+/g, ' ').trim() : '',
      statusText: status && !status.hidden ? String(status.textContent || '').replace(/\s+/g, ' ').trim() : '',
      hasHeader: !!header,
      hasBanner: !!banner,
      toastTop: tr ? Math.round(tr.top) : null,
      toastBottom: tr ? Math.round(tr.bottom) : null,
      toastLeft: tr ? Math.round(tr.left) : null,
      toastRight: tr ? Math.round(tr.right) : null,
      toastWidth: tr ? Math.round(tr.width) : null,
      footerTop: fr ? Math.round(fr.top) : null,
      coversFooter: !!(tr && fr && overlap(tr, fr)),
      menuLeft: mr ? Math.round(mr.left) : null,
      menuRight: mr ? Math.round(mr.right) : null,
      menuVisible: !!(mr && mr.width > 1),
      coversMenu: !!(tr && mr && overlap(tr, mr)),
      closeWidth: cr ? Math.round(cr.width) : null,
      closeHeight: cr ? Math.round(cr.height) : null,
      vw: window.innerWidth,
      ariaLive: toast ? toast.getAttribute('aria-live') : null,
      hasClose: !!closeBtn,
      entering: !!(toast && toast.classList && toast.classList.contains('is-entering')),
      bg: styles ? styles.backgroundColor : '',
    };
  });
}

(async function main() {
  const browser = await puppeteer.launch(launchOpts());
  const page = await browser.newPage();
  page.setDefaultNavigationTimeout(25000);
  page.setDefaultTimeout(25000);
  const report = { ok: false, cases: {} };
  try {
    await login(page);
    for (const spec of cases) {
      const row = { shots: {}, widths: {} };
      for (const width of [1280, 390]) {
        await page.setViewport({
          width,
          height: width === 390 ? 844 : 900,
          isMobile: width === 390,
          hasTouch: width === 390,
        });
        await page.goto(base + spec.path, { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('[data-qh-header]');
        await showFlash(page, spec.message);
        const file = 'quotation-flash-' + spec.key + '-' + width + '.png';
        row.shots[String(width)] = await shot(page, file);
        row.widths[String(width)] = await metrics(page);
      }
      report.cases[spec.key] = row;
    }
    report.ok = true;
    fs.mkdirSync(path.dirname(outPath), { recursive: true });
    fs.writeFileSync(outPath, JSON.stringify(report, null, 2));
    await browser.close();
    process.exit(0);
  } catch (err) {
    report.error = String(err && err.stack ? err.stack : err);
    try { fs.writeFileSync(outPath, JSON.stringify(report, null, 2)); } catch (e) {}
    console.error(err);
    await browser.close();
    process.exit(1);
  }
})();
