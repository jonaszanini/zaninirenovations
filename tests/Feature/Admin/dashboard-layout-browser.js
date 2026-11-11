'use strict';

const assert = require('assert');
const fs = require('fs');
const http = require('http');
const path = require('path');
const puppeteer = require('puppeteer');

const ROOT = path.resolve(__dirname, '../../..');
const publicDir = path.join(ROOT, 'public');
const htmlPath = process.env.DASH_HTML;
const outDir = process.env.DASH_SHOTS || path.join(ROOT, 'storage/app/qa-screenshots');
const reportPath = process.env.DASH_OUT || path.join(ROOT, 'storage/app/dashboard-layout-browser.json');
const appUrl = process.env.DASH_APP_URL || 'http://localhost';

const types = {
  '.js': 'text/javascript',
  '.css': 'text/css',
  '.html': 'text/html; charset=utf-8',
  '.woff': 'font/woff',
  '.woff2': 'font/woff2',
  '.ttf': 'font/ttf',
  '.eot': 'application/vnd.ms-fontobject',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
};

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

function startServer(html) {
  return new Promise((resolve, reject) => {
    const server = http.createServer((req, res) => {
      const urlPath = decodeURIComponent((req.url || '/').split('?')[0]);
      if (urlPath === '/' || urlPath === '/dashboard') {
        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end(html);
        return;
      }
      let file = path.join(publicDir, urlPath.replace(/^\//, ''));
      if (!file.startsWith(publicDir + path.sep)) {
        res.writeHead(403);
        res.end();
        return;
      }
      fs.readFile(file, (err, data) => {
        if (err) {
          res.writeHead(404);
          res.end('404');
          return;
        }
        const ext = path.extname(file);
        res.writeHead(200, { 'Content-Type': types[ext] || 'application/octet-stream' });
        res.end(data);
      });
    });
    server.listen(0, '127.0.0.1', () => resolve({ server, port: server.address().port }));
    server.on('error', reject);
  });
}

async function measure(page, width) {
  return page.evaluate((vp) => {
    const doc = document.documentElement;
    const body = document.body;
    const pageEl = document.querySelector('.dash-page');
    const sidebar = document.querySelector('.admin-sidebar');
    const today = document.querySelector('.dash-two-col--today');
    const cards = document.querySelector('.dash-grid--cards');
    const compact = [...document.querySelectorAll('.dash-compact-row')];
    const titles = [...document.querySelectorAll('.dash-title-icon')];
    const primary = getComputedStyle(document.documentElement).getPropertyValue('--admin-primary').trim();
    const titleColor = (() => {
      const el = document.querySelector('.dash-toolbar-title');
      return el ? getComputedStyle(el).color : '';
    })();
    const labelColor = (() => {
      const el = document.querySelector('.dash-card--stat .dash-stat-label');
      return el ? getComputedStyle(el).color : '';
    })();
    const kpiLabels = [...document.querySelectorAll('.dash-card--stat .dash-stat-label')].map((label) => {
      const icon = label.querySelector('.dash-title-icon');
      const copy = label.querySelector('.dash-stat-label__copy');
      const tipBtn = label.querySelector('.admin-info-tip');
      const labelBox = label.getBoundingClientRect();
      const iconBox = icon ? icon.getBoundingClientRect() : null;
      const copyBox = copy ? copy.getBoundingClientRect() : null;
      const tipBox = tipBtn ? tipBtn.getBoundingClientRect() : null;
      const copyStyle = copy ? getComputedStyle(copy) : null;
      const lineHeight = copyStyle ? parseFloat(copyStyle.lineHeight) || 0 : 0;
      let titleLineCount = 0;
      if (copy) {
        const range = document.createRange();
        [...copy.childNodes].forEach((node) => {
          if (node.nodeType !== Node.TEXT_NODE || !String(node.textContent || '').trim()) {
            return;
          }
          range.selectNodeContents(node);
          titleLineCount += range.getClientRects().length;
        });
      }
      return {
        text: copy ? String(copy.textContent || '').replace(/\s+/g, ' ').trim() : '',
        hasCopy: !!copy,
        hasTip: !!tipBtn,
        tipExpanded: !!(tipBtn && tipBtn.getAttribute('aria-expanded') === 'true'),
        flexWrap: getComputedStyle(label).flexWrap,
        justify: getComputedStyle(label).justifyContent,
        copyDisplay: copyStyle ? copyStyle.display : '',
        copyWhiteSpace: copyStyle ? copyStyle.whiteSpace : '',
        iconRight: iconBox ? Math.round(iconBox.right) : null,
        copyLeft: copyBox ? Math.round(copyBox.left) : null,
        copyRight: copyBox ? Math.round(copyBox.right) : null,
        copyHeight: copyBox ? Math.round(copyBox.height) : null,
        lineHeight: Math.round(lineHeight),
        titleLineCount,
        labelWidth: Math.round(labelBox.width),
        copyWidth: copyBox ? Math.round(copyBox.width) : null,
        iconCy: iconBox ? iconBox.top + iconBox.height / 2 : null,
        copyCy: copyBox ? copyBox.top + copyBox.height / 2 : null,
        tipCy: tipBox ? tipBox.top + tipBox.height / 2 : null,
        tipLeft: tipBox ? Math.round(tipBox.left) : null,
        tipTop: tipBox ? Math.round(tipBox.top) : null,
        copyTop: copyBox ? Math.round(copyBox.top) : null,
        copyBottom: copyBox ? Math.round(copyBox.bottom) : null,
        tipDropped: !!(tipBox && copyBox && tipBox.top > copyBox.bottom - 2),
        tipParkedRight: !!(tipBox && copyBox && tipBox.left - copyBox.right > 24),
      };
    });
    const firstAmt = document.querySelector('.dash-compact-row__amt');
    const firstClient = document.querySelector('.dash-compact-row__client');
    const firstTitle = document.querySelector('.dash-compact-row__title');
    const remind = document.querySelector('.dash-overdue-item__action button, .dash-overdue-item__action .admin-btn');
    const refresh = [...document.querySelectorAll('a')].find((a) => (a.textContent || '').includes('Refresh data'));
    const sidebarStyle = sidebar ? getComputedStyle(sidebar) : null;
    const sidebarBox = sidebar ? sidebar.getBoundingClientRect() : null;
    return {
      viewport: vp,
      scrollWidth: doc.scrollWidth,
      clientWidth: doc.clientWidth,
      bodyScrollWidth: body ? body.scrollWidth : 0,
      pageScrollWidth: pageEl ? pageEl.scrollWidth : 0,
      pageClientWidth: pageEl ? pageEl.clientWidth : 0,
      overflow: doc.scrollWidth > doc.clientWidth + 2,
      pageOverflow: pageEl ? pageEl.scrollWidth > pageEl.clientWidth + 2 : false,
      iconCount: titles.length,
      compactCount: compact.length,
      pillCount: document.querySelectorAll('.dash-row-pill').length,
      hasRemind: !!remind,
      hasRefresh: !!refresh,
      primary,
      titleColor,
      labelColor,
      dark: doc.classList.contains('admin-dark'),
      twoCol: today ? getComputedStyle(today).gridTemplateColumns : '',
      cardCols: cards ? getComputedStyle(cards).gridTemplateColumns : '',
      sidebarTransform: sidebarStyle ? sidebarStyle.transform : '',
      sidebarLeft: sidebarBox ? sidebarBox.left : null,
      sidebarWidth: sidebarBox ? sidebarBox.width : null,
      amtRight: firstAmt ? Math.round(firstAmt.getBoundingClientRect().right) : null,
      clientRight: firstClient ? Math.round(firstClient.getBoundingClientRect().right) : null,
      titleLeft: firstTitle ? Math.round(firstTitle.getBoundingClientRect().left) : null,
      rowText: compact.slice(0, 8).map((row) => (row.textContent || '').replace(/\s+/g, ' ').trim()),
      kpiLabels,
    };
  }, width);
}

function columnCount(template) {
  const t = String(template || '').trim();
  if (!t || t === 'none') {
    return 1;
  }
  return t.replace(/minmax\([^)]+\)/g, 'track').split(/\s+/).filter(Boolean).length;
}

function rgbOf(cssColor) {
  const m = String(cssColor || '').match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
  if (!m) {
    return null;
  }
  return [Number(m[1]), Number(m[2]), Number(m[3])];
}

function contrastOn(rgb, bg) {
  const lum = (c) => {
    const s = c / 255;
    const v = s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
    return v;
  };
  const L1 = 0.2126 * lum(rgb[0]) + 0.7152 * lum(rgb[1]) + 0.0722 * lum(rgb[2]);
  const L2 = 0.2126 * lum(bg[0]) + 0.7152 * lum(bg[1]) + 0.0722 * lum(bg[2]);
  const hi = Math.max(L1, L2);
  const lo = Math.min(L1, L2);
  return (hi + 0.05) / (lo + 0.05);
}

async function capture(page, port, width, height, dark, label, fullPage) {
  await page.setViewport({ width, height, deviceScaleFactor: 1 });
  await page.goto(`http://127.0.0.1:${port}/dashboard`, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForSelector('.dash-page', { timeout: 15000 });
  await page.evaluate((isDark) => {
    document.documentElement.classList.toggle('admin-dark', isDark);
    document.body.classList.toggle('admin-dark', isDark);
  }, dark);
  try {
    await page.evaluate(() => document.fonts && document.fonts.ready);
  } catch (e) {}
  await new Promise((r) => setTimeout(r, 250));
  const metrics = await measure(page, width);
  fs.mkdirSync(outDir, { recursive: true });
  const dest = path.join(outDir, `${label}.png`);
  await page.screenshot({ path: dest, fullPage: !!fullPage });
  metrics.shot = dest;

  const activeCard = await page.$('.dash-card--stat:nth-child(4)');
  if (activeCard) {
    const crop = path.join(outDir, label.replace('admin-dashboard-action-', 'admin-dashboard-active-card-') + '.png');
    await activeCard.screenshot({ path: crop });
    metrics.activeCardShot = crop;
  }

  const tipBtn = await page.$('.dash-card--stat .admin-info-tip');
  if (tipBtn) {
    await tipBtn.click();
    await new Promise((r) => setTimeout(r, 120));
    metrics.tipOpen = await page.$eval('.dash-card--stat .admin-info-tip', (el) => el.getAttribute('aria-expanded') === 'true');
    const wrapOpen = await page.$eval('.dash-card--stat .admin-info-tip-wrap', (el) => el.classList.contains('is-open'));
    metrics.tipWrapOpen = wrapOpen;
    await tipBtn.click();
  }
  return metrics;
}

(async () => {
  if (!htmlPath || !fs.existsSync(htmlPath)) {
    throw new Error('DASH_HTML missing: actual Blade HTML must be written by the PHPUnit test');
  }
  let html = fs.readFileSync(htmlPath, 'utf8');
  assert.ok(html.includes('dash-page'), 'captured HTML must be the Blade dashboard');
  assert.ok(html.includes('What needs action'), 'captured HTML must include What needs action');
  assert.ok(!html.includes('hand-written fixture'), 'must not use a handwritten fixture');
  html = html.split(appUrl).join('');
  html = html.replace(/https?:\/\/localhost(?::\d+)?/g, '');

  const { server, port } = await startServer(html);
  const browser = await puppeteer.launch(launchOpts());
  const page = await browser.newPage();
  page.setDefaultNavigationTimeout(25000);
  page.setDefaultTimeout(25000);

  const report = {};
  report.w1280_light = await capture(page, port, 1280, 900, false, 'admin-dashboard-action-1280-light', false);
  report.w1280_dark = await capture(page, port, 1280, 900, true, 'admin-dashboard-action-1280-dark', false);
  report.w1024_light = await capture(page, port, 1024, 900, false, 'admin-dashboard-action-1024-light', false);
  report.w1024_dark = await capture(page, port, 1024, 900, true, 'admin-dashboard-action-1024-dark', false);
  report.w390_light = await capture(page, port, 390, 844, false, 'admin-dashboard-action-390-light', true);
  report.w390_dark = await capture(page, port, 390, 844, true, 'admin-dashboard-action-390-dark', true);

  await browser.close();
  server.close();

  for (const [key, row] of Object.entries(report)) {
    assert.ok(row.iconCount >= 12, `${key} missing title icons (${row.iconCount})`);
    assert.ok(row.compactCount >= 2, `${key} missing compact rows (${row.compactCount})`);
    assert.ok(row.hasRemind, `${key} lost Remind`);
    assert.ok(row.hasRefresh, `${key} lost Refresh data`);
    assert.ok(!row.overflow, `${key} horizontal overflow scrollWidth=${row.scrollWidth} clientWidth=${row.clientWidth}`);
    assert.ok(!row.pageOverflow, `${key} dash-page overflow`);
    assert.ok(row.primary, `${key} missing --admin-primary`);
    const rgb = rgbOf(row.titleColor);
    assert.ok(rgb, `${key} title color not rgb: ${row.titleColor}`);
    if (key.includes('dark')) {
      assert.ok(row.dark, `${key} html.admin-dark missing`);
      assert.ok(contrastOn(rgb, [15, 23, 42]) >= 4.5, `${key} title contrast on dark bg too low (${row.titleColor})`);
    } else {
      assert.ok(!row.dark, `${key} unexpectedly dark`);
      assert.ok(contrastOn(rgb, [255, 255, 255]) >= 4.5, `${key} title contrast on light bg too low (${row.titleColor})`);
    }
    for (const text of row.rowText) {
      assert.ok(!text.includes('—'), `${key} compact row still has em dash: ${text}`);
      assert.ok(!text.includes(' – '), `${key} compact row still has en dash: ${text}`);
    }
    assert.ok(Array.isArray(row.kpiLabels) && row.kpiLabels.length === 4, `${key} expected 4 KPI labels`);
    row.kpiLabels.forEach((kpi, idx) => {
      assert.ok(kpi.hasCopy, `${key} KPI ${idx} missing dash-stat-label__copy`);
      assert.strictEqual(kpi.flexWrap, 'nowrap', `${key} KPI ${idx} label must not wrap flex items`);
      assert.ok(kpi.copyLeft - kpi.iconRight >= -2 && kpi.copyLeft - kpi.iconRight < 20, `${key} KPI ${idx} icon/title gap ${kpi.copyLeft - kpi.iconRight}`);
      if (kpi.hasTip) {
        assert.ok(kpi.titleLineCount >= 1 && kpi.titleLineCount <= 2, `${key} KPI ${idx} title split across ${kpi.titleLineCount} lines`);
        assert.ok(!kpi.tipDropped, `${key} KPI ${idx} info tip dropped under the title`);
        assert.ok(!kpi.tipParkedRight, `${key} KPI ${idx} info tip parked at the far right of the card`);
        assert.ok(Math.abs(kpi.tipCy - kpi.iconCy) < 16, `${key} KPI ${idx} tip not on the same row as the icon`);
        assert.ok(kpi.tipLeft - kpi.copyRight < 28, `${key} KPI ${idx} tip too far from title end`);
      }
    });
    const active = row.kpiLabels.find((k) => /active projects/i.test(k.text));
    assert.ok(active, `${key} missing Active projects card`);
    assert.ok(active.hasTip, `${key} Active projects missing info tip`);
    assert.strictEqual(active.titleLineCount, 1, `${key} Active projects title must stay on one line`);
    assert.ok(row.tipOpen, `${key} Active projects info tip did not open on click`);
  }

  const desktop = report.w1280_light;
  assert.ok(columnCount(desktop.twoCol) >= 2, '1280 today columns should sit side by side: ' + desktop.twoCol);
  assert.ok(columnCount(desktop.cardCols) >= 4, '1280 KPI cards should be four across: ' + desktop.cardCols);
  assert.ok(desktop.sidebarWidth > 200, '1280 sidebar should stay open');
  assert.ok(desktop.sidebarLeft >= -1 && desktop.sidebarLeft < 40, '1280 sidebar should be on-screen');

  const mid = report.w1024_light;
  assert.ok(columnCount(mid.twoCol) >= 2, '1024 today columns should sit side by side: ' + mid.twoCol);
  assert.ok(mid.sidebarWidth > 200, '1024 sidebar should stay open');
  assert.ok(mid.sidebarLeft >= -1 && mid.sidebarLeft < 40, '1024 sidebar should be on-screen');

  const phone = report.w390_light;
  assert.ok(columnCount(phone.twoCol) <= 1, '390 today stack: ' + phone.twoCol);
  assert.ok(columnCount(phone.cardCols) === 2, '390 KPI cards should be two across: ' + phone.cardCols);
  assert.ok(phone.sidebarLeft < 0 || (phone.sidebarTransform && phone.sidebarTransform !== 'none'), '390 sidebar should be off-canvas');

  fs.mkdirSync(path.dirname(reportPath), { recursive: true });
  fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
  console.log('dashboard-layout-browser: ok');
  console.log(JSON.stringify({
    shots: Object.fromEntries(Object.entries(report).map(([k, v]) => [k, v.shot])),
    icons: desktop.iconCount,
    compact: desktop.compactCount,
  }, null, 2));
})().catch((err) => {
  console.error(err);
  process.exit(1);
});
