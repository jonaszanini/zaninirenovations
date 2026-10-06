/**
 * Headless dark-mode audit for authenticated admin pages.
 *
 * Usage:
 *   php artisan dark-mode:prepare
 *   php artisan serve --host=127.0.0.1 --port=8000
 *   node dark-mode-audit.js
 *
 * Options:
 *   --ci            Exit 1 when any offender remains
 *   --width=1280    Only this viewport (default: 1280 and 390)
 *   --limit=N       First N pages (debug)
 *   --json=path     Write JSON report (default storage/app/dark-mode-audit-report.json)
 *
 * Public site, login, PDFs, emails, and print/purchase lists stay light-only
 * by product rule and are not audited here.
 */
const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

const BASE_URL = process.env.DARK_MODE_AUDIT_BASE_URL || 'http://127.0.0.1:8000';
const MANIFEST = process.env.DARK_MODE_AUDIT_MANIFEST
  || path.join(__dirname, 'storage/app/dark-mode-audit-pages.json');
const DEFAULT_REPORT = path.join(__dirname, 'storage/app/dark-mode-audit-report.json');

const args = process.argv.slice(2);
const CI = args.includes('--ci');
const onlyWidth = Number((args.find(a => a.startsWith('--width=')) || '').split('=')[1]) || 0;
const limit = Number((args.find(a => a.startsWith('--limit=')) || '').split('=')[1]) || 0;
const reportPath = (args.find(a => a.startsWith('--json=')) || '').split('=')[1] || DEFAULT_REPORT;

const VIEWPORTS = onlyWidth
  ? [{ width: onlyWidth, height: onlyWidth <= 500 ? 844 : 900 }]
  : [
      { width: 1280, height: 900 },
      { width: 390, height: 844 },
    ];

const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

async function login(page, email, password) {
  await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'networkidle0', timeout: 60000 });
  await page.evaluate((e, p) => {
    const emailInput = document.querySelector('input[name="email"]');
    const passInput = document.querySelector('input[name="password"]');
    if (emailInput) emailInput.value = e;
    if (passInput) passInput.value = p;
  }, email, password);
  await Promise.all([
    page.click('button[type="submit"]'),
    page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 60000 }),
  ]);
}

async function enableDark(page) {
  await page.evaluate(() => {
    try { localStorage.setItem('zanini-admin-theme', 'dark'); } catch (e) {}
    document.documentElement.classList.add('admin-dark');
    if (document.body) document.body.classList.add('admin-dark');
  });
}

