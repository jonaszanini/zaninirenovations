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
echo '<script src="/js/room-area-interior-geometry.js?v='.$v.'"></script>';
echo '<script src="/js/openplan3d-measurement-bridge.js?v='.$v.'"></script>';
echo '<script src="/js/openplan3d-zanini-extensions.js?v='.$v.'"></script>';
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
  const chipState = await page.evaluate((plan) => {
    var strip = document.getElementById('openplan3d-takeoffs-strip');
    var toggle = document.getElementById('openplan3d-takeoffs-toggle');
    if (strip) {
      strip.classList.remove('is-collapsed', 'is-collapsed-on-phone');
    }
    if (toggle) {
      toggle.setAttribute('aria-expanded', 'true');
    }
    plan = JSON.parse(JSON.stringify(plan));
    plan.zanini = plan.zanini || { version: 1, zones: [], bulkheads: [] };
    plan.zanini.wallFinishes = plan.zanini.wallFinishes || {};
  var floor = plan.floors && plan.floors[0];
  if (floor && floor.walls) {
    for (var wi = 0; wi < floor.walls.length; wi++) {
      var wid = String(floor.walls[wi].id);
      plan.zanini.wallFinishes[wid] = {
        drywall: true,
        paint: true,
        insulation: true,
        studs: true,
        baseboard: true,
        drywallFaces: 'both',
      };
    }
  }
    var HostLib = window.OpenPlan3DHostLib;
    var bridge = window.OpenPlan3DMeasurementBridge;
    var metrics = HostLib && bridge
      ? HostLib.computeMetrics(plan, { wall_height_ft: 8.0, ceiling_height_ft: 8.0 }, null, bridge)
      : null;
    var set = function (id, val, dec) {
      var el = document.getElementById(id);
      if (el && val != null) el.textContent = Number(val).toFixed(dec == null ? 2 : dec);
    };
    if (metrics) {
      set('metric-area', metrics.area_sqft);
      set('metric-perimeter', metrics.perimeter_lf);
      set('metric-baseboard', metrics.finish_baseboard_lf != null ? metrics.finish_baseboard_lf : metrics.baseboard_lf);
      set('metric-wall-area', metrics.wall_area_sqft);
      set('metric-finish-drywall', metrics.finish_drywall_sqft);
      set('metric-finish-paint', metrics.finish_paint_sqft);
      set('metric-finish-insulation', metrics.finish_insulation_sqft);
      set('metric-finish-studs', metrics.finish_stud_lf);
      var openEl = document.getElementById('metric-openings');
      if (openEl) {
        openEl.textContent = (metrics.door_count || 0) + ' doors \u00b7 ' + (metrics.window_count || 0) + ' windows';
      }
      set('metric-casing', metrics.casing_lf);
      set('metric-boxes', metrics.flooring_boxes, 0);
    }
    var areaSqft = metrics && metrics.area_sqft != null ? Number(metrics.area_sqft) : 0;
    var areaText = areaSqft.toFixed(2);
    var floorSummary = document.getElementById('openplan3d-takeoffs-summary-floor');
    var zones = document.getElementById('openplan3d-takeoffs-summary-zones');
    if (floorSummary) floorSummary.textContent = areaText + ' sf floor';
    if (zones) zones.textContent = '0 zones';
    return {
      areaSqft: areaSqft,
      finishDrywall: metrics ? Number(metrics.finish_drywall_sqft) : 0,
      finishInsulation: metrics ? Number(metrics.finish_insulation_sqft) : 0,
      finishBaseboard: metrics ? Number(metrics.finish_baseboard_lf != null ? metrics.finish_baseboard_lf : metrics.baseboard_lf) : 0,
    };
  }, hand0Plan);
  assert.strictEqual(chipState.areaSqft, 1307.52, 'takeoffs ribbon must show measured hand0 interior floor area');
  assert.ok(chipState.finishDrywall > 0, 'drywall finish chip must be non-zero on measured hand0 plan');
  assert.ok(chipState.finishInsulation > 0, 'insulation finish chip must be non-zero on measured hand0 plan');
  assert.ok(Math.abs(chipState.finishDrywall - 1596.22) < 1.5, 'hand0 drywall finish pin ~1596.22 sf');
  assert.ok(Math.abs(chipState.finishBaseboard - 199.53) < 0.05, 'hand0 finish_baseboard_lf ~199.53 lf with all finishes');

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
    let clipViolations = [];
    if (ribbon) {
      ribbon.querySelectorAll('.openplan3d-metric-card, .openplan3d-metric-val, #metric-openings').forEach((el) => {
        if (el.scrollWidth > el.clientWidth + 1) clipViolations.push((el.id || el.className) + ':' + (el.textContent || '').trim().slice(0, 80));
      });
    }
    const hintRect = hint ? hint.getBoundingClientRect() : null;
    const titleRect = title ? title.getBoundingClientRect() : null;
    let ribbonRowCount = 0;
    if (ribbon) {
      const tops = new Set();
      ribbon.querySelectorAll('.openplan3d-metric-card').forEach((card) => {
        tops.add(Math.round(card.getBoundingClientRect().top));
      });
      ribbonRowCount = tops.size;
    }
    return {
      chipsInBar,
      ribbonRowCount,
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
      ribbonBesideSummary: barRect && ribbonRect && floor
        ? ribbonRect.left >= floor.getBoundingClientRect().right - 4
        : false,
      clipViolations,
    };
  });

  assert.strictEqual(layout.chipsInBar, true, 'metric chips must live in the takeoffs top bar');
  assert.strictEqual(layout.ribbonOverflows, false, `${width}px: metrics ribbon must not scroll horizontally`);
  assert.strictEqual(layout.clipViolations.length, 0, `${width}px: chip text must not clip: ${layout.clipViolations.join('; ')}`);
  assert.strictEqual(layout.hintVisible, true, 'empty zones hint must show when there are no zones');
  assert.strictEqual(layout.hintInHead, true, 'zones empty hint must live in the Area zones header');
  assert.strictEqual(layout.hintBesideTitle, true, 'zones empty hint must sit beside the Area zones label');
  if (width >= 1024) {
    assert.ok(layout.ribbonBesideSummary, `${width}px: chips should sit to the right of the floor summary`);
  }
  if (width === 1280) {
    assert.ok(layout.ribbonRowCount <= 3, `1280px ribbon must not grow past 3 rows (got ${layout.ribbonRowCount})`);
  }

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
  results.push(await captureTakeoffsPanel(1024, 900, 'room-area-takeoffs-panel-1024.png'));
  results.push(await captureTakeoffsPanel(1440, 900, 'room-area-takeoffs-panel-1440.png'));
  results.push(await captureTakeoffsPanel(1920, 900, 'room-area-takeoffs-panel-1920.png'));
  results.push(await captureTakeoffsPanel(390, 844, 'room-area-takeoffs-panel-390.png'));
  const row1280 = results.find((r) => r.width === 1280);
  console.log(JSON.stringify({ ok: true, chipRowCount1280: row1280 && row1280.layout.ribbonRowCount, results }, null, 2));
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
