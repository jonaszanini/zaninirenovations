const puppeteer = require('puppeteer');
const path = require('path');
const fs = require('fs');

const BASE_URL = 'http://localhost:8000';
const OUTPUT_DIR = '/opt/cursor/artifacts/phase4';
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));

const screenshots = [
  { name: 'invoices-list-desktop-1280-light', url: '/admin/financials/invoices', width: 1280, height: 900, theme: 'light' },
  { name: 'expenses-list-mobile-390-dark', url: '/admin/financials/expenses', width: 390, height: 844, theme: 'dark' },
  { name: 'quick-expense-project-page-mobile-390-light', url: '/admin/client-projects/1#financials', width: 390, height: 844, theme: 'light', scrollToExpenses: true, checkOverflow: true },
  { name: 'ctrlk-palette-desktop-1280-dark', url: '/admin/client-projects', width: 1280, height: 900, theme: 'dark', openPalette: true },
  { name: 'project-header-quick-status-desktop-1280-light', url: '/admin/client-projects/1', width: 1280, height: 900, theme: 'light' },
  { name: 'project-header-quick-status-mobile-390-light', url: '/admin/client-projects/1', width: 390, height: 844, theme: 'light' },
  { name: 'quotation-rows-actions-menu-open-mobile-390-light', url: '/admin/client-projects/1#quotation', width: 390, height: 844, theme: 'light', openQuotationMenu: true, checkOverflow: true },
  { name: 'duplicate-quotation-modal-desktop-1280-light', url: '/admin/client-projects/1#quotation', width: 1280, height: 900, theme: 'light', openDuplicateModal: true },
  { name: 'client-edit-form-desktop-1280-light', url: '/admin/clients/1/edit', width: 1280, height: 900, theme: 'light' },
  { name: 'client-project-edit-form-desktop-1280-dark', url: '/admin/client-projects/1/edit', width: 1280, height: 900, theme: 'dark' },
  { name: 'shared-prices-desktop-1280-light', url: '/admin/quotations/shared-prices', width: 1280, height: 900, theme: 'light', checkOverflow: true },
  { name: 'shared-prices-mat-price-1280-light', url: '/admin/quotations/shared-prices', width: 1280, height: 900, theme: 'light', scrollToMatPrice: true, checkOverflow: true },
  { name: 'quotation-cards-desktop-1280-light', url: '/admin/client-projects/1#quotation', width: 1280, height: 900, theme: 'light' },
  { name: 'projects-list-desktop-1280-light', url: '/admin/client-projects', width: 1280, height: 900, theme: 'light', checkOverflow: true },
  { name: 'clients-list-mobile-390-dark', url: '/admin/clients', width: 390, height: 844, theme: 'dark', checkOverflow: true },
  { name: 'clients-list-desktop-1280-dark', url: '/admin/clients', width: 1280, height: 900, theme: 'dark', checkOverflow: true },
  { name: 'clients-list-mobile-390-light', url: '/admin/clients', width: 390, height: 844, theme: 'light', checkOverflow: true },
  { name: 'clients-list-search-mobile-390-dark', url: '/admin/clients?q=Lucc', width: 390, height: 844, theme: 'dark', checkOverflow: true },
];

const overflowReport = {};

async function login(page) {
  await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'networkidle0' });
  await page.type('input[name="email"]', 'admin@zanini.ca');
  await page.type('input[name="password"]', 'password');
  await Promise.all([
    page.click('button[type="submit"]'),
    page.waitForNavigation({ waitUntil: 'networkidle0' }),
  ]);
}

async function setTheme(page, theme) {
  if (theme === 'dark') {
    await page.evaluate(() => {
      document.documentElement.classList.add('admin-dark');
    });
  } else {
    await page.evaluate(() => {
      document.documentElement.classList.remove('admin-dark');
    });
  }
  await delay(100);
}

