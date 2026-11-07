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

  if (process.env.QH_DEMO_DRAFT === '1') {
    const draft = { widths: {} };
    await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
    await page.goto(base + process.env.QH_DEMO_DRAFT_PATH, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#br-main-form');
    await page.type('[name="client_name"]', 'Demo Draft Blank Choice');
    const area = await page.$('#br-area');
    if (area) {
      await area.click({ clickCount: 3 });
      await area.type('400');
    }
    await openDemolition(page);
    const before = await readDemo(page);
    draft.before = before;
    draft.radiosRequiredAtRest = await page.evaluate(() => {
      const radios = [...document.querySelectorAll('[data-br-demo-choice]')];
      return radios.some((el) => !!(el && el.required));
    });
    draft.demoBlocksValidityAtRest = await page.evaluate(() => {
      const radios = [...document.querySelectorAll('[data-br-demo-choice]')];
      return radios.some((el) => !!(el && (el.required || (typeof el.matches === 'function' && el.matches(':invalid')))));
    });
    draft.hotkeyOnDraft = await page.$eval('#br-btn-draft', (el) => el.hasAttribute('data-hotkey-save'));
    draft.hotkeyOnPublish = await page.$eval('#br-btn-publish', (el) => el.hasAttribute('data-hotkey-save'));
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 20000 }),
      page.click('#br-btn-draft'),
    ]);
    await page.waitForSelector('#br-main-form');
    await openDemolition(page);
    draft.afterSave = await readDemo(page);
    draft.editUrl = page.url();
    draft.draftMarker = await page.$eval('#br-main-form', (el) => el.getAttribute('data-quotation-is-draft'));
    await page.click('#br-btn-publish');
    await new Promise((r) => setTimeout(r, 800));
    draft.publishUrl = page.url();
    draft.publishError = await page.evaluate(() => {
      const flash = document.querySelector('.admin-flash--danger, .admin-form-error, .admin-toast');
      const yes = document.querySelector('[data-br-demo-choice="1"]');
      const no = document.querySelector('[data-br-demo-choice="0"]');
      return {
        flash: flash ? String(flash.textContent || '').trim() : '',
        stillEdit: /\/edit(?:\/|$)/.test(location.pathname),
        yesChecked: !!(yes && yes.checked),
        noChecked: !!(no && no.checked),
        yesRequired: !!(yes && yes.required),
        invalidRadio: !!document.querySelector('[data-br-demo-choice]:invalid'),
      };
    });
    report.draft = draft;
  }

  if (process.env.QH_IMPORT_PATH) {
    const importRow = { ok: false };
    await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
    await page.goto(base + process.env.QH_IMPORT_PATH, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#br-main-form');
    const layoutsUrl = base + process.env.QH_IMPORT_LAYOUTS + '?q=' + encodeURIComponent(process.env.QH_IMPORT_LAYOUT_Q || '');
    const opt = await page.evaluate(async (url) => {
      const res = await fetch(url, { credentials: 'same-origin' });
      const rows = await res.json();
      return Array.isArray(rows) ? rows[0] : null;
    }, layoutsUrl);
    importRow.layoutId = opt && opt.id;
    if (!opt) {
      throw new Error('layouts search returned no Paint import mixed option');
    }
    const fireImport = async () => {
      await page.evaluate((option) => {
        const picker = document.querySelector('[data-br-layout-picker]');
        if (!picker) throw new Error('missing layout picker');
        picker.dispatchEvent(new CustomEvent('admin-search-select:change', {
          bubbles: true,
          detail: { option: option },
        }));
      }, opt);
    };
    await fireImport();
    await page.waitForSelector('#admin-dialog:not([hidden])', { timeout: 8000 });
    importRow.message = await page.$eval('#admin-dialog-message', (el) => String(el.textContent || ''));
    importRow.shot1280 = await shot(page, 'paint-import-confirm-1280.png');
    await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 1 });
    await new Promise((r) => setTimeout(r, 200));
    importRow.shot390 = await shot(page, 'paint-import-confirm-390.png');
    await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
    await page.click('#admin-dialog .admin-btn--ghost');
    await page.waitForFunction(() => {
      const dlg = document.getElementById('admin-dialog');
      return !dlg || dlg.hidden;
    });
    importRow.keptPaint = await page.$eval('#br-p-wall', (el) => parseFloat(el.value || '0'));
    await fireImport();
    await page.waitForSelector('#admin-dialog:not([hidden])', { timeout: 8000 });
    await page.click('#admin-dialog .admin-btn--primary');
    await page.waitForFunction(() => {
      const dlg = document.getElementById('admin-dialog');
      return !dlg || dlg.hidden;
    });
    importRow.replacedPaint = await page.$eval('#br-p-wall', (el) => parseFloat(el.value || '1'));
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 20000 }),
      page.click('#br-btn-draft'),
    ]);
    await page.waitForSelector('#br-p-wall');
    importRow.reopenPaint = await page.$eval('#br-p-wall', (el) => parseFloat(el.value || '1'));
    importRow.reopenRate = await page.$eval('[name="owner_hourly_rate"]', (el) => parseFloat(el.value || '0'));
    importRow.ok = importRow.keptPaint === 400 && importRow.replacedPaint === 0 && importRow.reopenPaint === 0;
    report.import = importRow;
  }

  fs.mkdirSync(path.dirname(outPath), { recursive: true });
  fs.writeFileSync(outPath, JSON.stringify(report, null, 2));
  await browser.close();
  process.stdout.write(JSON.stringify(report));
})().catch((err) => {
  console.error(err);
  process.exit(2);
});
