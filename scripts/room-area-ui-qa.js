/**
 * Room Area full-page chrome QA (Batch 0 + Batch 1).
 *
 * Checks 390 / 1024 / 1280 (light + dark where specified): no overlapping
 * chrome rects, header-to-toolbar gap ≤ 8px, Help opens the guide, Back
 * returns to the editor. Writes screenshots:
 *   ra-ui-1280-light.png, ra-ui-1024-light.png, ra-ui-390-light.png,
 *   ra-ui-390-dark.png, ra-ui-guide.png
 *   ra-b1-1280-light.png, ra-b1-1024-light.png, ra-b1-390-light.png,
 *   ra-b1-390-dark.png
 *
 * Usage:
 *   BASE_URL=http://127.0.0.1:8000 EDITOR_PATH=/admin/client-projects/1/utilities/room-area \
 *     node scripts/room-area-ui-qa.js
 */
const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8000';
const EDITOR_PATH = process.env.EDITOR_PATH || '';
const EMAIL = process.env.ADMIN_EMAIL || 'admin@zanini.ca';
const PASSWORD = process.env.ADMIN_PASSWORD || 'password';
const OUT = process.env.QA_OUT || '/opt/cursor/artifacts';
const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const shots = [
  { name: 'ra-ui-1280-light', width: 1280, height: 900, theme: 'light' },
  { name: 'ra-ui-1024-light', width: 1024, height: 900, theme: 'light' },
  { name: 'ra-ui-390-light', width: 390, height: 844, theme: 'light' },
  { name: 'ra-ui-390-dark', width: 390, height: 844, theme: 'dark' },
];

const batch1Shots = [
  { name: 'ra-b1-1280-light', width: 1280, height: 900, theme: 'light' },
  { name: 'ra-b1-1024-light', width: 1024, height: 900, theme: 'light' },
  { name: 'ra-b1-390-light', width: 390, height: 844, theme: 'light' },
  { name: 'ra-b1-390-dark', width: 390, height: 844, theme: 'dark' },
];

function visible(el, style, rect) {
  if (!el || el.hasAttribute('hidden') || el.closest('[hidden]')) return false;
  if (style.display === 'none' || style.visibility === 'hidden' || Number(style.opacity) === 0) return false;
  return rect.width > 0 && rect.height > 0;
}

async function login(page) {
  await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'networkidle0' });
  const email = await page.$('input[name="email"]');
  if (!email) throw new Error('Login form not found');
  await page.type('input[name="email"]', EMAIL);
  await page.type('input[name="password"]', PASSWORD);
  await Promise.all([
    page.click('button[type="submit"]'),
    page.waitForNavigation({ waitUntil: 'networkidle0' }),
  ]);
}

async function setTheme(page, theme) {
  await page.evaluate((mode) => {
    const root = document.documentElement;
    if (mode === 'dark') root.classList.add('admin-dark');
    else root.classList.remove('admin-dark');
    try {
      localStorage.setItem('admin-theme', mode === 'dark' ? 'dark' : 'light');
    } catch (e) {}
  }, theme);
  await delay(80);
}

async function findEditorPath(page) {
  if (EDITOR_PATH) return EDITOR_PATH;
  await page.goto(`${BASE_URL}/admin/client-projects`, { waitUntil: 'networkidle0' });
  const href = await page.evaluate(() => {
    const link = document.querySelector('a[href*="/utilities/room-area"]');
    if (link) return link.getAttribute('href');
    const first = document.querySelector('a[href*="/admin/client-projects/"]');
    return first ? first.getAttribute('href') : null;
  });
  if (href && href.includes('room-area')) return href;
  if (href) {
    await page.goto(`${BASE_URL}${href}`, { waitUntil: 'networkidle0' });
    const fromProject = await page.evaluate(() => {
      const a = document.querySelector('a[href*="/utilities/room-area"]');
      return a ? a.getAttribute('href') : null;
    });
    if (fromProject) return fromProject;
  }
  throw new Error('Could not find a Room Area editor URL. Set EDITOR_PATH.');
}

