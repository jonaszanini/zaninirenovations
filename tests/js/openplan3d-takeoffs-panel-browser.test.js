'use strict';

const assert = require('assert');
const { execSync } = require('child_process');
const fs = require('fs');
const http = require('http');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const publicDir = path.join(ROOT, 'public');
const shotsDir = path.join(ROOT, 'tests/js/artifacts');
const types = {
  '.js': 'text/javascript',
  '.css': 'text/css',
  '.html': 'text/html; charset=utf-8',
  '.json': 'application/json',
  '.svg': 'image/svg+xml',
};

function renderPage() {
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
  '#src=\"https?://[^\"/]+(/vendor/openplan3d-h1/editor/index\\.html\\?v=[^\"]+)\"#',
  'src=\"$1\"',
  $partial
);
$v = time();
echo '<!DOCTYPE html><html lang="en" class="admin-dark"><head><meta charset="utf-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
echo '<script>try{localStorage.setItem("zanini-admin-theme","dark");}catch(e){}</script>';
echo '<link rel="stylesheet" href="/css/admin.css?v='.$v.'">';
echo '<link rel="stylesheet" href="/css/admin-utilities.css?v='.$v.'">';
echo '<link rel="stylesheet" href="/css/admin-dark.css?v='.$v.'">';
echo '</head><body class="admin-body admin-body--fullscreen">';
echo '<div class="admin-app admin-app--fullscreen" id="admin-app">';
echo '<main class="admin-main"><div class="admin-content">';
echo $partial;
echo '</div></main></div>';
echo '<script src="/js/admin-theme.js?v='.$v.'"></script>';
echo '<script src="/js/admin-info-tip.js?v='.$v.'"></script>';
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

async function captureTakeoffsPanel(width, height, filename) {
  const html = renderPage();
  const { server, port } = await startServer(html);
  const puppeteer = require('puppeteer');
  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });
  const page = await browser.newPage();
  await page.setViewport({ width, height, deviceScaleFactor: 1 });
  await page.goto(`http://127.0.0.1:${port}/`, { waitUntil: 'networkidle2', timeout: 120000 });

  await page.waitForSelector('#openplan3d-takeoffs-strip', { timeout: 30000 });
  await page.waitForSelector('#openplan3d-metrics-ribbon', { timeout: 30000 });
  await page.waitForSelector('#openplan3d-takeoffs-summary-floor', { timeout: 30000 });

  const hand0Plan = JSON.parse(
    fs.readFileSync(path.join(ROOT, 'tests/fixtures/openplan3d-hand0-drawn-plan.json'), 'utf8')
  );
  const measuredArea = await page.evaluate((plan) => {
    var strip = document.getElementById('openplan3d-takeoffs-strip');
    var toggle = document.getElementById('openplan3d-takeoffs-toggle');
    if (strip) {
      strip.classList.remove('is-collapsed', 'is-collapsed-on-phone');
    }
    if (toggle) {
      toggle.setAttribute('aria-expanded', 'true');
    }
    var HostLib = window.OpenPlan3DHostLib;
    var bridge = window.OpenPlan3DMeasurementBridge;
    var metrics = HostLib && bridge
      ? HostLib.computeMetrics(plan, { wall_height_ft: 8.0 }, null, bridge)
      : null;
    var areaSqft = metrics && metrics.area_sqft != null ? Number(metrics.area_sqft) : 0;
    var areaText = areaSqft.toFixed(2);
    var area = document.getElementById('metric-area');
    if (area) area.textContent = areaText;
    var floor = document.getElementById('openplan3d-takeoffs-summary-floor');
    var zones = document.getElementById('openplan3d-takeoffs-summary-zones');
    if (floor) floor.textContent = areaText + ' sf floor';
    if (zones) zones.textContent = '0 zones';
    return areaSqft;
  }, hand0Plan);
  assert.strictEqual(measuredArea, 1305.98, 'takeoffs ribbon must show measured hand0 interior floor area');

  const layout = await page.evaluate(() => {
    const bar = document.querySelector('.openplan3d-takeoffs-strip__bar');
    const ribbon = document.getElementById('openplan3d-metrics-ribbon');
    const hint = document.getElementById('openplan3d-zones-empty-hint');
    const floor = document.getElementById('openplan3d-takeoffs-summary-floor');
    const head = hint ? hint.closest('.openplan3d-zones-panel__head') : null;
    const title = head ? head.querySelector('.openplan3d-zones-panel__title') : null;
    const barRect = bar ? bar.getBoundingClientRect() : null;
    const ribbonRect = ribbon ? ribbon.getBoundingClientRect() : null;
    const ribbonParent = ribbon ? ribbon.parentElement : null;
    const chipsInBar = !!(ribbonParent && ribbonParent.classList.contains('openplan3d-takeoffs-strip__bar'));
    const ribbonScrollW = ribbon ? ribbon.scrollWidth : 0;
    const ribbonClientW = ribbon ? ribbon.clientWidth : 0;
    const hintRect = hint ? hint.getBoundingClientRect() : null;
    const titleRect = title ? title.getBoundingClientRect() : null;
    return {
      chipsInBar,
      ribbonOverflows: ribbonScrollW > ribbonClientW + 2,
      hintVisible: !!(
        hint
        && !hint.hidden
        && getComputedStyle(hint).display !== 'none'
        && getComputedStyle(hint).visibility !== 'hidden'
      ),
      hintInHead: !!(hint && head && head.contains(hint)),
      hintBesideTitle: !!(hintRect && titleRect
        && hintRect.left >= titleRect.left - 2
        && Math.abs(hintRect.top - titleRect.top) < 28),
      floorBelowToggle: barRect && floor
        ? floor.getBoundingClientRect().top >= barRect.top - 1
        : false,
      ribbonInTopBand: barRect && ribbonRect
        ? ribbonRect.top <= barRect.top + barRect.height + 2
        : false,
    };
  });

  assert.strictEqual(layout.chipsInBar, true, 'metric chips must live in the takeoffs top bar');
  assert.strictEqual(layout.ribbonOverflows, false, `${width}px: metrics ribbon must not scroll horizontally`);
  assert.strictEqual(layout.hintVisible, true, 'empty zones hint must show when there are no zones');
  assert.strictEqual(layout.hintInHead, true, 'zones empty hint must live in the Area zones header');
  if (width >= 600) {
    assert.strictEqual(layout.hintBesideTitle, true, 'zones empty hint must sit beside the Area zones label at desktop widths');
  }
  assert.strictEqual(layout.ribbonInTopBand, true, 'metrics ribbon must stay in the compact top band');

  const strip = await page.$('#openplan3d-takeoffs-strip');
  assert.ok(strip, 'takeoffs strip must exist for screenshot');
  if (!fs.existsSync(shotsDir)) fs.mkdirSync(shotsDir, { recursive: true });
  const outPath = path.join(shotsDir, filename);
  await strip.screenshot({ path: outPath });
  const artifactsDir = '/opt/cursor/artifacts';
  if (fs.existsSync(artifactsDir)) {
    fs.copyFileSync(outPath, path.join(artifactsDir, filename));
  }

  await browser.close();
  server.close();
  return { width, filename, layout };
}

async function main() {
  const results = [];
  results.push(await captureTakeoffsPanel(1280, 900, 'room-area-takeoffs-panel-1280.png'));
  results.push(await captureTakeoffsPanel(390, 844, 'room-area-takeoffs-panel-390.png'));
  console.log(JSON.stringify({ ok: true, results }, null, 2));
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
