'use strict';

const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

const email = process.env.QH_DEMO_EMAIL;
const password = process.env.QH_DEMO_PASSWORD;
const base = process.env.QH_DEMO_BASE;
const outPath = process.env.QH_DEMO_OUT;
const shotsDir = process.env.QH_DEMO_SHOTS || path.join(process.cwd(), 'storage/app/qa-screenshots');
const cases = JSON.parse(process.env.QH_DEMO_CASES || '[]');

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

async function readDemo(page) {
  return page.evaluate(() => {
    const yes = document.querySelector('[data-br-demo-choice="1"]');
    const no = document.querySelector('[data-br-demo-choice="0"]');
    const fields = document.getElementById('br-demo-fields');
    const footer = document.querySelector('[data-qh-footer-amount]');
    const overflow = document.documentElement.scrollWidth > window.innerWidth + 2;
    return {
      yesChecked: !!(yes && yes.checked),
      noChecked: !!(no && no.checked),
      fieldsHidden: !!(fields && fields.hidden),
      footer: footer ? String(footer.textContent || '').trim() : '',
      overflow,
    };
  });
}

async function openDemolition(page) {
  const goto = await page.$('[data-br-goto="demolition"]');
  if (goto) {
    await goto.click();
  }
  await page.evaluate(() => {
    const step = document.querySelector('[data-br-step="demolition"]');
    if (step) {
      step.hidden = false;
      step.setAttribute('open', '');
      step.classList.add('is-active');
      const radios = step.querySelector('[data-br-demo-choice]') || step;
      if (radios && radios.scrollIntoView) {
        radios.scrollIntoView({ block: 'center' });
      }
    }
  });
  await new Promise((r) => setTimeout(r, 350));
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
    await page.goto(base + spec.path, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#br-main-form');
    await openDemolition(page);
    if (spec.expectedMoney) {
      await page.waitForSelector('[data-qh-footer-amount]');
      row.settled = await waitSettled(page, spec.expectedMoney);
    }
    const editor = await readDemo(page);
    row.editor = editor;
    row.widths['1280'] = {
      shot: await shot(page, spec.key + '-1280.png'),
      overflow: editor.overflow,
      footer: editor.footer,
    };

    await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 1 });
    await new Promise((r) => setTimeout(r, 200));
    await openDemolition(page);
    if (spec.expectedMoney) {
      await waitSettled(page, spec.expectedMoney);
    }
    const mobile = await readDemo(page);
    row.widths['390'] = {
      shot: await shot(page, spec.key + '-390.png'),
      overflow: mobile.overflow,
      footer: mobile.footer,
    };
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