async function auditChrome(page) {
  return page.evaluate(() => {
    const issues = [];
    const editor = document.querySelector('.util-ra--fullpage');
    if (!editor) {
      return { issues: ['full-page editor root missing'], gap: null, overlap: [] };
    }

    const header = editor.querySelector('.util-ra-full-header');
    const toolbar = editor.querySelector('.util-ra-canvas-toolbar');
    const sidebar = document.querySelector('.admin-sidebar');
    const topbar = document.querySelector('.admin-topbar');
    const sidebarStyle = sidebar ? getComputedStyle(sidebar) : null;
    const topbarStyle = topbar ? getComputedStyle(topbar) : null;
    if (sidebarStyle && sidebarStyle.display !== 'none') issues.push('admin sidebar visible');
    if (topbarStyle && topbarStyle.display !== 'none') issues.push('admin topbar visible');

    function box(el) {
      const r = el.getBoundingClientRect();
      const s = getComputedStyle(el);
      return { el, r, s, vis: visibleCheck(el, s, r) };
    }
    function visibleCheck(el, s, r) {
      if (!el || el.closest('[hidden]')) return false;
      if (s.display === 'none' || s.visibility === 'hidden' || Number(s.opacity) === 0) return false;
      return r.width > 0 && r.height > 0;
    }

    const chrome = [
      header,
      toolbar,
      editor.querySelector('.util-ra-object-dock'),
      editor.querySelector('.util-ra-full-header__row--primary'),
      editor.querySelector('.util-ra-full-header__row--tools'),
    ].filter(Boolean).map(box).filter((b) => b.vis);

    const overlap = [];
    for (let i = 0; i < chrome.length; i++) {
      for (let j = i + 1; j < chrome.length; j++) {
        const a = chrome[i].r;
        const b = chrome[j].r;
        const nested = chrome[i].el.contains(chrome[j].el) || chrome[j].el.contains(chrome[i].el);
        if (nested) continue;
        const x = Math.max(0, Math.min(a.right, b.right) - Math.max(a.left, b.left));
        const y = Math.max(0, Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top));
        if (x > 1 && y > 1) {
          overlap.push({
            a: chrome[i].el.className,
            b: chrome[j].el.className,
            area: Math.round(x * y),
          });
        }
      }
    }
    if (overlap.length) issues.push('overlapping chrome: ' + JSON.stringify(overlap));

    let gap = null;
    if (header && toolbar) {
      const hr = header.getBoundingClientRect();
      const tr = toolbar.getBoundingClientRect();
      gap = Math.round((tr.top - hr.bottom) * 10) / 10;
      if (gap > 8) issues.push('header-to-toolbar gap ' + gap + 'px > 8px');
      if (gap < -1) issues.push('toolbar overlaps header by ' + Math.abs(gap) + 'px');
    }

    const help = editor.querySelector('[data-ra-help-guide]');
    if (!help) issues.push('Help / Guide link missing');
    if (!editor.querySelector('[data-ra-templates-toggle]')) issues.push('templates control missing');
    if (!editor.querySelector('[data-ra-lock-layers]')) issues.push('lock layers control missing');
    if (!editor.querySelector('[data-ra-dimensions-mode]')) issues.push('dimensions mode missing');
    if (!editor.querySelector('[data-ra-unit-system="metric"]')) issues.push('metric toggle missing');
    if (!editor.querySelector('[data-ra-snap-toggle]')) issues.push('snap toggle missing');
    if (editor.querySelector('[data-ra-coming-soon]')) issues.push('coming-soon slot still present');
    const groups = ['Draw / Edit', 'Measure / Units', 'Room tools', 'View'];
    const text = editor.innerText || '';
    groups.forEach((label) => {
      if (!text.includes(label) && !editor.innerHTML.includes(label)) {
        issues.push('missing group label ' + label);
      }
    });

    ['data-ra-zoom-out', 'data-ra-zoom-in', 'data-ra-zoom-reset', 'data-ra-side-toggle'].forEach((sel) => {
      const el = editor.querySelector('[' + sel + ']');
      const r = el ? el.getBoundingClientRect() : null;
      if (!el || !r || r.width < 36 || r.height < 36) {
        issues.push(sel + ' not a visible 44px-class control');
      }
    });
    const zoomLabel = editor.querySelector('[data-ra-zoom-label]');
    const zr = zoomLabel ? zoomLabel.getBoundingClientRect() : null;
    if (!zoomLabel || !zr || zr.width < 20 || zr.height < 20) {
      issues.push('zoom percent label not visible');
    }

    const drawLabel = Array.from(editor.querySelectorAll('.util-ra-canvas-toolbar .util-ra-tool-group__label'))
      .find((el) => (el.textContent || '').indexOf('Draw') !== -1);
    if (drawLabel) {
      const lr = drawLabel.getBoundingClientRect();
      if (lr.width < 8 || lr.height < 8) {
        issues.push('Draw / Edit group label clipped');
      }
    }

    return { issues, gap, overlap };
  });
}

