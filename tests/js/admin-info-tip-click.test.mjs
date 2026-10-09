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
assert.match(adminCss, /\.admin-form-group\s+label\.admin-checkbox__tail[\s\S]{0,500}text-transform:\s*none/);
assert.match(adminCss, /\.admin-form-group\s+label\.admin-checkbox__tail[\s\S]{0,500}font-weight:\s*inherit/);
assert.match(adminCss, /\.admin-form-group\s+label\.admin-checkbox__tail[\s\S]{0,500}color:\s*inherit/);
assert.match(adminCss, /\.admin-heading-copy/);
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
    <form id="mw-test-form">
      <div id="mw-piece-list"></div>
      <button type="button" id="mw-add-piece">Add piece</button>
      <template id="mw-piece-template">
        <article data-mw-piece>
          <span class="admin-label-with-tip">
            <label class="admin-checkbox" for="pieces-__P__-edge_banding">
              <input type="hidden" name="pieces[__P__][edge_banding]" value="0">
              <input type="checkbox" name="pieces[__P__][edge_banding]" value="1" id="pieces-__P__-edge_banding" class="admin-checkbox__input">
              <span class="admin-checkbox__mark"></span>
              <span class="admin-checkbox__text">Edge</span>
            </label>
            <span class="admin-label-with-tip__end">
              <label for="pieces-__P__-edge_banding" class="admin-checkbox__tail">banding</label>
              <span class="admin-info-tip-wrap">
                <button type="button" class="admin-info-tip" aria-label="About Edge banding" aria-expanded="false" aria-controls="mw-tip-__P__">i</button>
                <span id="mw-tip-__P__" class="admin-info-tip__bubble" role="tooltip">Shrinks the raw cut.</span>
              </span>
            </span>
          </span>
        </article>
      </template>
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
        unitInEnd: !!(end && end.contains(span)),
      };
    });
    assert.equal(d2.afterHoist, 'Pot lights', 'D2: glue must not pull lights out of #br-lights-label');
    assert.equal(d2.fixtures, 'Fixtures');
    assert.equal(d2.back, 'Pot lights');
    assert.equal(d2.glued, true, 'N8: Pot lights + ⓘ wrap together');
    assert.equal(d2.unitInEnd, true);

    const headingGlue = await page.evaluate(() => {
      const h = document.getElementById('pricing-heading');
      const copy = h.querySelector('.admin-heading-copy');
      const end = h.querySelector('.admin-label-with-tip__end, .admin-heading-tip');
      const pdf = document.getElementById('pdf-line-heading');
      const pdfCopy = pdf.querySelector('.admin-heading-copy');
      const review = document.getElementById('review-summary');
      const reviewCopy = review.querySelector('.admin-heading-copy');
      const legend = document.getElementById('reply-legend');
      const star = legend.querySelector('.cp-required');
      const legendEnd = legend.querySelector('.admin-label-with-tip__end');
      return {
        hasEnd: !!end,
        last: end ? end.textContent.trim() : '',
        hasCopy: !!copy,
        copyHasEnd: !!(copy && copy.contains(end)),
        pdfText: pdfCopy ? pdfCopy.textContent.replace(/\s+/g, ' ').trim() : '',
        reviewHasNum: !!(review.querySelector('.br-acc__num') && review.querySelector('.br-acc__num').parentNode === review),
        reviewCopyText: reviewCopy ? reviewCopy.textContent.replace(/\s+/g, ' ').trim() : '',
        starText: star ? star.textContent : '',
        starInEnd: !!(legendEnd && legendEnd.contains(star)),
      };
    });
    assert.equal(headingGlue.hasEnd, true, 'D5: heading last word is glued');
    assert.match(headingGlue.last, /area/);
    assert.equal(headingGlue.hasCopy, true, 'N4: heading copy wrap');
    assert.equal(headingGlue.copyHasEnd, true);
    assert.match(headingGlue.pdfText, /Client PDF line items/);
    assert.ok(!headingGlue.pdfText.includes('lineitems'), 'N4: spaces in flex headings must not collapse');
    assert.equal(headingGlue.reviewHasNum, true, 'num stays outside the copy span');
    assert.match(headingGlue.reviewCopyText, /live edits/);
    assert.equal(headingGlue.starText, '*');
    assert.equal(headingGlue.starInEnd, false, 'N5: required * stays outside the glue');

    const millwork = await page.evaluate(() => {
      const list = document.getElementById('mw-piece-list');
      const tpl = document.getElementById('mw-piece-template');
      const form = document.getElementById('mw-test-form');
      function addPiece() {
        const i = list.querySelectorAll('[data-mw-piece]').length;
        const wrap = document.createElement('div');
        wrap.innerHTML = tpl.innerHTML.replace(/__P__/g, String(i)).trim();
        const piece = wrap.firstElementChild;
        list.appendChild(piece);
        if (window.AdminInfoTip.refreshControlIds) window.AdminInfoTip.refreshControlIds(piece);
        list.querySelectorAll('[data-mw-piece]').forEach(function (p, idx) {
          p.querySelectorAll('[name]').forEach(function (input) {
            input.name = input.name.replace(/pieces\[\d+\]/, 'pieces[' + idx + ']');
            if (input.id) input.id = input.id.replace(/pieces-\d+/, 'pieces-' + idx);
          });
          p.querySelectorAll('label[for]').forEach(function (lab) {
            const next = (lab.getAttribute('for') || '').replace(/pieces-\d+/, 'pieces-' + idx);
            lab.setAttribute('for', next);
          });
        });
      }
      addPiece();
      addPiece();
      addPiece();
      const boxes = Array.from(list.querySelectorAll('input[type="checkbox"]'));
      const ids = boxes.map((el) => el.id);
      const tails = Array.from(list.querySelectorAll('.admin-checkbox__tail'));
      tails[0].click();
      tails[2].click();
      const submitted = {};
      new FormData(form).forEach((value, key) => { submitted[key] = value; });
      const values = [0, 1, 2].map((i) => submitted['pieces[' + i + '][edge_banding]']);
      return {
        count: boxes.length,
        ids,
        unique: new Set(ids).size,
        checked: boxes.map((el) => el.checked),
        values,
        fors: tails.map((el) => el.getAttribute('for')),
      };
    });
    assert.equal(millwork.count, 3, 'M1: three added pieces');
    assert.equal(millwork.unique, 3, 'M1: each piece has its own checkbox id ' + millwork.ids.join(','));
    assert.deepEqual(millwork.checked, [true, false, true], 'M1: ticking piece 1 and 3 must not toggle piece 2');
    assert.deepEqual(millwork.values, ['1', '0', '1']);
    millwork.ids.forEach((id, i) => {
      assert.equal(millwork.fors[i], id, 'M1: tail for= matches input id for piece ' + i);
    });

    const tailCss = await page.evaluate(() => {
      const tail = document.querySelector('#review-check .admin-checkbox__tail');
      const text = document.querySelector('#review-check .admin-checkbox__text');
      const cs = getComputedStyle(tail);
      const ts = getComputedStyle(text);
      return {
        transform: cs.textTransform,
        weight: cs.fontWeight,
        spacing: cs.letterSpacing,
        color: cs.color,
        textColor: ts.color,
        size: cs.fontSize,
        textSize: ts.fontSize,
      };
    });
    assert.equal(tailCss.transform, 'none', 'M3: tail must not pick up .admin-form-group label uppercase');
    assert.ok(['500', '400', 'normal'].includes(String(tailCss.weight)) || Number(tailCss.weight) <= 500, 'M3: tail weight follows checkbox text, got ' + tailCss.weight);
    assert.ok(tailCss.spacing === 'normal' || tailCss.spacing === '0px', 'M3: tail letter-spacing inherit, got ' + tailCss.spacing);
    assert.equal(tailCss.color, tailCss.textColor, 'M3: tail color matches checkbox text');
    assert.equal(tailCss.size, tailCss.textSize, 'M3: tail size matches checkbox text');

    for (const width of [320, 390, 1280]) {
      await page.setViewport({ width, height: 800 });
      const aligned = await page.evaluate(() => {
        function sameLine(a, b) {
          if (!a || !b) return false;
          const ar = a.getBoundingClientRect();
          const br = b.getBoundingClientRect();
          const overlap = br.bottom > ar.top && ar.bottom > br.top;
          const wrappedBelow = br.top > ar.bottom + 2;
          return { sameLine: overlap && !wrappedBelow, aTop: ar.top, bTop: br.top };
        }
        const checks = Array.from(document.querySelectorAll('#review-check, #toggle-check, #stack-check')).map((row) => {
          const tail = row.querySelector('.admin-checkbox__tail');
          const tip = row.querySelector('.admin-info-tip');
          return sameLine(tail, tip);
        });
        const lights = sameLine(document.getElementById('br-lights-label'), document.querySelector('#lights-row .admin-info-tip'));
        const review = document.getElementById('review-summary');
        const reviewLast = review.querySelector('.admin-heading-tip, .admin-label-with-tip__end');
        const reviewTip = review.querySelector('.admin-info-tip');
        const reviewLine = sameLine(reviewLast, reviewTip);
        return { checks, lights, reviewLine };
      });
      aligned.checks.forEach((row, i) => {
        assert.ok(row.sameLine, `D4/C3: checkbox ${i} tip stays on the last word at ${width}px (tail ${row.aTop} tip ${row.bTop})`);
      });
      assert.ok(aligned.lights.sameLine, `N8: pot lights ⓘ stays with the label at ${width}px`);
      assert.ok(aligned.reviewLine.sameLine, `N8: review summary ⓘ stays with the last word at ${width}px`);
    }
  } finally {
    await browser.close();
  }
  return true;
}

jsDomClick();
await puppeteerClick();
console.log('admin-info-tip-click.test.mjs: ok (headless + JS DOM click)');
