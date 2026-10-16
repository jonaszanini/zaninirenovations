'use strict';

const assert = require('assert');
const fs = require('fs');
const http = require('http');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const FIXTURE = path.join(__dirname, 'fixtures/openplan3d-room-area.html');

let lastSavedBody = null;

function startServer() {
  return new Promise((resolve, reject) => {
    const publicDir = path.join(ROOT, 'public');
    const server = http.createServer((req, res) => {
      const urlPath = decodeURIComponent((req.url || '/').split('?')[0]);

      if (urlPath.startsWith('/api/rooms/')) {
        const chunks = [];
        req.on('data', (c) => chunks.push(c));
        req.on('end', () => {
          lastSavedBody = JSON.parse(Buffer.concat(chunks).toString('utf8'));
          res.writeHead(200, { 'Content-Type': 'application/json' });
          res.end(JSON.stringify({
            ok: true,
            room: { id: 10, name: lastSavedBody.name, area_sqft: 180.0 },
          }));
        });
        return;
      }

      let file;
      if (urlPath === '/' || urlPath === '/openplan3d-room-area.html') {
        file = FIXTURE;
      } else {
        file = path.join(publicDir, urlPath.replace(/^\//, ''));
      }

      const allowed = file === FIXTURE || file.startsWith(publicDir + path.sep);
      if (!allowed) {
        res.writeHead(403);
        res.end();
        return;
      }

      fs.readFile(file, (err, data) => {
        if (err) {
          res.writeHead(404);
          res.end('not found');
          return;
        }
        const ext = path.extname(file);
        const types = {
          '.css': 'text/css',
          '.html': 'text/html',
          '.js': 'text/javascript',
          '.json': 'application/json',
          '.svg': 'image/svg+xml',
          '.png': 'image/png',
        };
        res.writeHead(200, { 'Content-Type': types[ext] || 'application/octet-stream' });
        res.end(data);
      });
    });

    server.listen(0, '127.0.0.1', () => resolve({ server, port: server.address().port }));
    server.on('error', reject);
  });
}

async function launch() {
  const puppeteer = require('puppeteer');
  return puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });
}

