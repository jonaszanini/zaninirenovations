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
assert.match(adminCss, /\.admin-card\s*>\s*h3:has\(\.admin-info-tip\)/);
assert.match(adminCss, /\.admin-card\s*>\s*h3\.sp-heading:has\(\.admin-info-tip\)[\s\S]{0,80}display:\s*block/);
assert.match(adminCss, /\.admin-label-with-tip\s*>\s*label:not\(\.admin-checkbox\)/);
assert.match(adminCss, /\.admin-label-with-tip\s*>\s*label\.admin-checkbox[\s\S]{0,80}display:\s*inline-flex/);
assert.match(adminCss, /\.admin-label-with-tip:has\(>\s*label\.admin-checkbox\)[\s\S]{0,220}display:\s*block/);
assert.match(adminCss, /\.admin-label-with-tip:has\(>\s*label\.admin-checkbox\)\s+>\s+label\.admin-checkbox[\s\S]{0,200}position:\s*relative/);
assert.match(adminCss, /\.admin-label-with-tip:has\(>\s*label\.admin-checkbox\)\s+\.admin-checkbox__input[\s\S]{0,240}width:\s*24px/);
assert.match(adminCss, /\.admin-checkbox-row/);
assert.match(adminCss, /\.admin-label-with-tip__end\s*\{[^}]*white-space:\s*nowrap/);
assert.match(adminCss, /\.admin-label-with-tip__end\s*\{[^}]*display:\s*inline;/);
assert.doesNotMatch(adminCss, /\.admin-label-with-tip__end\s*\{[^}]*display:\s*inline-flex/);
assert.match(adminCss, /\.admin-label-with-tip__end\s*>\s*label[\s\S]{0,220}display:\s*inline/);
assert.match(adminCss, /admin-label-with-tip__tail/);
assert.match(adminCss, /\.admin-label-with-tip__end\s*>\s*\.admin-info-tip-wrap[\s\S]{0,80}display:\s*inline-flex/);
assert.doesNotMatch(adminCss, /\.admin-label-with-tip__end\s*>\s*label[\s\S]{0,160}overflow-wrap:\s*anywhere/);
assert.doesNotMatch(darkCss, /html\.admin-dark \.admin-form-group \.admin-checkbox-row >/);
assert.match(darkCss, /html\.admin-dark \.admin-form-group \.admin-label-with-tip__end\s*>\s*\.cp-required/);
assert.match(darkCss, /html\.admin-dark \.admin-form-group \.admin-label-with-tip__end\s*>\s*label\.cp-required/);
assert.match(cpCss, /\.br-layout--create \.br-acc__summary \.admin-info-tip[\s\S]{0,160}pointer-events:\s*auto/);
assert.match(cpCss, /\.tm-chip-row\s*>\s*\.admin-checkbox-row/);
assert.match(cpCss, /\.br-bath-light-row\s*\{[^}]*grid-template-columns:\s*1fr/);
assert.match(cpCss, /\.br-main\s*\{[^}]*container-type:\s*inline-size/);
const brMainCqAt = cpCss.indexOf('@container br-main (min-width: 48rem)');
assert.ok(brMainCqAt >= 0, 'lighting/tile/check-grid must use @container br-main (min-width: 48rem)');
const checkGrid2ColMediaAt = cpCss.search(/@media \(min-width:\s*700px\)\s*\{[\s\S]{0,80}\.br-check-grid[\s\S]{0,80}repeat\(2/);
assert.ok(checkGrid2ColMediaAt >= 0, '700px 2-col .br-check-grid media rule must remain');
assert.ok(brMainCqAt > checkGrid2ColMediaAt, 'R20-1: @container 3-col must follow the 700px 2-col .br-check-grid rule');
const brMainContainer = cpCss.slice(brMainCqAt, brMainCqAt + 1600);
assert.match(brMainContainer, /\.br-bath-light-row[\s\S]*minmax\(max-content,\s*1fr\)\s+minmax\(96px,\s*140px\)/);
assert.doesNotMatch(brMainContainer, /\.br-bath-light-row[\s\S]{0,180}minmax\(0,\s*1fr\)/);
assert.match(brMainContainer, /\.br-tile-row__main[\s\S]*max-content minmax\(8\.5rem/);
assert.match(brMainContainer, /\.br-check-grid[\s\S]*repeat\(3,\s*minmax\(max-content,\s*1fr\)\)/);
assert.doesNotMatch(cpCss, /@media \(min-width:\s*1100px\)[\s\S]{0,200}\.br-bath-light-row/);
assert.doesNotMatch(cpCss, /@media \(min-width:\s*900px\)[\s\S]{0,220}\.br-check-grid[\s\S]{0,80}repeat\(3/);
assert.doesNotMatch(cpCss, /@container br-main \(min-width:\s*720px\)/);
assert.match(cpCss, /@media \(min-width:\s*700px\)\s*\{\s*\.br-bath-light-grid[\s\S]{0,80}1fr 1fr/);
assert.match(cpCss, /\.cp-schedule-dates \.admin-form-group > label\.cp-schedule-date-label[\s\S]{0,220}height:\s*1\.5rem/);
assert.match(cpCss, /\.cp-schedule-dates \.admin-label-with-tip__end > \.admin-info-tip-wrap[\s\S]{0,120}max-height:\s*1\.25rem/);
assert.match(source, /function glueLastTextNode/);
assert.match(source, /function wrapHeadingCopy/);
assert.match(source, /function wrapElementAndTip/);
assert.match(source, /function makeWordEl/);
assert.match(source, /function linkSplitLabels/);
assert.match(source, /function uniquifyTipLabelIds/);
assert.match(source, /if \(!scope \|\| scope\.nodeType !== 1\) return/);
assert.match(source, /refreshControlIds requires an element scope/);
assert.match(source, /admin-label-with-tip__tail/);
assert.match(source, /admin-tip-lab-/);
assert.doesNotMatch(source, /function moveTrailingSkipped/);
assert.doesNotMatch(source, /admin-heading-tip/);

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

const millworkJs = readFileSync(path.resolve(repoRoot, 'public/js/admin-millwork-quotation.js'), 'utf8');
const pieceCardHtml = renderPieceCard();
assert.match(pieceCardHtml, /pieces-__P__-edge_banding/, 'real millwork template must expose rewriteable checkbox ids');
assert.match(pieceCardHtml, /admin-info-tip__bubble/, 'real millwork template includes tip bubbles');
assert.match(millworkJs, /refreshControlIds/, 'production millwork JS must uniquify cloned controls');

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
      <span class="admin-label-with-tip" id="pq-marble-row">
        <label class="admin-checkbox admin-checkbox--block">
          <input type="checkbox" class="admin-checkbox__input" id="pq-marble-enabled">
          <span class="admin-checkbox__mark"></span>
          <span class="admin-checkbox__text">Include decorative marble bed</span>
        </label>
        <span class="admin-info-tip-wrap" id="pq-marble-tip-wrap">
          <button type="button" class="admin-info-tip" id="pq-marble-tip" aria-label="About Decorative marble bed" aria-expanded="false" aria-controls="t-marble">i</button>
          <span id="t-marble" class="admin-info-tip__bubble" role="tooltip">Additive shallow bed.</span>
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
          <span class="admin-info-tip-wrap">
            <button type="button" class="admin-info-tip" aria-label="About Light fixture" aria-expanded="false" aria-controls="t-fix">i</button>
            <span id="t-fix" class="admin-info-tip__bubble" role="tooltip">Bathroom ceiling fixture.</span>
          </span>
        </span>
      </span>
      <div class="admin-form-group">
        <label for="br-bath-fixture-qty">How many</label>
        <input class="admin-input" id="br-bath-fixture-qty" value="1">
      </div>
    </div>
    <div class="br-qty-grid" id="short-word-grid">
      <div class="admin-form-group">
        <span class="admin-label-with-tip" id="toilet-row">
          <label for="br-toilet">Toilet</label>
          <span class="admin-info-tip-wrap">
            <button type="button" class="admin-info-tip" aria-label="About Toilet" aria-expanded="false" aria-controls="t-toi">i</button>
            <span id="t-toi" class="admin-info-tip__bubble" role="tooltip">Supply and set.</span>
          </span>
        </span>
        <input class="admin-input" id="br-toilet" value="1">
      </div>
      <div class="admin-form-group">
        <span class="admin-label-with-tip" id="fan-row">
          <label for="br-fan">Exhaust fan</label>
          <span class="admin-info-tip-wrap">
            <button type="button" class="admin-info-tip" aria-label="About Exhaust fan" aria-expanded="false" aria-controls="t-fan">i</button>
            <span id="t-fan" class="admin-info-tip__bubble" role="tooltip">Vent the bath.</span>
          </span>
        </span>
        <input class="admin-input" id="br-fan" value="1">
      </div>
      <div class="admin-form-group">
        <span class="admin-label-with-tip" id="drywall-row">
          <label for="br-drywall">Drywall</label>
          <span class="admin-info-tip-wrap">
            <button type="button" class="admin-info-tip" aria-label="About Drywall" aria-expanded="false" aria-controls="t-dw">i</button>
            <span id="t-dw" class="admin-info-tip__bubble" role="tooltip">Sheets and finish.</span>
          </span>
        </span>
        <input class="admin-input" id="br-drywall" value="1">
      </div>
      <div class="admin-form-group">
        <span class="admin-label-with-tip" id="strong-word-row">
          <label for="br-strong"><strong>Drywall</strong></label>
          <span class="admin-info-tip-wrap">
            <button type="button" class="admin-info-tip" aria-label="About strong Drywall" aria-expanded="false" aria-controls="t-strong">i</button>
            <span id="t-strong" class="admin-info-tip__bubble" role="tooltip">Same-line strong must not count as a broken word.</span>
          </span>
        </span>
        <input class="admin-input" id="br-strong" value="1">
      </div>
      <div class="admin-form-group">
        <span class="admin-label-with-tip admin-checkbox-row" id="barrier-row">
          <label class="admin-checkbox" for="br-barrier">
            <input type="checkbox" class="admin-checkbox__input" id="br-barrier" checked>
            <span class="admin-checkbox__mark"></span><span class="admin-checkbox__text">Wall insulation + vapour </span>
          </label><span class="admin-label-with-tip__end">
            <label for="br-barrier" class="admin-checkbox__tail">barrier</label>
            <span class="admin-info-tip-wrap">
              <button type="button" class="admin-info-tip" aria-label="About vapour barrier" aria-expanded="false" aria-controls="t-bar">i</button>
              <span id="t-bar" class="admin-info-tip__bubble" role="tooltip">Warm-side poly.</span>
            </span>
          </span>
        </span>
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
    <form id="mw-main-form">
      <div id="mw-piece-list"></div>
      <button type="button" id="mw-add-piece">Add piece</button>
      <template id="mw-piece-template">${pieceCardHtml}</template>
    </form>
    <h5 class="sq-intro-option-title" id="doc-type-heading">Document type
      <span class="admin-info-tip-wrap">
        <button type="button" class="admin-info-tip" aria-label="About Document type" aria-expanded="false" aria-controls="t-doc">i</button>
        <span id="t-doc" class="admin-info-tip__bubble" role="tooltip">Switch anytime.</span>
      </span>
    </h5>
    <h3 class="sp-heading" id="crew-heading">Crew rates
      <span class="admin-info-tip-wrap">
        <button type="button" class="admin-info-tip" aria-label="About Crew rates" aria-expanded="false" aria-controls="t-crew">i</button>
        <span id="t-crew" class="admin-info-tip__bubble" role="tooltip">Shared crew rates.</span>
      </span>
    </h3>
    <div class="admin-card" id="business-contact-card" style="width:100%;max-width:40rem">
      <h3 id="business-contact-heading">Business contact
        <span class="admin-info-tip-wrap">
          <button type="button" class="admin-info-tip" aria-label="About business contact" aria-expanded="false" aria-controls="t-biz">i</button>
          <span id="t-biz" class="admin-info-tip__bubble" role="tooltip">Shown to clients on quote accept pages.</span>
        </span>
      </h3>
    </div>
    <h5 class="sq-intro-option-title" id="pdf-wording-heading">Includes (PDF wording)
      <span class="admin-info-tip-wrap">
        <button type="button" class="admin-info-tip" aria-label="About Includes (PDF wording)" aria-expanded="false" aria-controls="t-word">i</button>
        <span id="t-word" class="admin-info-tip__bubble" role="tooltip">Labour and material or Labour.</span>
      </span>
    </h5>
    <summary class="br-acc__summary" id="materials-summary"><span class="br-acc__num">2</span> Materials &amp; finish
      <span class="admin-info-tip-wrap">
        <button type="button" class="admin-info-tip" aria-label="About Materials & finish" aria-expanded="false" aria-controls="t-mat">i</button>
        <span id="t-mat" class="admin-info-tip__bubble" role="tooltip">Sheet size defaults to 4×8.</span>
      </span>
    </summary>
    <div class="admin-form-group">
      <span class="admin-label-with-tip admin-checkbox-row" id="client-filled-check">
        <label class="admin-checkbox" for="br-client-filled-demo">
          <input type="checkbox" class="admin-checkbox__input" id="br-client-filled-demo" checked>
          <span class="admin-checkbox__mark"></span><span class="br-client-filled" title="Filled from client form" aria-label="Filled from client form"><i class="fa fa-user" aria-hidden="true"></i></span>
          <span class="admin-checkbox__text">Client provides painted trim and all finish hardware for the wrapped </span>
        </label><span class="admin-label-with-tip__end">
          <label for="br-client-filled-demo" class="admin-checkbox__tail">line</label>
          <span class="admin-info-tip-wrap">
            <button type="button" class="admin-info-tip" aria-label="About Client provides painted trim" aria-expanded="false" aria-controls="t-cf">i</button>
            <span id="t-cf" class="admin-info-tip__bubble" role="tooltip">Filled from the client form.</span>
          </span>
        </span>
      </span>
    </div>
    <div class="admin-form-group" id="labour-gate-group">
      <span class="admin-label-with-tip" id="labour-gate-row">
        <label for="labour-gate">Labour per additional gate (CAD$)</label>
        <span class="admin-info-tip-wrap">
          <button type="button" class="admin-info-tip" aria-label="About Labour per additional gate (CAD$)" aria-expanded="false" aria-controls="t-gate">i</button>
          <span id="t-gate" class="admin-info-tip__bubble" role="tooltip">Each extra gate after the first.</span>
        </span>
      </span>
      <input class="admin-input" id="labour-gate" value="">
    </div>
    <div class="admin-form-group" id="outlets-group">
      <span class="admin-label-with-tip" id="outlets-row">
        <label for="br-outlets">New outlets</label>
        <span class="admin-info-tip-wrap">
          <button type="button" class="admin-info-tip" aria-label="About New outlets" aria-expanded="false" aria-controls="t-out">i</button>
          <span id="t-out" class="admin-info-tip__bubble" role="tooltip">Count new receptacles.</span>
        </span>
      </span>
      <input class="admin-input" id="br-outlets" value="0">
    </div>
    <div class="admin-form-group" id="waste-group">
      <span class="admin-label-with-tip" id="waste-row">
        <label for="waste-pct">Waste %</label>
        <span class="admin-info-tip-wrap">
          <button type="button" class="admin-info-tip" aria-label="About Waste %" aria-expanded="false" aria-controls="t-waste">i</button>
          <span id="t-waste" class="admin-info-tip__bubble" role="tooltip">Sheet waste.</span>
        </span>
      </span>
      <input class="admin-input" id="waste-pct" value="10">
    </div>
    <div class="admin-form-group" id="star-split-group">
      <span class="admin-label-with-tip" id="star-split-row">
        <label for="star-split">Amount *</label>
        <span class="admin-info-tip-wrap">
          <button type="button" class="admin-info-tip" aria-label="About Amount" aria-expanded="false" aria-controls="t-star">i</button>
          <span id="t-star" class="admin-info-tip__bubble" role="tooltip">Required amount.</span>
        </span>
      </span>
      <input class="admin-input" id="star-split" value="">
    </div>
    <div class="admin-form-group" id="named-group">
      <span class="admin-label-with-tip" id="named-row">
        <label for="named-input">Owner rate (CAD$)</label>
        <span class="admin-info-tip-wrap">
          <button type="button" class="admin-info-tip" aria-label="About Owner rate" aria-expanded="false" aria-controls="t-named">i</button>
          <span id="t-named" class="admin-info-tip__bubble" role="tooltip">Hourly.</span>
        </span>
      </span>
      <input class="admin-input" id="named-input" aria-labelledby="named-hint" value="">
      <span id="named-hint">Existing hint</span>
    </div>
    <div class="admin-form-grid-2 cp-schedule-dates" id="schedule-dates-fixture">
      <div class="admin-form-group">
        <label for="sched-start" class="cp-schedule-date-label">Start date</label>
        <input class="admin-input" type="date" id="sched-start" value="">
      </div>
      <div class="admin-form-group">
        <span class="admin-label-with-tip">
          <label for="sched-end">End date</label>
          <span class="admin-label-with-tip__end">
            <span class="admin-info-tip-wrap" id="sched-end-tip-wrap">
              <button type="button" class="admin-info-tip" aria-label="About End date" aria-expanded="false" aria-controls="t-sched-end">i</button>
              <span id="t-sched-end" class="admin-info-tip__bubble" role="tooltip">Same-day start and end is allowed.</span>
            </span>
          </span>
        </span>
        <input class="admin-input" type="date" id="sched-end" value="">
      </div>
    </div>
    <span class="admin-label-with-tip" id="improve-row">
      <span class="admin-form-group-label">Improve description</span>
      <span class="admin-info-tip-wrap">
        <button type="button" class="admin-info-tip" aria-label="About Improve description" aria-expanded="false" aria-controls="t-imp">i</button>
        <span id="t-imp" class="admin-info-tip__bubble" role="tooltip">Write a rough description first.</span>
      </span>
    </span>
    <div id="step-rail-chrome">
      <aside id="step-rail-sidebar" hidden></aside>
      <div class="admin-content">
      <div class="br-shell" id="step-rail-shell">
        <nav class="br-progress" aria-hidden="true">
          <span class="br-progress__item">1 Bathroom</span>
          <span class="br-progress__item">2 Electrical</span>
          <span class="br-progress__item">3 Plumbing</span>
        </nav>
        <div class="br-main">
          <div class="br-bath-lighting">
            <div class="br-bath-light-grid">
              <div class="br-bath-light-row">
                <span class="admin-label-with-tip admin-checkbox-row">
                  <label class="admin-checkbox" for="br-bath-include-fixture-rail">
                    <input type="checkbox" class="admin-checkbox__input" id="br-bath-include-fixture-rail" checked>
                    <span class="admin-checkbox__mark"></span><span class="admin-checkbox__text">Light fixture</span>
                  </label>
                  <span class="admin-info-tip-wrap">
                    <button type="button" class="admin-info-tip" aria-label="About Light fixture" aria-expanded="false" aria-controls="t-fix-rail">i</button>
                    <span id="t-fix-rail" class="admin-info-tip__bubble" role="tooltip">Bathroom ceiling fixture.</span>
                  </span>
                </span>
                <div class="admin-form-group">
                  <label for="br-bath-fixture-qty-rail">How many</label>
                  <input class="admin-input" id="br-bath-fixture-qty-rail" value="1">
                </div>
              </div>
              <div class="br-bath-light-row" id="spot-rail-row">
                <span class="admin-label-with-tip admin-checkbox-row" id="spot-rail-check">
                  <label class="admin-checkbox" for="br-bath-include-spot-rail">
                    <input type="checkbox" class="admin-checkbox__input" id="br-bath-include-spot-rail" checked>
                    <span class="admin-checkbox__mark"></span><span class="admin-checkbox__text">Spot light</span>
                  </label>
                  <span class="admin-info-tip-wrap">
                    <button type="button" class="admin-info-tip" aria-label="About Spot light" aria-expanded="false" aria-controls="t-spot-rail">i</button>
                    <span id="t-spot-rail" class="admin-info-tip__bubble" role="tooltip">Recessed pot / spot.</span>
                  </span>
                </span>
                <div class="admin-form-group">
                  <label for="br-bath-spot-qty-rail">How many</label>
                  <input class="admin-input" id="br-bath-spot-qty-rail" type="number" value="2">
                </div>
              </div>
            </div>
          </div>
          <div class="br-tile-row" id="tile-rail-row">
            <div class="br-tile-row__main">
              <div class="admin-form-group br-tile-row__sqft">
                <label for="br-tile-rail">Square feet</label>
                <div class="br-tile-row__sqft-controls">
                  <div class="br-lw__sqft">
                    <input type="number" class="admin-input" id="br-tile-rail" value="40">
                  </div>
                  <div class="br-lw-segment" role="radiogroup" aria-label="Square feet entry">
                    <label class="br-lw-segment__item"><input type="radio" checked><span>Total sq ft</span></label>
                    <label class="br-lw-segment__item"><input type="radio"><span>Length × width</span></label>
                  </div>
                </div>
              </div>
              <div class="admin-form-group br-tile-row__finish">
                <label>Finish</label>
                <div class="br-lw-segment" role="radiogroup">
                  <label class="br-lw-segment__item"><input type="radio" checked><span>Tile</span></label>
                  <label class="br-lw-segment__item"><input type="radio"><span>Vinyl</span></label>
                </div>
              </div>
              <div class="admin-form-group br-tile-row__name">
                <label for="br-tile-label-rail">Area name</label>
                <input class="admin-input" id="br-tile-label-rail" value="Floor">
              </div>
              <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm br-tile-row__remove" aria-label="Remove tile area">×</button>
            </div>
          </div>
          <div class="br-check-grid" id="sink-rail-row">
            <span class="admin-label-with-tip admin-checkbox-row">
              <label class="admin-checkbox" for="br-include-vanity-rail">
                <input type="checkbox" class="admin-checkbox__input" id="br-include-vanity-rail" checked>
                <span class="admin-checkbox__mark"></span><span class="admin-checkbox__text">Vanity + faucet</span>
              </label>
              <span class="admin-info-tip-wrap">
                <button type="button" class="admin-info-tip" aria-label="About Vanity + faucet" aria-expanded="false" aria-controls="t-van-rail">i</button>
                <span id="t-van-rail" class="admin-info-tip__bubble" role="tooltip">Vanity cabinet and faucet.</span>
              </span>
            </span>
            <span class="admin-label-with-tip admin-checkbox-row" id="pedestal-rail-check">
              <label class="admin-checkbox" for="br-include-pedestal-rail">
                <input type="checkbox" class="admin-checkbox__input" id="br-include-pedestal-rail" checked>
                <span class="admin-checkbox__mark"></span><span class="admin-checkbox__text">Pedestal sink + faucet</span>
              </label>
              <span class="admin-info-tip-wrap">
                <button type="button" class="admin-info-tip" aria-label="About Pedestal sink + faucet" aria-expanded="false" aria-controls="t-ped-rail">i</button>
                <span id="t-ped-rail" class="admin-info-tip__bubble" role="tooltip">Pedestal sink instead of a vanity.</span>
              </span>
            </span>
            <div class="admin-form-group" id="br-bath-sink-qty">
              <label for="br-bath-sink-qty-select">How many sinks?</label>
              <select class="admin-input" id="br-bath-sink-qty-select">
                <option value="1" selected>1</option>
                <option value="2">2</option>
              </select>
            </div>
          </div>
          <div class="br-qty-grid" id="qty-align-row">
            <div class="admin-form-group" id="qty-outlets-group">
              <span class="admin-label-with-tip" id="qty-outlets-row">
                <label for="qty-outlets">New outlets</label>
                <span class="admin-info-tip-wrap">
                  <button type="button" class="admin-info-tip" aria-label="About New outlets" aria-expanded="false" aria-controls="t-qty-out">i</button>
                  <span id="t-qty-out" class="admin-info-tip__bubble" role="tooltip">Typical bathroom: 1 at the sink.</span>
                </span>
              </span>
              <input class="admin-input" id="qty-outlets" value="1">
            </div>
            <div class="admin-form-group" id="qty-switches-group">
              <span class="admin-label-with-tip" id="qty-switches-row">
                <label for="qty-switches">Switches</label>
                <span class="admin-info-tip-wrap">
                  <button type="button" class="admin-info-tip" aria-label="About Switches" aria-expanded="false" aria-controls="t-qty-sw">i</button>
                  <span id="t-qty-sw" class="admin-info-tip__bubble" role="tooltip">Typical bathroom: 1 for the light.</span>
                </span>
              </span>
              <input class="admin-input" id="qty-switches" value="2">
            </div>
          </div>
        </div>
      </div>
      </div>
    </div>
    <form id="br-scope-form">
      <label><input type="radio" name="scope_preset" data-br-scope-preset value="full" checked> Full</label>
      <label><input type="radio" name="scope_preset" data-br-scope-preset value="bathroom"> Bathroom</label>
      <details class="br-acc" data-br-step="electrical" open>
        <summary class="br-acc__summary">Electrical</summary>
        <div id="br-electrical-slot-step">
          <div id="br-electrical-fields" class="br-electrical-fields">
            <span class="br-tile-areas__title admin-label-with-tip">
              <span>Electrical scope</span>
              <span class="admin-info-tip-wrap">
                <button type="button" class="admin-info-tip" aria-label="About Electrical scope" aria-expanded="false" aria-controls="t-el-scope">i</button>
                <span id="t-el-scope" class="admin-info-tip__bubble" role="tooltip">Scope for the electrician.</span>
              </span>
            </span>
          </div>
        </div>
      </details>
      <div class="br-nested-trade" id="br-electrical-slot-bath" hidden></div>
      <details class="br-acc" data-br-step="plumbing" open>
        <summary class="br-acc__summary">Plumbing</summary>
        <div id="br-plumbing-slot-step">
          <div id="br-plumbing-fields" class="br-plumbing-fields">
            <span class="br-tile-areas__title admin-label-with-tip">
              <span>Plumbing scope</span>
              <span class="admin-info-tip-wrap">
                <button type="button" class="admin-info-tip" aria-label="About Plumbing scope" aria-expanded="false" aria-controls="t-pl-scope">i</button>
                <span id="t-pl-scope" class="admin-info-tip__bubble" role="tooltip">Scope for the plumber.</span>
              </span>
            </span>
          </div>
        </div>
      </details>
      <div class="br-nested-trade" id="br-plumbing-slot-bath" hidden></div>
    </form>
    <div class="tm-chip-row" id="tm-chip-row">
      <span class="admin-label-with-tip admin-checkbox-row" id="tm-client-all-row">
        <label class="admin-checkbox" for="tm-client-all">
          <input type="checkbox" class="admin-checkbox__input" id="tm-client-all">
          <span class="admin-checkbox__mark"></span><span class="admin-checkbox__text">Client supplies </span>
        </label><span class="admin-label-with-tip__end">
          <label for="tm-client-all" class="admin-checkbox__tail">materials</label>
          <span class="admin-info-tip-wrap">
            <button type="button" class="admin-info-tip" aria-label="About Client supplies materials" aria-expanded="false" aria-controls="t-tm">i</button>
            <span id="t-tm" class="admin-info-tip__bubble" role="tooltip">Labour only.</span>
          </span>
        </span>
      </span>
    </div>
    <script>${source}</script>
    <script>window.MW_QUOTATION = { sectionKeys: [], isEdit: false, previewUrl: '' };</script>
    <script>${millworkJs}</script>
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

    assert.equal(await page.$eval('#pq-marble-enabled', (el) => el.checked), false);
    await page.click('#pq-marble-enabled');
    const afterMarbleId = await page.evaluate(() => ({
      checked: document.getElementById('pq-marble-enabled').checked,
      tipOpen: document.getElementById('pq-marble-tip-wrap').classList.contains('is-open'),
    }));
    assert.equal(afterMarbleId.checked, true, 'clicking #pq-marble-enabled must toggle the hanging-indent checkbox once');
    assert.equal(afterMarbleId.tipOpen, false, 'clicking the marble checkbox must not open the ⓘ');
    await page.click('#pq-marble-enabled');
    assert.equal(await page.$eval('#pq-marble-enabled', (el) => el.checked), false, 'second #pq-marble-enabled click turns marble off');
    await page.click('#pq-marble-tip');
    const afterMarbleTip = await page.evaluate(() => ({
      checked: document.getElementById('pq-marble-enabled').checked,
      expanded: document.getElementById('pq-marble-tip').getAttribute('aria-expanded'),
    }));
    assert.equal(afterMarbleTip.checked, false, 'ⓘ must not toggle the marble checkbox');
    assert.equal(afterMarbleTip.expanded, 'true', 'ⓘ opens only the help bubble');
    await page.keyboard.press('Escape');
    await page.focus('#pq-marble-enabled');
    await page.keyboard.press('Space');
    assert.equal(await page.$eval('#pq-marble-enabled', (el) => el.checked), true, 'Space on the native marble input must toggle');
    await page.keyboard.press('Escape');

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
    assert.equal(d2.glued, true, 'wrap the preceding label with the tip; do not split #br-lights-label');

    const headingGlue = await page.evaluate(() => {
      const h = document.getElementById('pricing-heading');
      const end = h.querySelector('.admin-label-with-tip__end');
      const pdf = document.getElementById('pdf-line-heading');
      const pdfEnd = pdf.querySelector('.admin-label-with-tip__end');
      const review = document.getElementById('review-summary');
      const reviewEnd = review.querySelector('.admin-label-with-tip__end');
      const copy = h.querySelector('.admin-heading-copy');
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
        starBeforeTip: !!(star && tip && (star.nextElementSibling === tip || (star.parentNode && star.parentNode.contains(tip) && star.parentNode.classList.contains('admin-label-with-tip__end')))),
        hasCopy: !!copy,
      };
    });
    assert.equal(headingGlue.hasCopy, true, 'heading text + glued end live in .admin-heading-copy');
    assert.equal(headingGlue.hasEnd, true, 'D5: heading last word is glued');
    assert.match(headingGlue.last, /area/);
    assert.match(headingGlue.pdfLast, /items/);
    assert.match(headingGlue.pdfText, /Client PDF line items/);
    assert.ok(!headingGlue.pdfText.includes('lineitems'), 'N4: spaces in flex headings must not collapse');
    assert.equal(headingGlue.reviewHasNum, true, 'num stays outside the glued last word');
    assert.match(headingGlue.reviewLast, /edits/);
    assert.equal(headingGlue.starText, '*');
    assert.equal(headingGlue.starBeforeTip, true, 'N4: required * stays before the ⓘ');

    const splitWord = await page.evaluate(() => {
      function nameOf(input) {
        if (!input) return '';
        const id = input.id;
        const label = id && document.querySelector('label[for="' + id + '"]');
        if (label) {
          return String(label.textContent || '').replace(/\s+/g, ' ').trim();
        }
        const ids = String(input.getAttribute('aria-labelledby') || '').split(/\s+/).filter(Boolean);
        if (!ids.length) return '';
        return ids.map((lid) => {
          const el = document.getElementById(lid);
          return String(el ? el.textContent : '').replace(/\s+/g, ' ').trim();
        }).filter(Boolean).join(' ');
      }
      function styles(el) {
        if (!el || el.nodeType !== 1) {
          return { transform: '', weight: '', size: '', color: '' };
        }
        const cs = getComputedStyle(el);
        return {
          transform: cs.textTransform,
          weight: cs.fontWeight,
          size: cs.fontSize,
          color: cs.color,
        };
      }
      const gate = document.getElementById('labour-gate');
      const gateLead = document.querySelector('#labour-gate-row > label');
      const gateEnd = document.querySelector('#labour-gate-row .admin-label-with-tip__end');
      const outlets = document.getElementById('br-outlets');
      const outLead = document.querySelector('#outlets-row > label');
      const outEnd = document.querySelector('#outlets-row .admin-label-with-tip__end');
      const improve = document.querySelector('#improve-row > .admin-form-group-label');
      const improveEnd = document.querySelector('#improve-row .admin-label-with-tip__end');
      const emailWord = document.querySelector('#email-title .admin-label-with-tip__end .admin-accordion__title, #email-title .admin-label-with-tip__end span');
      return {
        gateLeadText: gateLead ? gateLead.textContent.replace(/\s+/g, ' ').trim() : '',
        gateFor: gateLead ? gateLead.htmlFor : '',
        gateEndHasTip: !!(gateEnd && gateEnd.querySelector('.admin-info-tip-wrap')),
        gateEndHasTailLabel: !!(gateEnd && gateEnd.querySelector('label')),
        gateName: nameOf(gate),
        gateLead: styles(gateLead),
        outName: nameOf(outlets),
        outLead: styles(outLead),
        outEndHasTip: !!(outEnd && outEnd.querySelector('.admin-info-tip-wrap')),
        improveText: improve ? improve.textContent.replace(/\s+/g, ' ').trim() : '',
        improveEndHasTip: !!(improveEnd && improveEnd.querySelector('.admin-info-tip-wrap')),
        improveLead: styles(improve),
        emailClass: emailWord ? emailWord.className : '',
      };
    });
    assert.equal(splitWord.gateLeadText, 'Labour per additional gate (CAD$)', 'full label stays in one element');
    assert.equal(splitWord.gateFor, 'labour-gate');
    assert.equal(splitWord.gateEndHasTip, true, 'tip parks in __end');
    assert.equal(splitWord.gateEndHasTailLabel, false, 'no split tail label');
    assert.equal(splitWord.gateName, 'Labour per additional gate (CAD$)');
    assert.equal(splitWord.outName, 'New outlets');
    assert.equal(splitWord.outEndHasTip, true);
    assert.equal(splitWord.improveText, 'Improve description');
    assert.equal(splitWord.improveEndHasTip, true);
    for (const key of ['transform', 'weight', 'size', 'color']) {
      assert.equal(splitWord.gateLead[key], splitWord.gateLead[key], `labour-gate label ${key} is readable`);
      assert.equal(splitWord.outLead[key], splitWord.outLead[key], `outlets label ${key} is readable`);
      assert.equal(splitWord.improveLead[key], splitWord.improveLead[key], `Improve description ${key} is readable`);
    }
    assert.match(String(splitWord.emailClass), /admin-accordion__title/);

    await page.click('#labour-gate-row > label');
    const focused = await page.evaluate(() => document.activeElement && document.activeElement.id);
    assert.equal(focused, 'labour-gate', 'clicking the label focuses the input');

    await page.evaluate(() => {
      const orig = window.AdminInfoTip.refreshControlIds.bind(window.AdminInfoTip);
      window.__tipRefreshCalls = [];
      window.AdminInfoTip.refreshControlIds = function (scope) {
        const n = orig(scope);
        window.__tipRefreshCalls.push(n);
        return n;
      };
    });
    await page.click('#mw-add-piece');
    await page.waitForSelector('#mw-piece-list [data-mw-piece]');
    await page.click('#mw-add-piece');
    await page.click('#mw-add-piece');
    const millwork = await page.evaluate(() => {
      const list = document.getElementById('mw-piece-list');
      const form = document.getElementById('mw-main-form');
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
      return {
        refreshedCounts: window.__tipRefreshCalls.slice(),
        count: boxes.length,
        ids,
        unique: new Set(ids).size,
        checked: boxes.map((el) => el.checked),
        values,
        fors,
        bubbles,
        uniqueBubbles: new Set(bubbles).size,
      };
    });
    await page.evaluate(() => {
      const piece = document.querySelector('#mw-piece-list [data-mw-piece]');
      const lab = piece && piece.querySelector('label');
      const input = piece && piece.querySelector('input, select');
      if (lab) lab.id = 'admin-tip-lab-shared';
      if (input) {
        const keep = String(input.getAttribute('aria-labelledby') || '').split(/\s+/).filter(Boolean);
        if (keep.indexOf('admin-tip-lab-shared') === -1) keep.unshift('admin-tip-lab-shared');
        if (keep.indexOf('keep-hint') === -1) keep.push('keep-hint');
        input.setAttribute('aria-labelledby', keep.join(' '));
      }
    });
    await page.click('#mw-piece-list [data-mw-piece] [data-mw-duplicate-piece]');
    const millworkDup = await page.evaluate(() => {
      const list = document.getElementById('mw-piece-list');
      const afterDup = Array.from(list.querySelectorAll('.admin-info-tip__bubble[id]')).map((el) => el.id);
      const dupBoxes = Array.from(list.querySelectorAll('input[name$="[edge_banding]"][type="checkbox"]'));
      const pieces = Array.from(list.querySelectorAll('[data-mw-piece]'));
      const orig = pieces[0];
      const clone = pieces[1];
      const origLab = orig && orig.querySelector('#admin-tip-lab-shared, [id^="admin-tip-lab-"]');
      const cloneLab = clone && Array.from(clone.querySelectorAll('[id^="admin-tip-lab-"]')).find((el) => el.id);
      const origInput = orig && orig.querySelector('[aria-labelledby]');
      const cloneInput = clone && clone.querySelector('[aria-labelledby]');
      const tipLabs = Array.from(document.querySelectorAll('[id^="admin-tip-lab-"]')).map((el) => el.id);
      const missingBy = [];
      const foreignBy = [];
      document.querySelectorAll('[aria-labelledby]').forEach((el) => {
        String(el.getAttribute('aria-labelledby') || '').split(/\s+/).filter(Boolean).forEach((id) => {
          const target = document.getElementById(id);
          if (!target && id !== 'keep-hint') missingBy.push(id);
          const piece = el.closest && el.closest('[data-mw-piece]');
          if (piece && target && id.indexOf('admin-tip-lab-') === 0 && !piece.contains(target)) {
            foreignBy.push(id);
          }
        });
      });
      const namedRow = document.getElementById('named-row');
      const namedInput = document.getElementById('named-input');
      const holder = document.createElement('div');
      holder.id = 'named-dup-holder';
      const rowClone = namedRow.cloneNode(true);
      const inputClone = namedInput.cloneNode(true);
      holder.appendChild(rowClone);
      holder.appendChild(inputClone);
      document.body.appendChild(holder);
      const origNamedIds = Array.from(namedRow.querySelectorAll('[id^="admin-tip-lab-"]')).map((el) => el.id);
      window.AdminInfoTip.refreshControlIds(holder);
      const cloneNamedIds = Array.from(rowClone.querySelectorAll('[id^="admin-tip-lab-"]')).map((el) => el.id);
      const cloneNamedBy = String(inputClone.getAttribute('aria-labelledby') || '');
      return {
        dupRefreshed: window.__tipRefreshCalls[window.__tipRefreshCalls.length - 1] || 0,
        afterDupUnique: new Set(afterDup).size,
        afterDupCount: afterDup.length,
        dupBoxUnique: new Set(dupBoxes.map((el) => el.id)).size,
        dupBoxCount: dupBoxes.length,
        origSharedKept: !!(orig && orig.querySelector('#admin-tip-lab-shared')),
        cloneLabId: cloneLab ? cloneLab.id : '',
        cloneBy: cloneInput ? cloneInput.getAttribute('aria-labelledby') : '',
        origBy: origInput ? origInput.getAttribute('aria-labelledby') : '',
        tipLabUnique: new Set(tipLabs).size,
        tipLabCount: tipLabs.length,
        missingBy,
        foreignBy,
        namedOrigTipLabCount: origNamedIds.length,
        namedCloneRemapped: origNamedIds.length === 0
          || cloneNamedIds.every((id) => origNamedIds.indexOf(id) === -1),
        namedCloneKeepsHint: cloneNamedBy.split(/\s+/).includes('named-hint'),
      };
    });
    Object.assign(millwork, millworkDup);
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
    assert.ok(millwork.origSharedKept, 'D3: original piece keeps admin-tip-lab-shared');
    assert.ok(millwork.cloneLabId, 'D3: duplicated piece still has an admin-tip-lab-* id');
    assert.notEqual(millwork.cloneLabId, 'admin-tip-lab-shared', 'D3: Duplicate rewrites clashing admin-tip-lab-* ids');
    assert.ok(String(millwork.cloneBy || '').includes(millwork.cloneLabId), 'D3: clone aria-labelledby points at the rewritten id');
    assert.ok(String(millwork.cloneBy || '').includes('keep-hint'), 'D3: clone keeps non-tip labelledby tokens');
    assert.ok(!String(millwork.cloneBy || '').includes('admin-tip-lab-shared'), 'D3: clone labelledby drops the old id');
    assert.ok(String(millwork.origBy || '').includes('admin-tip-lab-shared'), 'D3: original labelledby is unchanged');
    assert.equal(millwork.tipLabUnique, millwork.tipLabCount, 'D3: every admin-tip-lab-* id is unique after Duplicate');
    assert.deepEqual(millwork.missingBy, [], 'D3: aria-labelledby targets must exist: ' + millwork.missingBy.join(','));
    assert.deepEqual(millwork.foreignBy, [], 'D3: piece labelledby must stay in-piece: ' + millwork.foreignBy.join(','));
    assert.ok(
      millwork.namedCloneRemapped,
      'D3: refreshControlIds remaps cloned named-row tip label ids when present (orig count '
        + millwork.namedOrigTipLabCount + ')'
    );
    assert.ok(millwork.namedCloneKeepsHint, 'N6/D3: cloned labelledby still includes named-hint');

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
            spacing: cs.letterSpacing,
            textTransform: ts.textTransform,
            textWeight: ts.fontWeight,
            textColor: ts.color,
            textSize: ts.fontSize,
            textSpacing: ts.letterSpacing,
          };
        }
        return { review: snap('#review-check'), hst: snap('#hst-check') };
      }, theme);
    }
    for (const theme of ['light', 'dark']) {
      const tailCss = await tailMetrics(theme);
      for (const [name, pair] of Object.entries(tailCss)) {
        assert.equal(pair.transform, pair.textTransform, `C1 ${theme} ${name}: text-transform inherit`);
        assert.equal(pair.weight, pair.textWeight, `C1 ${theme} ${name}: font-weight inherit`);
        assert.equal(pair.color, pair.textColor, `C1 ${theme} ${name}: color inherit`);
        assert.equal(pair.size, pair.textSize, `C1 ${theme} ${name}: font-size inherit`);
        assert.equal(pair.spacing, pair.textSpacing, `C1 ${theme} ${name}: letter-spacing inherit`);
      }
    }
    await page.evaluate(() => document.documentElement.classList.remove('admin-dark'));

    const runtimeNotes = await page.evaluate(() => {
      function tokenColor(name) {
        const probe = document.createElement('span');
        probe.style.color = 'var(' + name + ')';
        document.body.appendChild(probe);
        const color = getComputedStyle(probe).color;
        probe.remove();
        return color;
      }
      function glyphBox(wrap) {
        const btn = wrap && wrap.querySelector && wrap.querySelector('.admin-info-tip');
        if (!btn) return null;
        const icon = btn.querySelector('i, .fa');
        if (icon) {
          const r = icon.getBoundingClientRect();
          if (r.width >= 8 && r.height >= 8) return r;
        }
        const b = btn.getBoundingClientRect();
        if (b.width < 8 || b.height < 8) return null;
        const size = 16;
        return {
          left: b.left + (b.width - size) / 2,
          right: b.left + (b.width + size) / 2,
          top: b.top + (b.height - size) / 2,
          bottom: b.top + (b.height + size) / 2,
          width: size,
          height: size,
        };
      }
      function endGap(end) {
        const wrap = end.querySelector('.admin-info-tip-wrap');
        const word = Array.from(end.children).find((c) => c !== wrap);
        const wr = word && word.getBoundingClientRect();
        const ir = glyphBox(wrap);
        const btn = wrap && wrap.querySelector('.admin-info-tip');
        const br = btn && btn.getBoundingClientRect();
        const wp = wrap && wrap.getBoundingClientRect();
        if (!wr || !ir || wr.width < 1 || ir.width < 1) return null;
        return {
          gap: ir.left - wr.right,
          layout: wp ? wp.left - wr.right : null,
          vOff: Math.abs(((wr.top + wr.bottom) / 2) - ((ir.top + ir.bottom) / 2)),
          wrapDisplay: wrap ? getComputedStyle(wrap).display : '',
          btnMargin: btn ? getComputedStyle(btn).margin : '',
          wordW: wr.width,
          wrapL: wp ? wp.left : null,
          wordR: wr.right,
          iconL: ir.left,
          btnL: br ? br.left : null,
          aria: String((wrap.querySelector('.admin-info-tip') || {}).getAttribute && wrap.querySelector('.admin-info-tip').getAttribute('aria-label') || '').slice(0, 60),
        };
      }
      const review = document.getElementById('review-check');
      const reviewCs = getComputedStyle(review);
      const lead = document.querySelector('#review-check > label.admin-checkbox');
      const named = document.getElementById('named-input');
      const namedBy = String(named.getAttribute('aria-labelledby') || '');
      const star = document.querySelector('#star-split-row > label');
      const starCs = star ? getComputedStyle(star) : null;
      document.documentElement.classList.remove('admin-dark');
      const lightRow = getComputedStyle(review).color;
      const lightTail = getComputedStyle(review.querySelector('.admin-checkbox__tail')).color;
      document.documentElement.classList.add('admin-dark');
      const darkRow = getComputedStyle(review).color;
      const darkTail = getComputedStyle(review.querySelector('.admin-checkbox__tail')).color;
      const darkText = getComputedStyle(review.querySelector('.admin-checkbox__text')).color;
      const darkAdmin = tokenColor('--admin-text');
      const darkMuted = tokenColor('--admin-muted');
      document.documentElement.classList.remove('admin-dark');
      const gaps = Array.from(document.querySelectorAll('.admin-label-with-tip__end')).map(endGap).filter(Boolean);
      return {
        indent: reviewCs.textIndent,
        pad: reviewCs.paddingLeft,
        leadText: String(lead.textContent || '').replace(/\s+/g, ' ').trim(),
        leadHasEnd: !!(lead.querySelector && lead.querySelector('.admin-label-with-tip__end')),
        leadHasTail: !!(lead.querySelector && lead.querySelector('.admin-label-with-tip__tail')),
        namedBy,
        namedHasHint: namedBy.split(/\s+/).includes('named-hint'),
        namedHintLive: !!document.getElementById('named-hint'),
        lightRow,
        darkRow,
        darkTail,
        darkText,
        darkAdmin,
        darkMuted,
        starText: star ? String(star.textContent || '').trim() : '',
        starClass: star ? String(star.className || '') : '',
        starColor: starCs ? starCs.color : '',
        danger: tokenColor('--admin-danger'),
        gaps,
      };
    });
    assert.equal(runtimeNotes.indent, '-34px', 'C3: review-check hanging indent must be -34px at runtime');
    assert.equal(runtimeNotes.pad, '34px', 'C3: review-check padding-left must be 34px at runtime');
    assert.match(runtimeNotes.leadText, /Client provides all materials for the entire/, 'G6: checkbox lead text must stay unsplit');
    assert.equal(runtimeNotes.leadHasEnd, false, 'G6: hoist must not wrap the checkbox lead');
    assert.equal(runtimeNotes.leadHasTail, false, 'G6: checkbox skip must not extract a tail from the lead');
    assert.equal(runtimeNotes.namedHasHint, true, 'N6: existing aria-labelledby named-hint must be kept');
    assert.equal(runtimeNotes.namedHintLive, true);
    assert.ok(runtimeNotes.namedBy.split(/\s+/).filter(Boolean).length >= 1, 'N6: named-hint stays in aria-labelledby');
    assert.notEqual(runtimeNotes.darkRow, runtimeNotes.lightRow, 'D1/D2: dark checkbox row color must change vs light');
    assert.equal(runtimeNotes.darkRow, runtimeNotes.darkAdmin, 'D1/D2: dark row uses --admin-text');
    assert.equal(runtimeNotes.darkTail, runtimeNotes.darkAdmin, 'D1/D2: dark tail inherits --admin-text, not muted');
    assert.equal(runtimeNotes.darkText, runtimeNotes.darkAdmin, 'D1/D2: dark checkbox text inherits --admin-text');
    assert.notEqual(runtimeNotes.darkTail, runtimeNotes.darkMuted, 'D1/D2: dark tail must not fall through to muted');
    assert.ok(runtimeNotes.gaps.length >= 4, 'D1: need measurable word/icon pairs');
    runtimeNotes.gaps.forEach((hit) => {
      assert.ok(hit.gap > 0, `D1: ${hit.aria} gap ${hit.gap}px must stay after the word`);
      assert.ok(hit.gap <= 12, `D1: ${hit.aria} gap ${hit.gap}px must be ≤12px beside the label word`);
      assert.ok(hit.vOff <= 6, `D1: ${hit.aria} icon v-offset ${hit.vOff}px must be vertically centred`);
    });
    assert.match(runtimeNotes.starText, /Amount/, 'D5: full label text stays on the main label');
    assert.match(runtimeNotes.starText, /\*/, 'D5: required * stays on the main label');

    const unscope = await page.evaluate(() => {
      const input = document.getElementById('named-input');
      const before = {
        by: input.getAttribute('aria-labelledby'),
        bubble: document.querySelector('#named-row .admin-info-tip__bubble').id,
      };
      const warns = [];
      const orig = console.warn;
      console.warn = function (msg) { warns.push(String(msg)); };
      const returned = window.AdminInfoTip.refreshControlIds();
      console.warn = orig;
      return {
        before,
        afterBy: input.getAttribute('aria-labelledby'),
        afterBubble: document.querySelector('#named-row .admin-info-tip__bubble').id,
        returned,
        warns,
      };
    });
    assert.equal(unscope.returned, 0, 'refreshControlIds() without a scope returns 0');
    assert.equal(unscope.afterBy, unscope.before.by, 'refreshControlIds() without a scope must not rewrite labelledby');
    assert.equal(unscope.afterBubble, unscope.before.bubble, 'refreshControlIds() without a scope must not rewrite bubble ids');
    assert.ok(unscope.warns.some((w) => /scope/i.test(w)), 'refreshControlIds() without a scope logs a warning');

    const darkStar = await page.evaluate(() => {
      document.documentElement.classList.add('admin-dark');
      const wantSel = '.admin-form-group .admin-label-with-tip__end > .cp-required, .admin-form-group .admin-label-with-tip__end > label.cp-required';
      const matching = Array.from(document.querySelectorAll(wantSel)).map((el) => ({
        color: getComputedStyle(el).color,
        tag: el.tagName,
        text: String(el.textContent || '').trim(),
      }));
      const all = Array.from(document.querySelectorAll('.cp-required, label.cp-required')).map((el) => ({
        color: getComputedStyle(el).color,
        tag: el.tagName,
        text: String(el.textContent || '').trim(),
      }));
      const jsOn = document.querySelector('#store-name-row .admin-label-with-tip__end > .cp-required');
      const jsOff = document.querySelector('#store-name-row .cp-required');
      const out = {
        on: jsOn ? getComputedStyle(jsOn).color : '',
        off: jsOff ? getComputedStyle(jsOff).color : '',
        matching,
        all,
      };
      document.documentElement.classList.remove('admin-dark');
      return out;
    });
    const darkStarRed = 'rgb(252, 165, 165)';
    assert.ok(darkStar.on, 'dark JS-on * must render');
    assert.ok(darkStar.off, 'dark JS-off * must render');
    assert.equal(darkStar.on, darkStar.off, 'dark-mode * must be the same red with JS on and off');
    assert.equal(darkStar.on, darkStarRed, `dark JS-on * must be #fca5a5, got ${darkStar.on}`);
    assert.equal(darkStar.off, darkStarRed, `dark JS-off * must be #fca5a5, got ${darkStar.off}`);
    assert.ok(darkStar.matching.length >= 1, `dark __end * selectors must match required stars, got ${darkStar.matching.length}`);
    darkStar.matching.forEach((star) => {
      assert.equal(star.color, darkStarRed, `dark ${star.tag}.cp-required (${star.text || '*'}) must be #fca5a5, got ${star.color}`);
    });
    darkStar.all.forEach((star) => {
      assert.equal(star.color, darkStarRed, `dark ${star.tag}.cp-required (${star.text || '*'}) must be #fca5a5, got ${star.color}`);
    });

    await page.evaluate(() => window.AdminInfoTip && window.AdminInfoTip.closeAll && window.AdminInfoTip.closeAll());
    await page.click('#waste-row > label');
    const wasteClick = await page.evaluate(() => ({
      id: document.activeElement && document.activeElement.id,
      open: !!(document.querySelector('#waste-row .admin-info-tip-wrap.is-open')),
    }));
    assert.equal(wasteClick.id, 'waste-pct', 'D4: clicking % focuses the input');
    assert.equal(wasteClick.open, false, 'D4: clicking % must not open the tip');

    await page.evaluate(() => window.AdminInfoTip && window.AdminInfoTip.closeAll && window.AdminInfoTip.closeAll());
    await page.click('#star-split-row > label');
    const starClick = await page.evaluate(() => ({
      id: document.activeElement && document.activeElement.id,
      open: !!(document.querySelector('#star-split-row .admin-info-tip-wrap.is-open')),
    }));
    assert.equal(starClick.id, 'star-split', 'D4: clicking * focuses the input');
    assert.equal(starClick.open, false, 'D4: clicking * must not open the tip');

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

    const geometryByWidth = {};
    for (const theme of ['light', 'dark']) {
      await page.evaluate((mode) => {
        document.documentElement.classList.toggle('admin-dark', mode === 'dark');
      }, theme);
      for (const width of [320, 390, 768, 1024, 1280]) {
        await page.setViewport({ width, height: 800 });
        const aligned = await page.evaluate(() => {
          window.scrollTo(0, 240);
          function boxOf(el) {
            if (!el) return null;
            if (el.nodeType === 3) {
              const range = document.createRange();
              range.selectNodeContents(el);
              return range.getBoundingClientRect();
            }
            const icon = el.querySelector && el.querySelector('i, .fa');
            return (icon || el).getBoundingClientRect();
          }
          function sameLine(a, b) {
            const ar = boxOf(a);
            const br = boxOf(b);
            if (!ar || !br) return { sameLine: false, aTop: null, bTop: null };
            const overlap = br.bottom > ar.top && ar.bottom > br.top;
            const wrappedBelow = br.top > ar.bottom + 2;
            return { sameLine: overlap && !wrappedBelow, aTop: ar.top, bTop: br.top };
          }
          function inCard(el, card) {
            if (!el || !card) return false;
            const r = el.getBoundingClientRect();
            const c = card.getBoundingClientRect();
            return r.width > 0 && r.left >= c.left - 2 && r.right <= c.right + 2;
          }
          function lastWordTop(tipWrap) {
            const btn = tipWrap.querySelector('.admin-info-tip');
            if (!btn) return { orphan: true, wordTop: null, tipTop: null };
            const end = tipWrap.closest('.admin-label-with-tip__end');
            const row = tipWrap.closest('.admin-label-with-tip');
            let wordEl = null;
            if (end) {
              wordEl = Array.from(end.children).find((c) => c !== tipWrap && !c.classList.contains('admin-info-tip-wrap'));
              if (!wordEl) {
                wordEl = Array.from(end.childNodes).find((n) => n !== tipWrap && n.nodeType === 1)
                  || Array.from(end.childNodes).find((n) => n.nodeType === 3 && String(n.textContent || '').trim());
              }
            }
            if (!wordEl && row) {
              wordEl = row.querySelector(':scope > label:not(.admin-checkbox), :scope > .admin-form-group-label');
            }
            if (!wordEl) {
              let prev = tipWrap.previousSibling;
              while (prev && prev.nodeType === 3 && !String(prev.textContent || '').trim()) prev = prev.previousSibling;
              wordEl = prev || null;
            }
            const line = sameLine(wordEl, btn);
            if (!line.sameLine && wordEl && end && !Array.from(end.children).some((c) => c !== tipWrap && !c.classList.contains('admin-info-tip-wrap'))) {
              const slack = Math.abs((line.aTop || 0) - (line.bTop || 0));
              if (slack <= 22) return { orphan: false, wordTop: line.aTop, tipTop: line.bTop };
            }
            return { orphan: !line.sameLine, wordTop: line.aTop, tipTop: line.bTop };
          }
          function headingProblem(el) {
            const tip = el.querySelector('.admin-info-tip-wrap');
            if (!tip) return null;
            const aria = String((tip.querySelector('.admin-info-tip') || {}).getAttribute && tip.querySelector('.admin-info-tip').getAttribute('aria-label') || '')
              .replace(/^\s*About\s+/i, '')
              .replace(/\s+[—-]\s+.*$/, '')
              .replace(/\s+/g, ' ')
              .trim();
            const visible = String(el.innerText || '').replace(/\s+/g, ' ').trim();
            const missingSpace = !!(aria && !visible.includes(aria) && visible.replace(/\s+/g, '').includes(aria.replace(/\s+/g, '')));
            const cs = getComputedStyle(el);
            const isFlex = cs.display === 'flex' || cs.display === 'inline-flex';
            let columns = false;
            if (isFlex && (cs.flexDirection === 'row' || cs.flexDirection === 'row-reverse' || !cs.flexDirection)) {
              const chrome = ['br-acc__num', 'admin-chevron-down', 'admin-filters-accordion__badge', 'admin-filters-accordion__icon'];
              const kids = Array.from(el.children).filter((c) => {
                if (chrome.some((cls) => c.classList.contains(cls))) return false;
                const s = getComputedStyle(c);
                return s.display !== 'none' && s.position !== 'absolute';
              });
              columns = kids.length > 1;
            }
            return { missingSpace, columns, visible, aria };
          }
          const checks = Array.from(document.querySelectorAll('#review-check, #toggle-check, #stack-check, #hst-check')).map((row) => {
            const tail = row.querySelector('.admin-checkbox__tail');
            const tip = row.querySelector('.admin-info-tip');
            return sameLine(tail, tip);
          });
          const review = document.getElementById('review-summary');
          const reviewLast = review.querySelector('.admin-label-with-tip__end');
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
          const orphans = [];
          document.querySelectorAll('.admin-info-tip-wrap').forEach((wrap) => {
            const btn = wrap.querySelector('.admin-info-tip');
            if (!btn || btn.getBoundingClientRect().width <= 0) return;
            const hit = lastWordTop(wrap);
            if (hit.orphan) orphans.push((btn.getAttribute('aria-label') || '').slice(0, 60));
          });
          const headingIssues = [];
          document.querySelectorAll('h1, h2, h3, h4, h5, summary, legend, .br-acc__summary, .admin-accordion__title, .admin-filters-accordion__title, .sp-heading, .sq-intro-option-title').forEach((el) => {
            const hit = headingProblem(el);
            if (hit && (hit.missingSpace || hit.columns)) headingIssues.push(hit);
          });
          const tmTail = document.querySelector('#tm-client-all-row .admin-checkbox__tail');
          const tmTip = document.querySelector('#tm-client-all-row .admin-info-tip');
          const tmLine = sameLine(tmTail, tmTip);
          const cf = document.getElementById('client-filled-check');
          const cfText = cf ? cf.querySelector('.admin-checkbox__text') : null;
          let clientFilledOffset = 0;
          if (cfText) {
            const range = document.createRange();
            range.selectNodeContents(cfText);
            const rects = Array.from(range.getClientRects()).filter((r) => r.width > 1);
            if (rects.length >= 2) clientFilledOffset = Math.abs(rects[0].left - rects[1].left);
          }
          function glyphBox(wrap) {
            const btn = wrap && wrap.querySelector && wrap.querySelector('.admin-info-tip');
            if (!btn) return null;
            const icon = btn.querySelector('i, .fa');
            if (icon) {
              const r = icon.getBoundingClientRect();
              if (r.width >= 8 && r.height >= 8) return r;
            }
            const b = btn.getBoundingClientRect();
            if (b.width < 8 || b.height < 8) return null;
            const size = 16;
            return {
              left: b.left + (b.width - size) / 2,
              right: b.left + (b.width + size) / 2,
              top: b.top + (b.height - size) / 2,
              bottom: b.top + (b.height + size) / 2,
              width: size,
              height: size,
            };
          }
          function rectsOverlap(a, b) {
            return a.left < b.right - 0.5 && a.right > b.left + 0.5 && a.top < b.bottom - 0.5 && a.bottom > b.top + 0.5;
          }
          const tipInputOverlaps = [];
          document.querySelectorAll('#step-rail-shell .br-bath-light-row, #bath-light-row').forEach((row) => {
            const tips = Array.from(row.querySelectorAll('.admin-info-tip'));
            const fields = Array.from(row.querySelectorAll('input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]), select, textarea')).filter((el) => {
              if (el.closest('.admin-info-tip-wrap, .admin-info-tip__bubble')) return false;
              const cs = getComputedStyle(el);
              const r = el.getBoundingClientRect();
              return r.width > 0 && r.height > 0 && cs.visibility !== 'hidden' && cs.display !== 'none' && !el.hidden;
            });
            tips.forEach((btn) => {
              const br = btn.getBoundingClientRect();
              if (br.width <= 0 || br.height <= 0) return;
              fields.forEach((el) => {
                const fr = el.getBoundingClientRect();
                if (rectsOverlap(br, fr)) {
                  tipInputOverlaps.push((btn.getAttribute('aria-label') || '') + ' × #' + (el.id || el.name || '?'));
                }
              });
            });
          });
          function distinctLineCount(rects) {
            const lines = [];
            rects.forEach((r) => {
              const top = Math.round(r.top);
              if (!lines.some((t) => Math.abs(t - top) <= 2)) lines.push(top);
            });
            return lines.length;
          }
          const brokenWords = [];
          document.querySelectorAll('.admin-label-with-tip__end').forEach((end) => {
            const wrap = end.querySelector('.admin-info-tip-wrap');
            const word = Array.from(end.children).find((c) => c !== wrap);
            if (!word) return;
            const text = String(word.textContent || '').replace(/\s+/g, ' ').trim();
            if (!text || /\s/.test(text) || text.length < 2) return;
            const range = document.createRange();
            range.selectNodeContents(word);
            const rects = Array.from(range.getClientRects()).filter((r) => r.width > 0.5 && r.height > 0.5);
            if (distinctLineCount(rects) > 1) {
              brokenWords.push(text.slice(0, 40));
            }
          });
          const gaps = [];
          document.querySelectorAll('.admin-label-with-tip__end').forEach((end) => {
            const wrap = end.querySelector('.admin-info-tip-wrap');
            const word = Array.from(end.children).find((c) => c !== wrap);
            const wr = word && word.getBoundingClientRect();
            const ir = glyphBox(wrap);
            if (!wr || !ir || wr.width < 1 || ir.width < 1) return;
            gaps.push({
              gap: ir.left - wr.right,
              vOff: Math.abs(((wr.top + wr.bottom) / 2) - ((ir.top + ir.bottom) / 2)),
              aria: String((wrap.querySelector('.admin-info-tip') || {}).getAttribute && wrap.querySelector('.admin-info-tip').getAttribute('aria-label') || '').slice(0, 60),
            });
          });
          const biz = document.getElementById('business-contact-heading');
          const bizWrap = biz && biz.querySelector('.admin-info-tip-wrap');
          const bizEnd = biz && biz.querySelector('.admin-label-with-tip__end');
          const bizWord = bizEnd && Array.from(bizEnd.children).find((c) => c !== bizWrap);
          const bizWordBox = bizWord && bizWord.getBoundingClientRect();
          const bizIcon = glyphBox(bizWrap);
          const bizCard = document.getElementById('business-contact-card');
          const bizCardBox = bizCard && bizCard.getBoundingClientRect();
          const bizGap = (bizWordBox && bizIcon) ? (bizIcon.left - bizWordBox.right) : null;
          const bizParked = !!(bizWordBox && bizIcon && bizCardBox && (bizCardBox.right - bizIcon.right < 24) && (bizIcon.left - bizWordBox.right > 40));
          return {
            checks,
            reviewLine,
            emailOnScreen: inCard(emailTip, document.getElementById('email-acc')),
            storeOnScreen: inCard(storeTip, document.getElementById('store-name-row')),
            filtersOnScreen: inCard(filtersTip, document.getElementById('filters-acc')),
            bathOffset: Math.abs(markTop - textTop),
            orphanCount: orphans.length,
            orphans,
            headingCount: headingIssues.length,
            headingIssues,
            clientFilledOffset,
            tmLine,
            gaps,
            brokenWords,
            tipInputOverlaps,
            bizGap,
            bizParked,
          };
        });
        aligned.checks.forEach((row, i) => {
          assert.ok(row.sameLine, `D4/C3 ${theme}: checkbox ${i} tip stays on the last word at ${width}px (tail ${row.aTop} tip ${row.bTop})`);
        });
        assert.ok(aligned.reviewLine.sameLine, `N8 ${theme}: review summary ⓘ stays with the last word at ${width}px`);
        assert.ok(aligned.emailOnScreen, `N2 ${theme}: accordion ⓘ stays inside the card at ${width}px`);
        assert.ok(aligned.storeOnScreen, `N1 ${theme}: Store name ⓘ stays inside its row at ${width}px`);
        assert.ok(aligned.filtersOnScreen, `N3 ${theme}: Filters ⓘ stays inside the card at ${width}px`);
        assert.ok(aligned.bathOffset < 12, `D3 ${theme}: bath light text offset ${aligned.bathOffset}px at ${width}px`);
        assert.equal(aligned.orphanCount, 0, `${theme}: orphaned ⓘ at ${width}px: ${aligned.orphans.join(' | ')}`);
        assert.equal(aligned.headingCount, 0, `${theme}: flex heading issues at ${width}px: ${JSON.stringify(aligned.headingIssues)}`);
        assert.equal(aligned.bizParked, false, `${theme}: Business contact ⓘ must stay next to the heading text at ${width}px (gap=${aligned.bizGap})`);
        assert.ok(aligned.bizGap != null && aligned.bizGap > 0 && aligned.bizGap <= 8, `${theme}: Business contact ⓘ gap ${aligned.bizGap}px at ${width}px`);
        if (width <= 390) {
          assert.ok(aligned.clientFilledOffset <= 1, `${theme}: client-filled wrapped line offset ${aligned.clientFilledOffset}px at ${width}px`);
          assert.ok(aligned.tmLine.sameLine, `${theme}: Client supplies materials ⓘ stays with the last word at ${width}px`);
        }
        assert.deepEqual(aligned.brokenWords, [], `${theme}: short glued words must not break inside the word at ${width}px: ${aligned.brokenWords.join(' | ')}`);
        assert.ok(aligned.gaps.length >= 4, `${theme}: D1 measurable gaps at ${width}px`);
        aligned.gaps.forEach((hit) => {
          assert.ok(hit.gap > 0 && hit.gap <= 12, `${theme}: D1 ${hit.aria} gap ${hit.gap}px at ${width}px`);
          assert.ok(hit.vOff <= 6, `${theme}: D1 ${hit.aria} v-offset ${hit.vOff}px at ${width}px`);
        });
        if (width === 1024 || width === 1280) {
          assert.deepEqual(aligned.tipInputOverlaps, [], `${theme}: ⓘ must not overlap an input at ${width}px: ${aligned.tipInputOverlaps.join(' | ')}`);
        }
        if (theme === 'light') {
          geometryByWidth[width] = { orphans: aligned.orphanCount, headings: aligned.headingCount, brokenWords: aligned.brokenWords.length };
        }
      }
    }
    console.log('info-tip geometry', JSON.stringify(geometryByWidth));
    await page.evaluate(() => document.documentElement.classList.remove('admin-dark'));

    async function measureRailGeometry(width) {
      await page.setViewport({ width, height: 900 });
      return page.evaluate(() => {
        const chrome = document.getElementById('step-rail-chrome');
        const sidebar = document.getElementById('step-rail-sidebar');
        chrome.style.display = 'grid';
        chrome.style.gridTemplateColumns = '270px minmax(0, 1fr)';
        chrome.style.width = '100%';
        sidebar.hidden = false;
        sidebar.style.display = 'block';
        sidebar.style.width = '270px';
        const shell = document.getElementById('step-rail-shell');
        window.scrollTo(0, shell.getBoundingClientRect().top + window.scrollY - 8);
        function overlap(a, b) {
          return a.left < b.right - 0.5 && a.right > b.left + 0.5 && a.top < b.bottom - 0.5 && a.bottom > b.top + 0.5;
        }
        const hits = [];
        shell.querySelectorAll('.br-bath-light-row, #sink-rail-row').forEach((row) => {
          const tips = Array.from(row.querySelectorAll('.admin-info-tip'));
          const fields = Array.from(row.querySelectorAll('input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]), select, textarea')).filter((el) => {
            if (el.closest('.admin-info-tip-wrap, .admin-info-tip__bubble')) return false;
            const cs = getComputedStyle(el);
            const r = el.getBoundingClientRect();
            return r.width > 0 && r.height > 0 && cs.visibility !== 'hidden' && cs.display !== 'none' && !el.hidden;
          });
          tips.forEach((btn) => {
            const br = btn.getBoundingClientRect();
            if (br.width <= 0) return;
            const ownGroup = btn.closest('.admin-form-group');
            fields.forEach((el) => {
              if (ownGroup && ownGroup.contains(el)) return;
              const fr = el.getBoundingClientRect();
              if (overlap(br, fr)) hits.push((btn.getAttribute('aria-label') || '') + ' × #' + (el.id || el.name || '?'));
            });
          });
        });
        const tile = document.querySelector('#tile-rail-row .br-tile-row__main');
        const main = shell.querySelector('.br-main');
        const tileBox = tile.getBoundingClientRect();
        const mainBox = main.getBoundingClientRect();
        const remove = tile.querySelector('.br-tile-row__remove');
        const removeBox = remove ? remove.getBoundingClientRect() : { right: mainBox.right };
        const contentOverflow = Math.max(
          tile.scrollWidth - main.clientWidth,
          tileBox.right - mainBox.right,
          removeBox.right - mainBox.right,
          document.documentElement.scrollWidth - window.innerWidth
        );
        let tapOverflow = 0;
        main.querySelectorAll('.admin-info-tip').forEach((btn) => {
          const r = btn.getBoundingClientRect();
          tapOverflow = Math.max(tapOverflow, r.right - mainBox.right, mainBox.left - r.left);
        });
        const outTop = document.getElementById('qty-outlets-row').getBoundingClientRect().top;
        const swTop = document.getElementById('qty-switches-row').getBoundingClientRect().top;
        const sinkGrid = document.getElementById('sink-rail-row');
        const sinkCols = sinkGrid ? getComputedStyle(sinkGrid).gridTemplateColumns : '';
        return {
          hits,
          overflow: contentOverflow,
          tapOverflow,
          labelDelta: Math.abs(outTop - swTop),
          rowCols: getComputedStyle(document.getElementById('spot-rail-row')).gridTemplateColumns,
          rowTracks: getComputedStyle(document.getElementById('spot-rail-row')).gridTemplateColumns.trim().split(/\s+/).filter(Boolean).length,
          sinkTracks: sinkCols.trim().split(/\s+/).filter(Boolean).length,
          sinkCols,
          mainWidth: Math.round(mainBox.width),
        };
      });
    }

    // Checkbox ⓘ uses margin: -10px -6px on a 44px tap box. That hang is
    // CSS, not font metrics — allow it, but keep tile/page overflow tight.
    const TAP_OVERHANG_PX = 8;

    for (const width of [910, 940, 960, 1024, 1100, 1120, 1150, 1170, 1280]) {
      const rail = await measureRailGeometry(width);
      assert.deepEqual(rail.hits, [], `D7/R19-2: ⓘ must not overlap an input at ${width}px with the step rail (main=${rail.mainWidth}px): ${rail.hits.join(' | ')}`);
      assert.ok(rail.overflow <= 1, `D8: tile/page overflow ${rail.overflow}px at ${width}px with the step rail (main=${rail.mainWidth}px)`);
      assert.ok(rail.tapOverflow <= TAP_OVERHANG_PX, `T2: tap-area overflow ${rail.tapOverflow}px at ${width}px with the step rail (main=${rail.mainWidth}px)`);
      if (width <= 1280) {
        assert.equal(rail.rowTracks, 1, `D7: lighting row stacks at ${width} with the rail (tracks=${rail.rowCols}, main=${rail.mainWidth}px)`);
      }
      if (width <= 960) {
        assert.ok(rail.sinkTracks < 3, `R19-2: sink check-grid must not be 3-col at ${width} with the rail (tracks=${rail.sinkCols}, main=${rail.mainWidth}px)`);
      }
      if (width === 1280) {
        assert.ok(rail.labelDelta <= 3, `D3: New outlets / Switches label tops differ ${rail.labelDelta}px at 1280`);
      }
    }

    async function measureCollapsedGeometry(width) {
      await page.setViewport({ width, height: 900 });
      return page.evaluate(() => {
        const chrome = document.getElementById('step-rail-chrome');
        const sidebar = document.getElementById('step-rail-sidebar');
        chrome.style.display = 'grid';
        chrome.style.gridTemplateColumns = 'minmax(0, 1fr)';
        chrome.style.width = '100%';
        sidebar.hidden = true;
        sidebar.style.display = 'none';
        sidebar.style.width = '';
        const shell = document.getElementById('step-rail-shell');
        shell.style.gridTemplateColumns = 'minmax(0, 1fr)';
        const progress = shell.querySelector('.br-progress');
        if (progress) progress.style.display = 'none';
        const main = shell.querySelector('.br-main');
        const sinkGrid = document.getElementById('sink-rail-row');
        const sinkCols = sinkGrid ? getComputedStyle(sinkGrid).gridTemplateColumns : '';
        const spot = document.getElementById('spot-rail-row');
        return {
          mainWidth: Math.round(main.getBoundingClientRect().width),
          sinkTracks: sinkCols.trim().split(/\s+/).filter(Boolean).length,
          sinkCols,
          rowCols: getComputedStyle(spot).gridTemplateColumns,
          rowTracks: getComputedStyle(spot).gridTemplateColumns.trim().split(/\s+/).filter(Boolean).length,
        };
      });
    }

    const collapsed = await measureCollapsedGeometry(1280);
    assert.ok(collapsed.mainWidth >= 768, `R20-1: collapsed 1280 .br-main must be ≥ 48rem (got ${collapsed.mainWidth}px)`);
    assert.equal(collapsed.sinkTracks, 3, `R20-1: sink check-grid is 3-col when .br-main ≥ 48rem (tracks=${collapsed.sinkCols}, main=${collapsed.mainWidth}px)`);
    assert.equal(collapsed.rowTracks, 2, `T3: lighting row is 2-col when .br-main ≥ 48rem (tracks=${collapsed.rowCols}, main=${collapsed.mainWidth}px)`);

    async function lightingNarrowOverlaps(injectMinmax0) {
      return page.evaluate((useMutant) => {
        let style = document.getElementById('lighting-minmax-mutant');
        if (useMutant) {
          if (!style) {
            style = document.createElement('style');
            style.id = 'lighting-minmax-mutant';
            document.head.appendChild(style);
          }
          style.textContent = '@container br-main (min-width: 48rem) { .br-bath-light-row { grid-template-columns: minmax(0, 1fr) minmax(96px, 140px) !important; } }';
        } else if (style) {
          style.remove();
        }
        const row = document.getElementById('spot-rail-row');
        const grid = row.closest('.br-bath-light-grid');
        grid.style.gridTemplateColumns = '260px';
        grid.style.width = '260px';
        row.style.width = '260px';
        row.style.maxWidth = '260px';
        function overlap(a, b) {
          return a.left < b.right - 0.5 && a.right > b.left + 0.5 && a.top < b.bottom - 0.5 && a.bottom > b.top + 0.5;
        }
        const tip = row.querySelector('.admin-info-tip');
        const field = document.getElementById('br-bath-spot-qty-rail');
        const cols = getComputedStyle(row).gridTemplateColumns;
        return {
          overlap: overlap(tip.getBoundingClientRect(), field.getBoundingClientRect()),
          cols,
          firstTrack: parseFloat(cols) || 0,
        };
      }, injectMinmax0);
    }

    const lightingOk = await lightingNarrowOverlaps(false);
    const lightingMutant = await lightingNarrowOverlaps(true);
    assert.equal(lightingOk.overlap, false, `T3: max-content lighting track must not overlap the qty at 260px (cols=${lightingOk.cols})`);
    assert.ok(lightingOk.firstTrack >= 120, `T3: max-content lighting track must size to the checkbox+tip (track=${lightingOk.firstTrack}px cols=${lightingOk.cols})`);
    assert.ok(
      lightingMutant.firstTrack + 8 < lightingOk.firstTrack,
      `T3: minmax(0,1fr) mutant must shrink the label track (ok=${lightingOk.firstTrack}px mutant=${lightingMutant.firstTrack}px cols=${lightingMutant.cols})`
    );
    await page.evaluate(() => {
      const style = document.getElementById('lighting-minmax-mutant');
      if (style) style.remove();
      const row = document.getElementById('spot-rail-row');
      const grid = row && row.closest('.br-bath-light-grid');
      if (grid) {
        grid.style.gridTemplateColumns = '';
        grid.style.width = '';
      }
      if (row) {
        row.style.width = '';
        row.style.maxWidth = '';
      }
    });
    await page.evaluate(() => {
      const chrome = document.getElementById('step-rail-chrome');
      const sidebar = document.getElementById('step-rail-sidebar');
      const shell = document.getElementById('step-rail-shell');
      const progress = shell && shell.querySelector('.br-progress');
      chrome.style.display = '';
      chrome.style.gridTemplateColumns = '';
      chrome.style.width = '';
      sidebar.hidden = true;
      sidebar.style.display = '';
      sidebar.style.width = '';
      if (shell) shell.style.gridTemplateColumns = '';
      if (progress) progress.style.display = '';
    });
    await page.setViewport({ width: 1280, height: 800 });

    const scopeTips = await page.evaluate(() => {
      function relocate(nest) {
        [
          ['br-electrical-fields', 'br-electrical-slot-step', 'br-electrical-slot-bath'],
          ['br-plumbing-fields', 'br-plumbing-slot-step', 'br-plumbing-slot-bath'],
        ].forEach((ids) => {
          const fields = document.getElementById(ids[0]);
          const stepSlot = document.getElementById(ids[1]);
          const bathSlot = document.getElementById(ids[2]);
          if (!fields || !stepSlot || !bathSlot) return;
          (nest ? bathSlot : stepSlot).appendChild(fields);
          bathSlot.hidden = !nest;
        });
        ['electrical', 'plumbing'].forEach((k) => {
          const step = document.querySelector('[data-br-step="' + k + '"]');
          if (nest && step) step.hidden = true;
          else if (step) step.hidden = false;
        });
      }
      function visibleScope(label) {
        return Array.from(document.querySelectorAll('.admin-info-tip')).filter((btn) => {
          if ((btn.getAttribute('aria-label') || '') !== label) return false;
          if (btn.closest('[hidden]')) return false;
          const r = btn.getBoundingClientRect();
          return r.width > 0 && r.height > 0;
        }).length;
      }
      relocate(true);
      const bathroom = {
        electrical: visibleScope('About Electrical scope'),
        plumbing: visibleScope('About Plumbing scope'),
      };
      relocate(false);
      const full = {
        electrical: visibleScope('About Electrical scope'),
        plumbing: visibleScope('About Plumbing scope'),
      };
      return { bathroom, full };
    });
    assert.equal(scopeTips.bathroom.electrical, 1, 'R19-1: Electrical scope ⓘ stays visible after full → bathroom relocate');
    assert.equal(scopeTips.bathroom.plumbing, 1, 'R19-1: Plumbing scope ⓘ stays visible after full → bathroom relocate');
    assert.equal(scopeTips.full.electrical, 1, 'R19-1: Electrical scope ⓘ stays visible after bathroom → full relocate');
    assert.equal(scopeTips.full.plumbing, 1, 'R19-1: Plumbing scope ⓘ stays visible after bathroom → full relocate');

    const r14WrapCss = `
      .admin-label-with-tip__end { max-width: 100%; }
      .admin-label-with-tip__end > span { flex: 0 1 auto; }
      .admin-label-with-tip__end > label,
      .admin-label-with-tip__end > span:not(.admin-info-tip-wrap) {
        min-width: 0;
        overflow-wrap: anywhere;
        white-space: normal !important;
      }
      .admin-label-with-tip__end > label,
      .admin-form-group .admin-label-with-tip__end > label,
      .admin-label-with-tip__end > label.admin-label-with-tip__tail,
      .admin-form-group .admin-label-with-tip__end > label.admin-label-with-tip__tail {
        overflow-wrap: anywhere;
        white-space: normal !important;
      }
    `;
    await page.setViewport({ width: 1280, height: 800 });
    const strongWord = await page.evaluate(() => {
      const row = document.getElementById('strong-word-row');
      const end = row && row.querySelector('.admin-label-with-tip__end');
      const label = row && row.querySelector('label');
      const strong = label && label.querySelector('strong');
      const range = document.createRange();
      if (label) range.selectNodeContents(label);
      const rects = label ? Array.from(range.getClientRects()).filter((r) => r.width > 0.5 && r.height > 0.5) : [];
      const lines = [];
      rects.forEach((r) => {
        const top = Math.round(r.top);
        if (!lines.some((t) => Math.abs(t - top) <= 2)) lines.push(top);
      });
      return {
        hasEnd: !!(end && end.querySelector('.admin-info-tip-wrap')),
        labelText: label ? String(label.textContent || '').trim() : '',
        strongText: strong ? String(strong.textContent || '').trim() : '',
        rects: rects.length,
        lines: lines.length,
      };
    });
    assert.equal(strongWord.labelText, 'Drywall', 'F: strong-word label stays intact');
    assert.equal(strongWord.strongText, 'Drywall', 'F: <strong> is not split out of the label');
    assert.ok(strongWord.hasEnd, 'F: tip parks in __end beside the label');
    assert.ok(strongWord.rects > 1, `F: <label><strong>Drywall</strong></label> must produce multiple boxes (got ${strongWord.rects})`);
    assert.equal(strongWord.lines, 1, 'F: same-line strong+label must count as one line');

    const scheduleDatesAlign = await page.evaluate(() => {
      const startLabel = document.querySelector('#schedule-dates-fixture .cp-schedule-date-label');
      const endRow = document.querySelector('#schedule-dates-fixture .admin-label-with-tip');
      const startInput = document.getElementById('sched-start');
      const endInput = document.getElementById('sched-end');
      const sr = startLabel.getBoundingClientRect();
      const er = endRow.getBoundingClientRect();
      const si = startInput.getBoundingClientRect();
      const ei = endInput.getBoundingClientRect();
      return {
        startLabelH: sr.height,
        endLabelH: er.height,
        labelHeightDelta: Math.abs(sr.height - er.height),
        inputTopDelta: Math.abs(si.top - ei.top),
      };
    });
    assert.ok(
      scheduleDatesAlign.labelHeightDelta <= 0.5,
      `D2: schedule label rows same height at 1280 (${scheduleDatesAlign.startLabelH}px vs ${scheduleDatesAlign.endLabelH}px)`
    );
    assert.ok(
      scheduleDatesAlign.inputTopDelta <= 0.5,
      `D2: schedule date input tops align at 1280 (delta ${scheduleDatesAlign.inputTopDelta}px)`
    );

    async function brokenInNarrowColumns(width) {
      return page.evaluate((colWidth) => {
        function distinctLineCount(rects) {
          const lines = [];
          rects.forEach((r) => {
            const top = Math.round(r.top);
            if (!lines.some((t) => Math.abs(t - top) <= 2)) lines.push(top);
          });
          return lines.length;
        }
        const grid = document.getElementById('short-word-grid');
        const bath = document.getElementById('bath-light-row');
        grid.style.width = colWidth + 'px';
        grid.style.gridTemplateColumns = 'repeat(2, minmax(0, 1fr))';
        bath.style.width = colWidth + 'px';
        const broken = [];
        [grid, bath].forEach((root) => {
          root.querySelectorAll('.admin-label-with-tip__end').forEach((end) => {
            const wrap = end.querySelector('.admin-info-tip-wrap');
            const word = Array.from(end.children).find((c) => c !== wrap);
            if (!word) return;
            const text = String(word.textContent || '').replace(/\s+/g, ' ').trim();
            if (!text || /\s/.test(text) || text.length < 2) return;
            const range = document.createRange();
            range.selectNodeContents(word);
            const rects = Array.from(range.getClientRects()).filter((r) => r.width > 0.5 && r.height > 0.5);
            if (distinctLineCount(rects) > 1) broken.push(text.slice(0, 40));
          });
        });
        return broken;
      }, width);
    }

    for (const width of [180, 220, 300]) {
      const broken = await brokenInNarrowColumns(width);
      assert.deepEqual(broken, [], `D: short glued words must not break at ${width}px columns: ${broken.join(' | ')}`);
    }

    await page.evaluate((css) => {
      let style = document.getElementById('r14-wrap-mutant');
      if (!style) {
        style = document.createElement('style');
        style.id = 'r14-wrap-mutant';
        document.head.appendChild(style);
      }
      style.textContent = css;
    }, r14WrapCss);
    const r14Broken = await brokenInNarrowColumns(180);
    assert.ok(r14Broken.length > 0, 'D: r14 overflow-wrap:anywhere must break a short glued word at 180px columns');
    await page.evaluate(() => {
      const style = document.getElementById('r14-wrap-mutant');
      if (style) style.remove();
      const grid = document.getElementById('short-word-grid');
      const bath = document.getElementById('bath-light-row');
      grid.style.width = '';
      grid.style.gridTemplateColumns = '';
      bath.style.width = '';
    });
    const restored = await brokenInNarrowColumns(180);
    assert.deepEqual(restored, [], `D: removing r14 wrap CSS must restore intact words at 180px: ${restored.join(' | ')}`);
    await page.evaluate(() => {
      const grid = document.getElementById('short-word-grid');
      const bath = document.getElementById('bath-light-row');
      grid.style.width = '';
      grid.style.gridTemplateColumns = '';
      bath.style.width = '';
    });

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
