'use strict';

const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

const email = process.env.MW_EMAIL;
const password = process.env.MW_PASSWORD;
const base = process.env.MW_BASE;
const createUrl = process.env.MW_CREATE || '/admin/quotations/millwork/create';
const leaveUrl = process.env.MW_LEAVE || '/admin/quotations/millwork';
const outPath = process.env.MW_OUT || path.join(process.cwd(), 'storage/app/millwork-leave-save-browser.json');

(async () => {
  const report = { cleanDialogs: 0, dirtyDialogs: 0, dirtyTypes: [], ctrlSClicked: false, afterUrl: '' };
  const browser = await puppeteer.launch({
    headless: true,
    protocolTimeout: 30000,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
  });
  const page = await browser.newPage();
  page.setDefaultNavigationTimeout(30000);
  const dialogs = [];
  page.on('dialog', async (dialog) => {
    dialogs.push(dialog.type());
    await dialog.accept().catch(() => {});
  });

  async function go(url) {
    await page.goto(url, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('body');
  }

  async function revealWizard() {
    await page.evaluate(() => {
      document.querySelectorAll('details').forEach((d) => { d.open = true; });
      document.querySelectorAll('.br-acc').forEach((el) => {
        el.classList.add('is-active');
        el.setAttribute('open', '');
        el.hidden = false;
      });
    });
  }

  async function fillRequiredDraftFields() {
    await revealWizard();
    const name = await page.$('#mw-client-name');
    if (name) {
      await name.click({ clickCount: 3 });
      await page.keyboard.type('Ctrl S check', { delay: 10 });
    }
    await page.evaluate(() => {
      function pick(selector) {
        const el = document.querySelector(selector);
        if (!el || el.checked) return;
        el.checked = true;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
        if (typeof el.click === 'function') el.click();
      }
      pick('input[name="payment_installments"][value="3"]');
      pick('input[name="include_commute"][value="0"]');
      pick('input[name="include_debris_removal"][value="0"]');
    });
  }

  async function typeVisibleDim(value) {
    const dim = await page.evaluateHandle(() => {
      return Array.from(document.querySelectorAll('#mw-piece-list .mw-input--dim')).find((el) => {
        const style = window.getComputedStyle(el);
        const rect = el.getBoundingClientRect();
        return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 2 && rect.height > 2;
      }) || null;
    });
    if (dim && dim.asElement()) {
      await dim.asElement().click({ clickCount: 3 });
      await page.keyboard.type(value, { delay: 15 });
    }
  }

  try {
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

    await go(base + createUrl);
    await page.waitForSelector('#mw-main-form');
    const beforeLeave = dialogs.length;
    await go(base + leaveUrl);
    report.cleanDialogs = dialogs.length - beforeLeave;

    await go(base + createUrl);
    await page.waitForSelector('#mw-main-form');
    await revealWizard();
    await typeVisibleDim('36');
    await page.waitForFunction(() => {
      const form = document.getElementById('mw-main-form');
      return form && form.getAttribute('data-quotation-user-edited') === '1';
    }, { timeout: 5000 }).catch(() => {});

    const beforeDirty = dialogs.length;
    await go(base + leaveUrl);
    report.dirtyDialogs = dialogs.length - beforeDirty;
    report.dirtyTypes = dialogs.slice(beforeDirty);

    await go(base + createUrl);
    await page.waitForSelector('#mw-main-form');
    await fillRequiredDraftFields();
    await typeVisibleDim('36');
    await page.waitForFunction(() => {
      const form = document.getElementById('mw-main-form');
      return form && form.getAttribute('data-quotation-user-edited') === '1' && window.AdminHotkeySave;
    }, { timeout: 8000 }).catch(() => {});
    report.hasHotkeyTarget = await page.evaluate(() => {
      const btn = document.querySelector('[data-hotkey-save]');
      return !!(btn && (btn.id === 'mw-btn-draft' || btn.classList.contains('qh-actions__draft')));
    });
    report.formValid = await page.evaluate(() => {
      const form = document.getElementById('mw-main-form');
      return !!(form && typeof form.checkValidity === 'function' && form.checkValidity());
    });
    report.invalid = await page.evaluate(() => {
      return Array.from(document.querySelectorAll('#mw-main-form :invalid')).map((el) => el.name || el.id || el.tagName);
    });
    const savedUrl = /millwork\/\d+|millwork\/[^/]+\/edit/;
    const nav = page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 15000 }).catch(() => null);
    await page.keyboard.down('Control');
    await page.keyboard.press('KeyS');
    await page.keyboard.up('Control');
    await nav;
    report.afterUrl = page.url();
    report.ctrlSClicked = savedUrl.test(report.afterUrl);

    fs.mkdirSync(path.dirname(outPath), { recursive: true });
    fs.writeFileSync(outPath, JSON.stringify(report, null, 2));
    if (report.cleanDialogs !== 0) {
      throw new Error('Clean millwork form must not warn on leave');
    }
    if (report.dirtyDialogs < 1) {
      throw new Error('Dirty millwork form must warn on leave');
    }
    if (!report.hasHotkeyTarget || !report.ctrlSClicked) {
      throw new Error('Ctrl+S must click the millwork draft hotkey');
    }
    if (!/millwork\/\d+|millwork\/[^/]+\/edit/.test(report.afterUrl)) {
      throw new Error('Ctrl+S on a dirty millwork form must save: ' + report.afterUrl);
    }
    await browser.close();
  } catch (err) {
    try {
      fs.mkdirSync(path.dirname(outPath), { recursive: true });
      fs.writeFileSync(outPath, JSON.stringify({ error: String(err && err.message ? err.message : err), report }, null, 2));
    } catch (writeErr) { /* ignore */ }
    await browser.close().catch(() => {});
    process.exit(1);
  }
})();
