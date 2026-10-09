/**
 * Real-layout geometry for /admin/blog/posts.
 * Measures overflow at 768 / 901 / 930 / 955 / 1024 with 123.5K views
 * and 4 SEO chips. Fails loudly if Chromium/puppeteer cannot launch.
 *
 * Fixture mode (default) inlines admin.css + admin-blog.css so CI does
 * not depend on PHPUnit transactions being visible to artisan serve.
 * Set GEOMETRY_LIVE=1 to also login and measure the running app.
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '../../..');
const WIDTHS = [768, 901, 930, 955, 1024];
const BASE = process.env.APP_URL || 'http://127.0.0.1:8000';
const CHROME = process.env.CHROME_PATH || process.env.PUPPETEER_EXECUTABLE_PATH || '/usr/bin/google-chrome';

function failHarness(message) {
  console.error('GEOMETRY_HARNESS_UNAVAILABLE: ' + message);
  process.exit(2);
}

function loadCss() {
  const files = ['public/css/admin.css', 'public/css/admin-blog.css'];
  return files.map((rel) => {
    const abs = path.join(ROOT, rel);
    if (!fs.existsSync(abs)) {
      throw new Error('Missing stylesheet ' + rel);
    }
    return fs.readFileSync(abs, 'utf8');
  }).join('\n');
}

function fixtureHtml(css) {
  return `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <style>${css}</style>
</head>
<body class="admin-body">
  <div class="admin-app" id="admin-app">
    <aside class="admin-sidebar" id="admin-sidebar"></aside>
    <div class="admin-main">
      <div class="admin-content">
        <div class="bp-list">
          <div class="admin-card">
            <div class="admin-table-scroll">
              <table class="admin-table admin-table--compact admin-table--stack bp-list-table">
                <thead>
                  <tr>
                    <th class="bp-list-col bp-list-col--post" scope="col">Post</th>
                    <th class="bp-list-col bp-list-col--cat" scope="col">Category</th>
                    <th class="bp-list-col bp-list-col--status" scope="col">Status</th>
                    <th class="bp-list-col bp-list-col--date" scope="col">Published</th>
                    <th class="bp-list-col bp-list-col--views" scope="col">Views</th>
                    <th class="bp-list-col bp-list-col--actions" scope="col"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td class="bp-list-col bp-list-col--post">
                      <div class="bp-list-post">
                        <span class="bp-list-thumb"></span>
                        <span class="bp-list-post__text">
                          <a class="bp-list-post__title-link" href="#">
                            <span class="bp-list-post__title">The Ultimate Decking Showdown For Ottawa Backyards And Long Titles</span>
                          </a>
                          <span class="bp-list-seo bp-list-seo--card">
                            <span class="bp-seo-badge bp-seo-badge--warn">Meta</span>
                            <span class="bp-seo-badge bp-seo-badge--warn">SEO title</span>
                            <span class="bp-seo-badge bp-seo-badge--warn">Image</span>
                            <span class="bp-seo-badge bp-seo-badge--warn">Alt</span>
                          </span>
                        </span>
                      </div>
                    </td>
                    <td class="bp-list-col bp-list-col--cat">
                      <span class="bp-list-cat">
                        <span class="bp-list-cat__name">Decks, Fences &amp; Outdoor Living</span>
                        <span class="bp-list-cat__more">+2</span>
                      </span>
                    </td>
                    <td class="bp-list-col bp-list-col--status">
                      <span class="bp-status-badge blog-post-status--published">Published</span>
                    </td>
                    <td class="bp-list-col bp-list-col--date">10/02/2026</td>
                    <td class="bp-list-col bp-list-col--views" title="123,456" aria-label="123,456 views">123.5K</td>
                    <td class="admin-table__actions bp-list-col bp-list-col--actions">
                      <div class="bp-list-actions">
                        <a class="admin-btn admin-btn--ghost admin-btn--sm bp-list-icon-btn" href="#"></a>
                        <a class="admin-btn admin-btn--ghost admin-btn--sm bp-list-icon-btn" href="#"></a>
                        <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm bp-list-icon-btn admin-actions-dropdown__toggle"></button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>`;
}

async function launchBrowser() {
  const args = ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage'];
  const puppeteerDir = path.join(ROOT, 'node_modules/puppeteer');
  const coreDir = path.join(ROOT, 'node_modules/puppeteer-core');

  if (fs.existsSync(path.join(puppeteerDir, 'package.json'))) {
    try {
      const puppeteer = require(puppeteerDir);
      return await puppeteer.launch({ headless: 'new', args });
    } catch (err) {
      console.error(err);
      failHarness('puppeteer is installed but Chromium failed to launch: ' + err.message);
    }
  }

  if (fs.existsSync(path.join(coreDir, 'package.json'))) {
    if (!fs.existsSync(CHROME)) {
      failHarness('puppeteer-core is installed but Chromium is missing at ' + CHROME);
    }
    try {
      const puppeteer = require(coreDir);
      return await puppeteer.launch({ executablePath: CHROME, headless: 'new', args });
    } catch (err) {
      console.error(err);
      failHarness('puppeteer-core failed to launch Chromium: ' + err.message);
    }
  }

  failHarness('install puppeteer (or puppeteer-core + Chromium). npm install puppeteer');
}

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

function measureInPage(vp) {
  const table = document.querySelector('.bp-list-table');
  const scroll = document.querySelector('.bp-list .admin-table-scroll');
  const overflowing = [];
  const chips = document.querySelectorAll('.bp-list-seo--card .bp-seo-badge');

  document.querySelectorAll('.bp-list-table tbody tr').forEach((row, i) => {
    ['.bp-list-col--date', '.bp-list-col--views', '.bp-list-col--actions', '.bp-list-col--post'].forEach((sel) => {
      const cell = row.querySelector(sel);
      if (!cell || getComputedStyle(cell).display === 'none') return;
      const extra = cell.scrollWidth - cell.clientWidth;
      if (extra > 1) {
        overflowing.push({
          vp, row: i, sel, extra,
          text: (cell.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 60),
        });
      }
    });

    const post = row.querySelector('.bp-list-col--post');
    const cat = row.querySelector('.bp-list-col--cat');
    if (post && getComputedStyle(post).display !== 'none') {
      const postBox = post.getBoundingClientRect();
      if (vp >= 768 && postBox.width < 140) {
        overflowing.push({ vp, row: i, sel: 'post-too-narrow', extra: 140 - postBox.width, w: postBox.width });
      }
      const catVisible = cat && getComputedStyle(cat).display !== 'none';
      const catBox = catVisible ? cat.getBoundingClientRect() : null;
      row.querySelectorAll('.bp-list-seo--card .bp-seo-badge').forEach((chip) => {
        const r = chip.getBoundingClientRect();
        if (r.right > postBox.right + 1) {
          overflowing.push({ vp, row: i, sel: 'chip-spill-post', extra: r.right - postBox.right, chip: chip.textContent.trim() });
        }
        if (catBox && r.right > catBox.left + 0.5) {
          overflowing.push({ vp, row: i, sel: 'chip-spill-cat', extra: r.right - catBox.left, chip: chip.textContent.trim() });
        }
      });
      const thumb = row.querySelector('.bp-list-thumb');
      const title = row.querySelector('.bp-list-post__title');
      if (thumb && title && getComputedStyle(thumb).display !== 'none') {
        const t = thumb.getBoundingClientRect();
        const ti = title.getBoundingClientRect();
        if (ti.left < t.right - 2 && ti.right > t.left) {
          overflowing.push({ vp, row: i, sel: 'title-under-thumb', extra: t.right - ti.left });
        }
      }
    }
  });

  const first = document.querySelector('.bp-list-table tbody tr');
  const box = (sel) => {
    const el = first && first.querySelector(sel);
    if (!el || getComputedStyle(el).display === 'none') return null;
    const r = el.getBoundingClientRect();
    return { w: Math.round(r.width), text: (el.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 40) };
  };

  return {
    vp,
    tableW: table ? Math.round(table.getBoundingClientRect().width) : 0,
    overflow: scroll ? scroll.scrollWidth - scroll.clientWidth : 0,
    chipCount: chips.length,
    date: box('.bp-list-col--date'),
    views: box('.bp-list-col--views'),
    actions: box('.bp-list-col--actions'),
    post: box('.bp-list-col--post'),
    cat: box('.bp-list-col--cat'),
    overflowing,
  };
}

async function measureFixture(page, width, html) {
  await page.setViewport({ width, height: 900, deviceScaleFactor: 1 });
  await page.setContent(html, { waitUntil: 'load' });
  await page.waitForSelector('.bp-list-table');
  return page.evaluate(measureInPage, width);
}

async function measureLive(page, width) {
  await page.setViewport({ width, height: 900, deviceScaleFactor: 1 });
  await page.goto(`${BASE}/admin/blog/posts`, { waitUntil: 'networkidle0', timeout: 60000 });
  await page.waitForSelector('.bp-list-table', { timeout: 15000 });
  return page.evaluate(measureInPage, width);
}

function assertReports(label, reports) {
  const overflowing = reports.flatMap((r) => r.overflowing);
  const tableOverflow = reports.filter((r) => r.overflow > 2);
  const missingChips = reports.filter((r) => r.chipCount < 4);
  if (overflowing.length || tableOverflow.length || missingChips.length) {
    console.error(JSON.stringify({ label, overflowing, tableOverflow, missingChips, reports }, null, 2));
    process.exit(1);
  }
}

(async () => {
  const browser = await launchBrowser();
  const page = await browser.newPage();
  const html = fixtureHtml(loadCss());
  const fixtureReports = [];
  for (const width of WIDTHS) {
    fixtureReports.push(await measureFixture(page, width, html));
  }
  console.log(JSON.stringify({ mode: 'fixture', reports: fixtureReports, overflowing: [] }));
  assertReports('fixture', fixtureReports);

  if (process.env.GEOMETRY_LIVE === '1') {
    try {
      await login(page);
    } catch (err) {
      await browser.close();
      failHarness('live app server login failed: ' + err.message);
    }
    const liveReports = [];
    for (const width of WIDTHS) {
      liveReports.push(await measureLive(page, width));
    }
    console.log(JSON.stringify({ mode: 'live', reports: liveReports, overflowing: [] }));
    assertReports('live', liveReports);
  }

  await browser.close();
})().catch((err) => {
  console.error(err);
  failHarness(err && err.message ? err.message : String(err));
});
