const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE = 'http://127.0.0.1:8000';
const OUT = '/opt/cursor/artifacts';
const VIEWPORTS = [1280, 390];
const STEPS = [
  'overview', 'demolition', 'framing', 'electrical', 'plumbing', 'insulation',
  'ceiling', 'drywall', 'flooring', 'doors_trim', 'painting', 'bathroom',
  'finishing', 'review',
];

async function login(page) {
  await page.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle' });
  await page.fill('input[name="email"]', 'admin@zanini.ca');
  await page.fill('input[name="password"]', 'password');
  await Promise.all([
    page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 15000 }),
    page.click('button[type="submit"]'),
  ]);
}

async function gotoStep(page, key) {
  const btn = page.locator(`.br-progress__item[data-br-goto="${key}"]`);
  if (await btn.count()) {
    await btn.click();
    await page.waitForTimeout(220);
  }
}

function auditVisible() {
  const root = document.querySelector('.br-acc.is-active') || document.querySelector('.br-acc[open]') || document.querySelector('#br-main-form');
  if (!root) return [];
  const flags = [];
  const canvas = document.createElement('canvas');
  const ctx = canvas.getContext('2d');

  function fontOf(el) {
    const cs = getComputedStyle(el);
    return `${cs.fontStyle} ${cs.fontWeight} ${cs.fontSize} ${cs.fontFamily}`;
  }

  function visible(el) {
    if (!el || el.closest('[hidden]')) return false;
    if (el.closest('.util-sr-only, .visually-hidden, .sr-only')) return false;
    const cs = getComputedStyle(el);
    if (cs.display === 'none' || cs.visibility === 'hidden') return false;
    if (cs.position === 'absolute' && (cs.clip !== 'auto' || cs.width === '1px' || parseFloat(cs.width) <= 1)) return false;
    const r = el.getBoundingClientRect();
    return r.width > 2 && r.height > 2;
  }

  function describe(el) {
    return {
      tag: el.tagName.toLowerCase(),
      id: el.id || '',
      name: el.getAttribute('name') || '',
      text: (el.tagName === 'SELECT'
        ? (el.options[el.selectedIndex] ? el.options[el.selectedIndex].text : '')
        : (el.getAttribute('placeholder') || el.textContent || '')).replace(/\s+/g, ' ').trim().slice(0, 80),
    };
  }

  root.querySelectorAll('select').forEach((el) => {
    if (!visible(el)) return;
    const opt = el.options[el.selectedIndex];
    const text = opt ? opt.text.trim() : '';
    if (!text) return;
    ctx.font = fontOf(el);
    const textW = ctx.measureText(text).width;
    const avail = el.clientWidth - 32;
    if (textW > avail + 0.5) {
      flags.push({ kind: 'select', step: null, ...describe(el), textW: Math.round(textW), avail: Math.round(avail), clientWidth: el.clientWidth });
    }
  });

  root.querySelectorAll('label, .q-ac-row__hst-label, .br-acc__summary, legend').forEach((el) => {
    if (!visible(el)) return;
    const cs = getComputedStyle(el);
    if (cs.textOverflow !== 'ellipsis' && cs.overflow !== 'hidden') {
      if (el.scrollWidth <= el.clientWidth + 1) return;
    }
    if (el.scrollWidth > el.clientWidth + 1) {
      flags.push({
        kind: 'label',
        step: null,
        ...describe(el),
        scrollWidth: el.scrollWidth,
        clientWidth: el.clientWidth,
        ellipsis: cs.textOverflow === 'ellipsis',
      });
    }
  });

  root.querySelectorAll('input[placeholder], textarea[placeholder]').forEach((el) => {
    if (!visible(el)) return;
    const ph = el.getAttribute('placeholder') || '';
    if (!ph) return;
    ctx.font = fontOf(el);
    const textW = ctx.measureText(ph).width;
    const cs = getComputedStyle(el);
    const pad = (parseFloat(cs.paddingLeft) || 0) + (parseFloat(cs.paddingRight) || 0);
    const avail = el.clientWidth - pad;
    if (textW > avail + 0.5) {
      flags.push({ kind: 'placeholder', step: null, ...describe(el), textW: Math.round(textW), avail: Math.round(avail), clientWidth: el.clientWidth });
    }
  });

  return flags;
}

async function runAudit(page, label) {
  const all = [];
  for (const width of VIEWPORTS) {
    const height = width === 390 ? 844 : 900;
    await page.setViewportSize({ width, height });
    await page.goto(`${BASE}/admin/quotations/basement/create`, { waitUntil: 'networkidle' });
    if (await page.locator('#br-area').count()) await page.locator('#br-area').fill('800');
    await page.waitForTimeout(250);
    for (const key of STEPS) {
      await gotoStep(page, key);
      const flags = await page.evaluate(auditVisible);
      flags.forEach((f) => {
        f.step = key;
        f.width = width;
        all.push(f);
      });
    }
  }
  const report = { label, count: all.length, flags: all };
  fs.writeFileSync(path.join(OUT, `bq6-audit-${label}.json`), JSON.stringify(report, null, 2));
  return report;
}

async function main() {
  fs.mkdirSync(OUT, { recursive: true });
  const label = process.argv[2] || 'pass';
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await login(page);
  const report = await runAudit(page, label);
  await browser.close();
  console.log(JSON.stringify({
    label: report.label,
    count: report.count,
    flags: report.flags.map((f) => ({
      width: f.width,
      step: f.step,
      kind: f.kind,
      id: f.id,
      name: f.name,
      text: f.text,
    })),
  }, null, 2));
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
