'use strict';

const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

const email = process.env.QH_HELP_EMAIL;
const password = process.env.QH_HELP_PASSWORD;
const base = process.env.QH_HELP_BASE;
const outPath = process.env.QH_HELP_OUT;
const shotsDir = process.env.QH_HELP_SHOTS || path.join(process.cwd(), 'storage/app/qh-help-tip-shots');

const pages = [
  {
    key: 'paving',
    href: '/admin/quotations/paving/create',
    tipLabel: 'About Waste factor (%)',
  },
  {
    key: 'stairs',
    href: '/admin/quotations/stairs/create',
    tipLabel: 'About Stair configuration',
  },
];

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

async function tipState(page, ariaLabel) {
  return page.evaluate((label) => {
    const btn = document.querySelector('button.admin-info-tip[aria-label="' + label + '"]');
    const wrap = btn && btn.closest('.admin-info-tip-wrap');
    const bubble = wrap && wrap.querySelector(':scope > .admin-info-tip__bubble');
    const footer = document.querySelector('[data-qh-footer]');
    const amount = footer && footer.querySelector('[data-qh-footer-amount]');
    const hints = [...document.querySelectorAll('p.pq-hint')].map((el) => (el.textContent || '').replace(/\s+/g, ' ').trim()).filter(Boolean);
    return {
      hasTip: !!btn,
      expanded: !!(btn && btn.getAttribute('aria-expanded') === 'true'),
      openClass: !!(wrap && wrap.classList.contains('is-open')),
      bubbleText: bubble ? String(bubble.textContent || '').replace(/\s+/g, ' ').trim() : '',
      hasFooter: !!footer,
      footerPinned: !!(footer && Math.abs(footer.getBoundingClientRect().bottom - window.innerHeight) <= 2),
      leftoverHints: hints,
    };
  }, ariaLabel);
}

(async () => {
  fs.mkdirSync(shotsDir, { recursive: true });
  const browser = await puppeteer.launch(launchOpts());
  const page = await browser.newPage();
  page.setDefaultNavigationTimeout(25000);
  page.setDefaultTimeout(25000);

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

  const report = {};
  for (const spec of pages) {
    report[spec.key] = {};
    await page.goto(base + spec.href, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('button.admin-info-tip[aria-label="' + spec.tipLabel + '"]');
    for (const width of [1280, 390]) {
      await page.setViewport({ width, height: 900, deviceScaleFactor: 1 });
      await new Promise((r) => setTimeout(r, 200));
      const closedSel = 'button.admin-info-tip[aria-label="' + spec.tipLabel + '"]';
      await page.waitForSelector(closedSel);
      await page.$eval(closedSel, (el) => el.scrollIntoView({ block: 'center', inline: 'nearest' }));
      await page.evaluate((label) => {
        const btn = document.querySelector('button.admin-info-tip[aria-label="' + label + '"]');
        if (btn && btn.getAttribute('aria-expanded') === 'true') btn.click();
      }, spec.tipLabel);
      await page.waitForFunction((label) => {
        const btn = document.querySelector('button.admin-info-tip[aria-label="' + label + '"]');
        return btn && btn.getAttribute('aria-expanded') === 'false';
      }, {}, spec.tipLabel);
      const closed = await tipState(page, spec.tipLabel);
      closed.shot = await shot(page, spec.key + '-help-closed-' + width + '.png');
      await page.$eval(closedSel, (el) => el.click());
      await page.waitForFunction((label) => {
        const btn = document.querySelector('button.admin-info-tip[aria-label="' + label + '"]');
        const wrap = btn && btn.closest('.admin-info-tip-wrap');
        return btn && btn.getAttribute('aria-expanded') === 'true' && wrap && wrap.classList.contains('is-open');
      }, {}, spec.tipLabel);
      const opened = await tipState(page, spec.tipLabel);
      opened.shot = await shot(page, spec.key + '-help-open-' + width + '.png');
      await page.$eval(closedSel, (el) => el.click());
      report[spec.key][String(width)] = { closed, opened };
    }
  }

  fs.mkdirSync(path.dirname(outPath), { recursive: true });
  fs.writeFileSync(outPath, JSON.stringify(report, null, 2));
  await browser.close();
  process.stdout.write(JSON.stringify(report));
})().catch((err) => {
  console.error(err);
  process.exit(2);
});
