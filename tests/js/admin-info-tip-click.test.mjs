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
import { execSync } from 'node:child_process';
import assert from 'node:assert/strict';
import { createEnv, makeTip, source } from './admin-info-tip-harness.mjs';

const require = createRequire(import.meta.url);
const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, '../..');
const adminCssPath = path.resolve(__dirname, '../../public/css/admin.css');
const cpCssPath = path.resolve(__dirname, '../../public/css/admin-client-projects.css');
const darkCssPath = path.resolve(__dirname, '../../public/css/admin-dark.css');
const adminCss = readFileSync(adminCssPath, 'utf8');
const cpCss = readFileSync(cpCssPath, 'utf8');
const darkCss = readFileSync(darkCssPath, 'utf8');

assert.match(adminCss, /\.admin-info-tip[\s\S]{0,80}pointer-events:\s*auto/);
assert.match(adminCss, /\.admin-label-with-tip\s*>\s*label:not\(\.admin-checkbox\)/);
assert.match(adminCss, /\.admin-label-with-tip\s*>\s*label\.admin-checkbox[\s\S]{0,80}display:\s*inline-flex/);
assert.match(adminCss, /\.admin-label-with-tip:has\(>\s*label\.admin-checkbox\)[\s\S]{0,220}text-indent:\s*-34px/);
assert.match(adminCss, /\.admin-label-with-tip:has\(>\s*label\.admin-checkbox\)[\s\S]{0,220}display:\s*block/);
assert.match(adminCss, /\.admin-form-group\s+label\.admin-checkbox__tail[\s\S]{0,500}text-transform:\s*inherit/);
assert.match(adminCss, /\.admin-form-group\s+label\.admin-checkbox__tail[\s\S]{0,500}font-weight:\s*inherit/);
assert.match(adminCss, /\.admin-form-group\s+label\.admin-checkbox__tail[\s\S]{0,500}color:\s*inherit/);
assert.match(adminCss, /\.admin-checkbox-row/);
assert.match(adminCss, /\.admin-label-with-tip__end\s*\{[^}]*white-space:\s*nowrap/);
assert.match(cpCss, /\.br-layout--create \.br-acc__summary \.admin-info-tip[\s\S]{0,160}pointer-events:\s*auto/);
assert.match(source, /function glueLastTextNode/);
assert.doesNotMatch(source, /function moveTrailingSkipped/);
assert.doesNotMatch(source, /function wrapHeadingCopy/);

function renderPieceCard() {
  const php = `<?php
    require __DIR__ . '/vendor/autoload.php';
    $app = require __DIR__ . '/bootstrap/app.php';
    $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
    echo view('admin.quotations.millwork._piece_card', [
      'pi' => '__P__',
      'piece' => App\\Services\\MillworkQuotationCalculator::defaultPiece('shelf'),
    ])->render();
  `;
  return execSync('php', { input: php, cwd: repoRoot, encoding: 'utf8', maxBuffer: 20 * 1024 * 1024 });
}

