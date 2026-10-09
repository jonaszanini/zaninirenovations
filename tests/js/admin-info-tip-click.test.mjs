/**
 * Real-Chrome click tests for admin info-tips. Puppeteer + Chromium are
 * required (CI installs puppeteer@24.43.1). Fake-DOM JS clicks still run
 * first so heading tips stay covered without a browser.
 */
import { readFileSync, mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';
import assert from 'node:assert/strict';
import { createEnv, makeTip, source } from './admin-info-tip-harness.mjs';

const require = createRequire(import.meta.url);
const __dirname = path.dirname(fileURLToPath(import.meta.url));
const adminCssPath = path.resolve(__dirname, '../../public/css/admin.css');
const cpCssPath = path.resolve(__dirname, '../../public/css/admin-client-projects.css');
const adminCss = readFileSync(adminCssPath, 'utf8');
const cpCss = readFileSync(cpCssPath, 'utf8');

assert.match(adminCss, /\.admin-info-tip[\s\S]{0,80}pointer-events:\s*auto/);
assert.match(adminCss, /\.admin-label-with-tip\s*>\s*label:not\(\.admin-checkbox\)/);
assert.match(adminCss, /\.admin-label-with-tip\s*>\s*label\.admin-checkbox[\s\S]{0,80}display:\s*inline-flex/);
assert.match(adminCss, /\.admin-label-with-tip:has\(>\s*label\.admin-checkbox\)[\s\S]{0,220}text-indent:\s*-34px/);
assert.match(adminCss, /\.admin-label-with-tip:has\(>\s*label\.admin-checkbox\)[\s\S]{0,80}display:\s*block/);
assert.match(adminCss, /\.admin-checkbox__tail[\s\S]{0,80}display:\s*inline/);
assert.match(adminCss, /\.admin-label-with-tip__end\s*\{[^}]*white-space:\s*nowrap/);
assert.match(cpCss, /\.br-layout--create \.br-acc__summary \.admin-info-tip[\s\S]{0,160}pointer-events:\s*auto/);

function jsDomClick() {
  const { document, body, listeners } = createEnv();
  const layout = document.createElement('div');
  layout.className = 'br-layout--create';
  const details = document.createElement('details');
  const summary = document.createElement('summary');
  summary.className = 'br-acc__summary';
  const { wrap, btn } = makeTip(document, 'admin-info-tip-heading', 'Pieces');
  summary.appendChild(wrap);
  details.appendChild(summary);
  layout.appendChild(details);
  body.appendChild(layout);

  const click = listeners.document.click.find((l) => l.opts === true || (l.opts && l.opts.capture));
  assert.ok(click, 'capture-phase click listener');

  assert.equal(typeof btn.click, 'function', 'button has a JS DOM click()');
  btn.click();
  assert.equal(wrap.classList.contains('is-open'), true, 'JS DOM click() opened the heading tip');
  assert.equal(btn.getAttribute('aria-expanded'), 'true');
}

function requirePuppeteer() {
  let puppeteer;
  try {
    puppeteer = require('puppeteer');
  } catch (err) {
    throw new Error(
      'puppeteer is required for admin-info-tip-click tests. CI must install Chromium via `npm install --no-save puppeteer@24.43.1`. ' +
        String(err && err.message ? err.message : err)
    );
  }
  return puppeteer;
}

function fixtureHtml() {
  return `<!doctype html><html><head>
    <style>${adminCss}</style>
    <style>${cpCss}</style>
  </head><body>
    <div class="br-layout--create">
      <details class="br-acc is-active" id="mw-details">
        <summary class="br-acc__summary"><span class="br-acc__num">1</span> Pieces
          <span class="admin-info-tip-wrap" id="heading-wrap">
            <button type="button" class="admin-info-tip" id="heading-tip" aria-label="About Pieces" aria-expanded="false" aria-controls="t1"><i class="fa fa-info-circle" aria-hidden="true"></i></button>
            <span id="t1" class="admin-info-tip__bubble" role="tooltip">Add each millwork item. Extra bubble copy so the tooltip has a clickable area. <a href="/admin/materials/config" id="bubble-link">Materials config</a></span>
          </span>
        </summary>
        <div class="br-acc__body">Accordion body</div>
      </details>
    </div>
    <h4 id="pricing-heading">Pricing engine — $/sq ft by area
      <span class="admin-info-tip-wrap" id="pricing-wrap">
        <button type="button" class="admin-info-tip" id="pricing-tip" aria-label="About Pricing engine — $/sq ft by area" aria-expanded="false" aria-controls="t-price">i</button>
        <span id="t-price" class="admin-info-tip__bubble" role="tooltip">Interpolated linearly.</span>
      </span>
    </h4>
    <div class="admin-form-group">
    <span class="admin-label-with-tip" id="review-check">
      <label class="admin-checkbox" for="br-client-all">
        <input type="checkbox" class="admin-checkbox__input" id="br-client-all" checked>
        <span class="admin-checkbox__mark"></span>
        <span class="admin-checkbox__text">Client provides all materials for the entire</span>
      </label>
      <span class="admin-label-with-tip__end">
        <label for="br-client-all" class="admin-checkbox__tail">project</label>
        <span class="admin-info-tip-wrap">
          <button type="button" class="admin-info-tip" aria-label="About Client provides all materials" aria-expanded="false" aria-controls="t2">i</button>
          <span id="t2" class="admin-info-tip__bubble" role="tooltip">Labour only.</span>
        </span>
      </span>
    </span>
    </div>
    <div class="br-toggle-qty admin-form-group">
      <span class="admin-label-with-tip" id="toggle-check">
        <label class="admin-checkbox" for="br-floor-demo">
          <input type="checkbox" class="admin-checkbox__input" id="br-floor-demo" checked>
          <span class="admin-checkbox__mark"></span>
          <span class="admin-checkbox__text">Include flooring</span>
        </label>
        <span class="admin-label-with-tip__end">
          <label for="br-floor-demo" class="admin-checkbox__tail">demo</label>
          <span class="admin-info-tip-wrap">
            <button type="button" class="admin-info-tip" aria-label="About Include flooring demo" aria-expanded="false" aria-controls="t3">i</button>
            <span id="t3" class="admin-info-tip__bubble" role="tooltip">Demo the existing floor.</span>
          </span>
        </span>
      </span>
    </div>
    <div class="br-check-stack admin-form-group">
      <span class="admin-label-with-tip" id="stack-check">
        <label class="admin-checkbox" for="br-pot">
          <input type="checkbox" class="admin-checkbox__input" id="br-pot" checked>
          <span class="admin-checkbox__mark"></span>
          <span class="admin-checkbox__text"></span>
        </label>
        <span class="admin-label-with-tip__end">
          <label for="br-pot" class="admin-checkbox__tail">Subpanel</label>
          <span class="admin-info-tip-wrap">
            <button type="button" class="admin-info-tip" aria-label="About Subpanel" aria-expanded="false" aria-controls="t4">i</button>
            <span id="t4" class="admin-info-tip__bubble" role="tooltip">Count fixtures.</span>
          </span>
        </span>
      </span>
    </div>
    <span class="admin-label-with-tip" id="br-area-label">
      <label for="br-area">Total basement area (sq</label>
      <span class="admin-label-with-tip__end">
        <label for="br-area">ft)</label>
        <span class="admin-info-tip-wrap">
          <button type="button" class="admin-info-tip" aria-label="About Total basement area (sq ft)" aria-expanded="false" aria-controls="t-area">i</button>
          <span id="t-area" class="admin-info-tip__bubble" role="tooltip">Finished floor area.</span>
        </span>
      </span>
    </span>
    <span class="admin-label-with-tip" id="lights-row">
      <label for="br-lights"><span id="br-lights-label">Pot lights</span></label>
      <span class="admin-info-tip-wrap">
        <button type="button" class="admin-info-tip" aria-label="About Ceiling lights" aria-expanded="false" aria-controls="t-lights">i</button>
        <span id="t-lights" class="admin-info-tip__bubble" role="tooltip">Count pot lights.</span>
      </span>
    </span>
    <script>${source}</script>
  </body></html>`;
}

async function puppeteerClick() {
  const puppeteer = requirePuppeteer();
  const file = path.join(mkdtempSync(path.join(tmpdir(), 'info-tip-click-')), 'click.html');
  writeFileSync(file, fixtureHtml());

  const launch = { headless: 'new', args: ['--no-sandbox', '--disable-dev-shm-usage'] };
  try {
    launch.executablePath = puppeteer.executablePath();
  } catch (err) { /* default */ }

  let browser;
  try {
    browser = await puppeteer.launch(launch);
  } catch (err) {
    throw new Error('Could not launch Chromium for admin-info-tip-click tests: ' + String(err && err.message ? err.message : err));
  }
  try {
    const page = await browser.newPage();
    await page.setViewport({ width: 1280, height: 800 });
    await page.goto('file://' + file, { waitUntil: 'domcontentloaded' });

    await page.click('#heading-tip');
    const afterOpen = await page.evaluate(() => ({
      expanded: document.querySelector('#heading-tip').getAttribute('aria-expanded'),
      open: document.querySelector('#heading-wrap').classList.contains('is-open'),
      detailsOpen: document.querySelector('#mw-details').open,
      pe: getComputedStyle(document.querySelector('#heading-tip')).pointerEvents,
    }));
    assert.equal(afterOpen.expanded, 'true');
    assert.equal(afterOpen.open, true);
    assert.equal(afterOpen.pe, 'auto');
    assert.equal(afterOpen.detailsOpen, false, 'M4: clicking the heading ⓘ must not toggle the accordion');

    await page.click('#t1');
    const afterBubble = await page.evaluate(() => ({
      expanded: document.querySelector('#heading-tip').getAttribute('aria-expanded'),
      open: document.querySelector('#heading-wrap').classList.contains('is-open'),
      detailsOpen: document.querySelector('#mw-details').open,
    }));
    assert.equal(afterBubble.open, true, 'M17: a bubble click must not toggle the tip');
    assert.equal(afterBubble.expanded, 'true', 'M17: bubble click leaves the tip open');
    assert.equal(afterBubble.detailsOpen, false, 'M4: bubble click (non-link) must not toggle the accordion');

    await page.mouse.click(8, 8);
    const afterOutside = await page.evaluate(() => ({
      open: document.querySelector('#heading-wrap').classList.contains('is-open'),
      expanded: document.querySelector('#heading-tip').getAttribute('aria-expanded'),
    }));
    assert.equal(afterOutside.open, false, 'M16: outside click must close the tip');
    assert.equal(afterOutside.expanded, 'false', 'M16: outside click sets aria-expanded false');

    const marks = await page.evaluate(() => {
      return Array.from(document.querySelectorAll('.admin-checkbox__mark')).map((el) => {
        const r = el.getBoundingClientRect();
        const cs = getComputedStyle(el);
        return {
          w: r.width,
          h: r.height,
          display: cs.display,
          parentDisplay: getComputedStyle(el.parentElement).display,
        };
      });
    });
    assert.equal(marks.length, 3, 'review / toggle-qty / check-stack marks');
    marks.forEach((m, i) => {
      assert.ok(m.w >= 16, `checkbox ${i} mark width ${m.w}px must be at least 16`);
      assert.ok(m.h >= 16, `checkbox ${i} mark height ${m.h}px must be at least 16`);
      assert.ok(Math.abs(m.w - m.h) <= 2, `checkbox ${i} mark must stay square, got ${m.w}×${m.h}`);
      assert.ok(m.display.includes('inline') || m.parentDisplay.includes('inline'), `C2: checkbox ${i} stays inline (got mark ${m.display} / label ${m.parentDisplay})`);
    });

    const d1 = await page.evaluate(() => {
      const row = document.getElementById('br-area-label');
      const copy = window.AdminInfoTip.labelCopy(row);
      const after = window.AdminInfoTip.setLabelText(row, 'Bathroom / work area (sq ft)');
      return { before: copy, after, stillHasTip: !!row.querySelector('.admin-info-tip-wrap') };
    });
    assert.equal(d1.before, 'Total basement area (sq ft)');
    assert.equal(d1.after, 'Bathroom / work area (sq ft)', 'D1: bathroom preset is exact after setLabelText');
    assert.equal(d1.stillHasTip, true);

    const d2 = await page.evaluate(() => {
      window.AdminInfoTip.hoist(document);
      const span = document.getElementById('br-lights-label');
      const afterHoist = span.textContent;
      span.textContent = 'Fixtures';
      const fixtures = span.textContent;
      span.textContent = 'Pot lights';
      return {
        afterHoist,
        fixtures,
        back: span.textContent,
        glued: document.querySelector('#lights-row .admin-label-with-tip__end'),
      };
    });
    assert.equal(d2.afterHoist, 'Pot lights', 'D2: glue must not pull lights out of #br-lights-label');
    assert.equal(d2.fixtures, 'Fixtures');
    assert.equal(d2.back, 'Pot lights');
    assert.equal(d2.glued, null);

    const headingGlue = await page.evaluate(() => {
      const h = document.getElementById('pricing-heading');
      const end = h.querySelector('.admin-label-with-tip__end, .admin-heading-tip');
      return { hasEnd: !!end, last: end ? end.textContent.trim() : '' };
    });
    assert.equal(headingGlue.hasEnd, true, 'D5: heading last word is glued');
    assert.match(headingGlue.last, /area/);

    for (const width of [320, 390, 1280]) {
      await page.setViewport({ width, height: 800 });
      const aligned = await page.evaluate(() => {
        return Array.from(document.querySelectorAll('#review-check, #toggle-check, #stack-check')).map((row) => {
          const tail = row.querySelector('.admin-checkbox__tail');
          const tip = row.querySelector('.admin-info-tip');
          const tr = tail.getBoundingClientRect();
          const ir = tip.getBoundingClientRect();
          const overlap = ir.bottom > tr.top && tr.bottom > ir.top;
          const wrappedBelow = ir.top > tr.bottom + 2;
          return {
            sameLine: overlap && !wrappedBelow,
            tailTop: tr.top,
            tipTop: ir.top,
            tailBottom: tr.bottom,
            tipBottom: ir.bottom,
          };
        });
      });
      aligned.forEach((row, i) => {
        assert.ok(row.sameLine, `D4/C3: checkbox ${i} tip stays on the last word at ${width}px (tail ${row.tailTop} tip ${row.tipTop})`);
      });
    }
  } finally {
    await browser.close();
  }
  return true;
}

jsDomClick();
await puppeteerClick();
console.log('admin-info-tip-click.test.mjs: ok (headless + JS DOM click)');
