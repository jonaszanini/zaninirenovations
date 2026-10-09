/**
 * Headless geometry check for /admin/blog/posts.
 * Fails if date/views/actions cells overflow at 1024 or 768.
 */
const puppeteer = require('puppeteer-core');

const BASE = process.env.APP_URL || 'http://127.0.0.1:8000';
const CHROME = process.env.CHROME_PATH || '/usr/bin/google-chrome';

async function login(page) {
  await page.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle0', timeout: 60000 });
  if (!(await page.$('input[name="email"]'))) return;
  await page.type('input[name="email"]', process.env.ADMIN_EMAIL || 'admin@zanini.ca');
  await page.type('input[name="password"]', process.env.ADMIN_PASSWORD || 'password');
  await Promise.all([
    page.click('button[type="submit"]'),
    page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 60000 }),
  ]);
}

async function measure(page, width) {
  await page.setViewport({ width, height: 900, deviceScaleFactor: 1 });
  await page.goto(`${BASE}/admin/blog/posts`, { waitUntil: 'networkidle0', timeout: 60000 });
  await page.waitForSelector('.bp-list-table', { timeout: 15000 });
  return page.evaluate((vp) => {
    const table = document.querySelector('.bp-list-table');
    const scroll = document.querySelector('.bp-list .admin-table-scroll');
    const overflowing = [];
    document.querySelectorAll('.bp-list-table tbody tr').forEach((row, i) => {
      ['.bp-list-col--date', '.bp-list-col--views', '.bp-list-col--actions', '.bp-list-col--post'].forEach((sel) => {
        const cell = row.querySelector(sel);
        if (!cell) return;
        const extra = cell.scrollWidth - cell.clientWidth;
        if (extra > 1) {
          overflowing.push({
            vp,
            row: i,
            sel,
            extra,
            text: (cell.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 60),
          });
        }
      });
    });
    const first = document.querySelector('.bp-list-table tbody tr');
    const box = (sel) => {
      const el = first && first.querySelector(sel);
      if (!el) return null;
      const r = el.getBoundingClientRect();
      return { w: Math.round(r.width), text: (el.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 40) };
    };
    return {
      vp,
      tableW: table ? Math.round(table.getBoundingClientRect().width) : 0,
      overflow: scroll ? scroll.scrollWidth - scroll.clientWidth : 0,
      date: box('.bp-list-col--date'),
      views: box('.bp-list-col--views'),
      actions: box('.bp-list-col--actions'),
      post: box('.bp-list-col--post'),
      overflowing,
    };
  }, width);
}

(async () => {
  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: 'new',
    args: ['--no-sandbox', '--disable-gpu'],
  });
  const page = await browser.newPage();
  await login(page);
  const reports = [];
  for (const width of [1024, 768]) {
    reports.push(await measure(page, width));
  }
  await browser.close();
  const overflowing = reports.flatMap((r) => r.overflowing);
  const tableOverflow = reports.filter((r) => r.overflow > 2);
  const result = { reports, overflowing, tableOverflow };
  console.log(JSON.stringify(result));
  if (overflowing.length || tableOverflow.length) {
    process.exit(1);
  }
})().catch((err) => {
  console.error(err);
  process.exit(1);
});
