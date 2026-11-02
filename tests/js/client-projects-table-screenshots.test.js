'use strict';

const assert = require('assert');
const fs = require('fs');
const http = require('http');
const path = require('path');
const puppeteer = require('puppeteer');

const ROOT = path.resolve(__dirname, '../..');
const FIXTURE = path.join(__dirname, '../fixtures/client-projects-table-preview.html');
const publicDir = path.join(ROOT, 'public');
const outDir = process.env.QA_OUT || path.join(ROOT, 'storage', 'app/qa-screenshots');
const types = {
  '.js': 'text/javascript',
  '.css': 'text/css',
  '.html': 'text/html; charset=utf-8',
};

function startServer(html) {
  return new Promise((resolve, reject) => {
    const server = http.createServer((req, res) => {
      const urlPath = decodeURIComponent((req.url || '/').split('?')[0]);
      if (urlPath === '/') {
        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end(html);
        return;
      }
      let file = path.join(publicDir, urlPath.replace(/^\//, '').split('?')[0]);
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

async function capture(label, width, height, port, minClientColWidth) {
  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });
  const page = await browser.newPage();
  await page.setViewport({ width, height });
  await page.goto(`http://127.0.0.1:${port}/`, { waitUntil: 'networkidle0', timeout: 60000 });
  await page.waitForSelector('.cp-client-action-btn--phone', { timeout: 15000 });
  const layout = await page.evaluate(() => {
    const td = document.querySelector('.cp-table tbody tr:first-child td:first-child');
    const name = document.querySelector('.cp-table tbody tr:first-child .cp-client-name');
    const longRow = document.querySelector('.cp-table tbody tr:first-child');
    return {
      clientColWidth: td ? td.getBoundingClientRect().width : 0,
      nameHeight: name ? name.getBoundingClientRect().height : 0,
      rowHeight: longRow ? longRow.getBoundingClientRect().height : 0,
    };
  });
  assert.ok(layout.clientColWidth >= minClientColWidth, `${label} client column width ${layout.clientColWidth}px < ${minClientColWidth}px`);
  if (label.includes('390')) {
    assert.ok(layout.clientColWidth >= 120, `${label} client column must not crush below 120px`);
  }
  if (label.includes('1280')) {
    assert.ok(layout.rowHeight >= 44 && layout.rowHeight < 120, `${label} long-name row height ${layout.rowHeight}px out of range`);
  }
  fs.mkdirSync(outDir, { recursive: true });
  await page.screenshot({ path: path.join(outDir, `${label}.png`), fullPage: false });
  const count = await page.$$eval('.cp-client-action-btn', (els) => els.length);
  await browser.close();
  assert.ok(count >= 3, `${label} must render phone/email/address icons`);
  return { label, count, layout };
}

async function main() {
  const html = fs.readFileSync(FIXTURE, 'utf8');
  assert.ok(html.includes('cp-client-action-btn'), 'fixture must include contact icons');
  const { server, port } = await startServer(html);
  const results = [];
  results.push(await capture('client-projects-table-desktop', 1280, 900, port, 180));
  results.push(await capture('client-projects-table-390', 390, 844, port, 120));
  server.close();
  console.log(JSON.stringify(results, null, 2));
  console.log('client-projects-table-screenshots: ok');
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