async function main() {
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--window-size=1280,900'],
  });
  const page = await browser.newPage();
  page.setDefaultTimeout(30000);
  await login(page);

  const editorPath = await findEditorPath(page);
  const editorUrl = editorPath.startsWith('http') ? editorPath : `${BASE_URL}${editorPath}`;
  const report = { editorUrl, shots: [], batch1: [], help: null };

  for (const shot of shots) {
    await page.setViewport({ width: shot.width, height: shot.height, deviceScaleFactor: shot.width <= 400 ? 2 : 1 });
    await page.goto(editorUrl, { waitUntil: 'networkidle0' });
    await page.waitForSelector('.util-ra--fullpage', { timeout: 15000 });
    await setTheme(page, shot.theme);
    await page.evaluate(() => {
      try { localStorage.removeItem('zr-room-area-guide-tip-v1'); } catch (e) {}
      const tip = document.querySelector('[data-ra-first-tip]');
      if (tip) tip.hidden = true;
    });
    await delay(250);
    const audit = await auditChrome(page);
    const file = path.join(OUT, `${shot.name}.png`);
    await page.screenshot({ path: file, fullPage: false });
    report.shots.push({ ...shot, file, audit });
    if (audit.issues.length) {
      console.error('QA fail', shot.name, audit);
    } else {
      console.log('QA ok', shot.name, 'gap', audit.gap);
    }
  }

  await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 2 });
  await page.goto(editorUrl, { waitUntil: 'networkidle0' });
  await page.waitForSelector('[data-ra-help-guide]');
  await Promise.all([
    page.click('[data-ra-help-guide]'),
    page.waitForNavigation({ waitUntil: 'networkidle0' }),
  ]);
  const onGuide = await page.evaluate(() => !!document.querySelector('[data-ra-guide]'));
  if (!onGuide) throw new Error('Help did not open the guide');
  const guideFile = path.join(OUT, 'ra-ui-guide.png');
  await page.screenshot({ path: guideFile, fullPage: true });
  await Promise.all([
    page.click('[data-ra-guide-back]'),
    page.waitForNavigation({ waitUntil: 'networkidle0' }),
  ]);
  const backOnEditor = await page.evaluate(() => !!document.querySelector('.util-ra--fullpage'));
  if (!backOnEditor) throw new Error('Back from guide did not return to the editor');
  report.help = { openedGuide: true, returnedToEditor: true, file: guideFile };

  for (const shot of batch1Shots) {
    await page.setViewport({ width: shot.width, height: shot.height, deviceScaleFactor: shot.width <= 400 ? 2 : 1 });
    await page.goto(editorUrl, { waitUntil: 'networkidle0' });
    await page.waitForSelector('.util-ra--fullpage', { timeout: 15000 });
    await setTheme(page, shot.theme);
    await page.evaluate(() => {
      try { localStorage.removeItem('zr-room-area-guide-tip-v1'); } catch (e) {}
      const tip = document.querySelector('[data-ra-first-tip]');
      if (tip) tip.hidden = true;
    });
    await delay(200);
    await page.evaluate(() => {
      const w = document.querySelector('[data-ra-type-rect-w]');
      const l = document.querySelector('[data-ra-type-rect-l]');
      const add = document.querySelector('[data-ra-type-rect]');
      if (w) w.value = '12';
      if (l) l.value = '10';
      if (add) add.click();
    });
    await delay(400);
    await page.evaluate(() => {
      const lock = document.querySelector('[data-ra-lock-layers]');
      if (lock && !lock.checked) {
        lock.checked = true;
        lock.dispatchEvent(new Event('change', { bubbles: true }));
      }
      const dim = document.querySelector('[data-ra-dimensions-mode]');
      if (dim) {
        dim.value = 'all';
        dim.dispatchEvent(new Event('change', { bubbles: true }));
      }
      const metric = document.querySelector('[data-ra-unit-system="imperial"]');
      if (metric) metric.click();
      const tpl = document.querySelector('[data-ra-templates-toggle]');
      if (tpl) tpl.click();
    });
    await delay(250);
    const audit = await page.evaluate(() => {
      const issues = [];
      const editor = document.querySelector('.util-ra--fullpage');
      if (!editor) return { issues: ['full-page editor root missing'] };
      const undo = editor.querySelector('[data-ra-undo-vertex]');
      if (!undo || undo.disabled) issues.push('undo not enabled after canvas edit');
      const tpl = editor.querySelector('[data-ra-templates-popover]');
      if (!tpl || tpl.hasAttribute('hidden')) issues.push('templates menu not open');
      const dim = editor.querySelector('[data-ra-dimensions-mode]');
      if (!dim || dim.value !== 'all') issues.push('dimension toggle not on All');
      const lock = editor.querySelector('[data-ra-lock-layers]');
      if (!lock || !lock.checked) issues.push('lock layers not on');
      const ft = editor.querySelector('[data-ra-unit-system="imperial"]');
      if (!ft || !ft.classList.contains('is-active')) issues.push('imperial units toggle not active');
      const labels = editor.querySelectorAll('.util-ra-tool-group__label');
      const text = Array.from(labels).map((el) => el.textContent || '').join(' ');
      if (text.indexOf('Room tools') === -1) issues.push('Room tools group missing');
      return { issues };
    });
    const file = path.join(OUT, `${shot.name}.png`);
    await page.screenshot({ path: file, fullPage: false });
    report.batch1.push({ ...shot, file, audit });
    if (audit.issues.length) console.error('QA fail', shot.name, audit);
    else console.log('QA ok', shot.name);
  }

  const failed = report.shots.filter((s) => s.audit.issues.length)
    .concat(report.batch1.filter((s) => s.audit.issues.length));
  fs.writeFileSync(path.join(OUT, 'ra-ui-qa-report.json'), JSON.stringify(report, null, 2));
  await browser.close();
  if (failed.length) {
    console.error('Room Area UI QA failed on', failed.map((s) => s.name).join(', '));
    process.exit(1);
  }
  console.log('Room Area UI QA passed. Screenshots in', OUT);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
