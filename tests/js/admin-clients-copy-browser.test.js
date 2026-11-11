'use strict';

const assert = require('assert');
const fs = require('fs');
const http = require('http');
const path = require('path');
const puppeteer = require('puppeteer');

const ROOT = path.resolve(__dirname, '../..');
const publicDir = path.join(ROOT, 'public');
const outDir = process.env.QA_OUT || path.join(ROOT, 'storage', 'app/qa-screenshots');
const extraOut = process.env.CURSOR_ARTIFACTS || '/opt/cursor/artifacts';
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

const pages = [
  { name: 'clients-list', file: 'clients-list-copy-preview.html', minCopy: 7 },
  { name: 'clients-view', file: 'clients-view-preview.html', minCopy: 4 },
];

function startServer() {
  return new Promise((resolve, reject) => {
    const server = http.createServer((req, res) => {
      const urlPath = decodeURIComponent((req.url || '/').split('?')[0]);
      let file;
      if (urlPath.startsWith('/fixtures/')) {
        file = path.join(ROOT, 'tests', urlPath.replace(/^\/fixtures\//, 'fixtures/'));
      } else {
        file = path.join(publicDir, urlPath.replace(/^\//, '').split('?')[0]);
      }
      const allowed = file.startsWith(publicDir + path.sep) || file.startsWith(path.join(ROOT, 'tests', 'fixtures') + path.sep);
      if (!allowed) {
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

async function capture(pageName, fixtureFile, width, height, port, minCopy) {
  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });
  const page = await browser.newPage();
  await page.setViewport({ width, height });
  await page.goto(`http://127.0.0.1:${port}/fixtures/${fixtureFile}`, {
    waitUntil: 'networkidle0',
    timeout: 60000,
  });
  await page.waitForSelector('.cl-copy-btn', { timeout: 15000 });

  const layout = await page.evaluate(() => {
    const doc = document.documentElement;
    const firstBtn = document.querySelector('.cl-copy-btn');
    const r = firstBtn ? firstBtn.getBoundingClientRect() : { width: 0, height: 0 };
    const value = document.querySelector('.cl-copy-field__value');
    const vr = value ? value.getBoundingClientRect() : { right: 0 };
    const name = document.querySelector('.cp-clients-col-name, .cl-show-field');
    const nr = name ? name.getBoundingClientRect() : { left: 0, width: 0 };
    return {
      scrollWidth: doc.scrollWidth,
      clientWidth: doc.clientWidth,
      btnW: r.width,
      btnH: r.height,
      btnLeft: r.left,
      valueRight: vr.right,
      nameLeft: nr.left,
      nameWidth: nr.width,
      overflowing: [...document.querySelectorAll('.cl-copy-field, .cp-clients-table, .cl-show-card')].some((el) => {
        return el.scrollWidth > el.clientWidth + 2;
      }),
    };
  });

  if (width <= 390) {
    assert.ok(layout.btnW >= 40 && layout.btnH >= 40, `${pageName}@${width} copy button ${layout.btnW}x${layout.btnH} below 40px`);
    assert.ok(layout.scrollWidth <= layout.clientWidth + 2, `${pageName}@${width} document overflow ${layout.scrollWidth} > ${layout.clientWidth}`);
    assert.ok(!layout.overflowing, `${pageName}@${width} copy field or card overflowed`);
  } else {
    assert.ok(layout.nameLeft < 80, `${pageName}@${width} name/field hidden under sidebar (left=${layout.nameLeft})`);
    assert.ok(layout.nameWidth > 40, `${pageName}@${width} name/field has no width`);
    assert.ok(
      layout.btnLeft - layout.valueRight < 24,
      `${pageName}@${width} copy button not beside value (gap=${layout.btnLeft - layout.valueRight})`
    );
  }

  fs.mkdirSync(outDir, { recursive: true });
  const fileName = `${pageName}-${width}.png`;
  const dest = path.join(outDir, fileName);
  const content = await page.$('.admin-content');
  if (content) {
    await content.screenshot({ path: dest });
  } else {
    await page.screenshot({ path: dest, fullPage: false });
  }
  copyToExtra(dest, fileName);

  const count = await page.$$eval('.cl-copy-btn', (els) => els.length);
  await browser.close();
  assert.ok(count >= minCopy, `${pageName}@${width} expected at least ${minCopy} copy buttons, got ${count}`);
  return { label: `${pageName}-${width}`, count, layout };
}

async function main() {
  const { server, port } = await startServer();
  const results = [];
  try {
    for (const spec of pages) {
      const html = fs.readFileSync(path.join(ROOT, 'tests/fixtures', spec.file), 'utf8');
      assert.ok(html.includes('cl-copy-btn'), `${spec.file} must include copy buttons`);
      assert.ok(html.includes('aria-label="Copy email"'), `${spec.file} must label copy email`);
      results.push(await capture(spec.name, spec.file, 1280, 900, port, spec.minCopy));
      results.push(await capture(spec.name, spec.file, 390, 844, port, spec.minCopy));
    }
  } finally {
    server.close();
  }
  console.log(JSON.stringify(results, null, 2));
  console.log('admin-clients-copy-browser: ok');
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
