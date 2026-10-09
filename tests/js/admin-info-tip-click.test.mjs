/**
 * Click the heading ⓘ inside a create-page <summary> that has
 * pointer-events:none. Uses headless Chrome when Puppeteer can launch;
 * always also performs a JS DOM .click() on the button element itself
 * (not Tab+Enter).
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
const adminCss = readFileSync(path.resolve(__dirname, '../../public/css/admin.css'), 'utf8');
const cpCss = readFileSync(path.resolve(__dirname, '../../public/css/admin-client-projects.css'), 'utf8');

assert.match(adminCss, /\.admin-info-tip[\s\S]{0,80}pointer-events:\s*auto/);
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

async function puppeteerClick() {
  let puppeteer;
  try {
    puppeteer = require('puppeteer');
  } catch (err) {
    return false;
  }

  const css = `
    .br-layout--create .br-acc__summary { pointer-events: none; }
    .br-layout--create .br-acc__summary .admin-info-tip,
    .br-layout--create .br-acc__summary .admin-info-tip-wrap,
    .br-layout--create .br-acc__summary .admin-info-tip__bubble { pointer-events: auto; }
    .admin-info-tip, .admin-info-tip-wrap, .admin-info-tip__bubble { pointer-events: auto; }
    .admin-info-tip__bubble { display: none; }
    .admin-info-tip-wrap.is-open > .admin-info-tip__bubble { display: block; }
  `;
  const html = `<!doctype html><html><head><style>${css}</style></head><body>
    <div class="br-layout--create"><details class="br-acc" open>
      <summary class="br-acc__summary">Pieces
        <span class="admin-info-tip-wrap">
          <button type="button" class="admin-info-tip" aria-label="About Pieces" aria-expanded="false" aria-controls="t1">i</button>
          <span id="t1" class="admin-info-tip__bubble" role="tooltip">Add each millwork item.</span>
        </span>
      </summary>
    </details></div>
    <script>${source}</script>
  </body></html>`;
  const file = path.join(mkdtempSync(path.join(tmpdir(), 'info-tip-click-')), 'click.html');
  writeFileSync(file, html);

  const launch = { headless: 'new', args: ['--no-sandbox', '--disable-dev-shm-usage'] };
  try {
    launch.executablePath = puppeteer.executablePath();
  } catch (err) { /* default */ }

  let browser;
  try {
    browser = await puppeteer.launch(launch);
  } catch (err) {
    return false;
  }
  try {
    const page = await browser.newPage();
    await page.goto('file://' + file, { waitUntil: 'domcontentloaded' });
    await page.click('.admin-info-tip');
    const after = await page.evaluate(() => ({
      expanded: document.querySelector('.admin-info-tip').getAttribute('aria-expanded'),
      open: document.querySelector('.admin-info-tip-wrap').classList.contains('is-open'),
      pe: getComputedStyle(document.querySelector('.admin-info-tip')).pointerEvents,
    }));
    assert.equal(after.expanded, 'true');
    assert.equal(after.open, true);
    assert.equal(after.pe, 'auto');
  } finally {
    await browser.close();
  }
  return true;
}

jsDomClick();
const usedBrowser = await puppeteerClick();
console.log('admin-info-tip-click.test.mjs: ok' + (usedBrowser ? ' (headless + JS DOM click)' : ' (JS DOM click)'));
