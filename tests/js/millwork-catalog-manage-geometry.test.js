'use strict';

const assert = require('assert');
const fs = require('fs');
const http = require('http');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const FIXTURE = path.join(__dirname, 'fixtures/millwork-catalog-manage.html');
const TEST_TIMEOUT_MS = 90000;
const WIDTHS = [390, 1024, 1280];

function startStaticServer() {
  return new Promise((resolve, reject) => {
    const server = http.createServer((req, res) => {
      const urlPath = decodeURIComponent((req.url || '/').split('?')[0]);
      let file;
      if (urlPath === '/' || urlPath === '/index.html') {
        file = FIXTURE;
      } else if (urlPath.startsWith('/css/')) {
        file = path.join(ROOT, 'public', urlPath);
      } else {
        res.writeHead(404);
        res.end('not found');
        return;
      }
      fs.readFile(file, (err, data) => {
        if (err) {
          res.writeHead(404);
          res.end('not found');
          return;
        }
        const ext = path.extname(file);
        const types = { '.css': 'text/css', '.html': 'text/html' };
        res.writeHead(200, { 'Content-Type': types[ext] || 'application/octet-stream' });
        res.end(data);
      });
    });
    server.listen(0, '127.0.0.1', () => {
      resolve({ server, port: server.address().port });
    });
    server.on('error', reject);
  });
}

async function run() {
  let puppeteer;
  try {
    puppeteer = require('puppeteer');
  } catch (err) {
    throw new Error('puppeteer is required for millwork catalog geometry: ' + err.message);
  }
  const { server, port } = await startStaticServer();
  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });
  try {
    const page = await browser.newPage();
    for (const width of WIDTHS) {
      await page.setViewport({
        width,
        height: width <= 414 ? 844 : 900,
        deviceScaleFactor: 1,
        isMobile: width <= 414,
        hasTouch: width <= 414,
      });
      await page.goto('http://127.0.0.1:' + port + '/', { waitUntil: 'load' });
      const geom = await page.evaluate(() => {
        const box = (el) => {
          if (!el) return null;
          const r = el.getBoundingClientRect();
          return { left: r.left, right: r.right, top: r.top, bottom: r.bottom, width: r.width, height: r.height };
        };
        const manage = document.querySelector('.mw-catalog-manage');
        const picker = document.querySelector('.mw-catalog-picker');
        const modal = document.querySelector('#mw-catalog-modal .mw-catalog-modal__panel');
        const cancel = document.querySelector('#mw-catalog-modal [data-mw-catalog-dismiss].admin-btn');
        const tip = document.querySelector('.admin-info-tip');
        const required = document.querySelector('.cp-required');
        return {
          innerWidth: window.innerWidth,
          scrollWidth: document.documentElement.scrollWidth,
          manage: box(manage),
          picker: box(picker),
          modal: box(modal),
          cancel: box(cancel),
          tip: box(tip),
          required: box(required),
        };
      });

      assert.ok(geom.manage, width + ': manage button present');
      assert.ok(geom.manage.height >= 44, width + ': manage height ' + geom.manage.height);
      assert.ok(geom.manage.width >= 44, width + ': manage width ' + geom.manage.width);
      assert.ok(geom.picker.right <= geom.innerWidth + 1, width + ': picker stays in viewport ' + JSON.stringify(geom.picker));
      assert.ok(geom.scrollWidth <= geom.innerWidth + 2, width + ': page scrollWidth ' + geom.scrollWidth + ' vs ' + geom.innerWidth);
      assert.ok(geom.modal, width + ': modal panel present');
      assert.ok(geom.modal.width <= geom.innerWidth, width + ': modal width ' + geom.modal.width);
      assert.ok(geom.modal.left >= -1, width + ': modal left ' + geom.modal.left);
      assert.ok(geom.modal.right <= geom.innerWidth + 1, width + ': modal right ' + geom.modal.right);
      assert.ok(geom.cancel && geom.cancel.width >= 44 && geom.cancel.height >= 44, width + ': Cancel target ' + JSON.stringify(geom.cancel));
      assert.ok(geom.tip && geom.tip.width >= 20, width + ': info tip visible');
      assert.ok(geom.required && geom.required.width > 0, width + ': Required star visible');
      console.log(JSON.stringify({ width, overflowing: [], manage: geom.manage, modal: geom.modal }));
    }
  } finally {
    await browser.close();
    server.close();
  }
}

const timer = setTimeout(() => {
  console.error('millwork catalog geometry tests: timed out');
  process.exit(1);
}, TEST_TIMEOUT_MS);

run().then(() => {
  clearTimeout(timer);
  console.log('millwork catalog geometry tests: ok');
}).catch((err) => {
  clearTimeout(timer);
  console.error(err);
  process.exit(1);
});