async function checkOverflow(page, shotName) {
  const result = await page.evaluate(() => {
    const SKIP_POS = new Set(['absolute', 'fixed', 'sticky']);
    const SKIP_CLASS = /(dropdown__menu|ss-combobox__list|admin-search-select|sp-search-wrap|asp|cp-modal)/;

    function visible(el) {
      if (el.closest('[hidden]')) return false;
      const s = getComputedStyle(el);
      if (s.display === 'none' || s.visibility === 'hidden' || s.opacity === '0') return false;
      const r = el.getBoundingClientRect();
      return r.width > 0 && r.height > 0;
    }

    function cls(el) {
      return (typeof el.className === 'string' ? el.className : '').slice(0, 90);
    }

    function info(el, reason, extra) {
      return {
        tag: el.tagName.toLowerCase(),
        id: el.id || '',
        class: cls(el),
        text: String(el.value || el.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 70),
        reason,
        scrollWidth: el.scrollWidth,
        clientWidth: el.clientWidth,
        extra: extra || null,
      };
    }

    const offenders = [];
    const measurements = {};

    document.querySelectorAll('input, select, textarea').forEach(el => {
      if (!visible(el)) return;
      if (['hidden', 'file', 'checkbox', 'radio', 'submit', 'button'].includes(el.type)) return;
      if (el.scrollWidth > el.clientWidth + 1) {
        offenders.push(info(el, 'input_clipped', { value: String(el.value || '').slice(0, 70) }));
      }
    });

    document.querySelectorAll('.sp-col-rate input, .sp-col-sell input, .sp-mat-fallback-price').forEach(el => {
      if (!visible(el)) return;
      const prev = el.value;
      el.value = '9,999.0000';
      if (el.scrollWidth > el.clientWidth + 1) {
        offenders.push(info(el, 'input_clipped', { value: '9,999.0000 (probe)' }));
      }
      el.value = prev;
    });

    const wrapTargets = document.querySelectorAll(
      '.sp-col-unit input, .sp-mat-name-input, .sp-work-label, .cp-quotation-accordion__title, .cp-quotation-accordion__number, .cp-table-project-name, .cp-table td:nth-child(7), .cp-col-amount, .cp-expenses-table td'
    );
    wrapTargets.forEach(el => {
      if (!visible(el)) return;
      if (el.scrollWidth > el.clientWidth + 1) {
        offenders.push(info(el, 'text_overflow'));
      }
      if (el.matches('.cp-quotation-accordion__title, .cp-quotation-accordion__number, .cp-table-project-name, .sp-work-label')) {
        const range = document.createRange();
        try {
          range.selectNodeContents(el);
          const rects = range.getClientRects();
          const lineCount = new Set(Array.from(rects).map(r => Math.round(r.top))).size;
          const hasBlockChild = el.querySelector('small, br, div, p');
          if (lineCount > 1 && !hasBlockChild) {
            offenders.push(info(el, 'unexpected_wrap', { lineCount }));
          }
        } catch (e) { /* ignore */ }
      }
    });

    document.querySelectorAll('.admin-card, .cp-quotation-accordion').forEach(card => {
      if (!visible(card)) return;
      const cardRect = card.getBoundingClientRect();
      if (cardRect.width < 8) return;
      card.querySelectorAll('*').forEach(el => {
        if (!visible(el)) return;
        const s = getComputedStyle(el);
        if (SKIP_POS.has(s.position) || SKIP_CLASS.test(cls(el))) return;
        const r = el.getBoundingClientRect();
        const overflowPx = Math.round(r.right - cardRect.right);
        if (overflowPx > 8) {
          offenders.push(info(el, 'wider_than_card', { overflowPx }));
        }
      });
    });

    const accordion = document.querySelector('.cp-quotation-accordion');
    const chevron = document.querySelector('.cp-quotation-accordion__chevron');
    const actions = document.querySelector('.cp-quotation-accordion__actions');
    if (accordion && chevron) {
      const cardR = accordion.getBoundingClientRect();
      const chevR = chevron.getBoundingClientRect();
      measurements.chevronInset = Math.round(chevR.left - cardR.left);
    }
    if (accordion && actions) {
      const cardR = accordion.getBoundingClientRect();
      const actR = actions.getBoundingClientRect();
      measurements.actionsRightGutter = Math.round(cardR.right - actR.right);
    }

    const unitInputs = Array.from(document.querySelectorAll('.sp-col-unit input')).map(el => ({
      value: el.value,
      clientWidth: el.clientWidth,
      scrollWidth: el.scrollWidth,
      clipped: el.scrollWidth > el.clientWidth + 1,
    }));
    if (unitInputs.length) measurements.unitInputs = unitInputs;

    const nameInputs = Array.from(document.querySelectorAll('.sp-mat-name-input')).map(el => ({
      value: el.value,
      clientWidth: el.clientWidth,
      scrollWidth: el.scrollWidth,
      clipped: el.scrollWidth > el.clientWidth + 1,
    }));
    if (nameInputs.length) measurements.nameInputs = nameInputs;

    const expenseAmounts = Array.from(document.querySelectorAll('.cp-expenses-table .cp-col-amount')).map(el => ({
      text: el.textContent.trim(),
      visible: el.getBoundingClientRect().width > 0,
    }));
    if (expenseAmounts.length) measurements.expenseAmounts = expenseAmounts;

    return { offenders, measurements };
  });

  overflowReport[shotName] = result;
  console.log(`\n=== OVERFLOW CHECK: ${shotName} ===`);
  console.log(`offenders: ${result.offenders.length}`);
  result.offenders.forEach(o => console.log(JSON.stringify(o)));
  console.log('measurements:', JSON.stringify(result.measurements, null, 2));
}

