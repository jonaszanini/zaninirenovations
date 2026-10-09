/**
 * Real-layout geometry for /admin/blog/posts.
 * Measures overflow, 44px action hits, and Post/Category balance.
 * Fails loudly if Chromium/puppeteer cannot launch.
 *
 * Fixture mode (default) inlines admin CSS so CI does not depend on
 * PHPUnit transactions being visible to artisan serve.
 * Set GEOMETRY_LIVE=1 to also login and measure the running app.
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '../../..');
const WIDTHS = [768, 901, 930, 955, 1001, 1024, 1050, 1100];
const BASE = process.env.APP_URL || 'http://127.0.0.1:8000';
const CHROME = process.env.CHROME_PATH || process.env.PUPPETEER_EXECUTABLE_PATH || '/usr/bin/google-chrome';

function failHarness(message) {
  console.error('GEOMETRY_HARNESS_UNAVAILABLE: ' + message);
  process.exit(2);
}

function failKeyboard(message) {
  console.error('KEYBOARD_HARNESS_FAILED: ' + message);
  process.exit(1);
}

function loadCss() {
  const files = ['public/css/admin.css', 'public/css/admin-blog.css', 'public/css/admin-dark.css'];
  return files.map((rel) => {
    const abs = path.join(ROOT, rel);
    if (!fs.existsSync(abs)) {
      throw new Error('Missing stylesheet ' + rel);
    }
    return fs.readFileSync(abs, 'utf8');
  }).join('\n');
}

function loadDropdownJs() {
  const abs = path.join(ROOT, 'public/js/admin-actions-dropdown.js');
  if (!fs.existsSync(abs)) {
    throw new Error('Missing admin-actions-dropdown.js');
  }
  return fs.readFileSync(abs, 'utf8');
}

function fixtureRow(opts) {
  const title = opts.title;
  const chips = opts.chips.map((c) => `<span class="bp-seo-badge bp-seo-badge--warn">${c}</span>`).join('');
  return `
                  <tr>
                    <td class="bp-list-col bp-list-col--post">
                      <div class="bp-list-post">
                        <span class="bp-list-thumb"></span>
                        <span class="bp-list-post__text">
                          <a class="bp-list-post__title-link" href="#">
                            <span class="bp-list-post__title">${title}</span>
                          </a>
                          <span class="bp-list-seo bp-list-seo--card">${chips}</span>
                          <span class="bp-list-post__meta">
                            <span class="bp-status-badge blog-post-status--published">Published</span>
                            <span class="bp-list-post__meta-cats">
                              <span class="bp-list-cat">
                                <span class="bp-list-cat__name">Decks, Fences &amp; Outdoor Living</span>
                                <span class="bp-list-cat__more">+2</span>
                              </span>
                            </span>
                            <span>10/02/2026</span>
                            <span>123.5K views</span>
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
                    <td class="bp-list-col bp-list-col--views" title="123,456">
                      <span aria-hidden="true">123.5K</span>
                      <span class="sr-only">123,456 views</span>
                    </td>
                    <td class="admin-table__actions bp-list-col bp-list-col--actions">
                      <div class="bp-list-actions">
                        <a class="admin-btn admin-btn--ghost admin-btn--sm bp-list-icon-btn" href="#"></a>
                        <a class="admin-btn admin-btn--ghost admin-btn--sm bp-list-icon-btn" href="#"></a>
                        <div class="admin-actions-dropdown">
                          <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm bp-list-icon-btn admin-actions-dropdown__toggle" aria-label="More actions" aria-expanded="false" aria-haspopup="menu"></button>
                          <div class="admin-actions-dropdown__menu" role="menu">
                            <button type="button" class="admin-actions-dropdown__item" role="menuitem" data-k="dup">Duplicate</button>
                            <button type="button" class="admin-actions-dropdown__item" role="menuitem" data-k="edit">Edit</button>
                            <button type="button" class="admin-actions-dropdown__item admin-actions-dropdown__item--danger" role="menuitem" data-k="del">Delete</button>
                          </div>
                        </div>
                      </div>
                    </td>
                  </tr>`;
}

function fixtureHtml(css, dark, js) {
  const rows = [
    fixtureRow({
      title: 'The Ultimate Decking Showdown For Ottawa Backyards And Long Titles',
      chips: ['Meta', 'SEO title', 'Image', 'Alt'],
    }),
    fixtureRow({
      title: 'Pressure-Treated Cedar Composite And PVC Decking Compared For Ontario Homes This Season',
      chips: ['Meta', 'SEO title', 'Image', 'Alt', 'missing image'],
    }),
  ].join('\n');

  return `<!doctype html>
<html lang="en"${dark ? ' class="admin-dark"' : ''}>
<head>
  <meta charset="utf-8">
  <style>${css}</style>
</head>
<body class="admin-body${dark ? ' admin-dark' : ''}">
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
                <tbody>${rows}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  ${js ? `<script>${js}</script>` : ''}
</body>
</html>`;
}

function keyboardFixtureHtml(css, js) {
  const row = fixtureRow({
    title: 'Keyboard harness post',
    chips: ['Meta'],
  });
  return `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <style>${css}</style>
</head>
<body class="admin-body">
  <input id="bp-harness-outside" type="search" class="admin-input" placeholder="Search posts">
  <div class="bp-list">
    <div class="admin-card">
      <div class="admin-table-scroll">
        <table class="admin-table admin-table--compact bp-list-table">
          <thead>
            <tr>
              <th class="bp-list-col bp-list-col--post" scope="col">Post</th>
              <th class="bp-list-col bp-list-col--actions" scope="col"></th>
            </tr>
          </thead>
          <tbody>${row}
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <input id="bp-harness-next" type="search" class="admin-input" placeholder="Next control">
  ${js ? `<script>${js}</script>` : ''}
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
  function hitSize(el) {
    if (!el) return { w: 0, h: 0, after: false };
    const r = el.getBoundingClientRect();
    const after = getComputedStyle(el, '::after');
    const hasAfter = after && after.content && after.content !== 'none' && after.content !== '""' && after.content !== "''";
    if (!hasAfter && after.content !== '""') {
      const named = after.content && after.content !== 'none';
      if (!named) {
        return { w: r.width, h: r.height, after: false };
      }
    }
    if (!after || after.content === 'none') {
      return { w: r.width, h: r.height, after: false };
    }
    const cs = getComputedStyle(el);
    const bl = parseFloat(cs.borderLeftWidth) || 0;
    const br = parseFloat(cs.borderRightWidth) || 0;
    const bt = parseFloat(cs.borderTopWidth) || 0;
    const bb = parseFloat(cs.borderBottomWidth) || 0;
    const padW = r.width - bl - br;
    const padH = r.height - bt - bb;
    const top = parseFloat(after.top);
    const bottom = parseFloat(after.bottom);
    const left = parseFloat(after.left);
    const right = parseFloat(after.right);
    if ([top, bottom, left, right].some((n) => Number.isNaN(n))) {
      return { w: r.width, h: r.height, after: false };
    }
    return { w: padW - left - right, h: padH - top - bottom, after: true };
  }

  const table = document.querySelector('.bp-list-table');
  const scroll = document.querySelector('.bp-list .admin-table-scroll');
  const overflowing = [];
  const chips = document.querySelectorAll('.bp-list-seo--card .bp-seo-badge');

  document.querySelectorAll('.bp-list-table tbody tr').forEach((row, i) => {
    ['.bp-list-col--date', '.bp-list-col--views', '.bp-list-col--actions', '.bp-list-col--post'].forEach((sel) => {
      const cell = row.querySelector(sel);
      if (!cell || getComputedStyle(cell).display === 'none') return;
      const extra = cell.scrollWidth - cell.clientWidth;
      const limit = sel === '.bp-list-col--actions' ? 4 : 1;
      if (extra > limit) {
        overflowing.push({
          vp, row: i, sel, extra,
          text: (cell.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 60),
        });
      }
      if (sel === '.bp-list-col--date' || sel === '.bp-list-col--views') {
        const fs = parseFloat(getComputedStyle(cell).fontSize);
        if (!Number.isNaN(fs) && fs > 13) {
          overflowing.push({
            vp, row: i, sel: sel.replace('.', '') + '-font', extra: fs - 12, fontSize: fs,
          });
        }
      }
    });

    const titleEl = row.querySelector('.bp-list-post__title');
    if (titleEl && getComputedStyle(titleEl).display !== 'none') {
      const titleFs = parseFloat(getComputedStyle(titleEl).fontSize);
      if (!Number.isNaN(titleFs) && titleFs > 14) {
        overflowing.push({
          vp, row: i, sel: 'title-font', extra: titleFs - 13, fontSize: titleFs,
        });
      }
    }

    const actions = row.querySelector('.bp-list-actions');
    if (actions && vp >= 768) {
      const gap = parseFloat(getComputedStyle(actions).columnGap || getComputedStyle(actions).gap);
      if (!Number.isNaN(gap) && gap < 11.5) {
        overflowing.push({ vp, row: i, sel: 'action-gap', extra: 12 - gap, gap });
      }
      const btns = [...actions.querySelectorAll('.bp-list-icon-btn, .admin-actions-dropdown__toggle')];
      const hits = btns.map((el) => {
        const size = hitSize(el);
        const r = el.getBoundingClientRect();
        return { el, size, r };
      });
      hits.forEach((h, idx) => {
        if (h.size.w < 44 - 0.5 || h.size.h < 44 - 0.5) {
          overflowing.push({
            vp, row: i, sel: 'hit-too-small', extra: 44 - Math.min(h.size.w, h.size.h),
            w: h.size.w, h: h.size.h, after: h.size.after, btn: idx,
          });
        }
        const after = getComputedStyle(h.el, '::after');
        const pe = (after.pointerEvents || '').toLowerCase();
        const pos = (after.position || '').toLowerCase();
        if (pe === 'none' || pos === 'static' || pos === '' || pos === 'relative') {
          overflowing.push({
            vp, row: i, sel: 'hit-after-inert', extra: 1, pointerEvents: pe, position: pos, btn: idx,
          });
        }
        const cx = h.r.left + h.r.width / 2;
        const cy = h.r.top + h.r.height / 2;
        const inset = 2;
        const half = 22;
        const corners = [
          [cx - half + inset, cy - half + inset],
          [cx + half - inset, cy - half + inset],
          [cx - half + inset, cy + half - inset],
          [cx + half - inset, cy + half - inset],
        ];
        corners.forEach(([x, y], cidx) => {
          const topEl = document.elementFromPoint(x, y);
          if (!topEl || (topEl !== h.el && !h.el.contains(topEl))) {
            overflowing.push({
              vp, row: i, sel: 'hit-miss-corner', extra: 1, btn: idx, corner: cidx,
              x, y, got: topEl ? (topEl.tagName + '.' + (topEl.className || '').toString().slice(0, 40)) : 'null',
            });
          }
        });
      });
      for (let a = 0; a < hits.length - 1; a += 1) {
        const left = hits[a].r.left + hits[a].r.width / 2 - hits[a].size.w / 2;
        const right = left + hits[a].size.w;
        const nextLeft = hits[a + 1].r.left + hits[a + 1].r.width / 2 - hits[a + 1].size.w / 2;
        if (right > nextLeft + 0.5) {
          overflowing.push({ vp, row: i, sel: 'hit-overlap', extra: right - nextLeft });
        }
      }
    }

    const post = row.querySelector('.bp-list-col--post');
    const cat = row.querySelector('.bp-list-col--cat');
    if (post && getComputedStyle(post).display !== 'none') {
      const postBox = post.getBoundingClientRect();
      if (vp >= 768 && postBox.width < 140) {
        overflowing.push({ vp, row: i, sel: 'post-too-narrow', extra: 140 - postBox.width, w: postBox.width });
      }
      const catVisible = cat && getComputedStyle(cat).display !== 'none';
      const catBox = catVisible ? cat.getBoundingClientRect() : null;
      if (vp >= 1001 && vp <= 1100 && catBox && postBox.width <= catBox.width + 1) {
        overflowing.push({
          vp, row: i, sel: 'post-not-widest', extra: catBox.width - postBox.width + 1,
          post: postBox.width, cat: catBox.width,
        });
      }
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
      if (vp >= 768 && vp <= 1000 && !catVisible) {
        const metaCats = row.querySelector('.bp-list-post__meta-cats');
        const meta = row.querySelector('.bp-list-post__meta');
        const shown = meta && metaCats
          && getComputedStyle(meta).display !== 'none'
          && metaCats.getBoundingClientRect().width > 4;
        if (!shown) {
          overflowing.push({ vp, row: i, sel: 'cat-missing-in-post', extra: 1 });
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
    dark: document.documentElement.classList.contains('admin-dark'),
    rows: document.querySelectorAll('.bp-list-table tbody tr').length,
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

async function measureLive(page, width, dark) {
  await page.setViewport({ width, height: 900, deviceScaleFactor: 1 });
  await page.goto(`${BASE}/admin/blog/posts`, { waitUntil: 'networkidle0', timeout: 60000 });
  await page.waitForSelector('.bp-list-table', { timeout: 15000 });
  await page.evaluate((mode) => {
    if (mode) {
      document.documentElement.classList.add('admin-dark');
      document.body.classList.add('admin-dark');
    } else {
      document.documentElement.classList.remove('admin-dark');
      document.body.classList.remove('admin-dark');
    }
  }, dark);
  return page.evaluate(measureInPage, width);
}

async function waitForFocusInMenu(page) {
  await page.waitForFunction(() => {
    const open = document.querySelector('.admin-actions-dropdown.is-open');
    const menu = open && (
      open._portaledMenu
      || document.querySelector('.admin-actions-dropdown__menu.is-portaled')
      || open.querySelector('.admin-actions-dropdown__menu')
    );
    return !!(open && menu && menu.contains(document.activeElement)
      && document.activeElement.getAttribute('role') === 'menuitem');
  });
}

async function testMenuKeyboard(page, html) {
  await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
  await page.setContent(html, { waitUntil: 'load' });
  await page.waitForSelector('.admin-actions-dropdown__toggle');
  const toggle = await page.$('.admin-actions-dropdown__toggle');

  async function openAndFocusMenu() {
    await toggle.click();
    await page.waitForFunction(() => document.querySelector('.admin-actions-dropdown.is-open'));
    await waitForFocusInMenu(page);
  }

  try {
    await openAndFocusMenu();

    const activeKey = () => page.evaluate(() => document.activeElement && document.activeElement.getAttribute('data-k'));

    await page.keyboard.press('ArrowDown');
    if (await activeKey() !== 'edit') {
      throw new Error('ArrowDown did not move to the second menuitem');
    }
    await page.keyboard.press('ArrowDown');
    if (await activeKey() !== 'del') {
      throw new Error('ArrowDown did not move to the third menuitem');
    }
    await page.keyboard.press('ArrowDown');
    if (await activeKey() !== 'dup') {
      throw new Error('ArrowDown did not wrap to the first menuitem');
    }
    await page.keyboard.press('ArrowUp');
    if (await activeKey() !== 'del') {
      throw new Error('ArrowUp did not wrap to the last menuitem');
    }
    await page.keyboard.press('Home');
    if (await activeKey() !== 'dup') {
      throw new Error('Home did not move to the first menuitem');
    }
    await page.keyboard.press('End');
    if (await activeKey() !== 'del') {
      throw new Error('End did not move to the last menuitem');
    }

    const prevented = await page.evaluate(() => {
      const ev = new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true, cancelable: true });
      document.dispatchEvent(ev);
      return ev.defaultPrevented;
    });
    if (!prevented) {
      throw new Error('ArrowDown did not call preventDefault');
    }

    await page.keyboard.press('Escape');
    const afterEsc = await page.evaluate(() => ({
      open: !!document.querySelector('.admin-actions-dropdown.is-open'),
      isToggle: !!(document.activeElement && document.activeElement.classList.contains('admin-actions-dropdown__toggle')),
    }));
    if (afterEsc.open || !afterEsc.isToggle) {
      throw new Error('Escape did not close the menu and return focus to the toggle');
    }

    await openAndFocusMenu();
    await page.keyboard.press('Tab');
    const afterTab = await page.evaluate(() => ({
      open: !!document.querySelector('.admin-actions-dropdown.is-open'),
      id: document.activeElement && document.activeElement.id,
      isToggle: !!(document.activeElement && document.activeElement.classList.contains('admin-actions-dropdown__toggle')),
      role: document.activeElement && document.activeElement.getAttribute('role'),
    }));
    if (afterTab.open) {
      throw new Error('Tab did not close the menu');
    }
    if (afterTab.isToggle) {
      throw new Error('Tab left focus on the toggle instead of moving to the next control');
    }
    if (afterTab.role === 'menuitem') {
      throw new Error('Tab left focus inside the menu');
    }
    if (afterTab.id !== 'bp-harness-next') {
      throw new Error('Tab did not move to the next control after the toggle (got ' + afterTab.id + ')');
    }

    await openAndFocusMenu();
    await page.click('#bp-harness-outside');
    const afterOutside = await page.evaluate(() => ({
      open: !!document.querySelector('.admin-actions-dropdown.is-open'),
      id: document.activeElement && document.activeElement.id,
      isToggle: !!(document.activeElement && document.activeElement.classList.contains('admin-actions-dropdown__toggle')),
    }));
    if (afterOutside.open) {
      throw new Error('Outside click did not close the menu');
    }
    if (afterOutside.isToggle) {
      throw new Error('Outside click stole focus back to the toggle');
    }
    if (afterOutside.id !== 'bp-harness-outside') {
      throw new Error('Outside click did not leave focus on the clicked input (got ' + afterOutside.id + ')');
    }

    return { mode: 'keyboard', overflowing: [], ok: true };
  } catch (err) {
    failKeyboard(err && err.message ? err.message : String(err));
  }
}

function assertReports(label, reports) {
  const overflowing = reports.flatMap((r) => r.overflowing);
  const tableOverflow = reports.filter((r) => r.overflow > 2);
  const missingChips = reports.filter((r) => r.chipCount < 4);
  const missingRows = reports.filter((r) => r.rows < 2);
  if (overflowing.length || tableOverflow.length || missingChips.length || missingRows.length) {
    console.error(JSON.stringify({ label, overflowing, tableOverflow, missingChips, missingRows, reports }, null, 2));
    process.exit(1);
  }
}

(async () => {
  const browser = await launchBrowser();
  const page = await browser.newPage();
  const css = loadCss();
  const dropdownJs = loadDropdownJs();
  const fixtureReports = [];
  for (const dark of [false, true]) {
    const html = fixtureHtml(css, dark, dropdownJs);
    for (const width of WIDTHS) {
      fixtureReports.push(await measureFixture(page, width, html));
    }
  }
  console.log(JSON.stringify({
    mode: 'fixture',
    reports: fixtureReports,
    overflowing: fixtureReports.flatMap((r) => r.overflowing),
  }));
  assertReports('fixture', fixtureReports);

  const keyboard = await testMenuKeyboard(page, keyboardFixtureHtml(css, dropdownJs));
  console.log(JSON.stringify(keyboard));

  if (process.env.GEOMETRY_LIVE === '1') {
    try {
      await login(page);
    } catch (err) {
      await browser.close();
      failHarness('live app server login failed: ' + err.message);
    }
    const liveReports = [];
    for (const dark of [false, true]) {
      for (const width of WIDTHS) {
        liveReports.push(await measureLive(page, width, dark));
      }
    }
    console.log(JSON.stringify({
      mode: 'live',
      reports: liveReports,
      overflowing: liveReports.flatMap((r) => r.overflowing),
    }));
    assertReports('live', liveReports);
  }

  await browser.close();
})().catch((err) => {
  console.error(err);
  failHarness(err && err.message ? err.message : String(err));
});