async function run() {
  console.log('Starting openPlan3D Puppeteer Browser Tests...');
  const { server, port } = await startServer();
  const browser = await launch();
  const page = await browser.newPage();

  try {
    // 1. Desktop Mode (1280 x 900)
    await page.setViewport({ width: 1280, height: 900 });
    await page.goto(`http://127.0.0.1:${port}/openplan3d-room-area.html`, { waitUntil: 'domcontentloaded' });

    // Assert toolbar and elements loaded
    const title = await page.$eval('.util-ra-openplan3d-title', (el) => el.textContent.trim());
    assert.strictEqual(title, 'Room Area', 'Page title should be Room Area');

    const quoteBtnVisible = await page.$eval('#openplan3d-quote-btn', (el) => !el.hidden);
    assert.strictEqual(quoteBtnVisible, true, 'Quote button should be visible for saved room');

    const quoteHref = await page.$eval('#openplan3d-quote-btn', (el) => el.getAttribute('href'));
    assert.strictEqual(quoteHref, '/admin/quotations?layout=room:10', 'Quote button should link to room 10');

    const initialArea = await page.$eval('#metric-area', (el) => el.textContent.trim());
    assert.strictEqual(initialArea, '150.00', 'Initial area should be 150.00 sq ft');

    const initialOpenings = await page.$eval('#metric-openings', (el) => el.textContent.trim());
    assert.strictEqual(initialOpenings, '0 doors · 0 windows', 'Initial openings should be 0');

    const dirtyAtLoad = await page.$eval('#openplan3d-dirty-badge', (el) => ({
      hidden: el.hidden,
      display: getComputedStyle(el).display,
    }));
    assert.strictEqual(dirtyAtLoad.hidden, true, 'Saved room must open without an Unsaved badge');
    assert.strictEqual(dirtyAtLoad.display, 'none', 'hidden Unsaved badge must not be visible');

    const blankBootPlan = {
      id: 'untitled',
      name: 'Untitled Project',
      floors: [{ id: 'f1', walls: [], rooms: [] }],
    };
    await page.evaluate((plan) => {
      window.postMessage({ type: 'OPENPLAN3D_PLAN_CHANGED', plan: plan }, '*');
    }, blankBootPlan);
    await new Promise((r) => setTimeout(r, 80));

    const areaBeforeAck = await page.$eval('#metric-area', (el) => el.textContent.trim());
    assert.strictEqual(areaBeforeAck, '150.00', 'Boot Untitled Project must not overwrite loaded metrics before LOAD_ACK');
    const dirtyBeforeAck = await page.$eval('#openplan3d-dirty-badge', (el) => !el.hidden);
    assert.strictEqual(dirtyBeforeAck, false, 'Boot store writes must not mark the room Unsaved');

    await page.evaluate((plan) => {
      window.postMessage({ type: 'OPENPLAN3D_LOAD_ACK', ok: false, plan: plan }, '*');
    }, blankBootPlan);
    await new Promise((r) => setTimeout(r, 50));

    const areaAfterFailedAck = await page.$eval('#metric-area', (el) => el.textContent.trim());
    assert.strictEqual(areaAfterFailedAck, '150.00', 'ACK ok:false must not adopt the editor current plan');
    const saveDisabledOnFail = await page.$eval('#openplan3d-save-btn', (el) => el.disabled);
    assert.strictEqual(saveDisabledOnFail, true, 'ACK ok:false must keep Save disabled');
    const failedAckFeedback = await page.$eval('#openplan3d-feedback', (el) => ({
      hidden: el.hidden,
      text: el.textContent,
      error: el.className.indexOf('openplan3d-feedback--error') !== -1,
    }));
    assert.strictEqual(failedAckFeedback.hidden, false, 'ACK ok:false must show a visible error');
    assert.ok(failedAckFeedback.error, 'ACK ok:false feedback is an error');
    assert.match(failedAckFeedback.text, /did not confirm/i);
    const stateAfterFail = await page.evaluate(() => window.OpenPlan3DHost.getState());
    assert.strictEqual(stateAfterFail.loadAcked, false, 'ACK ok:false must not mark loadAcked');
    assert.notStrictEqual(
      (stateAfterFail.currentPlan && stateAfterFail.currentPlan.name) || '',
      'Untitled Project',
      'ACK ok:false must keep the saved room plan'
    );

    await page.evaluate(() => {
      window.postMessage({ type: 'OPENPLAN3D_LOAD_ACK', ok: true }, '*');
    });
    await new Promise((r) => setTimeout(r, 50));

    const areaAfterAck = await page.$eval('#metric-area', (el) => el.textContent.trim());
    assert.strictEqual(areaAfterAck, '150.00', 'LOAD_ACK must keep the saved geometry metrics');
    const dirtyAfterAck = await page.$eval('#openplan3d-dirty-badge', (el) => !el.hidden);
    assert.strictEqual(dirtyAfterAck, false, 'LOAD_ACK must leave the Unsaved badge hidden');

    const loadedSamePlan = await page.evaluate(() => window.OpenPlan3DHost.getState().currentPlan);
    await page.evaluate((plan) => {
      window.postMessage({ type: 'OPENPLAN3D_PLAN_CHANGED', plan: plan }, '*');
    }, loadedSamePlan);
    await new Promise((r) => setTimeout(r, 50));
    const dirtySamePlan = await page.$eval('#openplan3d-dirty-badge', (el) => ({
      hidden: el.hidden,
      display: getComputedStyle(el).display,
    }));
    assert.strictEqual(dirtySamePlan.hidden, true, 'Re-emitting the loaded plan after ACK must not mark Unsaved');
    assert.strictEqual(dirtySamePlan.display, 'none', 'Unsaved stays visually hidden when the plan matches');
    const areaDelta = Math.abs(parseFloat(areaAfterAck) - 150.0);
    assert.ok(areaDelta <= 0.01, 'Opened metrics must match stored area_sqft within 0.01');

    // 2. Simulate Drawing Updates / OpenPlan3D Plan Changed with Door and Window
    const updatedPlan = {
      unit: 'cm',
      version: '1.0',
      floor: {
        walls: [
          { id: 'w1', start: { x: 0, y: 0 }, end: { x: 365.76, y: 0 }, height: 243.84, thickness: 10 },
          { id: 'w2', start: { x: 365.76, y: 0 }, end: { x: 365.76, y: 457.2 }, height: 243.84, thickness: 10 },
          { id: 'w3', start: { x: 365.76, y: 457.2 }, end: { x: 0, y: 457.2 }, height: 243.84, thickness: 10 },
          { id: 'w4', start: { x: 0, y: 457.2 }, end: { x: 0, y: 0 }, height: 243.84, thickness: 10 },
        ],
        rooms: [
          {
            id: 'r1',
            name: 'Living Room',
            walls: ['w1', 'w2', 'w3', 'w4'],
            polygon: [
              { x: 0, y: 0 },
              { x: 365.76, y: 0 },
              { x: 365.76, y: 457.2 },
              { x: 0, y: 457.2 },
            ],
          },
        ],
        doors: [
          { id: 'd1', wallId: 'w1', type: 'door', width: 91.44, height: 203.2, offset: 100 },
        ],
        windows: [
          { id: 'win1', wallId: 'w2', type: 'window', width: 121.92, height: 91.44, offset: 100, casing_sides: 4 },
        ],
      },
    };

    await page.evaluate((plan) => {
      window.postMessage({ type: 'OPENPLAN3D_PLAN_CHANGED', plan: plan }, '*');
    }, updatedPlan);

    await new Promise((r) => setTimeout(r, 100));

    // Assert live metrics updated
    const newArea = await page.$eval('#metric-area', (el) => el.textContent.trim());
    assert.strictEqual(newArea, '180.00', 'Area should update to 180.00 sq ft');

    const newOpenings = await page.$eval('#metric-openings', (el) => el.textContent.trim());
    assert.strictEqual(newOpenings, '1 doors · 1 windows', 'Openings count should update to 1 doors · 1 windows');

    const newCasing = await page.$eval('#metric-casing', (el) => parseFloat(el.textContent.trim()));
    assert.ok(newCasing > 10, 'Casing should be computed from doors and windows');

    const isDirtyVisible = await page.$eval('#openplan3d-dirty-badge', (el) => !el.hidden);
    assert.strictEqual(isDirtyVisible, true, 'Dirty badge must appear on drawing changes');

    // 3. Test Ctrl+S save shortcut forwarded from editor iframe
    lastSavedBody = null;
    await page.evaluate(() => {
      window.postMessage({ type: 'OPENPLAN3D_TRIGGER_SAVE' }, '*');
    });

    await new Promise((r) => setTimeout(r, 200));

    assert.ok(lastSavedBody !== null, 'Save request should have been dispatched to server');
    assert.strictEqual(lastSavedBody.name, 'Living Room', 'Saved name should match room name');
    assert.ok(lastSavedBody._submit_once, 'Save payload must contain _submit_once token');
    assert.strictEqual(lastSavedBody.plan.unit, 'cm', 'Saved plan must contain geometry');

    const isDirtyAfterSave = await page.$eval('#openplan3d-dirty-badge', (el) => !el.hidden);
    assert.strictEqual(isDirtyAfterSave, false, 'Dirty badge must disappear after save');

    // 4. Mobile Viewport (390 x 844) & Touch Interactions
    await page.setViewport({ width: 390, height: 844 });
    await new Promise((r) => setTimeout(r, 100));

    // Check Settings Drawer opens and closes on tap
    const drawerInitiallyHidden = await page.$eval('#openplan3d-settings-drawer', (el) => el.hidden);
    assert.strictEqual(drawerInitiallyHidden, true, 'Settings drawer should be closed initially');

    await page.click('#openplan3d-settings-btn');
    const drawerOpen = await page.$eval('#openplan3d-settings-drawer', (el) => !el.hidden);
    assert.strictEqual(drawerOpen, true, 'Settings drawer should open after tap');

    await page.click('#openplan3d-settings-close');
    const drawerClosed = await page.$eval('#openplan3d-settings-drawer', (el) => el.hidden);
    assert.strictEqual(drawerClosed, true, 'Settings drawer should close after tap');

    // Touch targets verification
    const saveBtnBox = await page.$eval('#openplan3d-save-btn', (el) => {
      const rect = el.getBoundingClientRect();
      return { width: rect.width, height: rect.height };
    });
    assert.ok(saveBtnBox.height >= 36, 'Save button height should be comfortably tappable');

    // 5. Two-room plan: strip uses whole plan, not database room id 10
    const twoRoomPlan = {
      unit: 'cm',
      version: '1.0',
      floor: {
        walls: [
          { id: 'w1', start: { x: 0, y: 0 }, end: { x: 304.8, y: 0 }, height: 243.84, thickness: 10 },
          { id: 'w2', start: { x: 304.8, y: 0 }, end: { x: 304.8, y: 304.8 }, height: 243.84, thickness: 10 },
          { id: 'w3', start: { x: 304.8, y: 304.8 }, end: { x: 0, y: 304.8 }, height: 243.84, thickness: 10 },
          { id: 'w4', start: { x: 0, y: 304.8 }, end: { x: 0, y: 0 }, height: 243.84, thickness: 10 },
          { id: 'w5', start: { x: 304.8, y: 0 }, end: { x: 609.6, y: 0 }, height: 243.84, thickness: 10 },
          { id: 'w6', start: { x: 609.6, y: 0 }, end: { x: 609.6, y: 304.8 }, height: 243.84, thickness: 10 },
          { id: 'w7', start: { x: 609.6, y: 304.8 }, end: { x: 304.8, y: 304.8 }, height: 243.84, thickness: 10 },
        ],
        rooms: [
          {
            id: 'r1',
            name: 'Kitchen',
            walls: ['w1', 'w2', 'w3', 'w4'],
            polygon: [
              { x: 0, y: 0 }, { x: 304.8, y: 0 }, { x: 304.8, y: 304.8 }, { x: 0, y: 304.8 },
            ],
          },
          {
            id: 'r2',
            name: 'Bath',
            walls: ['w5', 'w6', 'w7', 'w2'],
            polygon: [
              { x: 304.8, y: 0 }, { x: 609.6, y: 0 }, { x: 609.6, y: 304.8 }, { x: 304.8, y: 304.8 },
            ],
          },
        ],
      },
    };
    await page.evaluate((plan) => {
      window.postMessage({ type: 'OPENPLAN3D_PLAN_CHANGED', plan: plan }, '*');
    }, twoRoomPlan);
    await new Promise((r) => setTimeout(r, 80));
    const twoRoomStrip = await page.$eval('#metric-area', (el) => el.textContent.trim());
    assert.strictEqual(twoRoomStrip, '200.00', 'Metrics strip must use whole-plan area, not database room id 10');
    lastSavedBody = null;
    await page.evaluate(() => window.postMessage({ type: 'OPENPLAN3D_TRIGGER_SAVE' }, '*'));
    await new Promise((r) => setTimeout(r, 200));
    assert.ok(lastSavedBody, 'Two-room save should post');
    assert.strictEqual(twoRoomStrip, '200.00');
    assert.ok(lastSavedBody.plan.floor.rooms.length === 2, 'Saved plan must keep both openPlan3D rooms');

    // 6. Save Plan never sends 0 metrics for a >0 room without confirm
    const zeroPlan = { format: 'openplan3d', unit: 'cm', floor: { walls: [], rooms: [] } };
    await page.evaluate((plan) => {
      window.postMessage({ type: 'OPENPLAN3D_PLAN_CHANGED', plan: plan }, '*');
    }, zeroPlan);
    await new Promise((r) => setTimeout(r, 50));
    lastSavedBody = null;
    await page.evaluate(() => {
      window.__confirmAnswers = [false];
      window.confirm = function () {
        return window.__confirmAnswers.shift() !== false;
      };
    });
    await page.click('#openplan3d-save-btn');
    await new Promise((r) => setTimeout(r, 150));
    assert.strictEqual(lastSavedBody, null, 'Save Plan must not send a 0-area overwrite when confirm is declined');

    lastSavedBody = null;
    await page.evaluate(() => {
      window.__confirmAnswers = [true];
    });
    await page.click('#openplan3d-save-btn');
    await new Promise((r) => setTimeout(r, 200));
    assert.ok(lastSavedBody, 'Save Plan may send 0-area after explicit confirm');
    assert.strictEqual(lastSavedBody.confirm_zero_area, true, 'Confirmed 0-area save must send confirm_zero_area');

    console.log('All openPlan3D Puppeteer browser tests PASSED successfully!');
  } finally {
    await browser.close();
    server.close();
  }
}

run().catch((err) => {
  console.error('Browser test FAILED:', err);
  process.exit(1);
});
