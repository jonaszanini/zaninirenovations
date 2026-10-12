'use strict';

const puppeteer = require('puppeteer');

const email = process.env.MW_EMAIL;
const password = process.env.MW_PASSWORD;
const base = process.env.MW_BASE;
const jobs = JSON.parse(process.env.MW_JOBS || '[]');

(async () => {
  const report = { saved: [] };
  const browser = await puppeteer.launch({
    headless: true,
    protocolTimeout: 60000,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
  });
  const page = await browser.newPage();
  page.setDefaultNavigationTimeout(30000);
  page.setDefaultTimeout(20000);
  page.on('dialog', async (dialog) => {
    await dialog.accept().catch(() => {});
  });

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
  if (page.url().includes('/login')) {
    throw new Error('Login stayed on ' + page.url());
  }

  for (const job of jobs) {
    await page.goto(base + job.path, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#mw-main-form');
    await page.waitForSelector('#mw-btn-draft');
    const disabled = await page.$$eval('[data-mw-piece] input, [data-mw-piece] select, [data-mw-piece] textarea', (nodes) => {
      return nodes.filter((el) => el.disabled && (el.name || el.getAttribute('data-mw-name'))).map((el) => el.name || el.getAttribute('data-mw-name'));
    });
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      page.click('#mw-btn-draft'),
    ]);
    const error = await page.evaluate(() => {
      const el = document.querySelector('.admin-form-error, .admin-flash--danger, .admin-toast--danger');
      return el ? (el.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 300) : '';
    });
    report.saved.push({
      id: job.id,
      url: page.url(),
      error: error,
      disabled: disabled,
    });
  }

  process.stdout.write(JSON.stringify(report));
  await browser.close();
})().catch((err) => {
  console.error(err && err.stack ? err.stack : err);
  process.exit(1);
});
