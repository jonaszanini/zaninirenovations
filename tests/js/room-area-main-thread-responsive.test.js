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

const MAX_MAIN_THREAD_STALL_MS = 2000;

function renderPage(options) {
  const theme = options.theme || 'dark';
  const o3dTheme = options.o3dTheme != null ? options.o3dTheme : theme;
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
$htmlClass = ${theme === 'dark' ? "'admin-dark'" : "''"};
echo '<!DOCTYPE html><html lang="en" class="'.$htmlClass.'"><head><meta charset="utf-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
echo '<script>try{localStorage.setItem("zanini-admin-theme","${theme === "dark" ? "dark" : "light"}");localStorage.setItem("o3d_theme","${o3dTheme === "system" ? "system" : (o3dTheme === "dark" ? "dark" : "light")}");}catch(e){}</script>';
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
      if (urlPath === '/favicon.ico') {
        res.writeHead(204);
        res.end();
        return;
      }
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

async function assertResponsiveLoad(caseDef) {
  const html = renderPage(caseDef);
  const { server, port } = await startServer(html);
  const puppeteer = require('puppeteer');
  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });
  const page = await browser.newPage();
  await page.evaluateOnNewDocument(() => {
    window.__beat = Date.now();
    setInterval(() => {
      window.__beat = Date.now();
    }, 25);
  });
  if (caseDef.colorScheme === 'dark') {
    await page.emulateMediaFeatures([{ name: 'prefers-color-scheme', value: 'dark' }]);
  } else if (caseDef.colorScheme === 'light') {
    await page.emulateMediaFeatures([{ name: 'prefers-color-scheme', value: 'light' }]);
  }

  const errors = [];
  const failedReq = [];
  page.on('pageerror', (e) => errors.push(e.message));
  page.on('requestfailed', (req) => {
    const u = req.url();
    if (u.includes('favicon.ico')) return;
    failedReq.push(u + ' ' + (req.failure() || {}).errorText);
  });
  page.on('response', (res) => {
    if (res.status() >= 400 && !res.url().includes('favicon.ico')) {
      failedReq.push(`${res.status()} ${res.url()}`);
    }
  });

  await page.setViewport({ width: caseDef.width, height: caseDef.height });
  await page.goto(`http://127.0.0.1:${port}/`, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForSelector('#openplan3d-iframe', { timeout: 30000 });

  const iframeEl = await page.$('#openplan3d-iframe');
  const iframeMeta = iframeEl
    ? await iframeEl.evaluate((el) => ({ src: el.src, loading: el.loading }))
    : null;
  const frame = iframeEl && (await iframeEl.contentFrame());
  let canvas = false;
  if (frame) {
    canvas = await frame
      .waitForSelector('canvas', { timeout: 90000 })
      .then(() => true)
      .catch(() => false);
  }

  await page
    .waitForFunction(() => {
      const h = window.OpenPlan3DHost;
      return h && h.getState && h.getState().iframeReady;
    }, { timeout: 90000 })
    .catch(() => {});

  let maxStall = 0;
  const watchUntil = Date.now() + 5000;
  while (Date.now() < watchUntil) {
    const stall = await page.evaluate(() => Date.now() - (window.__beat || 0));
    if (stall > maxStall) maxStall = stall;
    if (stall > MAX_MAIN_THREAD_STALL_MS) break;
    await new Promise((r) => setTimeout(r, 100));
  }

  const host = await page.evaluate(() => {
    const h = window.OpenPlan3DHost;
    return h && h.getState ? h.getState() : null;
  });

  const themeToggle = await page.$('#openplan3d-theme-toggle');
  if (themeToggle) {
    for (let i = 0; i < 6; i++) {
      await themeToggle.click();
      await new Promise((r) => setTimeout(r, 80));
      const stall = await page.evaluate(() => Date.now() - (window.__beat || 0));
      if (stall > maxStall) maxStall = stall;
      assert.ok(stall <= MAX_MAIN_THREAD_STALL_MS, `${caseDef.label} stalled ${stall}ms during theme toggle`);
    }
  }

  const steadyWatchUntil = Date.now() + 3000;
  while (Date.now() < steadyWatchUntil) {
    const stall = await page.evaluate(() => Date.now() - (window.__beat || 0));
    if (stall > maxStall) maxStall = stall;
    if (stall > MAX_MAIN_THREAD_STALL_MS) break;
    await new Promise((r) => setTimeout(r, 100));
  }

  if (caseDef.screenshot) {
    const outDir = process.env.QA_OUT || path.join(ROOT, 'storage', 'app/qa-screenshots');
    fs.mkdirSync(outDir, { recursive: true });
    await page.screenshot({ path: path.join(outDir, `${caseDef.screenshot}.png`), fullPage: false });
  }

  await browser.close();
  server.close();

  assert.strictEqual(
    errors.length,
    0,
    `${caseDef.label} JS errors: ${errors.join('; ')} (failed: ${failedReq.slice(0, 8).join('; ')})`
  );
  assert.ok(
    maxStall <= MAX_MAIN_THREAD_STALL_MS,
    `${caseDef.label} main thread blocked ${maxStall}ms (max ${MAX_MAIN_THREAD_STALL_MS})`
  );
  assert.ok(
    canvas,
    `${caseDef.label} must render canvas (iframeEl=${!!iframeEl}, frame=${!!frame}, src=${iframeMeta && iframeMeta.src}, failed: ${failedReq.slice(0, 3).join('; ')})`
  );
  assert.ok(host && host.iframeReady, `${caseDef.label} host must be iframeReady`);

  return { label: caseDef.label, maxStall, canvas, iframeReady: host.iframeReady };
}

async function main() {
  const cases = [
    {
      label: 'dark-admin',
      theme: 'dark',
      o3dTheme: 'system',
      colorScheme: 'dark',
      width: 1280,
      height: 900,
      screenshot: 'room-area-editor-desktop',
    },
    {
      label: 'light-admin',
      theme: 'light',
      o3dTheme: 'light',
      colorScheme: 'light',
      width: 1280,
      height: 900,
    },
    {
      label: 'dark-admin-prefers-dark-390',
      theme: 'dark',
      o3dTheme: 'system',
      colorScheme: 'dark',
      width: 390,
      height: 844,
      screenshot: 'room-area-editor-390',
    },
    {
      label: 'dark-admin-prefers-light',
      theme: 'dark',
      o3dTheme: 'system',
      colorScheme: 'light',
      width: 390,
      height: 844,
    },
  ];

  for (const c of cases) {
    const r = await assertResponsiveLoad(c);
    console.log(JSON.stringify(r));
  }
  console.log('room-area-main-thread-responsive: ok');
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