async function takeScreenshot(page, shot) {
  console.log(`Taking: ${shot.name}`);

  await page.setViewport({ width: shot.width, height: shot.height });
  await page.goto(`${BASE_URL}${shot.url}`, { waitUntil: 'networkidle0' });
  await setTheme(page, shot.theme);
  await delay(300);

  const hash = (shot.url.split('#')[1] || '').split('?')[0];
  if (hash) {
    await page.evaluate((tab) => {
      const btn = document.querySelector('.cp-tab[data-tab="' + tab + '"]');
      if (btn) btn.click();
      const panel = document.querySelector('.cp-tab-panel[data-panel="' + tab + '"]');
      if (panel) {
        document.querySelectorAll('.cp-tab-panel').forEach(p => p.classList.remove('is-active'));
        document.querySelectorAll('.cp-tab').forEach(t => t.classList.remove('is-active'));
        panel.classList.add('is-active');
        const tabBtn = document.querySelector('.cp-tab[data-tab="' + tab + '"]');
        if (tabBtn) tabBtn.classList.add('is-active');
      }
    }, hash);
    await delay(200);
  }
  if (hash === 'quotation') {
    await page.evaluate(() => {
      const el = document.querySelector('.cp-quotation-accordion') || document.querySelector('#quotation');
      if (el) el.scrollIntoView({ behavior: 'instant', block: 'start' });
    });
    await delay(200);
  }

  if (shot.openPalette) {
    await page.keyboard.down('Control');
    await page.keyboard.press('k');
    await page.keyboard.up('Control');
    await delay(500);
  }

  if (shot.openQuotationMenu) {
    await delay(500);
    await page.evaluate(() => {
      const mobile = document.querySelector('.cp-quotation-toolbar__mobile .admin-actions-dropdown__toggle');
      const any = document.querySelector('.cp-quotation-accordion__actions .admin-actions-dropdown__toggle');
      const btn = mobile || any;
      if (btn) btn.click();
      const dd = (mobile || any) && (mobile || any).closest('.admin-actions-dropdown');
      if (dd) dd.classList.add('is-open');
    });
    await delay(300);
  }

  if (shot.openDuplicateModal) {
    await delay(500);
    await page.evaluate(() => {
      if (typeof cpOpenDuplicateModal === 'function') {
        cpOpenDuplicateModal(1, 2, 'SQ-2026-0099', 'Project 1');
      }
    });
    await delay(500);
  }

  if (shot.scrollToExpenses) {
    await delay(500);
    await page.evaluate(() => {
      const headings = document.querySelectorAll('h3');
      for (const h of headings) {
        if (h.textContent.trim() === 'Expenses') {
          h.scrollIntoView({ behavior: 'instant', block: 'start' });
          return;
        }
      }
      const table = document.querySelector('.cp-expenses-table');
      if (table) table.scrollIntoView({ behavior: 'instant', block: 'start' });
    });
    await delay(300);
  }

  if (shot.scrollToMatPrice) {
    await delay(300);
    await page.evaluate(() => {
      let el = document.getElementById('sp-mat-price-16');
      if (!el) {
        el = Array.from(document.querySelectorAll('.sp-mat-fallback-price')).find(i =>
          String(i.value).replace(/\s/g, '') === '1,410.0000' || String(i.value).replace(/,/g, '') === '1410.0000'
        );
      }
      if (!el) return;
      const details = el.closest('details');
      if (details) details.open = true;
      el.scrollIntoView({ behavior: 'instant', block: 'center' });
    });
    await delay(300);
  }

  if (shot.checkOverflow) {
    await checkOverflow(page, shot.name);
  }

  await page.screenshot({
    path: path.join(OUTPUT_DIR, `${shot.name}.png`),
    fullPage: false,
  });
  console.log(`Saved: ${shot.name}.png`);
}

(async () => {
  fs.mkdirSync(OUTPUT_DIR, { recursive: true });
  const browser = await puppeteer.launch({
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });

  try {
    const page = await browser.newPage();
    await login(page);

    for (const shot of screenshots) {
      try {
        await takeScreenshot(page, shot);
      } catch (err) {
        console.error(`Error taking ${shot.name}:`, err.message);
      }
    }
  } finally {
    await browser.close();
  }

  const reportPath = path.join(OUTPUT_DIR, 'overflow-report.json');
  fs.writeFileSync(reportPath, JSON.stringify(overflowReport, null, 2));
  console.log('\n========== OVERFLOW REPORT SUMMARY ==========');
  let total = 0;
  for (const [name, data] of Object.entries(overflowReport)) {
    const n = (data.offenders || []).length;
    total += n;
    console.log(`${name}: ${n} offender(s)`);
  }
  console.log(`TOTAL offenders: ${total}`);
  console.log(`Wrote ${reportPath}`);
  console.log('Done!');
})();
