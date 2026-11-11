'use strict';

const assert = require('assert');
const fs = require('fs');
const http = require('http');
const path = require('path');
const puppeteer = require('puppeteer');

const ROOT = path.resolve(__dirname, '../..');
const publicDir = path.join(ROOT, 'public');
const htmlPath = process.env.OVERVIEW_HTML;
const outDir = process.env.OVERVIEW_OUT || path.join(ROOT, 'storage', 'app/qa-screenshots');
const reportPath = process.env.OVERVIEW_REPORT || path.join(outDir, 'overview-real-report.json');
const artifactDir = '/opt/cursor/artifacts';
const types = {
  '.js': 'text/javascript',
  '.css': 'text/css',
  '.html': 'text/html; charset=utf-8',
  '.woff': 'font/woff',
  '.woff2': 'font/woff2',
  '.ttf': 'font/ttf',
  '.png': 'image/png',
};

function startServer(html) {
  return new Promise((resolve, reject) => {
    const server = http.createServer((req, res) => {
      const urlPath = decodeURIComponent((req.url || '/').split('?')[0]);
      if (urlPath === '/' || urlPath === '/overview') {
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

function copyArtifact(src, name) {
  try {
    fs.mkdirSync(artifactDir, { recursive: true });
    fs.copyFileSync(src, path.join(artifactDir, name));
  } catch (err) {
    // Local CI runners may not have the Cursor artifacts mount.
  }
}

async function measureDateInput(page) {
  return page.evaluate(() => {
    const input = document.querySelector('#cp-overview-visit-date');
    if (!input) return null;
    const r = input.getBoundingClientRect();
    const cs = getComputedStyle(input);
    return {
      w: r.width,
      h: r.height,
      minWidth: cs.minWidth,
      overflow: cs.overflow,
      value: input.value,
    };
  });
}

async function main() {
  assert.ok(htmlPath && fs.existsSync(htmlPath), 'OVERVIEW_HTML must point at a rendered Blade dump');
  const html = fs.readFileSync(htmlPath, 'utf8');
  assert.ok(html.includes('id="cp-overview-visit-form"'), 'dumped HTML must include the Overview visit form');
  assert.ok(!html.includes('name="client_id"'), 'Overview dump must not post client_id');

  const { server, port } = await startServer(html);
  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });
  fs.mkdirSync(outDir, { recursive: true });

  const report = {};
  try {
    async function shotAt(width, height, label) {
      const page = await browser.newPage();
      await page.setViewport({ width, height, deviceScaleFactor: 1 });
      await page.goto(`http://127.0.0.1:${port}/overview`, { waitUntil: 'networkidle0', timeout: 60000 });
      await page.waitForSelector('[data-cp-overview-edit="visit"]', { timeout: 15000 });
      await page.click('[data-cp-overview-edit="visit"]');
      await page.waitForFunction(() => {
        const form = document.querySelector('#cp-overview-visit-form');
        return form && !form.hidden;
      }, { timeout: 5000 });
      const measure = await measureDateInput(page);
      assert.ok(measure, `${label} missing visit date input`);
      assert.ok(measure.w >= 200, `${label} visit date width ${measure.w} clips the year`);
      const dest = path.join(outDir, `${label}.png`);
      const root = await page.$('.admin-content') || await page.$('body');
      assert.ok(root, `${label} missing screenshot root`);
      await root.screenshot({ path: dest });
      copyArtifact(dest, `${label}.png`);
      await page.close();
      return measure;
    }

    const m1280 = await shotAt(1280, 900, 'client-project-overview-visit-edit-real-1280');
    const page1024 = await browser.newPage();
    await page1024.setViewport({ width: 1024, height: 900, deviceScaleFactor: 1 });
    await page1024.goto(`http://127.0.0.1:${port}/overview`, { waitUntil: 'networkidle0', timeout: 60000 });
    await page1024.click('[data-cp-overview-edit="visit"]');
    await page1024.waitForFunction(() => {
      const form = document.querySelector('#cp-overview-visit-form');
      return form && !form.hidden;
    }, { timeout: 5000 });
    const m1024 = await measureDateInput(page1024);
    assert.ok(m1024 && m1024.w >= 200, `1024 visit date width ${m1024 && m1024.w} clips the year`);
    await page1024.close();
    const m390 = await shotAt(390, 844, 'client-project-overview-visit-edit-real-390');

    report.ok = true;
    report.width1280 = m1280.w;
    report.width1024 = m1024.w;
    report.width390 = m390.w;
  } finally {
    await browser.close();
    server.close();
  }

  fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
  console.log(JSON.stringify(report, null, 2));
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
