'use strict';

const assert = require('assert');
const { execFileSync } = require('child_process');
const fs = require('fs');
const http = require('http');
const path = require('path');
const puppeteer = require('puppeteer');

const ROOT = path.resolve(__dirname, '../..');
const publicDir = path.join(ROOT, 'public');
const outDir = process.env.QA_OUT || path.join(ROOT, 'storage', 'app/qa-screenshots');
const extraOut = process.env.CURSOR_ARTIFACTS || '/opt/cursor/artifacts';
const LONG_ADDRESS = '1025 Grenon Avenue, Britannia neighbourhood, Ottawa, Ontario K2B 8S5, Canada';
const types = {
  '.js': 'text/javascript',
  '.css': 'text/css',
  '.html': 'text/html; charset=utf-8',
  '.woff': 'font/woff',
  '.woff2': 'font/woff2',
  '.ttf': 'font/ttf',
  '.eot': 'application/vnd.ms-fontobject',
  '.svg': 'image/svg+xml',
};

function renderIndexHtml() {
  const php = path.join(__dirname, 'render-admin-clients-index.php');
  return execFileSync('php', [php], {
    encoding: 'utf8',
    maxBuffer: 10 * 1024 * 1024,
    cwd: ROOT,
  });
}

function startServer(html) {
  return new Promise((resolve, reject) => {
    const server = http.createServer((req, res) => {
      const urlPath = decodeURIComponent((req.url || '/').split('?')[0]);
      if (urlPath === '/' || urlPath === '/admin/clients') {
        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end(html);
        return;
      }
      const file = path.join(publicDir, urlPath.replace(/^\//, '').split('?')[0]);
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
        const ext = path.extname(file.split('?')[0]);
        res.writeHead(200, { 'Content-Type': types[ext] || 'application/octet-stream' });
        res.end(data);
      });
    });
    server.listen(0, '127.0.0.1', () => resolve({ server, port: server.address().port }));
    server.on('error', reject);
  });
}

function copyToExtra(src, destName) {
  if (!fs.existsSync(extraOut)) return;
  fs.mkdirSync(extraOut, { recursive: true });
  fs.copyFileSync(src, path.join(extraOut, destName));
}

async function measure(page, width) {
  await page.setViewport({ width, height: width <= 390 ? 844 : 900 });
  await page.reload({ waitUntil: 'networkidle0', timeout: 60000 });
  await page.waitForSelector('.cp-clients-col-address .cl-copy-field--address', { timeout: 15000 });

  return page.evaluate((longAddress) => {
    const doc = document.documentElement;
    const sidebar = document.querySelector('.admin-sidebar');
    const addressTd = document.querySelector('td.cp-clients-col-address');
    const value = addressTd ? addressTd.querySelector('.cl-copy-field__value') : null;
    const btn = addressTd ? addressTd.querySelector('.cl-copy-btn') : null;
    const tdRect = addressTd ? addressTd.getBoundingClientRect() : { width: 0, height: 0 };
    const valueRect = value ? value.getBoundingClientRect() : { width: 0, height: 0 };
    const btnRect = btn ? btn.getBoundingClientRect() : { width: 0, height: 0 };
    const cs = value ? getComputedStyle(value) : { lineHeight: '20px' };
    const lineHeight = parseFloat(cs.lineHeight) || 20;
    const lines = valueRect.height / lineHeight;
    const stacked = getComputedStyle(document.querySelector('.cp-clients-table thead')).display === 'none';
    return {
      scrollWidth: doc.scrollWidth,
      clientWidth: doc.clientWidth,
      hasSidebar: !!(sidebar && getComputedStyle(sidebar).display !== 'none'),
      sidebarWidth: sidebar ? sidebar.getBoundingClientRect().width : 0,
      addressWidth: tdRect.width,
      valueWidth: valueRect.width,
      valueHeight: valueRect.height,
      lines,
      btnW: btnRect.width,
      btnH: btnRect.height,
      stacked,
      addressText: value ? value.textContent.trim() : '',
      longAddress,
    };
  }, LONG_ADDRESS);
}

async function screenshotList(page, width) {
  fs.mkdirSync(outDir, { recursive: true });
  const fileName = `clients-list-${width}.png`;
  const dest = path.join(outDir, fileName);
  await page.screenshot({ path: dest, fullPage: false });
  copyToExtra(dest, fileName);
}

async function main() {
  const html = renderIndexHtml();
  assert.ok(html.includes('admin-sidebar'), 'real index layout must include the admin sidebar');
  assert.ok(html.includes('cp-clients-col-address'), 'real index must include the address column');
  assert.ok(html.includes('cl-copy-field--address'), 'real index must include the address copy field');
  assert.ok(html.includes(LONG_ADDRESS), 'real index must show the long address');
  assert.ok(html.includes('admin-client-projects.css'), 'real index must load client-projects CSS');
  assert.ok(!html.includes('http://127.0.0.1/css/'), 'asset URLs must be local so the test server can serve CSS');

  const { server, port } = await startServer(html);
  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });
  const page = await browser.newPage();
  const results = [];
  try {
    await page.goto(`http://127.0.0.1:${port}/admin/clients`, {
      waitUntil: 'networkidle0',
      timeout: 60000,
    });

    for (const width of [1024, 1280, 1440]) {
      const layout = await measure(page, width);
      results.push({ width, layout });
      assert.ok(layout.hasSidebar, `${width}px: sidebar must be present`);
      assert.ok(layout.sidebarWidth >= 240, `${width}px: sidebar should be open (w=${layout.sidebarWidth})`);
      assert.ok(!layout.stacked, `${width}px: desktop table must not use stacked cards`);
      assert.ok(
        layout.scrollWidth <= layout.clientWidth + 2,
        `${width}px: page overflow ${layout.scrollWidth} > ${layout.clientWidth}`
      );
      assert.ok(
        layout.addressWidth >= 140,
        `${width}px: address column too narrow (${layout.addressWidth}px)`
      );
      assert.ok(
        layout.valueWidth >= 100,
        `${width}px: address text too narrow (${layout.valueWidth}px)`
      );
      assert.ok(
        layout.lines < 8,
        `${width}px: address wrapped to ${layout.lines} lines (1ch wrap?)`
      );
      assert.ok(
        layout.btnH <= 40,
        `${width}px: copy button tall strip (${layout.btnH}px)`
      );
      assert.strictEqual(layout.addressText, LONG_ADDRESS);
    }

    const mobile = await measure(page, 390);
    results.push({ width: 390, layout: mobile });
    assert.ok(mobile.stacked, '390px: stacked card layout must stay in effect');
    assert.ok(mobile.btnW >= 40 && mobile.btnH >= 40, `390px copy button ${mobile.btnW}x${mobile.btnH} below 40px`);
    assert.ok(
      mobile.scrollWidth <= mobile.clientWidth + 2,
      `390px: page overflow ${mobile.scrollWidth} > ${mobile.clientWidth}`
    );
    assert.ok(mobile.valueWidth >= 100, `390px: address text too narrow (${mobile.valueWidth}px)`);
    assert.ok(mobile.lines < 8, `390px: address wrapped to ${mobile.lines} lines`);

    await measure(page, 1280);
    await screenshotList(page, 1280);
    await measure(page, 390);
    await screenshotList(page, 390);
  } finally {
    await browser.close();
    server.close();
  }

  console.log(JSON.stringify(results, null, 2));
  console.log('admin-clients-index-layout-browser: ok');
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
