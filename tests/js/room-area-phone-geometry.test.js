'use strict';

const assert = require('assert');
const puppeteer = require('puppeteer');

const BASE = process.env.RA_LIVE_URL || process.env.APP_URL || 'http://127.0.0.1:8001';
const EMAIL = process.env.RA_LIVE_EMAIL || 'admin@zanini.ca';
const PASSWORD = process.env.RA_LIVE_PASSWORD || 'password';

async function login(page) {
  await page.goto(`${BASE}/admin/login`, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.type('input[name="email"]', EMAIL);
  await page.type('input[name="password"]', PASSWORD);
  await Promise.all([
    page.click('button[type="submit"]'),
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 60000 }),
  ]);
}

async function run() {
  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });
  const page = await browser.newPage();
  page.on('pageerror', (e) => {
    throw new Error('page error: ' + e.message);
  });
  await page.setViewport({ width: 390, height: 844 });

  await login(page);
  await page.goto(`${BASE}/admin/utilities/room-area`, {
    waitUntil: 'domcontentloaded',
    timeout: 60000,
  });

  await page.waitForSelector('#openplan3d-iframe', { timeout: 30000 });

  const iframeMeta = await page.evaluate(() => {
    const el = document.getElementById('openplan3d-iframe');
    return {
      src: el ? el.getAttribute('src') || '' : '',
      pageOrigin: window.location.origin,
    };
  });

  assert.ok(iframeMeta.src.startsWith('/vendor/openplan3d/editor/index.html'), 'iframe must use root-relative vendor URL');

  const iframeEl = await page.$('#openplan3d-iframe');
  const frame = iframeEl && (await iframeEl.contentFrame());
  assert.ok(frame, 'openPlan3D iframe must load');

  const canvas = await frame
    .waitForSelector('canvas', { timeout: 90000 })
    .then(() => true)
    .catch(() => false);
  assert.ok(canvas, 'openPlan3D canvas must render at 390px width');

  const host = await page.evaluate(() => {
    const h = window.OpenPlan3DHost;
    return h && h.getState ? h.getState() : null;
  });
  assert.ok(host && host.iframeReady, 'OpenPlan3DHost must receive OPENPLAN3D_READY');
  assert.ok(host.loadAcked, 'OpenPlan3DHost must receive LOAD_ACK');

  const geom = await page.evaluate(() => {
    const el = document.getElementById('openplan3d-iframe');
    const r = el && el.getBoundingClientRect();
    return { iframeH: r ? r.height : 0 };
  });
  assert.ok(geom.iframeH >= 200, 'iframe must have usable height on phone layout');

  await browser.close();
  console.log('room-area-phone-geometry: ok');
}

run().catch((err) => {
  console.error(err);
  process.exit(1);
});
