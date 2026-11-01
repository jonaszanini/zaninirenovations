'use strict';

const assert = require('assert');
const { execSync } = require('child_process');
const fs = require('fs');
const http = require('http');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const publicDir = path.join(ROOT, 'public');
const types = {
  '.js': 'text/javascript',
  '.css': 'text/css',
  '.html': 'text/html; charset=utf-8',
  '.json': 'application/json',
  '.svg': 'image/svg+xml',
};

function renderBladePage() {
  const php = `<?php
require '${ROOT.replace(/'/g, "\\'")}/vendor/autoload.php';
$app = require_once '${ROOT.replace(/'/g, "\\'")}/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$partial = view('admin.utilities._room_area_openplan3d', [
  'pageTitle' => 'Room Area',
  'backUrl' => route('admin.utilities.index'),
  'backLabel' => 'All utilities',
  'projectsJson' => [],
  'clientProjects' => [],
  'initialRoomId' => null,
])->render();
$partial = preg_replace(
  '#src=\"https?://[^\"/]+(/vendor/openplan3d/editor/index\\.html\\?v=[^\"]+)\"#',
  'src=\"$1\"',
  $partial
);
$v = time();
echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
echo '<link rel="stylesheet" href="/css/admin.css?v='.$v.'">';
echo '<link rel="stylesheet" href="/css/admin-utilities.css?v='.$v.'">';
echo '<link rel="stylesheet" href="/css/admin-dark.css?v='.$v.'">';
echo '</head><body class="admin-body admin-body--fullscreen">';
echo '<div class="admin-app admin-app--fullscreen" id="admin-app">';
echo '<main class="admin-main"><div class="admin-content">';
echo $partial;
echo '</div></main></div>';
if (getenv('WITH_INFO_TIP') === '1') {
  echo '<script src="/js/admin-info-tip.js?v='.$v.'"></script>';
}
echo '<script src="/js/openplan3d-measurement-bridge.js?v='.$v.'"></script>';
echo '<script src="/js/openplan3d-host.js?v='.$v.'"></script>';
echo '</body></html>';
`;
  return execSync('php', { input: php, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 });
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
      let file = path.join(publicDir, urlPath.replace(/^\//, '').split('?')[0]);
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
        const ext = path.extname(file.split('?')[0]);
        res.writeHead(200, { 'Content-Type': types[ext] || 'application/octet-stream' });
        res.end(data);
      });
    });
    server.listen(0, '127.0.0.1', () => resolve({ server, port: server.address().port }));
    server.on('error', reject);
  });
}

async function auditViewport(page, port, label) {
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  const t0 = Date.now();

  const iframeEl = await page.$('#openplan3d-iframe');
  const frame = iframeEl && (await iframeEl.contentFrame());
  let canvas = false;
  if (frame) {
    canvas = await frame
      .waitForSelector('canvas', { timeout: 90000 })
      .then(() => true)
      .catch(() => false);
  }

  const host = await page.evaluate(() => {
    const h = window.OpenPlan3DHost;
    return h && h.getState ? h.getState() : null;
  });

  const metrics = await page.evaluate(() => ({
    area: document.getElementById('metric-area')?.textContent?.trim(),
    iframeH: document.getElementById('openplan3d-iframe')?.getBoundingClientRect().height || 0,
  }));

  const elapsed = Date.now() - t0;
  const outDir = process.env.QA_OUT || path.join(ROOT, 'storage', 'app/qa-screenshots');
  fs.mkdirSync(outDir, { recursive: true });
  await page.screenshot({ path: path.join(outDir, `${label}.png`), fullPage: false });

  return { label, elapsed, canvas, host, metrics, errors };
}

async function main() {
  const html = renderBladePage();
  assert.ok(html.includes('admin-info-tip-wrap'), 'rendered page must include info tips');
  assert.ok(html.includes('openplan3d-iframe'), 'rendered page must include iframe');

  const { server, port } = await startServer(html);
  const puppeteer = require('puppeteer');
  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });

  const cases = [
    { label: 'room-area-editor-desktop', width: 1280, height: 900 },
    { label: 'room-area-editor-390', width: 390, height: 844 },
  ];

  const results = [];
  for (const c of cases) {
    const page = await browser.newPage();
    await page.setViewport({ width: c.width, height: c.height });
    await page.goto(`http://127.0.0.1:${port}/`, { waitUntil: 'domcontentloaded', timeout: 60000 });
    const r = await auditViewport(page, port, c.label);
    results.push(r);
    await page.close();
  }

  await browser.close();
  server.close();

  for (const r of results) {
    console.log(JSON.stringify(r, null, 2));
    assert.strictEqual(r.errors.length, 0, `${r.label} JS errors: ${r.errors.join('; ')}`);
    assert.ok(r.canvas, `${r.label} must render openPlan3D canvas`);
    assert.ok(r.host && r.host.iframeReady, `${r.label} host must receive OPENPLAN3D_READY`);
    assert.ok(r.elapsed < 90000, `${r.label} took too long`);
  }

  console.log('openplan3d-room-area-blade-browser: ok');
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
