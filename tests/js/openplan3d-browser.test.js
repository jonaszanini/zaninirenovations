'use strict';

const assert = require('assert');
const fs = require('fs');
const http = require('http');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const FIXTURE = path.join(__dirname, 'fixtures/openplan3d-room-area.html');
const host = require('../../public/js/openplan3d-host.js');

let lastSavedBody = null;
let lastProjectBody = null;
let saveCount = 0;
let saveDelayMs = 0;
let failNextSave = false;
let nextProjectId = 50;

function jsonBody(req) {
  return new Promise((resolve) => {
    const chunks = [];
    req.on('data', (c) => chunks.push(c));
    req.on('end', () => {
      const raw = Buffer.concat(chunks).toString('utf8');
      resolve(raw ? JSON.parse(raw) : {});
    });
  });
}

function sendJson(res, status, payload) {
  res.writeHead(status, { 'Content-Type': 'application/json' });
  res.end(JSON.stringify(payload));
}

function fixtureHtml(blank) {
  let html = fs.readFileSync(FIXTURE, 'utf8');
  if (blank) {
    html = html
      .replace('data-initial-room-id="10"', 'data-initial-room-id=""')
      .replace('data-initial-project-id="1"', 'data-initial-project-id=""');
  }
  return html;
}

function startServer() {
  return new Promise((resolve, reject) => {
    const publicDir = path.join(ROOT, 'public');
    const server = http.createServer((req, res) => {
      const urlPath = decodeURIComponent((req.url || '/').split('?')[0]);
      const method = (req.method || 'GET').toUpperCase();

      if (urlPath === '/api/projects' && method === 'POST') {
        jsonBody(req).then((body) => {
          lastProjectBody = body;
          const project = {
            id: nextProjectId++,
            name: body.name || 'Room plans',
            client_project_id: body.client_project_id || null,
            rooms: [],
          };
          sendJson(res, 200, { ok: true, project: project });
        });
        return;
      }

      const projectUpdate = urlPath.match(/^\/api\/projects\/(\d+)$/);
      if (projectUpdate && (method === 'PUT' || method === 'POST')) {
        jsonBody(req).then((body) => {
          lastProjectBody = body;
          sendJson(res, 200, {
            ok: true,
            project: {
              id: parseInt(projectUpdate[1], 10),
              name: body.name || 'Linked project',
              client_project_id: body.client_project_id || null,
              rooms: [],
            },
          });
        });
        return;
      }

      if ((urlPath.startsWith('/api/rooms/') || /\/api\/projects\/\d+\/rooms$/.test(urlPath)) && method !== 'GET') {
        jsonBody(req).then((body) => {
          lastSavedBody = body;
          saveCount += 1;
          const delay = saveDelayMs;
          const fail = failNextSave;
          saveDelayMs = 0;
          failNextSave = false;
          setTimeout(() => {
            if (fail) {
              sendJson(res, 500, { ok: false, message: 'save failed' });
              return;
            }
            const metrics = lastSavedBody.plan ? host.computeMetrics(lastSavedBody.plan) : null;
            const planArea = metrics && metrics.area_sqft != null ? Number(metrics.area_sqft) : 180.0;
            sendJson(res, 200, {
              ok: true,
              room: { id: lastSavedBody.id || 10, name: lastSavedBody.name, area_sqft: planArea },
            });
          }, delay);
        });
        return;
      }

      if (urlPath === '/' || urlPath === '/openplan3d-room-area.html') {
        const blank = /(?:\?|&)blank=1(?:&|$)/.test(req.url || '');
        const buf = Buffer.from(fixtureHtml(blank), 'utf8');
        res.writeHead(200, {
          'Content-Type': 'text/html; charset=utf-8',
          'Content-Length': buf.length,
        });
        res.end(buf);
        return;
      }

      const file = path.join(publicDir, urlPath.replace(/^\//, ''));
      const allowed = file.startsWith(publicDir + path.sep);
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
        res.writeHead(200, {
          'Content-Type': types[ext] || 'application/octet-stream',
          'Content-Length': data.length,
        });
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
  page.on('dialog', async (dialog) => {
    try { await dialog.accept(); } catch (e) {}
  });

  try {
    // 1. Desktop Mode (1280 x 900)
    await page.setViewport({ width: 1280, height: 900 });
    await page.goto(`http://127.0.0.1:${port}/openplan3d-room-area.html`, { waitUntil: 'domcontentloaded' });

    async function postFromEditor(data) {
      const handle = await page.$('#openplan3d-iframe');
      const frame = handle && await handle.contentFrame();
      if (!frame) throw new Error('openPlan3D iframe has no content frame');
      await frame.evaluate((msg) => {
        parent.postMessage(msg, '*');
      }, data);
    }

    // Assert toolbar and elements loaded
    const title = await page.$eval('.util-ra-openplan3d-title', (el) => el.textContent.trim());
    assert.strictEqual(title, 'Room Area', 'Page title should be Room Area');
    assert.strictEqual(await page.$('[data-ra-help-guide]'), null, 'Guide control must be removed from the editor');
    await page.evaluate(() => {
      window.__nativeDialogs = [];
      window.confirm = function () { window.__nativeDialogs.push('confirm'); return false; };
      window.alert = function () { window.__nativeDialogs.push('alert'); };
      window.prompt = function () { window.__nativeDialogs.push('prompt'); return null; };
    });

    const quoteBtnVisible = await page.$eval('#openplan3d-quote-btn', (el) => !el.hidden);
    assert.strictEqual(quoteBtnVisible, true, 'Quote button should be visible for saved room');

    const quoteHref = await page.$eval('#openplan3d-quote-btn', (el) => el.getAttribute('href'));
    assert.strictEqual(quoteHref, '/admin/quotations?layout=room:10', 'Quote button should link to room 10');

    const initialArea = await page.$eval('#metric-area', (el) => el.textContent.trim());
    assert.strictEqual(initialArea, '142.07', 'Initial area should match live interior floor area for saved room');

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
    await postFromEditor({ type: 'OPENPLAN3D_PLAN_CHANGED', plan: blankBootPlan });
    await new Promise((r) => setTimeout(r, 80));

    const areaBeforeAck = await page.$eval('#metric-area', (el) => el.textContent.trim());
    assert.strictEqual(areaBeforeAck, '142.07', 'Boot Untitled Project must not overwrite loaded metrics before LOAD_ACK');
    const dirtyBeforeAck = await page.$eval('#openplan3d-dirty-badge', (el) => !el.hidden);
    assert.strictEqual(dirtyBeforeAck, false, 'Boot store writes must not mark the room Unsaved');

    await postFromEditor({ type: 'OPENPLAN3D_LOAD_ACK', ok: false, plan: blankBootPlan });
    await new Promise((r) => setTimeout(r, 50));

    const areaAfterFailedAck = await page.$eval('#metric-area', (el) => el.textContent.trim());
    assert.strictEqual(areaAfterFailedAck, '142.07', 'ACK ok:false must not adopt the editor current plan');
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

    await postFromEditor({ type: 'OPENPLAN3D_LOAD_ACK', ok: true });
    await new Promise((r) => setTimeout(r, 50));

    const areaAfterAck = await page.$eval('#metric-area', (el) => el.textContent.trim());
    assert.strictEqual(areaAfterAck, '142.07', 'LOAD_ACK must keep the saved geometry metrics');
    const dirtyAfterAck = await page.$eval('#openplan3d-dirty-badge', (el) => !el.hidden);
    assert.strictEqual(dirtyAfterAck, false, 'LOAD_ACK must leave the Unsaved badge hidden');

    const loadedSamePlan = await page.evaluate(() => window.OpenPlan3DHost.getState().currentPlan);
    await postFromEditor({ type: 'OPENPLAN3D_PLAN_CHANGED', plan: loadedSamePlan });
    await new Promise((r) => setTimeout(r, 50));
    const dirtySamePlan = await page.$eval('#openplan3d-dirty-badge', (el) => ({
      hidden: el.hidden,
      display: getComputedStyle(el).display,
    }));
    assert.strictEqual(dirtySamePlan.hidden, true, 'Re-emitting the loaded plan after ACK must not mark Unsaved');
    assert.strictEqual(dirtySamePlan.display, 'none', 'Unsaved stays visually hidden when the plan matches');
    const areaDelta = Math.abs(parseFloat(areaAfterAck) - 142.07);
    assert.ok(areaDelta <= 0.01, 'Opened metrics must match live interior area within 0.01');

    async function postPlan(plan) {
      await postFromEditor({ type: 'OPENPLAN3D_PLAN_CHANGED', plan: plan });
      await new Promise((r) => setTimeout(r, 50));
    }

    async function unsavedVisible() {
      return page.$eval('#openplan3d-dirty-badge', (el) => !el.hidden && getComputedStyle(el).display !== 'none');
    }

    const ackPlan = await page.evaluate(() => JSON.parse(JSON.stringify(window.OpenPlan3DHost.getState().currentPlan)));
    const workPlan = JSON.parse(JSON.stringify(ackPlan));
    const floor = workPlan.floor || (workPlan.floors && workPlan.floors[0]) || workPlan;
    floor.doors = [{ id: 'd1', wallId: 'w1', position: 0.3, offset: 50, width: 91.44, height: 203.2 }];
    floor.windows = Array.isArray(floor.windows) ? floor.windows : [];
    floor.furniture = [{ id: 'f1', position: { x: 40, y: 40 }, rotation: 0, width: 60, depth: 40 }];
    floor.textAnnotations = [{ id: 't1', text: 'Kitchen', position: { x: 20, y: 20 } }];
    if (!Array.isArray(floor.rooms) || !floor.rooms[0] || !Array.isArray(floor.rooms[0].polygon) || floor.rooms[0].polygon.length < 3) {
      floor.rooms = [{
        id: 'r1',
        name: 'Living Room',
        walls: ['w1', 'w2', 'w3', 'w4'],
        polygon: [
          { x: 0, y: 0 },
          { x: 365.76, y: 0 },
          { x: 365.76, y: 381 },
          { x: 0, y: 381 },
        ],
      }];
    }

    await postPlan(workPlan);
    lastSavedBody = null;
    await postFromEditor({ type: 'OPENPLAN3D_TRIGGER_SAVE' });
    await new Promise((r) => setTimeout(r, 200));
    assert.ok(lastSavedBody, 'Saving the working plan must post');
    assert.strictEqual(await unsavedVisible(), false, 'Unsaved clears after save');
    const baselineAfterSave = await page.evaluate(() => window.OpenPlan3DHost.getState().loadedPlanBaseline);
    assert.ok(baselineAfterSave, 'successful save must set loadedPlanBaseline');

    function clone(plan) {
      return JSON.parse(JSON.stringify(plan));
    }

    const movedDoor = clone(workPlan);
    (movedDoor.floor || movedDoor.floors[0]).doors[0].position = 0.72;
    await postPlan(movedDoor);
    assert.strictEqual(await unsavedVisible(), true, 'moving a door must mark Unsaved');
    await postPlan(workPlan);
    assert.strictEqual(await unsavedVisible(), false, 'undo back to the saved plan must clear Unsaved');

    const thickWall = clone(workPlan);
    (thickWall.floor || thickWall.floors[0]).walls[0].thickness = 20;
    await postPlan(thickWall);
    assert.strictEqual(await unsavedVisible(), true, 'changing wall thickness must mark Unsaved');
    await postPlan(workPlan);
    assert.strictEqual(await unsavedVisible(), false, 'undo wall thickness must clear Unsaved');

    const movedCorner = clone(workPlan);
    (movedCorner.floor || movedCorner.floors[0]).rooms[0].polygon[1].x += 30;
    await postPlan(movedCorner);
    assert.strictEqual(await unsavedVisible(), true, 'moving a middle room corner must mark Unsaved');
    await postPlan(workPlan);
    assert.strictEqual(await unsavedVisible(), false, 'undo middle corner must clear Unsaved');

    const addedFurn = clone(workPlan);
    (addedFurn.floor || addedFurn.floors[0]).furniture.push({
      id: 'f2', position: { x: 120, y: 80 }, rotation: 15, width: 40, depth: 40,
    });
    await postPlan(addedFurn);
    assert.strictEqual(await unsavedVisible(), true, 'adding furniture must mark Unsaved');
    await postPlan(workPlan);
    assert.strictEqual(await unsavedVisible(), false, 'undo furniture must clear Unsaved');

    const addedLabel = clone(workPlan);
    (addedLabel.floor || addedLabel.floors[0]).textAnnotations.push({
      id: 't2', text: 'Bath', position: { x: 90, y: 90 },
    });
    await postPlan(addedLabel);
    assert.strictEqual(await unsavedVisible(), true, 'adding a label must mark Unsaved');
    await postPlan(workPlan);
    assert.strictEqual(await unsavedVisible(), false, 'undo label must clear Unsaved');

    await postPlan(movedDoor);
    lastSavedBody = null;
    await postFromEditor({ type: 'OPENPLAN3D_TRIGGER_SAVE' });
    await new Promise((r) => setTimeout(r, 200));
    assert.ok(lastSavedBody, 'Save after door move must post');
    assert.strictEqual(await unsavedVisible(), false, 'Unsaved clears after saving the door move');
    await postPlan(movedDoor);
    assert.strictEqual(await unsavedVisible(), false, 're-emitting the saved plan must stay clean');
    await postPlan(workPlan);
    assert.strictEqual(await unsavedVisible(), true, 'undo to the pre-save plan after save must mark Unsaved');
    await postPlan(movedDoor);
    assert.strictEqual(await unsavedVisible(), false, 'undo back to the last saved plan must clear Unsaved');

    saveDelayMs = 450;
    lastSavedBody = null;
    await postFromEditor({ type: 'OPENPLAN3D_TRIGGER_SAVE' });
    await new Promise((r) => setTimeout(r, 40));
    await postPlan(addedLabel);
    await new Promise((r) => setTimeout(r, 700));
    assert.ok(lastSavedBody, 'in-flight save must still post the request-time plan');
    assert.ok(
      host.plansMatch(lastSavedBody.plan, movedDoor),
      'request body must be the plan at save click, not the in-flight edit'
    );
    assert.strictEqual(await unsavedVisible(), true, 'edits during an in-flight save must stay Unsaved');
    const afterInFlight = await page.evaluate(() => {
      const s = window.OpenPlan3DHost.getState();
      return { baseline: s.loadedPlanBaseline, current: s.currentPlan, dirty: s.isDirty };
    });
    assert.ok(host.plansMatch(afterInFlight.baseline, movedDoor), 'baseline is the plan that was SENT');
    assert.ok(host.plansMatch(afterInFlight.current, addedLabel), 'current plan is the in-flight edit');
    assert.strictEqual(afterInFlight.dirty, true, 'isDirty stays true when current differs from the sent snapshot');

    const baselineBeforeFail = await page.evaluate(() => window.OpenPlan3DHost.getState().loadedPlanBaseline);
    failNextSave = true;
    lastSavedBody = null;
    await postFromEditor({ type: 'OPENPLAN3D_TRIGGER_SAVE' });
    await new Promise((r) => setTimeout(r, 250));
    assert.ok(lastSavedBody, 'failed save still posted');
    assert.strictEqual(await unsavedVisible(), true, 'failed save must leave Unsaved visible');
    const afterFail = await page.evaluate(() => window.OpenPlan3DHost.getState().loadedPlanBaseline);
    assert.ok(host.plansMatch(afterFail, baselineBeforeFail), 'failed save must leave loadedPlanBaseline unchanged');

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

    await postFromEditor({ type: 'OPENPLAN3D_PLAN_CHANGED', plan: updatedPlan });

    await new Promise((r) => setTimeout(r, 100));

    // Assert live metrics updated
    const newArea = await page.$eval('#metric-area', (el) => el.textContent.trim());
    assert.strictEqual(newArea, '171.25', 'Area should update to interior floor area sq ft');

    const newOpenings = await page.$eval('#metric-openings', (el) => el.textContent.trim());
    assert.strictEqual(newOpenings, '1 doors · 1 windows', 'Openings count should update to 1 doors · 1 windows');

    const newCasing = await page.$eval('#metric-casing', (el) => parseFloat(el.textContent.trim()));
    assert.ok(newCasing > 10, 'Casing should be computed from doors and windows');

    const isDirtyVisible = await page.$eval('#openplan3d-dirty-badge', (el) => !el.hidden);
    assert.strictEqual(isDirtyVisible, true, 'Dirty badge must appear on drawing changes');

    // 3. Test Ctrl+S save shortcut forwarded from editor iframe
    lastSavedBody = null;
    await postFromEditor({ type: 'OPENPLAN3D_TRIGGER_SAVE' });

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
          },
          {
            id: 'r2',
            name: 'Bath',
            walls: ['w5', 'w6', 'w7', 'w2'],
          },
        ],
      },
    };
    await postFromEditor({ type: 'OPENPLAN3D_PLAN_CHANGED', plan: twoRoomPlan });
    await new Promise((r) => setTimeout(r, 80));
    const twoRoomStrip = await page.$eval('#metric-area', (el) => el.textContent.trim());
    assert.strictEqual(twoRoomStrip, '187.10', 'Metrics strip must use whole-plan interior area (two 10×10 ft cells, 10 cm walls)');
    lastSavedBody = null;
    await postFromEditor({ type: 'OPENPLAN3D_TRIGGER_SAVE' });
    await new Promise((r) => setTimeout(r, 200));
    assert.ok(lastSavedBody, 'Two-room save should post');
    assert.strictEqual(twoRoomStrip, '187.10');
    assert.ok(lastSavedBody.plan.floor.rooms.length === 2, 'Saved plan must keep both openPlan3D rooms');

    // 6. Save Plan never sends 0 metrics for a >0 room without in-app confirm
    const zeroPlan = { format: 'openplan3d', unit: 'cm', floor: { walls: [], rooms: [] } };
    await postFromEditor({ type: 'OPENPLAN3D_PLAN_CHANGED', plan: zeroPlan });
    await new Promise((r) => setTimeout(r, 50));
    lastSavedBody = null;
    await page.evaluate(() => window.OpenPlan3DHost.savePlan());
    await page.waitForSelector('#openplan3d-modal:not([hidden])');
    const zeroTitle = await page.$eval('#openplan3d-modal-title', (el) => el.textContent.trim());
    assert.match(zeroTitle, /Replace stored area/i, 'zero-area save uses the in-app modal');
    await page.click('#openplan3d-modal-cancel');
    await page.waitForSelector('#openplan3d-modal[hidden]');
    await new Promise((r) => setTimeout(r, 150));
    assert.strictEqual(lastSavedBody, null, 'Save Plan must not send a 0-area overwrite when confirm is declined');

    lastSavedBody = null;
    await page.evaluate(() => window.OpenPlan3DHost.savePlan());
    await page.waitForSelector('#openplan3d-modal:not([hidden])');
    await page.click('#openplan3d-modal-confirm');
    await new Promise((r) => setTimeout(r, 200));
    assert.ok(lastSavedBody, 'Save Plan may send 0-area after explicit confirm');
    assert.strictEqual(lastSavedBody.confirm_zero_area, true, 'Confirmed 0-area save must send confirm_zero_area');

    const areaBeforeSpoof = await page.$eval('#metric-area', (el) => el.textContent.trim());
    await page.evaluate(() => {
      window.postMessage({
        type: 'OPENPLAN3D_PLAN_CHANGED',
        plan: { unit: 'cm', floor: { walls: [], rooms: [{ id: 'spoof', polygon: [{ x: 0, y: 0 }, { x: 1000, y: 0 }, { x: 1000, y: 1000 }, { x: 0, y: 1000 }] }] } },
      }, '*');
    });
    await new Promise((r) => setTimeout(r, 80));
    const areaAfterSpoof = await page.$eval('#metric-area', (el) => el.textContent.trim());
    assert.strictEqual(areaAfterSpoof, areaBeforeSpoof, 'messages not from the editor iframe must be ignored');

    await postPlan(workPlan);
    lastSavedBody = null;
    await postFromEditor({ type: 'OPENPLAN3D_TRIGGER_SAVE' });
    await new Promise((r) => setTimeout(r, 200));
    assert.ok(lastSavedBody, 'reset baseline to workPlan before L2 fingerprint checks');
    assert.strictEqual(await unsavedVisible(), false, 'saved workPlan must clear Unsaved');

    const swingPlan = clone(workPlan);
    (swingPlan.floor || swingPlan.floors[0]).doors[0].swing = 'left';
    await postPlan(swingPlan);
    assert.strictEqual(await unsavedVisible(), true, 'changing door swing must mark Unsaved');

    const stairPlan = clone(workPlan);
    (stairPlan.floor || stairPlan.floors[0]).stairs = [{ id: 's1', position: { x: 10, y: 10 }, width: 90, run: 250, treads: 12 }];
    await postPlan(workPlan);
    await postPlan(stairPlan);
    assert.strictEqual(await unsavedVisible(), true, 'adding stairs must mark Unsaved');

    const typedFurn = clone(workPlan);
    (typedFurn.floor || typedFurn.floors[0]).furniture[0].type = 'sofa';
    (typedFurn.floor || typedFurn.floors[0]).furniture[0].material = 'oak';
    await postPlan(workPlan);
    await postPlan(typedFurn);
    assert.strictEqual(await unsavedVisible(), true, 'changing furniture type or material must mark Unsaved');

    const noScalePlan = {
      floor: {
        walls: [
          { id: 'w1', start: { x: 0, y: 0 }, end: { x: 100, y: 0 } },
          { id: 'w2', start: { x: 100, y: 0 }, end: { x: 100, y: 100 } },
          { id: 'w3', start: { x: 100, y: 100 }, end: { x: 0, y: 100 } },
          { id: 'w4', start: { x: 0, y: 100 }, end: { x: 0, y: 0 } },
        ],
        rooms: [{ id: 'r1', polygon: [{ x: 0, y: 0 }, { x: 100, y: 0 }, { x: 100, y: 100 }, { x: 0, y: 100 }] }],
      },
    };
    await postPlan(noScalePlan);
    const noScale = await page.$eval('#openplan3d-noscale-warning', (el) => !el.hidden && getComputedStyle(el).display !== 'none');
    assert.strictEqual(noScale, true, 'a room without scale or units must show the no-scale warning');

    await page.evaluate(() => {
      const host = window.OpenPlan3DHost;
      const metrics = host.computeMetrics(host.getState().currentPlan);
      host.getState().currentRoom.area_sqft = metrics.area_sqft;
    });

    saveDelayMs = 400;
    saveCount = 0;
    lastSavedBody = null;
    await postFromEditor({ type: 'OPENPLAN3D_TRIGGER_SAVE' });
    await postFromEditor({ type: 'OPENPLAN3D_TRIGGER_SAVE' });
    await page.click('#openplan3d-save-btn');
    await new Promise((r) => setTimeout(r, 700));
    assert.strictEqual(saveCount, 1, 'in-flight save lock must drop duplicate Save clicks');
    const busyDuring = await page.evaluate(() => window.OpenPlan3DHost.getState().isSaving);
    assert.strictEqual(busyDuring, false, 'isSaving clears after the single in-flight request');

    // On error/timeout: always clear isSaving, re-enable Save, keep Unsaved state, and show visible error
    const modifiedPlan = clone(noScalePlan);
    modifiedPlan.floor.rooms[0].polygon[0].x = 10;
    await postPlan(modifiedPlan);
    assert.strictEqual(await unsavedVisible(), true, 'modified plan is marked unsaved before save');

    await page.evaluate(() => {
      const host = window.OpenPlan3DHost;
      const metrics = host.computeMetrics(host.getState().currentPlan);
      host.getState().currentRoom.area_sqft = metrics.area_sqft;
    });

    failNextSave = true;
    await page.click('#openplan3d-save-btn');
    await page.waitForFunction(
      () => {
        const state = window.OpenPlan3DHost && window.OpenPlan3DHost.getState();
        const fb = document.getElementById('openplan3d-feedback');
        return state && !state.isSaving && fb && !fb.hidden && fb.textContent.length > 0;
      },
      { timeout: 5000 }
    );
    const isSavingAfterFail = await page.evaluate(() => window.OpenPlan3DHost.getState().isSaving);
    assert.strictEqual(isSavingAfterFail, false, 'isSaving clears after failed save');
    const saveDisabledAfterFail = await page.$eval('#openplan3d-save-btn', (el) => el.disabled);
    assert.strictEqual(saveDisabledAfterFail, false, 'save button re-enabled after failure');
    const unsavedAfterFail = await unsavedVisible();
    assert.strictEqual(unsavedAfterFail, true, 'unsaved state kept after failure');
    const feedbackVisible = await page.$eval('#openplan3d-feedback', (el) => !el.hidden && el.textContent.length > 0);
    assert.strictEqual(feedbackVisible, true, 'visible error message shown after failure');

    async function bootBlankEditor() {
      lastSavedBody = null;
      lastProjectBody = null;
      await page.setViewport({ width: 1280, height: 900 });
      await page.goto(`http://127.0.0.1:${port}/openplan3d-room-area.html?blank=1`, { waitUntil: 'domcontentloaded' });
      await page.evaluate(() => {
        window.__nativeDialogs = [];
        window.confirm = function () { window.__nativeDialogs.push('confirm'); return false; };
        window.alert = function () { window.__nativeDialogs.push('alert'); };
        window.prompt = function () { window.__nativeDialogs.push('prompt'); return null; };
      });
      await postFromEditor({ type: 'OPENPLAN3D_READY' });
      const empty = { unit: 'cm', floor: { walls: [], rooms: [] } };
      await postFromEditor({ type: 'OPENPLAN3D_LOAD_ACK', ok: true, plan: empty });
      await new Promise((r) => setTimeout(r, 50));
    }

    // 7. Blank default + Save destination (Link / Keep standalone) + Lock Save (Items 7 & 8)
    await bootBlankEditor();
    const blankState = await page.evaluate(() => window.OpenPlan3DHost.getState());
    assert.strictEqual(blankState.currentProject, null, 'Room Area opens with no project selected');
    const projectVal = await page.$eval('#openplan3d-project-select', (el) => el.value);
    assert.strictEqual(projectVal, '', 'Project select starts blank');
    const blankRoomName = await page.$eval('#openplan3d-room-name', (el) => el.value);
    assert.strictEqual(blankRoomName, 'Room 1', 'Blank session still names the new room');

    lastSavedBody = null;
    lastProjectBody = null;
    await page.evaluate(() => window.OpenPlan3DHost.savePlan());
    await page.waitForSelector('#openplan3d-modal:not([hidden])');
    const destTitle = await page.$eval('#openplan3d-modal-title', (el) => el.textContent.trim());
    assert.strictEqual(destTitle, 'Save plan');

    // Item 7: Save lock during link dialog
    const lockedDuringDialog = await page.evaluate(() => window.OpenPlan3DHost.getState().isSaving);
    assert.strictEqual(lockedDuringDialog, true, 'isSaving must be locked during destination choice dialog');
    const saveDisabledDuringDialog = await page.$eval('#openplan3d-save-btn', (el) => el.disabled);
    assert.strictEqual(saveDisabledDuringDialog, true, 'Save button must be disabled during destination choice dialog');

    const linkLabel = await page.$eval('#openplan3d-modal-link-btn', (el) => el.textContent.trim());
    const standLabel = await page.$eval('#openplan3d-modal-standalone-btn', (el) => el.textContent.trim());
    assert.strictEqual(linkLabel, 'Link to a project');
    assert.strictEqual(standLabel, 'Keep standalone');
    const destChrome = await page.evaluate(() => ({
      nameHidden: document.getElementById('openplan3d-modal-input-wrap').hidden,
      actionsHidden: document.getElementById('openplan3d-modal-actions').hidden,
      linkHidden: document.getElementById('openplan3d-modal-link').hidden,
    }));
    assert.strictEqual(destChrome.nameHidden, true, 'destination modal must not show the name prompt');
    assert.strictEqual(destChrome.actionsHidden, true, 'destination modal must not show the generic OK row');
    assert.strictEqual(destChrome.linkHidden, false, 'destination choices must be visible');

    await page.click('#openplan3d-modal-link-cancel');
    await page.waitForSelector('#openplan3d-modal[hidden]');
    assert.strictEqual(lastSavedBody, null, 'Cancel destination must not save');
    assert.strictEqual(lastProjectBody, null, 'Cancel destination must not create a project');
    const unlockedAfterCancel = await page.evaluate(() => window.OpenPlan3DHost.getState().isSaving);
    assert.strictEqual(unlockedAfterCancel, false, 'isSaving unlocked after cancelling destination dialog');

    await page.evaluate(() => window.OpenPlan3DHost.savePlan());
    await page.waitForSelector('#openplan3d-modal:not([hidden])');
    await page.click('#openplan3d-modal-standalone-btn');
    for (let i = 0; i < 80 && !lastSavedBody; i++) {
      await new Promise((r) => setTimeout(r, 50));
    }
    assert.ok(lastProjectBody, 'Keep standalone must create a project');
    assert.ok(!lastProjectBody.client_project_id, 'standalone project is unlinked');
    assert.ok(lastSavedBody, 'Keep standalone must then save the room');

    await bootBlankEditor();
    await page.evaluate(() => window.OpenPlan3DHost.savePlan());
    await page.waitForSelector('#openplan3d-modal:not([hidden])');
    await page.select('#openplan3d-modal-client-project', '2');
    await page.click('#openplan3d-modal-link-btn');
    for (let i = 0; i < 80 && !lastSavedBody; i++) {
      await new Promise((r) => setTimeout(r, 50));
    }
    assert.strictEqual(Number(lastProjectBody && lastProjectBody.client_project_id), 2, 'Link to a project posts the chosen client project');
    assert.ok(lastSavedBody, 'Link to a project must save after linking');

    // 8. Item 6: ask zero-area replace question BEFORE linking or creating a project
    await bootBlankEditor();
    await page.evaluate(() => {
      // Simulate an unlinked session that previously had an area > 0
      window.OpenPlan3DHost.getState().currentRoom.area_sqft = 120.0;
    });
    lastSavedBody = null;
    lastProjectBody = null;
    await page.evaluate(() => window.OpenPlan3DHost.savePlan());
    await page.waitForSelector('#openplan3d-modal:not([hidden])');
    const zeroPromptTitle = await page.$eval('#openplan3d-modal-title', (el) => el.textContent.trim());
    assert.match(zeroPromptTitle, /Replace stored area/i, 'Zero-area replace question asked BEFORE linking or creating a project');
    assert.strictEqual(lastProjectBody, null, 'no project created while zero-area question is pending');

    // Cancelling must leave no project linked and no project created
    await page.click('#openplan3d-modal-cancel');
    await page.waitForSelector('#openplan3d-modal[hidden]');
    assert.strictEqual(lastProjectBody, null, 'Cancelling leaves no project created');
    assert.strictEqual(lastSavedBody, null, 'Cancelling leaves no room saved');
    assert.strictEqual(await page.evaluate(() => window.OpenPlan3DHost.getState().isSaving), false, 'isSaving unlocked after zero cancel');

    // Confirming zero-area prompt proceeds to destination choice
    await page.evaluate(() => window.OpenPlan3DHost.savePlan());
    await page.waitForSelector('#openplan3d-modal:not([hidden])');
    await page.click('#openplan3d-modal-confirm');
    await page.waitForFunction(() => {
      const t = document.getElementById('openplan3d-modal-title');
      return t && t.textContent.trim() === 'Save plan';
    });
    assert.strictEqual(lastProjectBody, null, 'Destination modal shown, project not yet created');
    lastSavedBody = null;
    await page.click('#openplan3d-modal-standalone-btn');
    for (let i = 0; i < 80 && !lastSavedBody; i++) {
      await new Promise((r) => setTimeout(r, 50));
    }
    assert.ok(lastProjectBody, 'Project created after destination confirmed');
    assert.strictEqual(lastSavedBody && lastSavedBody.confirm_zero_area, true, 'confirm_zero_area is preserved');

    // 9. Item 8: Modal focus trap, close on Esc, and return focus to Save button
    await bootBlankEditor();
    await page.evaluate(() => window.OpenPlan3DHost.savePlan());
    await page.waitForSelector('#openplan3d-modal:not([hidden])');

    // Esc closes modal and returns focus to saveBtn
    await page.keyboard.press('Escape');
    await page.waitForSelector('#openplan3d-modal[hidden]');
    const activeAfterEsc = await page.evaluate(() => document.activeElement && document.activeElement.id);
    assert.strictEqual(activeAfterEsc, 'openplan3d-save-btn', 'Esc closes modal and returns focus to Save button');

    // Reopen to test focus trap
    await page.evaluate(() => window.OpenPlan3DHost.savePlan());
    await page.waitForSelector('#openplan3d-modal:not([hidden])');
    await new Promise((r) => setTimeout(r, 50));

    function modalFocusableInPage() {
      return page.evaluate(() => {
        const modal = document.getElementById('openplan3d-modal');
        const nodes = modal.querySelectorAll(
          'button:not([disabled]):not([hidden]), [href], input:not([disabled]):not([hidden]), select:not([disabled]):not([hidden]), textarea:not([disabled]):not([hidden]), [tabindex]:not([tabindex="-1"])'
        );
        const visible = [];
        nodes.forEach((el) => {
          if (el.hidden) return;
          if (el.closest && el.closest('[hidden]')) return;
          visible.push(el.id || el.tagName);
        });
        return visible;
      });
    }

    const focusableIds = await modalFocusableInPage();
    assert.ok(focusableIds.length >= 2, 'modal exposes multiple tab stops');

    const trapEnds = await page.evaluate(() => {
      const modal = document.getElementById('openplan3d-modal');
      const nodes = modal.querySelectorAll(
        'button:not([disabled]):not([hidden]), [href], input:not([disabled]):not([hidden]), select:not([disabled]):not([hidden]), textarea:not([disabled]):not([hidden]), [tabindex]:not([tabindex="-1"])'
      );
      const visible = Array.from(nodes).filter((el) => !el.hidden && !el.closest('[hidden]'));
      const first = visible[0];
      const last = visible[visible.length - 1];
      return {
        firstId: first && first.id,
        lastId: last && last.id,
      };
    });
    assert.ok(trapEnds.lastId, 'modal has a last tab stop');
    await page.focus('#' + trapEnds.lastId);
    await page.waitForFunction(
      (lastId) => (document.activeElement && document.activeElement.id) === lastId,
      { timeout: 8000 },
      trapEnds.lastId
    );
    await page.keyboard.press('Tab');
    const wrappedToFirst = await page.waitForFunction(
      (firstId) => (document.activeElement && document.activeElement.id) === firstId,
      { timeout: 15000 },
      trapEnds.firstId
    ).then(() => true).catch(() => false);
    assert.strictEqual(wrappedToFirst, true, 'Tab from last element wraps to first element');

    // Shift+Tab from first element wraps to last element
    await page.keyboard.down('Shift');
    await page.keyboard.press('Tab');
    await page.keyboard.up('Shift');
    const wrappedToLast = await page.waitForFunction(
      (lastId) => (document.activeElement && document.activeElement.id) === lastId,
      { timeout: 8000 },
      trapEnds.lastId
    ).then(() => true).catch(() => false);
    assert.strictEqual(wrappedToLast, true, 'Shift+Tab from first element wraps to last element');

    // Close via link cancel and verify focus returns to Save button
    await page.click('#openplan3d-modal-link-cancel');
    await page.waitForSelector('#openplan3d-modal[hidden]');
    const activeAfterCancel = await page.evaluate(() => document.activeElement && document.activeElement.id);
    assert.strictEqual(activeAfterCancel, 'openplan3d-save-btn', 'Closing modal returns focus to Save button');

    // 10. In-flight-save + cancel-discard case and return focus to modal opener
    await page.goto(`http://127.0.0.1:${port}/openplan3d-room-area.html`, { waitUntil: 'domcontentloaded' });
    await page.evaluate(() => {
      window.__nativeDialogs = [];
      window.confirm = function () { window.__nativeDialogs.push('confirm'); return false; };
      window.alert = function () { window.__nativeDialogs.push('alert'); };
      window.prompt = function () { window.__nativeDialogs.push('prompt'); return null; };
    });
    await postFromEditor({ type: 'OPENPLAN3D_READY' });
    await postFromEditor({ type: 'OPENPLAN3D_LOAD_ACK', ok: true, plan: noScalePlan });

    // (a) Return focus to the control that opened the modal (non-save control)
    await page.focus('#openplan3d-new-room-btn');
    const focusedBeforeOpen = await page.evaluate(() => document.activeElement && document.activeElement.id);
    assert.strictEqual(focusedBeforeOpen, 'openplan3d-new-room-btn', 'new room button is focused before modal open');

    await page.evaluate(() => {
      window.OpenPlan3DHost.askConfirm('Discard changes?', 'Unsaved changes');
    });
    await page.waitForSelector('#openplan3d-modal:not([hidden])');
    await page.click('#openplan3d-modal-cancel');
    await page.waitForSelector('#openplan3d-modal[hidden]');
    const activeAfterNonSaveCancel = await page.evaluate(() => document.activeElement && document.activeElement.id);
    assert.strictEqual(activeAfterNonSaveCancel, 'openplan3d-new-room-btn', 'Closing modal returns focus to the opener control');

    // (b) In-flight-save + cancel-discard case:
    // When a save request is in-flight, opening and dismissing a non-save modal must NOT clear isSaving or unlock Save.
    await page.evaluate(() => {
      const host = window.OpenPlan3DHost;
      host.getState().currentProject = { id: 1, name: 'Test Suite Project' };
      host.getState().currentRoom = { id: 10, name: 'Living Room', area_sqft: 150.0 };
      const metrics = host.computeMetrics(host.getState().currentPlan);
      if (metrics) host.getState().currentRoom.area_sqft = metrics.area_sqft;
    });
    saveDelayMs = 1200;
    lastSavedBody = null;
    await page.click('#openplan3d-save-btn');

    const isSavingDuringFlight = await page.evaluate(() => window.OpenPlan3DHost.getState().isSaving);
    assert.strictEqual(isSavingDuringFlight, true, 'isSaving must be true while save is in-flight');
    const saveBusy = await page.$eval('#openplan3d-save-btn', (el) => el.getAttribute('aria-busy'));
    const saveDisabled = await page.$eval('#openplan3d-save-btn', (el) => el.disabled);
    assert.strictEqual(saveBusy, 'true', 'Save button has aria-busy="true" during save flight');
    assert.strictEqual(saveDisabled, true, 'Save button is disabled during save flight');

    // Now, while save is still in flight, a discard dialog is triggered
    await page.focus('#openplan3d-new-room-btn');
    await page.evaluate(() => {
      window.OpenPlan3DHost.askConfirm('Discard changes?', 'Unsaved changes');
    });
    await page.waitForSelector('#openplan3d-modal:not([hidden])');

    // User cancels the discard modal
    await page.click('#openplan3d-modal-cancel');
    await page.waitForSelector('#openplan3d-modal[hidden]');

    // CRITICAL: isSaving must NOT be released by the non-save modal cancellation!
    const isSavingAfterDiscardCancel = await page.evaluate(() => window.OpenPlan3DHost.getState().isSaving);
    assert.strictEqual(isSavingAfterDiscardCancel, true, 'isSaving must REMAIN true after cancelling discard modal');
    const saveBusyStill = await page.$eval('#openplan3d-save-btn', (el) => el.getAttribute('aria-busy'));
    const saveDisabledStill = await page.$eval('#openplan3d-save-btn', (el) => el.disabled);
    assert.strictEqual(saveBusyStill, 'true', 'Save button must remain aria-busy while save is in-flight');
    assert.strictEqual(saveDisabledStill, true, 'Save button must remain disabled while save is in-flight');

    // Focus returned to the opener element
    const activeAfterDiscardCancel = await page.evaluate(() => document.activeElement && document.activeElement.id);
    assert.strictEqual(activeAfterDiscardCancel, 'openplan3d-new-room-btn', 'Focus returns to opener after discard cancel');

    // Wait for in-flight save to complete
    await page.waitForFunction(() => !window.OpenPlan3DHost.getState().isSaving, { timeout: 3000 });
    const isSavingAfterComplete = await page.evaluate(() => window.OpenPlan3DHost.getState().isSaving);
    assert.strictEqual(isSavingAfterComplete, false, 'isSaving is unlocked once the save completes');
    // (c) Failed jsonPost during in-flight save keeps Save locked:
    await page.evaluate(() => {
      const host = window.OpenPlan3DHost;
      const metrics = host.computeMetrics(host.getState().currentPlan);
      if (metrics) host.getState().currentRoom.area_sqft = metrics.area_sqft;
    });
    saveDelayMs = 1500;
    lastSavedBody = null;
    await page.click('#openplan3d-save-btn');
    assert.strictEqual(await page.evaluate(() => window.OpenPlan3DHost.getState().isSaving), true, 'save is in-flight');

    const jsonPostFailed = await page.evaluate(async () => {
      try {
        await window.OpenPlan3DHost.jsonPost('/nonexistent-failure-endpoint', 'POST', {});
        return false;
      } catch (e) {
        return true;
      }
    });
    assert.strictEqual(jsonPostFailed, true, 'jsonPost rejected on failed endpoint');

    const isSavingAfterFailedJsonPost = await page.evaluate(() => window.OpenPlan3DHost.getState().isSaving);
    assert.strictEqual(isSavingAfterFailedJsonPost, true, 'failed jsonPost during in-flight save must keep Save locked');
    const saveBusyAfterFailedJsonPost = await page.$eval('#openplan3d-save-btn', (el) => el.getAttribute('aria-busy'));
    const saveDisabledAfterFailedJsonPost = await page.$eval('#openplan3d-save-btn', (el) => el.disabled);
    assert.strictEqual(saveBusyAfterFailedJsonPost, 'true', 'Save button must remain aria-busy');
    assert.strictEqual(saveDisabledAfterFailedJsonPost, true, 'Save button must remain disabled');

    await page.waitForFunction(() => !window.OpenPlan3DHost.getState().isSaving, { timeout: 4000 });
    assert.strictEqual(await page.evaluate(() => window.OpenPlan3DHost.getState().isSaving), false, 'isSaving unlocked after save completed');

    // 14. Replace-area dialog does NOT appear when real area is unchanged
    await postPlan(workPlan);
    await page.evaluate(() => {
      const host = window.OpenPlan3DHost;
      const metrics = host.computeMetrics(host.getState().currentPlan);
      host.getState().currentRoom.area_sqft = metrics.area_sqft;
    });
    lastSavedBody = null;
    await page.click('#openplan3d-save-btn');
    await page.waitForFunction(() => !window.OpenPlan3DHost.getState().isSaving, { timeout: 4000 });
    const modalVisible = await page.$eval('#openplan3d-modal', (el) => !el.hidden && el.style.display !== 'none');
    assert.strictEqual(modalVisible, false, 'Replace-area dialog does NOT appear when real area is unchanged');
    assert.ok(lastSavedBody, 'Save proceeds directly when real area is unchanged');

    // 15. D6: Replace stored area modal triggers when incoming area drops < 50% of stored area
    await postPlan(workPlan);
    await page.evaluate(() => {
      const host = window.OpenPlan3DHost;
      host.getState().currentRoom.area_sqft = 100.0;
    });
    // Incoming area 40 sq ft (< 50% of 100) -> must show modal
    const dropPlan40 = {
      format: 'openplan3d',
      unit: 'cm',
      floor: {
        walls: [
          { id: 'w1', start: { x: 0, y: 0 }, end: { x: 192.8, y: 0 } },
          { id: 'w2', start: { x: 192.8, y: 0 }, end: { x: 192.8, y: 192.8 } },
          { id: 'w3', start: { x: 192.8, y: 192.8 }, end: { x: 0, y: 192.8 } },
          { id: 'w4', start: { x: 0, y: 192.8 }, end: { x: 0, y: 0 } },
        ],
        rooms: [{ id: 'r1', polygon: [{ x: 0, y: 0 }, { x: 192.8, y: 0 }, { x: 192.8, y: 192.8 }, { x: 0, y: 192.8 }] }],
      },
    };
    await postPlan(dropPlan40);
    lastSavedBody = null;
    await page.click('#openplan3d-save-btn');
    await page.waitForSelector('#openplan3d-modal:not([hidden])');
    const modalTitle = await page.$eval('#openplan3d-modal-title', (el) => el.textContent.trim());
    assert.match(modalTitle, /Replace stored area/i, '40% incoming area (< 50% threshold) must trigger replace dialog');
    await page.click('#openplan3d-modal-cancel');
    await page.waitForSelector('#openplan3d-modal[hidden]');

    // Incoming area ~68 sq ft (>= 50% of 100) -> must NOT show modal
    const dropPlan60 = {
      format: 'openplan3d',
      unit: 'cm',
      floor: {
        walls: [
          { id: 'w1', start: { x: 0, y: 0 }, end: { x: 274.32, y: 0 } },
          { id: 'w2', start: { x: 274.32, y: 0 }, end: { x: 274.32, y: 274.32 } },
          { id: 'w3', start: { x: 274.32, y: 274.32 }, end: { x: 0, y: 274.32 } },
          { id: 'w4', start: { x: 0, y: 274.32 }, end: { x: 0, y: 0 } },
        ],
        rooms: [{ id: 'r1', polygon: [{ x: 0, y: 0 }, { x: 274.32, y: 0 }, { x: 274.32, y: 274.32 }, { x: 0, y: 274.32 }] }],
      },
    };
    await postPlan(dropPlan60);
    const pre60State = await page.evaluate(() => {
      const btn = document.getElementById('openplan3d-save-btn');
      const s = window.OpenPlan3DHost.getState();
      const m = window.OpenPlan3DHost.computeMetrics(s.currentPlan);
      return {
        btnDisabled: btn ? btn.disabled : null,
        isSaving: s.isSaving,
        hasRoom: !!s.currentRoom,
        roomArea: s.currentRoom ? s.currentRoom.area_sqft : null,
        hasProject: !!s.currentProject,
        loadAcked: s.loadAcked,
        metricsArea: m ? m.area_sqft : null,
        feedback: document.getElementById('openplan3d-feedback') ? document.getElementById('openplan3d-feedback').textContent : null,
      };
    });
    lastSavedBody = null;
    await page.evaluate(() => window.OpenPlan3DHost.savePlan());
    await new Promise((r) => setTimeout(r, 200));
    const afterState = await page.evaluate(() => ({
      isSaving: window.OpenPlan3DHost.getState().isSaving,
      feedback: document.getElementById('openplan3d-feedback') ? document.getElementById('openplan3d-feedback').textContent : null,
      roomName: document.getElementById('openplan3d-room-name') ? document.getElementById('openplan3d-room-name').value : null,
      modalHidden: document.getElementById('openplan3d-modal') ? document.getElementById('openplan3d-modal').hidden : null,
      modalTitle: document.getElementById('openplan3d-modal-title') ? document.getElementById('openplan3d-modal-title').textContent : null,
    }));
    await page.waitForFunction(() => !window.OpenPlan3DHost.getState().isSaving, { timeout: 4000 });
    const modalHidden60 = await page.$eval('#openplan3d-modal', (el) => el.hidden || el.style.display === 'none');
    assert.strictEqual(modalHidden60, true, '60% incoming area (>= 50% threshold) must save directly without modal');
    assert.ok(lastSavedBody, 'Save completes directly without modal when incoming >= 50% of stored');

    // 16. D1 & D2: Single-tab load and save shows NO conflict banner, and canvas remains non-zero size & interactive
    await postPlan(workPlan);
    lastSavedBody = null;
    await page.click('#openplan3d-save-btn');
    await page.waitForFunction(() => !window.OpenPlan3DHost.getState().isSaving, { timeout: 4000 });
    assert.ok(lastSavedBody, 'Save succeeds');

    const iframeHandle = await page.$('#openplan3d-iframe');
    const iframe = iframeHandle && await iframeHandle.contentFrame();
    assert.ok(iframe, 'Editor iframe accessible');

    // Assert canvas has non-zero size (D1 regression check: clearSaveConflict must never hide canvas or root containers)
    const canvasDimensions = await iframe.evaluate(() => {
      const c = document.querySelector('canvas');
      if (!c) return null;
      const rect = c.getBoundingClientRect();
      const style = window.getComputedStyle(c);
      return { width: rect.width, height: rect.height, display: style.display, visibility: style.visibility };
    });
    if (canvasDimensions) {
      assert.ok(canvasDimensions.width > 0, 'Canvas client width must be > 0 after save');
      assert.ok(canvasDimensions.height > 0, 'Canvas client height must be > 0 after save');
      assert.notStrictEqual(canvasDimensions.display, 'none', 'Canvas must not be display: none');
    }

    // Assert no conflict banner in single tab (D2 regression check: hosted mode avoids false IndexedDB conflict)
    const conflictVisible = await iframe.evaluate(() => {
      const alerts = Array.from(document.querySelectorAll('div[role="alert"], div.bg-red-50, [data-save-conflict-banner]'));
      return alerts.some((el) => {
        const text = el.textContent || '';
        const visible = window.getComputedStyle(el).display !== 'none';
        return visible && text.includes('Changes are not saved') && text.includes('another tab');
      });
    });
    assert.strictEqual(conflictVisible, false, 'Single tab load and save must not show false conflict banner');

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