function auditInPage() {
  const SKIP_TAGS = new Set(['SCRIPT', 'STYLE', 'NOSCRIPT', 'META', 'LINK', 'HEAD', 'BR', 'HR', 'SOURCE', 'TRACK']);
  const SKIP_SEL = 'img, svg, canvas, video, iframe, picture, [hidden], .fc-event, .fc-bg-event, .fc-bg-event *, .tox, .tox-tinymce, .tox-editor-container';

  function parseColor(str) {
    if (!str || str === 'transparent' || str === 'none') return null;
    const m = str.match(/rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)(?:\s*,\s*([\d.]+))?\s*\)/i);
    if (!m) return null;
    const a = m[4] === undefined ? 1 : Number(m[4]);
    if (a <= 0.08) return null;
    return { r: Number(m[1]), g: Number(m[2]), b: Number(m[3]), a };
  }

  function luminance({ r, g, b }) {
    const lin = [r, g, b].map((v) => {
      v /= 255;
      return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * lin[0] + 0.7152 * lin[1] + 0.0722 * lin[2];
  }

  function contrast(a, b) {
    const L1 = luminance(a);
    const L2 = luminance(b);
    const light = Math.max(L1, L2);
    const dark = Math.min(L1, L2);
    return (light + 0.05) / (dark + 0.05);
  }

  function blend(fg, bg) {
    if (!fg) return bg;
    if (fg.a >= 0.99) return fg;
    const a = fg.a;
    return {
      r: fg.r * a + bg.r * (1 - a),
      g: fg.g * a + bg.g * (1 - a),
      b: fg.b * a + bg.b * (1 - a),
      a: 1,
    };
  }

  function effectiveBg(el) {
    let node = el;
    let acc = { r: 15, g: 23, b: 42, a: 1 }; // --admin-bg fallback
    const stack = [];
    while (node && node !== document.documentElement.parentNode) {
      if (node.nodeType === 1) {
        const s = getComputedStyle(node);
        const c = parseColor(s.backgroundColor);
        if (c) stack.push(c);
        if (c && c.a >= 0.99) break;
      }
      node = node.parentElement;
    }
    for (let i = stack.length - 1; i >= 0; i--) acc = blend(stack[i], acc);
    return acc;
  }

  function visible(el) {
    if (!el.getClientRects || el.getClientRects().length === 0) return false;
    const s = getComputedStyle(el);
    if (s.display === 'none' || s.visibility === 'hidden' || Number(s.opacity) === 0) return false;
    const r = el.getBoundingClientRect();
    return r.width >= 2 && r.height >= 2;
  }

  function selector(el) {
    if (el.id) return '#' + el.id;
    const cls = (typeof el.className === 'string' ? el.className : '').trim().split(/\s+/).slice(0, 3).join('.');
    const tag = el.tagName.toLowerCase();
    return cls ? tag + '.' + cls : tag;
  }

  function ownText(el) {
    let t = '';
    el.childNodes.forEach((n) => {
      if (n.nodeType === 3) t += n.textContent;
    });
    return t.replace(/\s+/g, ' ').trim();
  }

  const lightBg = [];
  const lowContrast = [];
  const invisible = [];
  const seen = new Set();

  document.querySelectorAll('body *').forEach((el) => {
    if (SKIP_TAGS.has(el.tagName)) return;
    if (el.closest(SKIP_SEL)) return;
    if (!visible(el)) return;
    const s = getComputedStyle(el);
    if (s.backgroundImage && s.backgroundImage !== 'none' && s.backgroundImage.includes('url(')) return;

    const bgParsed = parseColor(s.backgroundColor);
    if (bgParsed && bgParsed.a > 0.6) {
      const lum = luminance(blend(bgParsed, { r: 15, g: 23, b: 42, a: 1 }));
      if (lum > 0.6) {
        const key = 'bg:' + selector(el);
        if (!seen.has(key)) {
          seen.add(key);
          lightBg.push({
            kind: 'light_background',
            selector: selector(el),
            luminance: Number(lum.toFixed(3)),
            background: s.backgroundColor,
            text: ownText(el).slice(0, 80),
          });
        }
      }
    }

    const text = ownText(el);
    const isIcon = el.matches('i, .fa, [class*="fa-"]') && el.childElementCount === 0;
    if (!text && !isIcon) return;
    if (text.length < 1 && !isIcon) return;

    const fg = parseColor(s.color);
    if (!fg) return;
    const bg = effectiveBg(el);
    const ratio = contrast(fg, bg);
    const fontSize = parseFloat(s.fontSize) || 14;
    const bold = (parseInt(s.fontWeight, 10) || 400) >= 700;
    const large = fontSize >= 18 || (fontSize >= 14 && bold) || isIcon;
    const min = large ? 3 : 4.5;
    const rec = {
      selector: selector(el),
      contrast: Number(ratio.toFixed(2)),
      color: s.color,
      background: `rgb(${Math.round(bg.r)}, ${Math.round(bg.g)}, ${Math.round(bg.b)})`,
      text: (text || el.className || '').toString().slice(0, 80),
      fontSize,
    };
    if (ratio < 1.5) {
      const key = 'inv:' + rec.selector + rec.text;
      if (!seen.has(key)) {
        seen.add(key);
        invisible.push({ kind: 'invisible_text', ...rec });
      }
    } else if (ratio < min) {
      const key = 'lc:' + rec.selector + rec.text;
      if (!seen.has(key)) {
        seen.add(key);
        lowContrast.push({ kind: 'low_contrast', min, ...rec });
      }
    }
  });

  return {
    light_background: lightBg,
    low_contrast: lowContrast,
    invisible_text: invisible,
    counts: {
      light_background: lightBg.length,
      low_contrast: lowContrast.length,
      invisible_text: invisible.length,
    },
  };
}

async function activateTab(page, tab) {
  await page.evaluate((key) => {
    const btn = document.querySelector('.cp-tab[data-tab="' + key + '"]');
    if (btn) btn.click();
    const panel = document.querySelector('.cp-tab-panel[data-panel="' + key + '"]');
    if (panel) {
      document.querySelectorAll('.cp-tab-panel').forEach((p) => p.classList.remove('is-active'));
      document.querySelectorAll('.cp-tab').forEach((t) => t.classList.remove('is-active'));
      panel.classList.add('is-active');
      const tabBtn = document.querySelector('.cp-tab[data-tab="' + key + '"]');
      if (tabBtn) tabBtn.classList.add('is-active');
    }
  }, tab);
  await delay(250);
}

async function runInteractions(page, shot) {
  if (shot.tab) await activateTab(page, shot.tab);
  if (shot.openPalette) {
    await page.keyboard.down('Control');
    await page.keyboard.press('k');
    await page.keyboard.up('Control');
    await delay(400);
  }
  if (shot.openMarkPaid) {
    await page.evaluate(() => {
      const modal = document.getElementById('cp-mark-paid-modal');
      if (modal) {
        modal.hidden = false;
        document.body.classList.add('cp-modal-open');
      }
    });
    await delay(200);
  }
  if (shot.openQuotationMenu) {
    await page.evaluate(() => {
      const btn = document.querySelector('.cp-quotation-accordion__actions .admin-actions-dropdown__toggle')
        || document.querySelector('.admin-actions-dropdown__toggle');
      if (btn) {
        btn.click();
        const dd = btn.closest('.admin-actions-dropdown');
        if (dd) dd.classList.add('is-open');
      }
    });
    await delay(200);
  }
}

(async () => {
  if (!fs.existsSync(MANIFEST)) {
    console.error('Missing manifest at', MANIFEST, '\nRun: php artisan dark-mode:prepare');
    process.exit(2);
  }
  const manifest = JSON.parse(fs.readFileSync(MANIFEST, 'utf8'));
  let pages = manifest.pages || [];
  if (limit > 0) pages = pages.slice(0, limit);

  const browser = await puppeteer.launch({
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });

  const report = {
    generated_at: new Date().toISOString(),
    base_url: BASE_URL,
    pages_checked: 0,
    viewports: VIEWPORTS.map((v) => v.width),
    offenders: [],
    summary: { light_background: 0, low_contrast: 0, invisible_text: 0 },
  };

  try {
    const page = await browser.newPage();
    await login(page, manifest.admin_email, manifest.admin_password);
    await enableDark(page);

    for (const shot of pages) {
      for (const vp of VIEWPORTS) {
        const label = `${shot.name}@${vp.width}`;
        try {
          await page.setViewport({ width: vp.width, height: vp.height });
          await page.goto(`${BASE_URL}${shot.url}`, { waitUntil: 'networkidle0', timeout: 60000 });
          await enableDark(page);
          await delay(200);
          await runInteractions(page, shot);
          const result = await page.evaluate(auditInPage);
          const isAdmin = await page.evaluate(() => document.body.classList.contains('admin-body'));
          if (!isAdmin) {
            console.log(`${label}: skipped (not admin chrome — public/404/login)`);
            report.pages_checked += 1;
            continue;
          }
          report.pages_checked += 1;
          const total = result.counts.light_background + result.counts.low_contrast + result.counts.invisible_text;
          console.log(`${label}: ${total} offender(s) (bg=${result.counts.light_background} contrast=${result.counts.low_contrast} invisible=${result.counts.invisible_text})`);
          ['light_background', 'low_contrast', 'invisible_text'].forEach((k) => {
            report.summary[k] += result.counts[k];
            result[k].forEach((row) => {
              report.offenders.push({ page: label, url: shot.url, ...row });
            });
          });
        } catch (err) {
          console.error(`${label}: ERROR ${err.message}`);
          report.offenders.push({
            page: label,
            url: shot.url,
            kind: 'load_error',
            text: err.message,
          });
        }
      }
    }
  } finally {
    await browser.close();
  }

  fs.mkdirSync(path.dirname(reportPath), { recursive: true });
  fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
  const total = report.offenders.length;
  console.log('\n========== DARK MODE AUDIT ==========');
  console.log(`pages/viewport combos: ${report.pages_checked}`);
  console.log(`light backgrounds: ${report.summary.light_background}`);
  console.log(`low contrast: ${report.summary.low_contrast}`);
  console.log(`invisible text: ${report.summary.invisible_text}`);
  console.log(`load errors: ${report.offenders.filter((o) => o.kind === 'load_error').length}`);
  console.log(`TOTAL offenders: ${total}`);
  console.log(`Wrote ${reportPath}`);

  if (CI && total > 0) {
    process.exit(1);
  }
})();
