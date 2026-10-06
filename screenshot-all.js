const puppeteer = require('puppeteer');
const path = require('path');

const BASE_URL = 'http://localhost:8000';
const OUTPUT_DIR = '/opt/cursor/artifacts/phase4';
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));

const screenshots = [
  { name: 'invoices-list-desktop-1280-light', url: '/admin/financials/invoices', width: 1280, height: 900, theme: 'light' },
  { name: 'expenses-list-mobile-390-dark', url: '/admin/financials/expenses', width: 390, height: 844, theme: 'dark' },
  { name: 'quick-expense-project-page-mobile-390-light', url: '/admin/client-projects/1#financials', width: 390, height: 844, theme: 'light' },
  { name: 'ctrlk-palette-desktop-1280-dark', url: '/admin/client-projects', width: 1280, height: 900, theme: 'dark', openPalette: true },
  { name: 'project-header-quick-status-desktop-1280-light', url: '/admin/client-projects/1', width: 1280, height: 900, theme: 'light' },
  { name: 'project-header-quick-status-mobile-390-light', url: '/admin/client-projects/1', width: 390, height: 844, theme: 'light' },
  { name: 'quotation-rows-actions-menu-open-mobile-390-light', url: '/admin/client-projects/1#quotation', width: 390, height: 844, theme: 'light', openQuotationMenu: true },
  { name: 'duplicate-quotation-modal-desktop-1280-light', url: '/admin/client-projects/1#quotation', width: 1280, height: 900, theme: 'light', openDuplicateModal: true },
  { name: 'client-edit-form-desktop-1280-light', url: '/admin/clients/1/edit', width: 1280, height: 900, theme: 'light' },
  { name: 'client-project-edit-form-desktop-1280-dark', url: '/admin/client-projects/1/edit', width: 1280, height: 900, theme: 'dark' },
  { name: 'shared-prices-desktop-1280-light', url: '/admin/quotations/shared-prices', width: 1280, height: 900, theme: 'light' },
  { name: 'quotation-cards-desktop-1280-light', url: '/admin/client-projects/1#quotation', width: 1280, height: 900, theme: 'light' },
];

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

async function takeScreenshot(page, shot) {
  console.log(`Taking: ${shot.name}`);
  
  await page.setViewport({ width: shot.width, height: shot.height });
  await page.goto(`${BASE_URL}${shot.url}`, { waitUntil: 'networkidle0' });
  await setTheme(page, shot.theme);
  await delay(300);
  
  if (shot.openPalette) {
    await page.keyboard.down('Control');
    await page.keyboard.press('k');
    await page.keyboard.up('Control');
    await delay(500);
  }
  
  if (shot.openQuotationMenu) {
    await delay(500);
    const menuBtns = await page.$$('.admin-actions-dropdown__toggle');
    if (menuBtns.length > 0) {
      await menuBtns[0].click();
      await delay(400);
      await page.evaluate(() => {
        const dropdown = document.querySelector('.admin-actions-dropdown');
        if (dropdown) dropdown.classList.add('is-open');
      });
      await delay(200);
    }
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
  
  await page.screenshot({
    path: path.join(OUTPUT_DIR, `${shot.name}.png`),
    fullPage: false,
  });
  console.log(`Saved: ${shot.name}.png`);
}

(async () => {
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
  
  console.log('Done!');
})();