const pieceCardHtml = renderPieceCard();
assert.match(pieceCardHtml, /pieces-__P__-edge_banding/, 'real millwork template must expose rewriteable checkbox ids');
assert.match(pieceCardHtml, /admin-info-tip__bubble/, 'real millwork template includes tip bubbles');

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
    <style>${darkCss}</style>
  </head><body class="admin-body">
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
    <h5 class="sq-intro-option-title" id="pdf-line-heading">Client PDF line items
      <span class="admin-info-tip-wrap" id="pdf-line-wrap">
        <button type="button" class="admin-info-tip" aria-label="About Client PDF line items" aria-expanded="false" aria-controls="t-pdf">i</button>
        <span id="t-pdf" class="admin-info-tip__bubble" role="tooltip">Controls how material and labour appear.</span>
      </span>
    </h5>
    <summary class="br-acc__summary" id="review-summary"><span class="br-acc__num">8 of 8</span> Review, materials &amp; live edits
      <span class="admin-info-tip-wrap" id="review-summary-wrap">
        <button type="button" class="admin-info-tip" aria-label="About Review, materials & live edits" aria-expanded="false" aria-controls="t-rev">i</button>
        <span id="t-rev" class="admin-info-tip__bubble" role="tooltip">Edit hours below.</span>
      </span>
    </summary>
    <legend id="reply-legend">Ask client to reply I accept <span class="cp-required">*</span>
      <span class="admin-info-tip-wrap">
        <button type="button" class="admin-info-tip" aria-label="About Ask client to reply I accept" aria-expanded="false" aria-controls="t-leg">i</button>
        <span id="t-leg" class="admin-info-tip__bubble" role="tooltip">Required.</span>
      </span>
    </legend>
    <div class="admin-form-group">
      <span class="admin-label-with-tip" id="store-name-row">
        <label for="mat-name">Store name</label>
        <span class="cp-required">*</span>
        <span class="admin-info-tip-wrap" id="store-name-wrap">
          <button type="button" class="admin-info-tip" aria-label="About Store name" aria-expanded="false" aria-controls="t-store">i</button>
          <span id="t-store" class="admin-info-tip__bubble" role="tooltip">Full product title from the store.</span>
        </span>
      </span>
    </div>
    <details class="admin-accordion admin-card" id="email-acc">
      <summary class="admin-accordion__summary" id="email-summary">
        <span class="admin-chevron-down" aria-hidden="true"><i class="fa fa-chevron-down"></i></span>
        <span class="admin-accordion__title" id="email-title">Client Email Message with a long optional title that must wrap on a phone
          <span class="admin-info-tip-wrap" id="email-tip-wrap">
            <button type="button" class="admin-info-tip" aria-label="About Client Email Message" aria-expanded="false" aria-controls="t-email">i</button>
            <span id="t-email" class="admin-info-tip__bubble" role="tooltip">Optional. When filled in, this opens the client email.</span>
          </span>
        </span>
      </summary>
    </details>
    <details class="admin-filters-accordion admin-card" id="filters-acc">
      <summary class="admin-filters-accordion__summary" id="filters-summary">
        <span class="admin-filters-accordion__icon" aria-hidden="true">F</span>
        <span class="admin-filters-accordion__title" id="filters-title">Filters
          <span class="admin-info-tip-wrap" id="filters-tip-wrap">
            <button type="button" class="admin-info-tip" aria-label="About Filters" aria-expanded="false" aria-controls="t-filt">i</button>
            <span id="t-filt" class="admin-info-tip__bubble" role="tooltip">By default Completed and Rejected are hidden.</span>
          </span>
        </span>
        <span class="admin-filters-accordion__badge" id="filters-badge">3 active</span>
      </summary>
    </details>
    <div class="admin-form-group">
      <span class="admin-label-with-tip" id="secondary-row">
        <label for="cl-email-secondary">Secondary contact</label>
        <small class="admin-form-help cp-inline-help">(optional)</small>
        <span class="admin-info-tip-wrap">
          <button type="button" class="admin-info-tip" aria-label="About Secondary contact" aria-expanded="false" aria-controls="t-sec">i</button>
          <span id="t-sec" class="admin-info-tip__bubble" role="tooltip">Optional — CC'd on every client email.</span>
        </span>
      </span>
    </div>
    <div class="admin-form-group">
      <span class="admin-label-with-tip admin-checkbox-row" id="entity-check">
        <label class="admin-checkbox" for="br-demo-debris">
          <input type="checkbox" class="admin-checkbox__input" id="br-demo-debris" checked>
          <span class="admin-checkbox__mark"></span><span class="admin-checkbox__text">Include demolition &amp; </span>
        </label><span class="admin-label-with-tip__end">
          <label for="br-demo-debris" class="admin-checkbox__tail">debris</label>
          <span class="admin-info-tip-wrap">
            <button type="button" class="admin-info-tip" aria-label="About Include demolition & debris" aria-expanded="false" aria-controls="t-ent">i</button>
            <span id="t-ent" class="admin-info-tip__bubble" role="tooltip">Demo the existing finish.</span>
          </span>
        </span>
      </span>
    </div>
    <div class="admin-form-group">
      <span class="admin-label-with-tip admin-checkbox-row sq-checkbox-label" id="hst-check">
        <label class="admin-checkbox" for="sq-hst">
          <input type="checkbox" class="admin-checkbox__input" id="sq-hst" checked>
          <span class="admin-checkbox__mark"></span><span class="admin-checkbox__text">HST </span>
        </label><span class="admin-label-with-tip__end">
          <label for="sq-hst" class="admin-checkbox__tail">included</label>
          <span class="admin-info-tip-wrap">
            <button type="button" class="admin-info-tip" aria-label="About HST included" aria-expanded="false" aria-controls="t-hst">i</button>
            <span id="t-hst" class="admin-info-tip__bubble" role="tooltip">When checked, the amount is the total with tax.</span>
          </span>
        </span>
      </span>
    </div>
    <div class="br-bath-light-row" id="bath-light-row">
      <span class="admin-label-with-tip admin-checkbox-row" id="bath-light-check">
        <label class="admin-checkbox" for="br-bath-include-fixture">
          <input type="checkbox" class="admin-checkbox__input" id="br-bath-include-fixture" checked>
          <span class="admin-checkbox__mark"></span><span class="admin-checkbox__text">Light </span>
        </label><span class="admin-label-with-tip__end">
          <label for="br-bath-include-fixture" class="admin-checkbox__tail">fixture</label>
        </span>
      </span>
      <div class="admin-form-group">
        <label for="br-bath-fixture-qty">How many</label>
        <input class="admin-input" id="br-bath-fixture-qty" value="1">
      </div>
    </div>
    <div class="admin-form-group">
    <span class="admin-label-with-tip admin-checkbox-row" id="review-check">
      <label class="admin-checkbox" for="br-client-all">
        <input type="checkbox" class="admin-checkbox__input" id="br-client-all" checked>
        <span class="admin-checkbox__mark"></span><span class="admin-checkbox__text">Client provides all materials for the entire </span>
      </label><span class="admin-label-with-tip__end">
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
    <form id="mw-test-form">
      <div id="mw-piece-list"></div>
      <button type="button" id="mw-add-piece">Add piece</button>
      <template id="mw-piece-template">${pieceCardHtml}</template>
    </form>
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
    const consoleErrors = [];
    page.on('pageerror', (err) => consoleErrors.push(String(err && err.message ? err.message : err)));
    page.on('console', (msg) => {
      if (msg.type() === 'error') consoleErrors.push(msg.text());
    });
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
      return Array.from(document.querySelectorAll('#review-check .admin-checkbox__mark, #toggle-check .admin-checkbox__mark, #stack-check .admin-checkbox__mark')).map((el) => {
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
      const labels = {
        full: 'Total basement area (sq ft)',
        bathroom: 'Bathroom / work area (sq ft)',
        wall: 'Basement area (sq ft, optional)',
        custom: 'Work area (sq ft)',
      };
      function applyScope(preset) {
        return window.AdminInfoTip.setLabelText(row, labels[preset] || labels.full);
      }
      const afterBath = applyScope('bathroom');
      const afterWall = applyScope('wall');
      const afterCustom = applyScope('custom');
      const afterFull = applyScope('full');
      const last = row.querySelector('.admin-label-with-tip__end label');
      const aria = row.querySelector('.admin-info-tip').getAttribute('aria-label');
      applyScope('wall');
      return {
        before: copy,
        afterBath,
        afterWall,
        afterCustom,
        afterFull,
        wallLast: last ? last.textContent : '',
        wallAria: row.querySelector('.admin-info-tip').getAttribute('aria-label'),
        stillHasTip: !!row.querySelector('.admin-info-tip-wrap'),
        fullAria: aria,
      };
    });
    assert.equal(d1.before, 'Total basement area (sq ft)');
    assert.equal(d1.afterBath, 'Bathroom / work area (sq ft)', 'D1: bathroom preset is exact after setLabelText');
    assert.equal(d1.afterWall, 'Basement area (sq ft, optional)', 'N6: wall preset is exact');
    assert.equal(d1.afterCustom, 'Work area (sq ft)');
    assert.equal(d1.afterFull, 'Total basement area (sq ft)');
    assert.equal(d1.wallLast, 'optional)', 'N6: last word must become optional) not stay ft)');
    assert.equal(d1.wallAria, 'About Basement area (sq ft, optional)', 'N8: setLabelText updates aria-label');
    assert.equal(d1.stillHasTip, true);

    const d2 = await page.evaluate(() => {
      window.AdminInfoTip.hoist(document);
      const span = document.getElementById('br-lights-label');
      const afterHoist = span.textContent;
      const end = document.querySelector('#lights-row .admin-label-with-tip__end');
      span.textContent = 'Fixtures';
      const fixtures = span.textContent;
      span.textContent = 'Pot lights';
      return {
        afterHoist,
        fixtures,
        back: span.textContent,
        glued: !!end,
        stillInLabel: !!(span.closest && span.closest('label')),
      };
    });
    assert.equal(d2.afterHoist, 'Pot lights', 'D2: glue must not pull lights out of #br-lights-label');
    assert.equal(d2.fixtures, 'Fixtures');
    assert.equal(d2.back, 'Pot lights');
    assert.equal(d2.stillInLabel, true, 'do not move the JS-updated span');
    assert.equal(d2.glued, false, 'previous sibling of the tip is a label, so do not glue');

    const headingGlue = await page.evaluate(() => {
      const h = document.getElementById('pricing-heading');
      const end = h.querySelector('.admin-label-with-tip__end, .admin-heading-tip');
      const pdf = document.getElementById('pdf-line-heading');
      const pdfEnd = pdf.querySelector('.admin-label-with-tip__end');
      const review = document.getElementById('review-summary');
      const reviewEnd = review.querySelector('.admin-heading-tip, .admin-label-with-tip__end');
      const legend = document.getElementById('reply-legend');
      const star = legend.querySelector('.cp-required');
      const tip = legend.querySelector('.admin-info-tip-wrap');
      return {
        hasEnd: !!end,
        last: end ? end.textContent.trim() : '',
        pdfLast: pdfEnd ? pdfEnd.textContent.replace(/\s+/g, ' ').trim() : '',
        pdfText: String(pdf.textContent || '').replace(/\s+/g, ' ').trim(),
        reviewHasNum: !!(review.querySelector('.br-acc__num') && review.querySelector('.br-acc__num').parentNode === review),
        reviewLast: reviewEnd ? reviewEnd.textContent.replace(/\s+/g, ' ').trim() : '',
        starText: star ? star.textContent : '',
        starBeforeTip: !!(star && tip && star.nextElementSibling === tip),
      };
    });
    assert.equal(headingGlue.hasEnd, true, 'D5: heading last word is glued');
    assert.match(headingGlue.last, /area/);
    assert.match(headingGlue.pdfLast, /items/);
    assert.match(headingGlue.pdfText, /Client PDF line items/);
    assert.ok(!headingGlue.pdfText.includes('lineitems'), 'N4: spaces in flex headings must not collapse');
    assert.equal(headingGlue.reviewHasNum, true, 'num stays outside the glued last word');
    assert.match(headingGlue.reviewLast, /edits/);
    assert.equal(headingGlue.starText, '*');
    assert.equal(headingGlue.starBeforeTip, true, 'N4: required * stays before the ⓘ');

    const millwork = await page.evaluate(() => {
      const list = document.getElementById('mw-piece-list');
      const tpl = document.getElementById('mw-piece-template');
      const form = document.getElementById('mw-test-form');
      function reindexPieces() {
        list.querySelectorAll('[data-mw-piece]').forEach(function (piece, i) {
          piece.querySelectorAll('[name]').forEach(function (input) {
            input.name = input.name.replace(/pieces\[\d+\]/, 'pieces[' + i + ']');
            if (input.id) input.id = input.id.replace(/pieces-\d+/, 'pieces-' + i);
          });
          piece.querySelectorAll('label[for]').forEach(function (lab) {
            const next = (lab.getAttribute('for') || '').replace(/pieces-\d+/, 'pieces-' + i);
            lab.setAttribute('for', next);
            lab.htmlFor = next;
          });
        });
      }
      function addPiece() {
        const i = list.querySelectorAll('[data-mw-piece]').length;
        const wrap = document.createElement('div');
        wrap.innerHTML = tpl.innerHTML.replace(/__P__/g, String(i)).trim();
        const piece = wrap.firstElementChild;
        list.appendChild(piece);
        reindexPieces();
        const n = window.AdminInfoTip.refreshControlIds(piece);
        refreshedCounts.push(n);
      }
      const refreshedCounts = [];
      addPiece();
      addPiece();
      const beforeRefresh = [];
      document.querySelectorAll('#mw-piece-list .admin-info-tip__bubble[id]').forEach((el) => beforeRefresh.push(el.id));
      addPiece();
      const boxes = Array.from(list.querySelectorAll('input[name$="[edge_banding]"][type="checkbox"]'));
      const ids = boxes.map((el) => el.id);
      const tails = boxes.map((el) => list.querySelector('label.admin-checkbox__tail[for="' + CSS.escape(el.id) + '"]') || list.querySelector('label[for="' + CSS.escape(el.id) + '"]'));
      const fors = tails.map((el) => el ? el.getAttribute('for') : null);
      const bubbles = Array.from(list.querySelectorAll('.admin-info-tip__bubble[id]')).map((el) => el.id);
      tails[0].click();
      tails[2].click();
      const submitted = {};
      new FormData(form).forEach((value, key) => { submitted[key] = value; });
      const values = [0, 1, 2].map((i) => submitted['pieces[' + i + '][edge_banding]']);
      const first = list.querySelector('[data-mw-piece]');
      const clone = first.cloneNode(true);
      list.insertBefore(clone, first.nextSibling);
      reindexPieces();
      const dupRefreshed = window.AdminInfoTip.refreshControlIds(clone);
      const afterDup = Array.from(list.querySelectorAll('.admin-info-tip__bubble[id]')).map((el) => el.id);
      const dupBoxes = Array.from(list.querySelectorAll('input[name$="[edge_banding]"][type="checkbox"]'));
      return {
        refreshedCounts,
        dupRefreshed,
        count: boxes.length,
        ids,
        unique: new Set(ids).size,
        checked: boxes.map((el) => el.checked),
        values,
        fors,
        bubbles,
        uniqueBubbles: new Set(bubbles).size,
        afterDupUnique: new Set(afterDup).size,
        afterDupCount: afterDup.length,
        dupBoxUnique: new Set(dupBoxes.map((el) => el.id)).size,
        dupBoxCount: dupBoxes.length,
      };
    });
    assert.equal(millwork.count, 3, 'M1: three added pieces');
    assert.ok(millwork.refreshedCounts.every((n) => n >= 1), 'refreshControlIds must return wrap count (a no-op fails)');
    assert.ok(millwork.dupRefreshed >= 1, 'cloneNode path must call a working refreshControlIds');
    assert.equal(millwork.unique, 3, 'M1: each piece has its own checkbox id ' + millwork.ids.join(','));
    assert.deepEqual(millwork.checked, [false, true, false], 'M1: ticking piece 1 and 3 must not toggle piece 2');
    assert.deepEqual(millwork.values, ['0', '1', '0']);
    millwork.ids.forEach((id, i) => {
      assert.equal(millwork.fors[i], id, 'M1: tail for= matches input id for piece ' + i);
    });
    assert.equal(millwork.uniqueBubbles, millwork.bubbles.length, 'refreshControlIds must uniquify real-template bubble ids');
    assert.ok(millwork.bubbles.length >= 3, 'real template has tip bubbles to uniquify');
    assert.equal(millwork.afterDupUnique, millwork.afterDupCount, 'duplicate cloneNode path uniquifies bubbles');
    assert.equal(millwork.dupBoxUnique, millwork.dupBoxCount, 'duplicate cloneNode path uniquifies checkbox ids');

    async function tailMetrics(theme) {
      return page.evaluate((mode) => {
        document.documentElement.classList.toggle('admin-dark', mode === 'dark');
        function snap(rowSel) {
          const tail = document.querySelector(rowSel + ' .admin-checkbox__tail');
          const text = document.querySelector(rowSel + ' .admin-checkbox__text');
          const cs = getComputedStyle(tail);
          const ts = getComputedStyle(text);
          return {
            transform: cs.textTransform,
            weight: cs.fontWeight,
            color: cs.color,
            size: cs.fontSize,
            textTransform: ts.textTransform,
            textWeight: ts.fontWeight,
            textColor: ts.color,
            textSize: ts.fontSize,
          };
        }
        return { review: snap('#review-check'), hst: snap('#hst-check') };
      }, theme);
    }
    for (const theme of ['light', 'dark']) {
      const tailCss = await tailMetrics(theme);
      for (const [name, pair] of Object.entries(tailCss)) {
        assert.equal(pair.transform, pair.textTransform, `D2 ${theme} ${name}: text-transform`);
        assert.equal(pair.weight, pair.textWeight, `D2 ${theme} ${name}: font-weight`);
        assert.equal(pair.color, pair.textColor, `D2 ${theme} ${name}: color`);
        assert.equal(pair.size, pair.textSize, `D2 ${theme} ${name}: font-size`);
      }
    }
    await page.evaluate(() => document.documentElement.classList.remove('admin-dark'));

    const visual = await page.evaluate(() => {
      function order(el) {
        return String(el.textContent || '').replace(/\s+/g, ' ').trim();
      }
      const store = document.getElementById('store-name-row');
      const star = store.querySelector('.cp-required');
      const storeTip = store.querySelector('.admin-info-tip-wrap');
      const secondary = document.getElementById('secondary-row');
      const small = secondary.querySelector('small');
      const secTip = secondary.querySelector('.admin-info-tip-wrap');
      const summary = document.getElementById('email-summary');
      const filters = document.getElementById('filters-summary');
      const entity = document.getElementById('entity-check');
      return {
        storeOrder: order(store),
        starBeforeTip: star.nextElementSibling === storeTip,
        secondaryOrder: order(secondary),
        smallBeforeTip: small.nextElementSibling === secTip,
        chevronFirst: summary.firstElementChild && summary.firstElementChild.classList.contains('admin-chevron-down'),
        emailEnd: (summary.querySelector('.admin-label-with-tip__end') || {}).textContent || '',
        filtersOrder: order(filters),
        badgeAfterTitle: filters.querySelector('.admin-filters-accordion__title').nextElementSibling === filters.querySelector('.admin-filters-accordion__badge'),
        entityText: order(entity),
      };
    });
    assert.match(visual.storeOrder, /^Store name \*/);
    assert.ok(!visual.storeOrder.includes('NAME ⓘ *STORE') && !/^name \*/i.test(visual.storeOrder), 'N1: Store name * stays in order');
    assert.equal(visual.starBeforeTip, true);
    assert.match(visual.secondaryOrder, /^Secondary contact \(optional\)/);
    assert.equal(visual.smallBeforeTip, true, 'N4: (optional) stays before the ⓘ');
    assert.equal(visual.chevronFirst, true, 'N2: chevron stays first');
    assert.match(String(visual.emailEnd), /phone/);
    assert.ok(!String(visual.emailEnd).includes('Client Email Message with a long'), 'N2: do not nowrap the whole title');
    assert.match(visual.filtersOrder, /^F Filters/);
    assert.match(visual.filtersOrder, /3 active$/);
    assert.equal(visual.badgeAfterTitle, true, 'N3: badge stays after Filters ⓘ');
    assert.match(visual.entityText, /Include demolition &\s*debris/);
    assert.ok(!visual.entityText.includes('&amp;amp;'));

    for (const theme of ['light', 'dark']) {
      await page.evaluate((mode) => {
        document.documentElement.classList.toggle('admin-dark', mode === 'dark');
      }, theme);
      for (const width of [320, 390, 1280]) {
        await page.setViewport({ width, height: 800 });
        const aligned = await page.evaluate((vp) => {
          function sameLine(a, b) {
            if (!a || !b) return { sameLine: false, aTop: null, bTop: null };
            const ar = a.getBoundingClientRect();
            const br = b.getBoundingClientRect();
            const overlap = br.bottom > ar.top && ar.bottom > br.top;
            const wrappedBelow = br.top > ar.bottom + 2;
            return { sameLine: overlap && !wrappedBelow, aTop: ar.top, bTop: br.top };
          }
          function onScreen(el) {
            if (!el) return false;
            const r = el.getBoundingClientRect();
            return r.width > 0 && r.left >= -1 && r.right <= vp + 1 && r.top >= -1;
          }
          const checks = Array.from(document.querySelectorAll('#review-check, #toggle-check, #stack-check, #hst-check')).map((row) => {
            const tail = row.querySelector('.admin-checkbox__tail');
            const tip = row.querySelector('.admin-info-tip');
            return sameLine(tail, tip);
          });
          const review = document.getElementById('review-summary');
          const reviewLast = review.querySelector('.admin-heading-tip, .admin-label-with-tip__end');
          const reviewTip = review.querySelector('.admin-info-tip');
          const reviewLine = sameLine(reviewLast, reviewTip);
          const emailTip = document.querySelector('#email-summary .admin-info-tip');
          const storeTip = document.querySelector('#store-name-row .admin-info-tip');
          const filtersTip = document.querySelector('#filters-summary .admin-info-tip');
          const bath = document.getElementById('bath-light-row');
          const bathMark = bath.querySelector('.admin-checkbox__mark');
          const bathText = bath.querySelector('.admin-checkbox__text');
          const markTop = bathMark.getBoundingClientRect().top;
          const textTop = bathText.getBoundingClientRect().top;
          return {
            checks,
            reviewLine,
            emailOnScreen: onScreen(emailTip),
            storeOnScreen: onScreen(storeTip),
            filtersOnScreen: onScreen(filtersTip),
            bathOffset: Math.abs(markTop - textTop),
          };
        }, width);
        aligned.checks.forEach((row, i) => {
          assert.ok(row.sameLine, `D4/C3 ${theme}: checkbox ${i} tip stays on the last word at ${width}px (tail ${row.aTop} tip ${row.bTop})`);
        });
        assert.ok(aligned.reviewLine.sameLine, `N8 ${theme}: review summary ⓘ stays with the last word at ${width}px`);
        assert.ok(aligned.emailOnScreen, `N2 ${theme}: accordion ⓘ stays on-screen at ${width}px`);
        assert.ok(aligned.storeOnScreen, `N1 ${theme}: Store name ⓘ stays on-screen at ${width}px`);
        assert.ok(aligned.filtersOnScreen, `N3 ${theme}: Filters ⓘ stays on-screen at ${width}px`);
        assert.ok(aligned.bathOffset < 12, `D3 ${theme}: bath light text offset ${aligned.bathOffset}px at ${width}px`);
      }
    }
    await page.evaluate(() => document.documentElement.classList.remove('admin-dark'));
    assert.deepEqual(consoleErrors, [], 'fixture page must have zero console errors: ' + consoleErrors.join(' | '));

    const appUrl = process.env.APP_URL || 'http://127.0.0.1:8000';
    try {
      const login = await page.goto(appUrl + '/admin/login', { waitUntil: 'domcontentloaded', timeout: 8000 });
      if (login && login.ok()) {
        await page.type('input[name="email"]', 'admin@zanini.test');
        await page.type('input[name="password"]', 'password');
        await Promise.all([
          page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 8000 }).catch(() => null),
          page.click('button[type="submit"], input[type="submit"]'),
        ]);
        const liveErrors = [];
        page.removeAllListeners('pageerror');
        page.removeAllListeners('console');
        page.on('pageerror', (err) => liveErrors.push(String(err && err.message ? err.message : err)));
        page.on('console', (msg) => {
          if (msg.type() === 'error') liveErrors.push(msg.text());
        });
        const paths = ['/admin', '/admin/clients', '/admin/client-projects', '/admin/materials'];
        for (const pathName of paths) {
          const res = await page.goto(appUrl + pathName, { waitUntil: 'domcontentloaded', timeout: 10000 }).catch(() => null);
          if (!res || !res.ok()) continue;
        }
        assert.deepEqual(liveErrors, [], 'main admin pages must have zero console errors: ' + liveErrors.join(' | '));
      }
    } catch (err) {
      if (!/ERR_|net::|Timeout|Navigation/.test(String(err && err.message ? err.message : err))) {
        throw err;
      }
    }
  } finally {
    await browser.close();
  }
  return true;
}

jsDomClick();
await puppeteerClick();
console.log('admin-info-tip-click.test.mjs: ok (headless + JS DOM click)');
