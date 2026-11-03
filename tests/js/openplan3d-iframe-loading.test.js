'use strict';

const assert = require('assert');
const fs = require('fs');
const http = require('http');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const FIXTURE = path.join(__dirname, 'fixtures/openplan3d-room-area.html');
const publicDir = path.join(ROOT, 'public');
const types = {
  '.js': 'text/javascript',
  '.css': 'text/css',
  '.html': 'text/html; charset=utf-8',
  '.json': 'application/json',
  '.svg': 'image/svg+xml',
};

function buildPage(iframeAttrs) {
  let html = fs.readFileSync(FIXTURE, 'utf8');
  html = html.replace(
    '<iframe\n      id="openplan3d-iframe"\n      src="about:blank"',
    `<iframe\n      id="openplan3d-iframe"\n      src="/vendor/openplan3d-h1/editor/index.html"${iframeAttrs ? ' ' + iframeAttrs : ''}`
  );
  html = html.replace(
    '<body style="margin: 0; padding: 10px; font-family: sans-serif;">',
    '<body class="admin-body admin-body--fullscreen admin-dark" style="margin: 0; padding: 0; font-family: sans-serif;">'
  );
  html = html.replace(
    '<div class="util-ra-openplan3d-wrap"',
    '<div class="admin-app admin-app--fullscreen" id="admin-app"><main class="admin-main"><div class="admin-content"><div class="util-ra-openplan3d-wrap util-ra--fullpage"'
  );
  html = html.replace(
    '</div>\n</body>',
    '</div></div></main></div>\n</body>'
  );
  html = html.replace(
    '</head>',
    [
      '<link rel="stylesheet" href="/css/admin.css">',
      '<link rel="stylesheet" href="/css/admin-utilities.css">',
      '<script src="/js/admin-info-tip.js"></script>',
      '</head>',
    ].join('\n  ')
  );
  return html;
}

function startServer(html) {
  return new Promise((resolve, reject) => {
    const server = http.createServer((req, res) => {
      const urlPath = decodeURIComponent((req.url || '/').split('?')[0]);
      if (urlPath === '/') {
        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end(html);
        return;
      }
      let file = path.join(publicDir, urlPath.replace(/^\//, ''));
      try {
        if (fs.existsSync(file) && fs.statSync(file).isDirectory()) {
          file = path.join(file, 'index.html');
        }
      } catch (e) {}
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
        const ext = path.extname(file);
        res.writeHead(200, { 'Content-Type': types[ext] || 'application/octet-stream' });
        res.end(data);
      });
    });
    server.listen(0, '127.0.0.1', () => resolve({ server, port: server.address().port }));
    server.on('error', reject);
  });
}

async function runCase(label, iframeAttrs, viewport) {
  const html = buildPage(iframeAttrs);
  const { server, port } = await startServer(html);
  const puppeteer = require('puppeteer');
  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });
  const page = await browser.newPage();
  await page.setViewport(viewport);
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));

  await page.goto(`http://127.0.0.1:${port}/`, { waitUntil: 'domcontentloaded', timeout: 60000 });

  const geom = await page.evaluate(() => {
    const iframe = document.getElementById('openplan3d-iframe');
    const ir = iframe && iframe.getBoundingClientRect();
    return {
      iframeH: ir ? ir.height : 0,
      loading: iframe ? iframe.getAttribute('loading') : null,
    };
  });

  const iframeEl = await page.$('#openplan3d-iframe');
  const frame = iframeEl && (await iframeEl.contentFrame());
  let canvas = false;
  let iframeReady = false;
  if (frame) {
    canvas = await frame
      .waitForSelector('canvas', { timeout: 60000 })
      .then(() => true)
      .catch(() => false);
  }
  iframeReady = await page
    .waitForFunction(() => {
      const h = window.OpenPlan3DHost;
      return h && h.getState && h.getState().iframeReady;
    }, { timeout: 60000 })
    .then(() => true)
    .catch(() => false);

  await browser.close();
  server.close();

  return { label, geom, canvas, iframeReady, errors };
}

async function main() {
  const viewports = [
    { name: 'desktop', width: 1280, height: 900 },
    { name: 'phone390', width: 390, height: 844 },
  ];

  for (const vp of viewports) {
    const lazy = await runCase(`${vp.name}-lazy`, 'loading="lazy"', vp);
    const eager = await runCase(`${vp.name}-eager`, 'loading="eager"', vp);
    console.log(JSON.stringify({ lazy, eager }));
    assert.strictEqual(lazy.errors.length, 0, lazy.label);
    assert.strictEqual(eager.errors.length, 0, eager.label);
    assert.ok(eager.canvas, `${eager.label} canvas must render`);
    assert.ok(eager.iframeReady, `${eager.label} host must see OPENPLAN3D_READY`);
    if (!lazy.canvas || !lazy.iframeReady) {
      console.warn(`lazy load failed at ${vp.name}: canvas=${lazy.canvas} ready=${lazy.iframeReady} h=${lazy.geom.iframeH}`);
    }
  }
  console.log('openplan3d-iframe-loading: ok');
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
