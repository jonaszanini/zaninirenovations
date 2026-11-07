'use strict';

const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

const email = process.env.QH_FOOTER_EMAIL;
const password = process.env.QH_FOOTER_PASSWORD;
const base = process.env.QH_FOOTER_BASE;
const outPath = process.env.QH_FOOTER_OUT;
const shotsDir = process.env.QH_FOOTER_SHOTS || path.join(process.cwd(), 'storage/app/qh-footer-shots');

const pages = [
  { key: 'basement', href: '/admin/quotations/basement/create', painting: false },
  { key: 'millwork', href: '/admin/quotations/millwork/create', painting: false },
  { key: 'standard', href: '/admin/quotations/standard/create', painting: false },
  { key: 'fence', href: '/admin/quotations/fence/create', painting: false },
  { key: 'painting', href: '/admin/quotations/painting/create', painting: true },
];

function launchOpts() {
  const opts = {
    headless: true,
    protocolTimeout: 30000,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
  };
  if (process.env.PUPPETEER_EXECUTABLE_PATH) {
    opts.executablePath = process.env.PUPPETEER_EXECUTABLE_PATH;
  }
  return opts;
}

(async () => {
  fs.mkdirSync(shotsDir, { recursive: true });
  const browser = await puppeteer.launch(launchOpts());
  const page = await browser.newPage();
  page.setDefaultNavigationTimeout(20000);
  page.setDefaultTimeout(20000);

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
    await page.goto(base + spec.href, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('[data-qh-header]');
    report[spec.key] = {};
    for (const width of [1280, 390]) {
      await page.setViewport({ width, height: 900, deviceScaleFactor: 1 });
      await new Promise((r) => setTimeout(r, 150));
      const shot = path.join(shotsDir, spec.key + '-' + width + '.png');
      let shotOk = false;
      for (let attempt = 0; attempt < 4 && !shotOk; attempt++) {
        try {
          await page.screenshot({ path: shot, fullPage: false });
          shotOk = true;
        } catch (err) {
          if (attempt === 3) throw err;
          await new Promise((r) => setTimeout(r, 150 * (attempt + 1)));
        }
      }
      const row = await page.evaluate(async () => {
        const footer = document.querySelector('[data-qh-footer]');
        const save = footer && footer.querySelector('[data-qh-footer-save]');
        const amount = footer && footer.querySelector('[data-qh-footer-amount]');
        const headerSave = document.querySelector('[data-qh-header] .qh-actions__save');
        const scripts = [...document.scripts].map((s) => s.src || '').join('\n');
        const vh = window.innerHeight;
        const footerBox = footer ? footer.getBoundingClientRect() : null;
        const saveBox = save ? save.getBoundingClientRect() : null;
        const amountBox = amount ? amount.getBoundingClientRect() : null;
        let liveBefore = amount ? String(amount.textContent || '') : '';
        let liveAfter = liveBefore;
        if (amount) {
          window.__quoteGrandTotal = 1234.56;
          document.dispatchEvent(new CustomEvent('quote:grand-total', { detail: { total: 1234.56 } }));
          liveAfter = String(amount.textContent || '');
        }
        let headerClicks = 0;
        if (save && headerSave) {
          const onHeader = function (e) {
            e.preventDefault();
            e.stopPropagation();
            headerClicks += 1;
          };
          headerSave.addEventListener('click', onHeader, true);
          save.click();
          headerSave.removeEventListener('click', onHeader, true);
        }
        return {
          hasFooter: !!footer,
          footerCount: document.querySelectorAll('[data-qh-footer]').length,
          hasSave: !!save,
          hasAmount: !!amount,
          hasHeaderSave: !!headerSave,
          footerJs: scripts.indexOf('admin-quotation-editor-footer.js') !== -1,
          footerBottom: footerBox ? Math.round(footerBox.bottom) : null,
          viewportH: vh,
          saveInView: !!(saveBox && saveBox.width > 2 && saveBox.top >= -1 && saveBox.bottom <= vh + 1),
          amountInView: !!(amountBox && amountBox.width > 2 && amountBox.top >= -1 && amountBox.bottom <= vh + 1),
          pinned: !!(footerBox && Math.abs(footerBox.bottom - vh) <= 2),
          liveBefore,
          liveAfter,
          headerClicks,
        };
      });
      row.shot = shot;
      report[spec.key][String(width)] = row;
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
