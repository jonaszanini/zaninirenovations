'use strict';

const assert = require('assert');
const fs = require('fs');
const http = require('http');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const CSS = fs.readFileSync(path.join(ROOT, 'public/css/admin-utilities.css'), 'utf8');
const KONVA_FILE = path.join(ROOT, 'node_modules/konva/konva.min.js');
const TEST_TIMEOUT_MS = 120000;

function assertCssGeometry() {
  assert.ok(
    CSS.includes('.util-ra--fullpage.is-more-open .util-ra-more-sheet .admin-btn.util-ra-icon-btn:not([data-ra-more-close])'),
    '100% width rule must exclude Close'
  );
  assert.ok(
    CSS.includes('.util-ra--fullpage.is-more-open .util-ra-more-sheet .util-ra-more-sheet__head .admin-btn.util-ra-icon-btn'),
    'Close override must beat the 5-class width:100% rule'
  );
  const headRule = CSS.slice(CSS.indexOf('.util-ra--fullpage.is-more-open .util-ra-more-sheet .util-ra-more-sheet__head .admin-btn.util-ra-icon-btn'));
  assert.ok(/width:\s*auto/.test(headRule.slice(0, 240)), 'Close override sets width:auto');
  assert.ok(
    CSS.includes('.util-ra--fullpage:not(.is-sheet-expanded):not(.is-dock-open) .util-ra-side'),
    'collapsed sheet sizes to content'
  );
  const collapsed = CSS.slice(CSS.indexOf('.util-ra--fullpage:not(.is-sheet-expanded):not(.is-dock-open) .util-ra-side'));
  assert.ok(/height:\s*auto/.test(collapsed.slice(0, 220)), 'collapsed sheet height is auto');
  const phoneFeedback = CSS.slice(CSS.indexOf('.util-ra--fullpage .util-ra-canvas-wrap {\n    padding-top: 2.4em;'));
  const phoneFeedbackHead = phoneFeedback.slice(0, 720);
  assert.ok(/position:\s*absolute/.test(phoneFeedbackHead), '390 feedback overlays the canvas');
  assert.ok(/top:\s*0\.2rem/.test(phoneFeedbackHead), '390 feedback sits on the canvas top edge');
  assert.ok(/-webkit-line-clamp:\s*2/.test(phoneFeedbackHead), '390 feedback wraps to two lines max');
  assert.ok(!/color:\s*var\(--admin-text\)/.test(phoneFeedbackHead), '390 feedback must not override status colours');
  assert.ok(
    CSS.includes('.util-ra--fullpage .util-ra-feedback.is-error { color: var(--admin-danger); }'),
    'phone CSS keeps error status red'
  );
  assert.ok(
    CSS.includes('.util-ra--fullpage .util-ra-feedback.is-ok { color: var(--admin-success, #2e7d32); }'),
    'phone CSS keeps success status green'
  );
  assert.ok(
    CSS.includes('padding-top: 2.4em'),
    'phone canvas leaves a gutter so status does not cover the top wall'
  );
  assert.ok(
    /@media \(max-width: 320px\)[\s\S]{0,280}?bottom:\s*0\.2rem/.test(CSS),
    '320 status sits on the canvas bottom edge'
  );
  assert.ok(
    !/@media \(min-width: 901px\) and \(max-width: 1024px\)[\s\S]{0,900}?max-height:\s*calc\(\(var\(--admin-control-height\) \* 2\)/.test(CSS),
    '1024 toolbar must not cap at two clipped rows'
  );
  assert.ok(
    /@media \(min-width: 901px\) and \(max-width: 1024px\)[\s\S]{0,400}?overflow-x:\s*visible/.test(CSS),
    '1024 toolbar overflow stays visible so controls are not clipped'
  );
  assert.ok(
    !/@media \(min-width: 901px\) and \(max-width: 1024px\)[\s\S]{0,1200}?\.util-ra-help \{\s*display:\s*none/.test(CSS),
    '1024 toolbar keeps the ⓘ help buttons'
  );
  assert.ok(
    CSS.includes('max-height: min(40vh, 280px, calc(100dvh - 160px - 12.5rem))'),
    'expanded phone sheet is capped so at least 160px of canvas stays visible'
  );
  assert.ok(
    /@media \(min-width: 901px\) and \(max-width: 1024px\)[\s\S]{0,2400}?min-height:\s*300px/.test(CSS),
    '901–1024 canvas keeps a 300px floor'
  );
  assert.ok(
    /@media \(max-width: 1100px\)[\s\S]{0,800}?\.util-ra--fullpage \.util-ra-project-bar__row/.test(CSS),
    'project/client row collapses to one compact line below 1100px'
  );
  assert.ok(!/height:\s*70vh/.test(collapsed.slice(0, 220)), 'collapsed sheet must not use 70vh');
  assert.ok(
    CSS.includes('.util-ra-first-tip[hidden]') && CSS.includes('.util-ra-first-tip:empty'),
    'first-tip collapses when hidden or empty'
  );
  const firstTipCollapse = CSS.slice(CSS.indexOf('.util-ra-first-tip[hidden]'));
  assert.ok(/height:\s*0/.test(firstTipCollapse.slice(0, 280)), 'hidden first-tip height is 0');
  assert.ok(
    /\.util-ra-first-tip\s*\{[^}]*flex:\s*0 0 auto/.test(CSS.replace(/\s+/g, ' ')),
    'first-tip must not grow with flex: 1 0 100%'
  );
  assert.ok(
    /\.util-ra-first-tip\s*\{[^}]*width:\s*auto/.test(CSS.replace(/\s+/g, ' ')),
    'first-tip is an inline toolbar chip, not a full-width strip'
  );
  assert.ok(
    !/@media \(min-width: 901px\) and \(max-height: 800px\)[\s\S]{0,240}?\.util-ra-first-tip\s*\{[^}]*display:\s*none/.test(CSS),
    'short desktop viewports must not hide the first-tip toolbar chip'
  );
  assert.ok(
    /@media \(min-width: 901px\)[\s\S]{0,400}?\.util-ra-sheet-handle[\s\S]{0,280}?display:\s*none\s*!important/.test(CSS),
    'desktop hides the phone sheet handle'
  );
  const js = fs.readFileSync(path.join(ROOT, 'public/js/admin-utilities-room-area.js'), 'utf8');
  assert.ok(js.includes('function resetDesktopSheetState('), 'desktop resets leftover sheet collapse');
  assert.ok(/if \(!isPhoneChrome\(\)\) return;[\s\S]{0,160}is-sheet-expanded/.test(js), 'sheet expand is ignored on desktop');
  assert.ok(CSS.includes('.util-ra-project-bar__menu-btn'), 'phone project picker has a menu button');
  assert.ok(js.includes('function bindProjectMenu('), 'phone project menu is wired');
  assert.ok(
    /@media \(min-width: 901px\) and \(max-height: 800px\)[\s\S]{0,1600}?overflow-y:\s*auto/.test(CSS),
    'short desktop windows unlock page scroll so the 300px canvas is not clipped'
  );
  assert.ok(js.includes("addEventListener('pointerdown'"), 'phone project menu closes on canvas pointerdown');
  assert.ok(js.includes("e.key === 'Escape'"), 'phone project menu closes on Esc');
}

function resolveKonvaFile() {
  if (fs.existsSync(KONVA_FILE) && fs.statSync(KONVA_FILE).size > 1000) {
    return Promise.resolve(KONVA_FILE);
  }
  return Promise.reject(new Error('Konva must be installed as a local devDependency (node_modules/konva). Tests do not download from a CDN.'));
}

function startStaticServer(konvaFile) {
  const publicDir = path.join(ROOT, 'public');
  const fixturesDir = path.join(__dirname, 'fixtures');
  const fixture = path.join(fixturesDir, 'room-area-phone-geometry.html');
  const savedRoomFixture = path.join(fixturesDir, 'room-area-saved-room-fit.html');
  const layoutChangeFixture = path.join(fixturesDir, 'room-area-layout-change-fit.html');
  const zoomPanFixture = path.join(fixturesDir, 'room-area-zoom-pan-embedded.html');
  const chromeFixture = path.join(fixturesDir, 'room-area-chrome-geometry.html');
  return new Promise((resolve, reject) => {
    const server = http.createServer((req, res) => {
      const urlPath = decodeURIComponent((req.url || '/').split('?')[0]);
      let file;
      if (urlPath === '/') {
        file = fixture;
      } else if (urlPath === '/saved-room' || urlPath === '/saved-room.html') {
        file = savedRoomFixture;
      } else if (urlPath === '/layout-change' || urlPath === '/layout-change.html') {
        file = layoutChangeFixture;
      } else if (urlPath === '/zoom-pan' || urlPath === '/zoom-pan.html') {
        file = zoomPanFixture;
      } else if (urlPath === '/chrome' || urlPath === '/chrome.html') {
        file = chromeFixture;
      } else if (urlPath === '/vendor-test/konva.min.js') {
        file = konvaFile;
      } else {
        file = path.join(publicDir, urlPath.replace(/^\//, ''));
      }
      const allowed = file === fixture
        || file === savedRoomFixture
        || file === layoutChangeFixture
        || file === zoomPanFixture
        || file === chromeFixture
        || file === konvaFile
        || file.startsWith(publicDir + path.sep);
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
        const types = { '.css': 'text/css', '.html': 'text/html', '.js': 'text/javascript' };
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

function readCameraScript() {
  return () => {
    const wrapEl = document.querySelector('[data-ra-canvas-wrap]');
    const stage = window.Konva && window.Konva.stages && window.Konva.stages[0];
    const world = stage && stage.getLayers()[0] && stage.getLayers()[0].getChildren()[0];
    if (!wrapEl || !stage || !world) return null;
    const wrap = wrapEl.getBoundingClientRect();
    const cs = getComputedStyle(wrapEl);
    const pad = {
      top: parseFloat(cs.paddingTop) || 0,
      right: parseFloat(cs.paddingRight) || 0,
      bottom: parseFloat(cs.paddingBottom) || 0,
      left: parseFloat(cs.paddingLeft) || 0,
    };
    const visW = wrap.width - pad.left - pad.right;
    const visH = wrap.height - pad.top - pad.bottom;
    const scale = world.scaleX();
    const pos = world.position();
    return {
      scale,
      zoomPercent: Math.round(scale * 100),
      x: pos.x,
      y: pos.y,
      worldCx: (visW / 2 - pos.x) / scale,
      worldCy: (visH / 2 - pos.y) / scale,
      wrapW: wrap.width,
      wrapH: wrap.height,
      visW,
      visH,
      padTop: pad.top,
      padBottom: pad.bottom,
      stageW: stage.width(),
      stageH: stage.height(),
    };
  };
}

async function waitForEditingRoom(page) {
  await page.waitForFunction(() => {
    const wrap = document.querySelector('[data-ra-canvas-wrap]');
    const world = window.Konva && window.Konva.stages && window.Konva.stages[0]
      && window.Konva.stages[0].getLayers()[0]
      && window.Konva.stages[0].getLayers()[0].getChildren()[0];
    const feedback = document.querySelector('[data-ra-feedback]');
    return !!(wrap && wrap.getBoundingClientRect().width > 80 && world && world.scaleX() > 0
      && feedback && /Editing/.test(feedback.textContent || ''));
  }, { timeout: 15000 });
}

async function zoomAndPan(page) {
  const start = await page.evaluate(readCameraScript());
  assert.ok(start, 'camera exists before zoom');
  for (let i = 0; i < 8; i += 1) {
    await page.evaluate(() => {
      const btn = document.querySelector('[data-ra-zoom-out]');
      if (btn) btn.click();
    });
  }
  await page.waitForFunction((prev) => {
    const world = window.Konva.stages[0].getLayers()[0].getChildren()[0];
    return world && world.scaleX() < prev * 0.75;
  }, { timeout: 8000 }, start.scale);
  const afterZoom = await page.evaluate(readCameraScript());
  assert.ok(afterZoom && afterZoom.scale < start.scale * 0.75, 'zoom out lowered scale: ' + (afterZoom && afterZoom.scale) + ' from ' + start.scale);
  await page.evaluate(() => {
    const wrap = document.querySelector('[data-ra-canvas-wrap]');
    wrap.dispatchEvent(new WheelEvent('wheel', {
      deltaX: 90,
      deltaY: 0,
      bubbles: true,
      cancelable: true,
    }));
  });
  const afterX = await page.evaluate(readCameraScript());
  assert.ok(
    afterX && Math.abs(afterX.worldCx - afterZoom.worldCx) > 1,
    'horizontal wheel panned X: ' + JSON.stringify({ afterZoom, afterX })
  );
  await page.evaluate(() => {
    const wrap = document.querySelector('[data-ra-canvas-wrap]');
    wrap.dispatchEvent(new WheelEvent('wheel', {
      deltaX: 0,
      deltaY: 80,
      shiftKey: true,
      bubbles: true,
      cancelable: true,
    }));
  });
  const after = await page.evaluate(readCameraScript());
  assert.ok(
    after && Math.abs(after.scale - afterX.scale) <= afterX.scale * 0.02,
    'shift+wheel must pan, not zoom: ' + JSON.stringify({ afterX, after })
  );
  assert.ok(
    after && Math.abs(after.worldCy - afterX.worldCy) > 1,
    'shift+vertical wheel panned Y: ' + JSON.stringify({ afterX, after })
  );
  return after;
}

async function assertExtremePanClamped(page, label) {
  await page.evaluate(() => {
    const wrap = document.querySelector('[data-ra-canvas-wrap]');
    wrap.dispatchEvent(new WheelEvent('wheel', {
      deltaX: 8000,
      deltaY: 4000,
      bubbles: true,
      cancelable: true,
    }));
  });
  const clamped = await page.evaluate(readCameraScript());
  assert.ok(clamped, label + ' camera exists after extreme pan');
  assertRoomStaysOnScreen(clamped, label);
}

function readVisibleRoomGeometryScript() {
  return () => {
    const wrapEl = document.querySelector('[data-ra-canvas-wrap]');
    const stage = window.Konva && window.Konva.stages && window.Konva.stages[0];
    const world = stage && stage.getLayers()[0] && stage.getLayers()[0].getChildren()[0];
    const feedback = document.querySelector('[data-ra-feedback]');
    if (!wrapEl || !stage || !world) return null;
    const wrap = wrapEl.getBoundingClientRect();
    const cs = getComputedStyle(wrapEl);
    const pad = {
      top: parseFloat(cs.paddingTop) || 0,
      right: parseFloat(cs.paddingRight) || 0,
      bottom: parseFloat(cs.paddingBottom) || 0,
      left: parseFloat(cs.paddingLeft) || 0,
    };
    const vis = {
      left: wrap.left + pad.left,
      right: wrap.right - pad.right,
      top: wrap.top + pad.top,
      bottom: wrap.bottom - pad.bottom,
      width: wrap.width - pad.left - pad.right,
      height: wrap.height - pad.top - pad.bottom,
    };
    const stageBox = stage.container().getBoundingClientRect();
    const scale = world.scaleX();
    const pos = world.position();
    const verts = [{ x: 0, y: 0 }, { x: 144, y: 0 }, { x: 144, y: 120 }, { x: 0, y: 120 }];
    const screen = verts.map((v) => ({
      x: stageBox.left + pos.x + v.x * scale,
      y: stageBox.top + pos.y + v.y * scale,
    }));
    const minX = Math.min.apply(null, screen.map((p) => p.x));
    const maxX = Math.max.apply(null, screen.map((p) => p.x));
    const minY = Math.min.apply(null, screen.map((p) => p.y));
    const maxY = Math.max.apply(null, screen.map((p) => p.y));
    const fb = feedback && !feedback.hasAttribute('hidden') ? feedback.getBoundingClientRect() : null;
    const texts = [];
    world.find('Text').forEach((node) => {
      const box = node.getClientRect({ skipTransform: false });
      texts.push({
        left: stageBox.left + box.x,
        right: stageBox.left + box.x + box.width,
        top: stageBox.top + box.y,
        bottom: stageBox.top + box.y + box.height,
        text: node.text ? node.text() : '',
      });
    });
    return {
      wrap: { left: wrap.left, right: wrap.right, top: wrap.top, bottom: wrap.bottom, width: wrap.width, height: wrap.height },
      vis,
      pad,
      stage: { w: stage.width(), h: stage.height(), left: stageBox.left, top: stageBox.top, width: stageBox.width, height: stageBox.height },
      room: { minX, maxX, minY, maxY, width: maxX - minX, height: maxY - minY },
      scale,
      pos,
      firstFit: document.documentElement.classList.contains('is-geom-first-fit'),
      feedback: (feedback && feedback.textContent) || '',
      feedbackBox: fb && { left: fb.left, right: fb.right, top: fb.top, bottom: fb.bottom, width: fb.width, height: fb.height },
      labels: texts,
    };
  };
}

function rectsOverlap(a, b, slop) {
  const pad = slop == null ? 0.5 : slop;
  return a.left < b.right - pad && a.right > b.left + pad && a.top < b.bottom - pad && a.bottom > b.top + pad;
}

function assertVisibleCanvasGeometry(geom, label) {
  assert.ok(geom, label + ' geometry exists');
  assert.ok(Math.abs(geom.stage.w - geom.vis.width) < 3, label + ' stage width matches visible canvas: ' + JSON.stringify({ stage: geom.stage, vis: geom.vis }));
  assert.ok(Math.abs(geom.stage.h - geom.vis.height) < 3, label + ' stage height matches visible canvas: ' + JSON.stringify({ stage: geom.stage, vis: geom.vis }));
  assert.ok(
    geom.room.minX >= geom.vis.left - 2 && geom.room.maxX <= geom.vis.right + 2
      && geom.room.minY >= geom.vis.top - 2 && geom.room.maxY <= geom.vis.bottom + 2,
    label + ' room stays on the visible canvas: ' + JSON.stringify(geom)
  );
  if (geom.feedbackBox && geom.feedbackBox.height > 2) {
    const roomBox = {
      left: geom.room.minX,
      right: geom.room.maxX,
      top: geom.room.minY,
      bottom: geom.room.maxY,
    };
    assert.ok(!rectsOverlap(geom.feedbackBox, roomBox, 1), label + ' status does not cover the room: ' + JSON.stringify({ feedback: geom.feedbackBox, room: roomBox }));
    geom.labels.forEach((box, i) => {
      if (box.right - box.left < 2 || box.bottom - box.top < 2) return;
      assert.ok(!rectsOverlap(geom.feedbackBox, box, 1), label + ' status does not cover label ' + i + ': ' + JSON.stringify({ feedback: geom.feedbackBox, label: box }));
    });
  }
}

function readChromeLayoutScript() {
  return () => {
    const wrap = document.querySelector('[data-ra-canvas-wrap]');
    const sheet = document.querySelector('[data-ra-side]');
    const header = document.querySelector('.util-ra-full-header');
    const phone = document.querySelector('[data-ra-phone-bar]');
    const toolbar = document.querySelector('.util-ra-canvas-toolbar');
    const feedback = document.querySelector('[data-ra-feedback]');
    const box = (el) => {
      if (!el) return null;
      const r = el.getBoundingClientRect();
      const cs = getComputedStyle(el);
      return {
        top: r.top,
        bottom: r.bottom,
        left: r.left,
        right: r.right,
        width: r.width,
        height: r.height,
        display: cs.display,
        visibility: cs.visibility,
      };
    };
    const fb = feedback && !feedback.hasAttribute('hidden') ? box(feedback) : null;
    return {
      innerWidth: window.innerWidth,
      innerHeight: window.innerHeight,
      wrap: box(wrap),
      sheet: box(sheet),
      header: box(header),
      phone: box(phone),
      toolbar: box(toolbar),
      feedback: (feedback && feedback.textContent) || '',
      feedbackBox: fb,
    };
  };
}

function assertCanvasAboveSheet(layout, label) {
  assert.ok(layout && layout.wrap && layout.sheet, label + ' has wrap and sheet');
  assert.ok(layout.header && layout.header.height >= 40, label + ' uses the real header, not a 397px canvas fixture: ' + JSON.stringify(layout.header));
  assert.ok(layout.phone && layout.phone.height >= 40, label + ' uses the real phone bar: ' + JSON.stringify(layout.phone));
  assert.ok(
    layout.wrap.bottom <= layout.sheet.top + 1,
    label + ' canvas stays fully above the bottom bar: ' + JSON.stringify({ wrap: layout.wrap, sheet: layout.sheet })
  );
  assert.ok(layout.wrap.top >= 0 && layout.wrap.bottom <= layout.innerHeight + 1, label + ' canvas stays in the viewport');
  assert.ok(/Editing/.test(layout.feedback), label + ' shows Editing status: ' + layout.feedback);
  assert.ok(layout.feedbackBox && layout.feedbackBox.height > 2, label + ' Editing status is visible');
  assert.ok(
    layout.feedbackBox.top >= 0 && layout.feedbackBox.bottom <= layout.innerHeight + 1,
    label + ' Editing status stays on screen: ' + JSON.stringify(layout.feedbackBox)
  );
}

function visibleToolbarControls() {
  return () => {
    const toolbar = document.querySelector('.util-ra-canvas-toolbar');
    if (!toolbar) return [];
    const nodes = Array.from(toolbar.querySelectorAll('button, input:not([type="file"]):not([type="checkbox"]), select, label.admin-btn, .util-ra-help-q'))
      .filter((el) => {
        const r = el.getBoundingClientRect();
        const cs = getComputedStyle(el);
        return r.width > 4 && r.height > 4 && !el.hidden && !el.hasAttribute('hidden') && cs.display !== 'none' && cs.visibility !== 'hidden';
      });
    return nodes.map((el, i) => {
      const r = el.getBoundingClientRect();
      const hit = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
      return {
        i,
        label: el.getAttribute('aria-label') || el.getAttribute('data-ra-unit-system') || el.getAttribute('data-ra-help-pop') || el.tagName + ':' + (el.textContent || '').trim().slice(0, 24),
        width: r.width,
        height: r.height,
        top: r.top,
        bottom: r.bottom,
        left: r.left,
        right: r.right,
        reachable: !!(hit && (hit === el || el.contains(hit) || (hit.contains && hit.contains(el)))),
        overflow: !!document.querySelector('[data-ra-toolbar-overflow]'),
      };
    });
  };
}

function assertRoomStaysOnScreen(cam, label) {
  const verts = [{ x: 0, y: 0 }, { x: 144, y: 0 }, { x: 144, y: 120 }, { x: 0, y: 120 }];
  const xs = verts.map((v) => cam.x + v.x * cam.scale);
  const ys = verts.map((v) => cam.y + v.y * cam.scale);
  const minX = Math.min.apply(null, xs);
  const maxX = Math.max.apply(null, xs);
  const minY = Math.min.apply(null, ys);
  const maxY = Math.max.apply(null, ys);
  assert.ok(
    maxX > 8 && minX < (cam.visW || cam.wrapW) - 8 && maxY > 8 && minY < (cam.visH || cam.wrapH) - 8,
    label + ' keeps part of the room on screen: ' + JSON.stringify({ cam, minX, maxX, minY, maxY })
  );
}

function assertCameraHeld(before, after, label) {
  assert.ok(after, label + ' camera exists');
  assert.ok(
    Math.abs(after.scale - before.scale) <= before.scale * 0.03,
    label + ' keeps zoom: before=' + before.scale + ' after=' + after.scale
  );
  assert.ok(
    Math.abs(after.zoomPercent - before.zoomPercent) <= 4,
    label + ' keeps zoom percent: before=' + before.zoomPercent + ' after=' + after.zoomPercent
  );
  assert.ok(
    Math.abs(after.worldCx - before.worldCx) <= 6 && Math.abs(after.worldCy - before.worldCy) <= 6,
    label + ' keeps centre: ' + JSON.stringify({ before, after })
  );
}

async function waitForStableCanvasFit(page, options) {
  const opts = options || {};
  await page.evaluate(() => {
    window.__raFitStable = { sig: '', n: 0 };
  });
  await page.waitForFunction((minW, maxW, needGrown, needFirstFitGone, needEditing) => {
    const wrapEl = document.querySelector('[data-ra-canvas-wrap]');
    const canvas = wrapEl && wrapEl.querySelector('canvas');
    const world = window.Konva && window.Konva.stages && window.Konva.stages[0]
      && window.Konva.stages[0].getLayers()[0]
      && window.Konva.stages[0].getLayers()[0].getChildren()[0];
    const feedback = document.querySelector('[data-ra-feedback]');
    if (!wrapEl || !canvas || !world) return false;
    const wrap = wrapEl.getBoundingClientRect();
    const cs = getComputedStyle(wrapEl);
    const padL = parseFloat(cs.paddingLeft) || 0;
    const padR = parseFloat(cs.paddingRight) || 0;
    const padT = parseFloat(cs.paddingTop) || 0;
    const padB = parseFloat(cs.paddingBottom) || 0;
    const vis = {
      left: wrap.left + padL,
      right: wrap.right - padR,
      top: wrap.top + padT,
      bottom: wrap.bottom - padB,
    };
    if (wrap.width <= minW || wrap.width >= maxW) return false;
    if (needGrown && !document.documentElement.classList.contains('is-geom-grown')) return false;
    if (needFirstFitGone && document.documentElement.classList.contains('is-geom-first-fit')) return false;
    if (needEditing && !(feedback && /Editing/.test(feedback.textContent || ''))) return false;
    const verts = [{ x: 0, y: 0 }, { x: 144, y: 0 }, { x: 144, y: 120 }, { x: 0, y: 120 }];
    const scale = world.scaleX();
    const pos = world.position();
    const stageBox = window.Konva.stages[0].container().getBoundingClientRect();
    const screen = verts.map((v) => ({
      x: stageBox.left + pos.x + v.x * scale,
      y: stageBox.top + pos.y + v.y * scale,
    }));
    const minX = Math.min.apply(null, screen.map((p) => p.x));
    const maxX = Math.max.apply(null, screen.map((p) => p.x));
    const minY = Math.min.apply(null, screen.map((p) => p.y));
    const maxY = Math.max.apply(null, screen.map((p) => p.y));
    const inside = minX >= vis.left - 2 && maxX <= vis.right + 2
      && minY >= vis.top - 2 && maxY <= vis.bottom + 2;
    const signature = [
      wrap.width.toFixed(2),
      wrap.height.toFixed(2),
      Number(scale).toFixed(6),
      Number(pos.x).toFixed(2),
      Number(pos.y).toFixed(2),
    ].join('|');
    if (!inside || !(scale > 0)) {
      window.__raFitStable = { sig: '', n: 0 };
      return false;
    }
    const prev = window.__raFitStable || { sig: '', n: 0 };
    if (prev.sig === signature) prev.n += 1;
    else {
      prev.sig = signature;
      prev.n = 1;
    }
    window.__raFitStable = prev;
    return prev.n >= 8;
  }, { timeout: opts.timeout || 15000 }, opts.minWidth ?? 200, opts.maxWidth ?? 500, !!opts.grown, !!opts.firstFitGone, !!opts.editing);
}

async function measureViewports() {
  let puppeteer;
  try {
    puppeteer = require('puppeteer');
  } catch (err) {
    throw new Error('puppeteer is required for room-area phone geometry measurements');
  }
  const konvaFile = await resolveKonvaFile();
  const { server, port } = await startStaticServer(konvaFile);
  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });
  try {
    let page = await browser.newPage();
    for (const width of [320, 390, 414]) {
      await page.setViewport({ width, height: 720, deviceScaleFactor: 1, isMobile: true, hasTouch: true });
      await page.goto('http://127.0.0.1:' + port + '/', { waitUntil: 'load' });
      await page.waitForFunction(() => {
        const tabs = document.querySelector('[data-ra-side-tabs]');
        const close = document.querySelector('[data-ra-more-close]');
        const side = document.querySelector('[data-ra-side]');
        return !!(tabs && getComputedStyle(tabs).display === 'none'
          && close && close.getBoundingClientRect().width > 8
          && side && side.getBoundingClientRect().height > 64
          && side.getBoundingClientRect().height < 160);
      });
      const geom = await page.evaluate(() => {
        const close = document.querySelector('[data-ra-more-close]');
        const objects = document.querySelector('[data-ra-palette-toggle]');
        const walls = document.querySelector('[data-ra-walls-toggle]');
        const area = document.querySelector('[data-ra-sheet-total]');
        const side = document.querySelector('[data-ra-side]');
        const tabs = document.querySelector('[data-ra-side-tabs]');
        const body = document.querySelector('.util-ra-side__body');
        const box = (el) => {
          if (!el) return null;
          const r = el.getBoundingClientRect();
          const cs = getComputedStyle(el);
          return {
            left: r.left,
            right: r.right,
            top: r.top,
            bottom: r.bottom,
            width: r.width,
            height: r.height,
            cssWidth: cs.width,
            paddingLeft: parseFloat(cs.paddingLeft) || 0,
            paddingRight: parseFloat(cs.paddingRight) || 0,
            minWidth: cs.minWidth,
            icon: !!(el.querySelector && el.querySelector('i, .fa')),
          };
        };
        return {
          innerWidth: window.innerWidth,
          innerHeight: window.innerHeight,
          close: box(close),
          objects: box(objects),
          walls: box(walls),
          area: box(area),
          side: box(side),
          tabs: box(tabs),
          body: box(body),
        };
      });
      assert.ok(geom.close, 'Close exists at ' + width);
      assert.ok(geom.close.icon, 'Close includes the icon at ' + width);
      assert.ok(geom.close.width >= 44 && geom.close.width < width * 0.55, 'Close is padded auto-width at ' + width + ': ' + geom.close.width);
      assert.ok(geom.close.paddingLeft >= 8 && geom.close.paddingRight >= 8, 'Close has horizontal padding at ' + width + ': ' + geom.close.paddingLeft);
      assert.ok(geom.close.right <= geom.innerWidth + 0.5, 'Close stays inside viewport at ' + width + ': right=' + geom.close.right);
      assert.ok(geom.close.left >= -0.5, 'Close left edge is on-screen at ' + width);
      ['objects', 'walls', 'area'].forEach((key) => {
        const box = geom[key];
        assert.ok(box, key + ' exists at ' + width);
        assert.ok(box.top >= 0, key + ' top visible at ' + width + ': ' + box.top);
        assert.ok(box.bottom <= geom.innerHeight + 0.5, key + ' bottom visible at ' + width + ': ' + box.bottom + ' vs ' + geom.innerHeight);
        assert.ok(box.left >= -0.5 && box.right <= geom.innerWidth + 0.5, key + ' horizontally visible at ' + width);
      });
      assert.ok(geom.side && geom.side.height > 64 && geom.side.height < 160, 'collapsed sheet is handle+strip only at ' + width + ': ' + (geom.side && geom.side.height));
      assert.ok(!geom.tabs || geom.tabs.height < 2, 'collapsed sheet hides tabs at ' + width);
      assert.ok(!geom.body || geom.body.height < 2, 'collapsed sheet hides panel body at ' + width);
    }

    for (const width of [320, 390, 1280]) {
      await page.setViewport({
        width,
        height: width >= 1280 ? 900 : 720,
        deviceScaleFactor: 1,
        isMobile: width < 900,
        hasTouch: width < 900,
      });
      await page.goto('http://127.0.0.1:' + port + '/', { waitUntil: 'load' });
      await page.evaluate(() => {
        const root = document.querySelector('[data-room-area]');
        if (root) {
          root.classList.add('is-sheet-expanded');
          root.classList.remove('is-more-open');
        }
      });
      await page.waitForFunction(() => {
        const finish = document.querySelector('[data-ra-finish]');
        const grid = document.querySelector('.util-ra-finish__grid');
        return !!(finish && grid && getComputedStyle(finish).display !== 'none' && grid.getBoundingClientRect().width > 40);
      });
      const finish = await page.evaluate(() => {
        const grid = document.querySelector('.util-ra-finish__grid');
        const fields = Array.from(document.querySelectorAll('[data-ra-finish] .admin-input, [data-ra-finish] .admin-btn'));
        const help = document.querySelector('[data-ra-help-pop="finish"]');
        const box = (el) => {
          if (!el) return null;
          const r = el.getBoundingClientRect();
          const cs = getComputedStyle(el);
          return { width: r.width, height: r.height, left: r.left, right: r.right, columns: cs.gridTemplateColumns };
        };
        return {
          innerWidth: window.innerWidth,
          grid: box(grid),
          help: box(help),
          fields: fields.map((el) => {
            const r = el.getBoundingClientRect();
            return { height: r.height, right: r.right, overflow: r.right > window.innerWidth + 0.5 };
          }),
        };
      });
      assert.ok(finish.grid, 'finish grid exists at ' + width);
      assert.ok(finish.grid.width > 40 && finish.grid.right <= finish.innerWidth + 0.5, 'finish grid stays in viewport at ' + width);
      assert.ok((finish.grid.columns || '').split(' ').filter(Boolean).length >= 2, 'finish grid is two columns at ' + width + ': ' + finish.grid.columns);
      assert.ok(finish.help && finish.help.width >= 20, 'finish help tip is present at ' + width);
      finish.fields.forEach((field, i) => {
        assert.ok(field.height >= 40, 'finish control ' + i + ' meets touch height at ' + width + ': ' + field.height);
        assert.ok(!field.overflow, 'finish control ' + i + ' stays on screen at ' + width);
      });
    }

    await page.setViewport({ width: 390, height: 720, deviceScaleFactor: 1, isMobile: true, hasTouch: true });
    await page.goto('http://127.0.0.1:' + port + '/saved-room', { waitUntil: 'load' });
    await waitForStableCanvasFit(page, {
      minWidth: 200,
      maxWidth: 500,
      firstFitGone: true,
      editing: true,
    });
    const fitted = await page.evaluate(readVisibleRoomGeometryScript());
    assert.ok(!fitted.firstFit, 'stale 900×640 first-fit wrap must be released before asserting');
    assert.ok(fitted.wrap.width >= 200 && fitted.wrap.width < 500, 'canvas wrap is the 390 layout: ' + fitted.wrap.width);
    assert.ok(fitted.scale > 1.2 && fitted.scale < 2.85, 'saved room was re-fitted after wrap shrink, scale=' + fitted.scale);
    assert.ok(
      fitted.room.minX >= fitted.wrap.left - 2 && fitted.room.maxX <= fitted.wrap.right + 2,
      'saved room stays inside wrap horizontally at 390: ' + JSON.stringify(fitted)
    );
    assert.ok(
      fitted.room.minY >= fitted.wrap.top - 2 && fitted.room.maxY <= fitted.wrap.bottom + 2,
      'saved room stays inside wrap vertically at 390: ' + JSON.stringify(fitted)
    );
    const wrapCx = (fitted.wrap.left + fitted.wrap.right) / 2;
    const roomCx = (fitted.room.minX + fitted.room.maxX) / 2;
    assert.ok(
      Math.abs(roomCx - wrapCx) <= 28,
      'saved room is centred at 390: roomCx=' + roomCx + ' wrapCx=' + wrapCx + ' scale=' + fitted.scale
    );
    assert.ok(
      fitted.room.width >= fitted.wrap.width * 0.72,
      'saved room fills most of the wrap width at 390: ' + fitted.room.width + ' vs ' + fitted.wrap.width
    );
    assertVisibleCanvasGeometry(fitted, '390×720');
    assert.ok(/Click Save/.test(fitted.feedback), 'editing status names the Save button: ' + fitted.feedback);

    const viewportCases = [
      { width: 320, height: 568 },
      { width: 320, height: 720 },
      { width: 360, height: 640 },
      { width: 375, height: 667 },
      { width: 390, height: 664 },
      { width: 414, height: 736 },
      { width: 1024, height: 768, desktop: true },
    ];
    for (const vp of viewportCases) {
      await page.setViewport({
        width: vp.width,
        height: vp.height,
        deviceScaleFactor: 1,
        isMobile: !vp.desktop,
        hasTouch: !vp.desktop,
      });
      await waitForStableCanvasFit(page, {
        minWidth: vp.desktop ? 200 : 160,
        maxWidth: vp.desktop ? 1600 : 500,
        firstFitGone: true,
        editing: true,
      });
      const geom = await page.evaluate(readVisibleRoomGeometryScript());
      assertVisibleCanvasGeometry(geom, vp.width + '×' + vp.height);
      if (vp.width === 320 && vp.height === 568) {
        const chrome = await page.evaluate(readChromeLayoutScript());
        assertCanvasAboveSheet(chrome, '320×568');
        assert.ok(
          chrome.header.height + chrome.phone.height + chrome.sheet.height > 140,
          '320×568 chrome is the real header + phone bar + sheet, not a 397px canvas fixture: ' + JSON.stringify(chrome)
        );
      }
      if (vp.width === 1024) {
        const bar = await page.evaluate(() => {
          const toolbar = document.querySelector('.util-ra-canvas-toolbar');
          const overflow = document.querySelector('[data-ra-toolbar-overflow]');
          const helps = Array.from(document.querySelectorAll('.util-ra-canvas-toolbar .util-ra-help')).map((el) => {
            const r = el.getBoundingClientRect();
            const cs = getComputedStyle(el);
            return { width: r.width, height: r.height, display: cs.display };
          });
          const barBox = toolbar.getBoundingClientRect();
          return {
            height: barBox.height,
            overflowShown: !!(overflow && overflow.getBoundingClientRect().width > 8),
            helpVisible: helps.filter((h) => h.height > 8 && h.width > 8 && h.display !== 'none').length,
          };
        });
        assert.ok(!bar.overflowShown, '1024 has no More overflow menu');
        assert.ok(bar.helpVisible >= 2, '1024 keeps toolbar ⓘ visible: ' + bar.helpVisible);
      }
    }

    for (const width of [901, 960, 1024]) {
      await page.setViewport({ width, height: 768, deviceScaleFactor: 1, isMobile: false, hasTouch: false });
      await waitForStableCanvasFit(page, {
        minWidth: 200,
        maxWidth: 1600,
        firstFitGone: true,
      });
      const controls = await page.evaluate(visibleToolbarControls());
      assert.ok(controls.length >= 16, width + ' toolbar exposes the wrapping controls: ' + controls.length);
      assert.ok(controls.every((c) => !c.overflow), width + ' must not render a More menu');
      const needed = ['Imperial', 'Metric', 'Imperial format', 'Calibrate', 'Apply known length', 'About measure'];
      needed.forEach((label) => {
        assert.ok(controls.some((c) => String(c.label).includes(label) || String(c.label).toLowerCase().includes(label.toLowerCase()) || (label === 'Imperial' && c.label === 'imperial') || (label === 'Metric' && c.label === 'metric')), width + ' is missing ' + label + ': ' + JSON.stringify(controls.map((c) => c.label)));
      });
      controls.forEach((c) => {
        assert.ok(c.reachable, width + ' control is not under another layer: ' + JSON.stringify(c));
        assert.ok(c.bottom <= 768 + 1 && c.top >= 0, width + ' control is on screen: ' + JSON.stringify(c));
      });
      for (const control of controls) {
        const clicked = await page.evaluate((idx) => {
          const toolbar = document.querySelector('.util-ra-canvas-toolbar');
          const nodes = Array.from(toolbar.querySelectorAll('button, input:not([type="file"]):not([type="checkbox"]), select, label.admin-btn, .util-ra-help-q'))
            .filter((el) => {
              const r = el.getBoundingClientRect();
              const cs = getComputedStyle(el);
              return r.width > 4 && r.height > 4 && !el.hidden && !el.hasAttribute('hidden') && cs.display !== 'none' && cs.visibility !== 'hidden';
            });
          const el = nodes[idx];
          if (!el) return { ok: false, reason: 'missing' };
          const r = el.getBoundingClientRect();
          const hit = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
          if (!(hit && (hit === el || el.contains(hit) || (hit.contains && hit.contains(el))))) return { ok: false, reason: 'elementFromPoint', label: el.getAttribute('aria-label') };
          if (el.matches('.util-ra-file-label') || el.querySelector('input[type="file"]')) return { ok: true };
          el.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
          document.querySelectorAll('.util-ra-help-pop, [data-ra-templates-popover]').forEach((pop) => pop.setAttribute('hidden', ''));
          document.querySelectorAll('[data-ra-help-pop]').forEach((btn) => btn.setAttribute('aria-expanded', 'false'));
          return { ok: true };
        }, control.i);
        assert.ok(clicked && clicked.ok, width + ' click failed: ' + JSON.stringify({ control, clicked }));
      }
    }

    await page.close();
    page = await browser.newPage();
    await page.setViewport({ width: 320, height: 568, deviceScaleFactor: 1, isMobile: true, hasTouch: true });
    await page.goto('http://127.0.0.1:' + port + '/saved-room', { waitUntil: 'load' });
    await waitForStableCanvasFit(page, { minWidth: 160, maxWidth: 500, firstFitGone: true });
    const good320 = await page.evaluate(readChromeLayoutScript());
    assert.ok(good320.wrap.bottom <= good320.sheet.top + 1, '320×568 canvas stays above the bar after toolbar clicks');
    await page.addStyleTag({ content: '.util-ra--fullpage:not(.is-sheet-expanded):not(.is-dock-open) .util-ra-side { height: 70vh !important; max-height: 70vh !important; }' });
    const mutantSheet = await page.evaluate(readChromeLayoutScript());
    assert.ok(
      mutantSheet.wrap.bottom > mutantSheet.sheet.top + 1,
      'a 70vh collapsed-sheet mutant must push the canvas under the bar: ' + JSON.stringify(mutantSheet)
    );
    await page.goto('http://127.0.0.1:' + port + '/saved-room', { waitUntil: 'load' });
    await waitForStableCanvasFit(page, { minWidth: 160, maxWidth: 500, firstFitGone: true });
    await page.addStyleTag({ content: '.util-ra--fullpage .util-ra-canvas-wrap { margin-bottom: -120px !important; }' });
    const mutantWrap = await page.evaluate(readChromeLayoutScript());
    assert.ok(
      mutantWrap.wrap.bottom > mutantWrap.sheet.top + 1,
      'a canvas-under-bar mutant must fail the geometry check: ' + JSON.stringify(mutantWrap)
    );
    await page.goto('http://127.0.0.1:' + port + '/saved-room', { waitUntil: 'load' });
    await waitForStableCanvasFit(page, { minWidth: 160, maxWidth: 500, firstFitGone: true });

    await page.setViewport({ width: 414, height: 736, deviceScaleFactor: 1, isMobile: false, hasTouch: false });
    await page.goto('http://127.0.0.1:' + port + '/saved-room', { waitUntil: 'load' });
    await waitForStableCanvasFit(page, { minWidth: 200, maxWidth: 500, firstFitGone: true });
    await page.evaluate(() => {
      const handle = document.querySelector('[data-ra-sheet-handle]');
      if (handle) handle.click();
    });
    await page.waitForFunction(() => {
      const root = document.querySelector('[data-room-area]');
      return !!(root && root.classList.contains('is-sheet-expanded'));
    }, { timeout: 8000 });
    await waitForStableCanvasFit(page, { minWidth: 200, maxWidth: 500, firstFitGone: true });
    const sheet414 = await page.evaluate(readChromeLayoutScript());
    assert.ok(sheet414.sheet && sheet414.sheet.height <= 280 + 1, '414×736 open sheet is capped: ' + (sheet414.sheet && sheet414.sheet.height));
    assert.ok(sheet414.wrap && sheet414.wrap.height >= 160, '414×736 drawing area stays usable with the sheet open: ' + (sheet414.wrap && sheet414.wrap.height));
    assert.ok(sheet414.wrap.bottom <= sheet414.sheet.top + 1, '414×736 canvas stays above the open sheet');
    await page.evaluate(() => {
      const handle = document.querySelector('[data-ra-sheet-handle]');
      if (handle) handle.click();
    });

    await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 1, isMobile: true, hasTouch: true });
    await waitForStableCanvasFit(page, { minWidth: 200, maxWidth: 500, firstFitGone: true, editing: true });
    await page.evaluate(() => {
      const handle = document.querySelector('[data-ra-sheet-handle]');
      if (handle) handle.click();
    });
    await page.waitForFunction(() => {
      const root = document.querySelector('[data-room-area]');
      return !!(root && root.classList.contains('is-sheet-expanded'));
    }, { timeout: 8000 });
    await waitForStableCanvasFit(page, { minWidth: 200, maxWidth: 500, firstFitGone: true, editing: true });
    const sheetOpen = await page.evaluate(readVisibleRoomGeometryScript());
    assertVisibleCanvasGeometry(sheetOpen, '390×844 sheet open');
    await page.evaluate(() => {
      const handle = document.querySelector('[data-ra-sheet-handle]');
      if (handle) handle.click();
    });

    const layoutChangeHtml = fs.readFileSync(path.join(__dirname, 'fixtures/room-area-layout-change-fit.html'), 'utf8');
    assert.ok(!/width:\s*900px/.test(layoutChangeHtml), 'layout-change fixture must not force 900px');
    assert.ok(!/height:\s*640px/.test(layoutChangeHtml), 'layout-change fixture must not force 640px');
    assert.ok(!/!important/.test(layoutChangeHtml), 'layout-change fixture must not use !important sizes');
    assert.ok(!/requestAnimationFrame\s*=/.test(layoutChangeHtml), 'layout-change fixture must not shim requestAnimationFrame');
    assert.ok(/class="geom-host"/.test(layoutChangeHtml), 'layout-change fixture must use a real flex host');
    assert.ok(/class="geom-rail"/.test(layoutChangeHtml), 'layout-change fixture must change layout with a sibling rail');

    await page.setViewport({ width: 390, height: 720, deviceScaleFactor: 1, isMobile: true, hasTouch: true });
    await page.goto('http://127.0.0.1:' + port + '/layout-change', { waitUntil: 'load' });
    try {
      await waitForStableCanvasFit(page, {
        minWidth: 300,
        maxWidth: 450,
        grown: true,
      });
    } catch (err) {
      const dump = await page.evaluate(() => {
        const wrap = document.querySelector('[data-ra-canvas-wrap]');
        const world = window.Konva && window.Konva.stages && window.Konva.stages[0]
          && window.Konva.stages[0].getLayers()[0]
          && window.Konva.stages[0].getLayers()[0].getChildren()[0];
        return {
          grown: document.documentElement.classList.contains('is-geom-grown'),
          wrapW: wrap ? wrap.getBoundingClientRect().width : null,
          scale: world ? world.scaleX() : null,
          stages: !!(window.Konva && window.Konva.stages && window.Konva.stages[0]),
          feedback: (document.querySelector('[data-ra-feedback]') || {}).textContent || '',
        };
      });
      throw new Error('layout-change wait failed: ' + JSON.stringify(dump) + ' — ' + err.message);
    }
    const relayout = await page.evaluate(() => {
      const data = (function () {
        const wrapEl = document.querySelector('[data-ra-canvas-wrap]');
        const stage = window.Konva.stages[0];
        const world = stage.getLayers()[0].getChildren()[0];
        const wrap = wrapEl.getBoundingClientRect();
        const cs = getComputedStyle(wrapEl);
        const padL = parseFloat(cs.paddingLeft) || 0;
        const padR = parseFloat(cs.paddingRight) || 0;
        const padT = parseFloat(cs.paddingTop) || 0;
        const padB = parseFloat(cs.paddingBottom) || 0;
        const vis = {
          left: wrap.left + padL,
          right: wrap.right - padR,
          top: wrap.top + padT,
          bottom: wrap.bottom - padB,
        };
        const stageBox = stage.container().getBoundingClientRect();
        const verts = [{ x: 0, y: 0 }, { x: 144, y: 0 }, { x: 144, y: 120 }, { x: 0, y: 120 }];
        const scale = world ? world.scaleX() : stage.scaleX();
        const pos = world ? world.position() : stage.position();
        const screen = verts.map((v) => ({
          x: stageBox.left + pos.x + v.x * scale,
          y: stageBox.top + pos.y + v.y * scale,
        }));
        const minX = Math.min.apply(null, screen.map((p) => p.x));
        const maxX = Math.max.apply(null, screen.map((p) => p.x));
        const minY = Math.min.apply(null, screen.map((p) => p.y));
        const maxY = Math.max.apply(null, screen.map((p) => p.y));
        return {
          wrap: { left: wrap.left, right: wrap.right, top: wrap.top, bottom: wrap.bottom, width: wrap.width, height: wrap.height },
          vis,
          room: { minX, maxX, minY, maxY, width: maxX - minX, height: maxY - minY },
          scale,
          grown: document.documentElement.classList.contains('is-geom-grown'),
        };
      })();
      return data;
    });
    assert.ok(relayout.grown, 'layout-change wrap must grow after the first fallback fit');
    assert.ok(relayout.wrap.width >= 360 && relayout.wrap.width <= 420, 'layout-change wrap is a real 390 box: ' + relayout.wrap.width);
    assert.ok(relayout.scale > 1.2 && relayout.scale < 2.85, 'layout-change refit after wrap grew, scale=' + relayout.scale);
    assert.ok(
      relayout.room.minX >= relayout.wrap.left - 2 && relayout.room.maxX <= relayout.wrap.right + 2,
      'layout-change room stays inside wrap: ' + JSON.stringify(relayout)
    );
    const layoutCx = (relayout.wrap.left + relayout.wrap.right) / 2;
    const layoutRoomCx = (relayout.room.minX + relayout.room.maxX) / 2;
    assert.ok(
      Math.abs(layoutRoomCx - layoutCx) <= 28,
      'layout-change room is centred: roomCx=' + layoutRoomCx + ' wrapCx=' + layoutCx
    );

    const embedHtml = fs.readFileSync(path.join(__dirname, 'fixtures/room-area-zoom-pan-embedded.html'), 'utf8');
    assert.ok(!/data-ra-fullpage/.test(embedHtml), 'embedded fixture must not be full-page');
    assert.ok(!/util-ra--fullpage/.test(embedHtml), 'embedded fixture must not use the full-page class');

    await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1, isMobile: false, hasTouch: false });
    await page.goto('http://127.0.0.1:' + port + '/zoom-pan', { waitUntil: 'load' });
    await waitForEditingRoom(page);
    const embedZoomed = await zoomAndPan(page);
    await page.evaluate(() => document.documentElement.classList.add('is-embed-rail'));
    try {
      await page.waitForFunction((prevW) => {
        const wrap = document.querySelector('[data-ra-canvas-wrap]');
        const stage = window.Konva.stages[0];
        if (!wrap || !stage) return false;
        const wrapW = wrap.getBoundingClientRect().width;
        const cs = getComputedStyle(wrap);
        const visW = wrapW - (parseFloat(cs.paddingLeft) || 0) - (parseFloat(cs.paddingRight) || 0);
        return wrapW < prevW - 40 && Math.abs(stage.width() - visW) < 3;
      }, { timeout: 8000 }, embedZoomed.wrapW);
    } catch (err) {
      const dump = await page.evaluate(readCameraScript());
      throw new Error('embedded rail wait failed: ' + JSON.stringify({ before: embedZoomed, after: dump }) + ' — ' + err.message);
    }
    const embedAfterRail = await page.evaluate(readCameraScript());
    assertCameraHeld(embedZoomed, embedAfterRail, 'embedded rail resize');
    assert.ok(embedAfterRail.stageW < embedZoomed.stageW - 20, 'embedded observer resized the stage');
    await assertExtremePanClamped(page, 'embedded extreme pan');

    await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1, isMobile: false, hasTouch: false });
    await page.goto('http://127.0.0.1:' + port + '/saved-room', { waitUntil: 'load' });
    await waitForStableCanvasFit(page, { minWidth: 200, maxWidth: 1600, firstFitGone: true, editing: true });
    const deskZoomed = await zoomAndPan(page);
    await page.setViewport({ width: 1240, height: 900, deviceScaleFactor: 1, isMobile: false, hasTouch: false });
    await page.waitForFunction((prevStageW) => {
      const wrap = document.querySelector('[data-ra-canvas-wrap]');
      const stage = window.Konva && window.Konva.stages && window.Konva.stages[0];
      if (window.innerWidth !== 1240 || !wrap || !stage) return false;
      const wrapW = wrap.getBoundingClientRect().width;
      const cs = getComputedStyle(wrap);
      const visW = wrapW - (parseFloat(cs.paddingLeft) || 0) - (parseFloat(cs.paddingRight) || 0);
      return Math.abs(stage.width() - prevStageW) > 1 && Math.abs(stage.width() - visW) < 3;
    }, { timeout: 8000 }, deskZoomed.stageW);
    const deskAfterResize = await page.evaluate(readCameraScript());
    assertCameraHeld(deskZoomed, deskAfterResize, '1280 window resize');
    await page.evaluate(() => {
      const btn = document.querySelector('[data-ra-side-toggle]');
      if (btn) btn.click();
    });
    try {
      await page.waitForFunction((prevW) => {
        const wrap = document.querySelector('[data-ra-canvas-wrap]');
        const root = document.querySelector('[data-room-area]');
        const stage = window.Konva && window.Konva.stages && window.Konva.stages[0];
        if (!root || !root.classList.contains('is-side-collapsed') || !wrap || !stage) return false;
        const wrapW = wrap.getBoundingClientRect().width;
        const cs = getComputedStyle(wrap);
        const visW = wrapW - (parseFloat(cs.paddingLeft) || 0) - (parseFloat(cs.paddingRight) || 0);
        return Math.abs(wrapW - prevW) > 8 && Math.abs(stage.width() - visW) < 3;
      }, { timeout: 8000 }, deskAfterResize.wrapW);
    } catch (err) {
      const dump = await page.evaluate(readCameraScript());
      const flags = await page.evaluate(() => {
        const root = document.querySelector('[data-room-area]');
        return {
          collapsed: !!(root && root.classList.contains('is-side-collapsed')),
          hasToggle: !!document.querySelector('[data-ra-side-toggle]'),
        };
      });
      throw new Error('1280 panel wait failed: ' + JSON.stringify({ before: deskAfterResize, after: dump, flags }) + ' — ' + err.message);
    }
    const deskAfterPanel = await page.evaluate(readCameraScript());
    assertCameraHeld(deskZoomed, deskAfterPanel, '1280 panel toggle');
    await assertExtremePanClamped(page, '1280 extreme pan');

    await page.setViewport({ width: 390, height: 720, deviceScaleFactor: 1, isMobile: true, hasTouch: true });
    await page.goto('http://127.0.0.1:' + port + '/saved-room', { waitUntil: 'load' });
    await waitForStableCanvasFit(page, { minWidth: 200, maxWidth: 500, firstFitGone: true, editing: true });
    const phoneZoomed = await zoomAndPan(page);
    await page.evaluate(() => {
      const handle = document.querySelector('[data-ra-sheet-handle]');
      if (handle) handle.click();
    });
    try {
      await page.waitForFunction((prevH) => {
        const root = document.querySelector('[data-room-area]');
        const wrap = document.querySelector('[data-ra-canvas-wrap]');
        const stage = window.Konva && window.Konva.stages && window.Konva.stages[0];
        if (!root || !root.classList.contains('is-sheet-expanded') || !wrap || !stage) return false;
        const wrapH = wrap.getBoundingClientRect().height;
        const cs = getComputedStyle(wrap);
        const visH = wrapH - (parseFloat(cs.paddingTop) || 0) - (parseFloat(cs.paddingBottom) || 0);
        return Math.abs(wrapH - prevH) > 8 && Math.abs(stage.height() - visH) < 3;
      }, { timeout: 8000 }, phoneZoomed.wrapH);
    } catch (err) {
      const dump = await page.evaluate(() => {
        const root = document.querySelector('[data-room-area]');
        const cam = (function () {
          const wrapEl = document.querySelector('[data-ra-canvas-wrap]');
          const wrap = wrapEl ? wrapEl.getBoundingClientRect() : null;
          const stage = window.Konva && window.Konva.stages && window.Konva.stages[0];
          return {
            expanded: !!(root && root.classList.contains('is-sheet-expanded')),
            wrapH: wrap && wrap.height,
            stageH: stage && stage.height(),
          };
        })();
        return cam;
      });
      throw new Error('390 sheet wait failed: ' + JSON.stringify(dump) + ' — ' + err.message);
    }
    const phoneAfterSheet = await page.evaluate(readCameraScript());
    assertCameraHeld(phoneZoomed, phoneAfterSheet, '390 bottom sheet');
    await assertExtremePanClamped(page, '390 extreme pan');

    const chromePage = await browser.newPage();
    try {
      for (const width of [1024, 1280]) {
        await chromePage.setViewport({ width, height: 800, deviceScaleFactor: 1, isMobile: false, hasTouch: false });
        await chromePage.goto('http://127.0.0.1:' + port + '/chrome', { waitUntil: 'load' });
        const strip = await chromePage.evaluate(() => {
          const tools = document.querySelector('.util-ra-full-header__row--tools');
          const bar = document.querySelector('.util-ra-project-bar');
          const tip = document.querySelector('[data-ra-first-tip]');
          const feedback = document.querySelector('[data-ra-feedback]');
          const handle = document.querySelector('[data-ra-sheet-handle]');
          if (!tools || !bar) return { missing: true };
          const t = tools.getBoundingClientRect();
          const b = bar.getBoundingClientRect();
          const empties = [];
          document.querySelectorAll('.util-ra *').forEach((el) => {
            if (el === tools || tools.contains(el) || el === bar || bar.contains(el)) return;
            if (el.classList && el.classList.contains('util-ra-full-header')) return;
            const r = el.getBoundingClientRect();
            if (r.height <= 4 || r.width < 40) return;
            if (r.bottom <= t.bottom + 0.5 || r.top >= b.top - 0.5) return;
            const cs = getComputedStyle(el);
            const text = (el.innerText || '').trim();
            const empty = el.hasAttribute('hidden') || cs.display === 'none' || !text;
            if (empty) {
              empties.push({
                className: String(el.className || el.tagName),
                height: Math.round(r.height * 10) / 10,
                display: cs.display,
              });
            }
          });
          const tipBox = tip ? tip.getBoundingClientRect() : { height: 0 };
          const tipCs = tip ? getComputedStyle(tip) : { display: 'none' };
          const feedbackBox = feedback ? feedback.getBoundingClientRect() : { height: 0 };
          const handleBox = handle ? handle.getBoundingClientRect() : { height: 0 };
          const handleCs = handle ? getComputedStyle(handle) : { display: 'none' };
          return {
            empties,
            tipHeight: tipBox.height,
            tipDisplay: tipCs.display,
            tipHidden: !!(tip && tip.hasAttribute('hidden')),
            feedbackHeight: feedbackBox.height,
            handleHeight: handleBox.height,
            handleDisplay: handleCs.display,
            gap: Math.round((b.top - t.bottom) * 10) / 10,
          };
        });
        assert.ok(!strip.missing, 'chrome fixture rendered at ' + width);
        assert.ok(strip.tipHidden, 'first-tip stays hidden when no status is shown at ' + width);
        assert.ok(strip.tipDisplay === 'none' && strip.tipHeight < 0.5, 'hidden first-tip takes no space at ' + width + ': ' + JSON.stringify(strip));
        assert.ok(strip.feedbackHeight < 0.5, 'hidden status line takes no space at ' + width + ': ' + strip.feedbackHeight);
        assert.ok(strip.handleDisplay === 'none' && strip.handleHeight < 0.5, 'desktop sheet handle takes no space at ' + width + ': ' + JSON.stringify(strip));
        assert.strictEqual(
          strip.empties.length,
          0,
          'no empty block taller than 4px between toolbar and Project at ' + width + ': ' + JSON.stringify(strip)
        );

        const placed = await chromePage.evaluate(() => {
          const tools = document.querySelector('.util-ra-full-header__row--tools');
          const tip = document.querySelector('[data-ra-first-tip]');
          const bar = document.querySelector('.util-ra-project-bar');
          if (!tools || !tip || !bar) return { missing: true };
          tip.hidden = false;
          const t = tools.getBoundingClientRect();
          const r = tip.getBoundingClientRect();
          const b = bar.getBoundingClientRect();
          return {
            inToolbar: tools.contains(tip),
            tipTop: r.top,
            toolsTop: t.top,
            barTop: b.top,
            display: getComputedStyle(tip).display,
          };
        });
        assert.ok(!placed.missing, 'first-tip exists at ' + width);
        assert.ok(placed.inToolbar, 'visible first-tip lives in the View toolbar at ' + width);
        assert.ok(placed.display !== 'none' && placed.tipTop >= placed.toolsTop - 1 && placed.tipTop < placed.barTop, 'first-tip is on the toolbar row at ' + width + ': ' + JSON.stringify(placed));
        await chromePage.evaluate(() => {
          const tip = document.querySelector('[data-ra-first-tip]');
          if (tip) tip.hidden = true;
        });
      }
    } finally {
      await chromePage.close();
    }
  } finally {
    await browser.close();
    await new Promise((resolve) => server.close(resolve));
  }
}

function liveGeometryEnabled() {
  return process.env.RA_LIVE === '1' || process.env.GEOMETRY_LIVE === '1';
}

function liveOnly() {
  return process.env.RA_LIVE_ONLY === '1';
}

function liveSnapshotScript() {
  return function () {
    function box(el) {
      if (!el) return null;
      const r = el.getBoundingClientRect();
      return { top: r.top, bottom: r.bottom, left: r.left, right: r.right, width: r.width, height: r.height, x: r.left + r.width / 2, y: r.top + r.height / 2 };
    }
    function visibleStyle(el) {
      if (!el) return 'missing';
      if (el.closest('[hidden]')) return 'hidden';
      const cs = getComputedStyle(el);
      if (cs.display === 'none' || cs.visibility === 'hidden' || Number(cs.opacity) === 0) return 'hidden';
      const r = el.getBoundingClientRect();
      if (r.width < 1 || r.height < 1) return 'zero';
      return cs.display;
    }
    function parentHit(el, hit) {
      return !!(hit && el && hit !== el && hit.contains(el) && !el.contains(hit));
    }
    function selfHit(el) {
      const b = box(el);
      if (!b) return { self: false, parent: false, hit: null, ok: false };
      const hit = document.elementFromPoint(b.x, b.y);
      const overlay = document.getElementById('ra-mutant-overlay') || document.getElementById('ra-mutant-strip');
      const covered = !!(overlay && hit && (hit === overlay || overlay.contains(hit)));
      const self = !!(hit && (hit === el || el.contains(hit)));
      const parent = parentHit(el, hit);
      const disabled = !!(el.disabled || el.getAttribute('disabled') !== null);
      return {
        self: self,
        parent: parent,
        ok: !covered && (self || (disabled && parent)),
        hit: hit && (hit.className || hit.tagName),
        box: b,
      };
    }
    function overlaps(a, b) {
      if (!a || !b) return false;
      return a.left < b.right - 2 && a.right > b.left + 2 && a.top < b.bottom - 2 && a.bottom > b.top + 2;
    }
    const wrap = document.querySelector('.util-ra-canvas-wrap, [data-ra-canvas-wrap]');
    const stage = document.querySelector('.util-ra-konva, #ra-konva-container');
    const status = document.querySelector('[data-ra-feedback], .util-ra-feedback');
    const palette = document.querySelector('.util-ra-palette__toggle, [data-ra-palette-toggle]');
    const handle = document.querySelector('[data-ra-sheet-handle]');
    const undo = document.querySelector('[data-ra-undo-vertex]');
    const unit = document.querySelector('[data-ra-unit-system="imperial"], .util-ra-unit-system__btn');
    const sheet = document.querySelector('[data-ra-side], .util-ra-side');
    const menuBtn = document.querySelector('[data-ra-project-menu-toggle]');
    const bar = document.querySelector('.util-ra-project-bar');
    const project = document.querySelector('[data-ra-project-select]');
    const helps = Array.from(document.querySelectorAll('.util-ra-help-q')).map(function (el) {
      const hit = selfHit(el);
      return {
        id: el.getAttribute('data-ra-help-pop'),
        display: visibleStyle(el),
        h: hit.box && hit.box.height,
        self: hit.self,
        parent: hit.parent,
        box: hit.box,
      };
    });
    const visibleHelps = helps.filter(function (h) { return h.display !== 'hidden' && h.display !== 'zero' && h.display !== 'missing'; });
    const w = box(wrap);
    const st = status && visibleStyle(status) !== 'hidden' && visibleStyle(status) !== 'missing' ? box(status) : null;
    const stageBox = box(stage);
    const handleHit = selfHit(handle);
    const undoHit = selfHit(undo);
    const unitHit = selfHit(unit);
    const bottomX = w ? w.x : (window.innerWidth / 2);
    const bottomY = Math.min(window.innerHeight - 12, w ? Math.max(w.top + 8, w.bottom - 8) : window.innerHeight - 12);
    const lowY = window.innerHeight * 0.85;
    const bottomHit = document.elementFromPoint(bottomX, bottomY);
    const lowHit = document.elementFromPoint(window.innerWidth / 2, lowY);
    function isStrip(hit) {
      return !!(hit && (hit.id === 'ra-mutant-strip' || (hit.className && String(hit.className).indexOf('ra-mutant-strip') !== -1)));
    }
    const root = document.querySelector('.util-ra--fullpage');
    const main = document.querySelector('.admin-main') || document.scrollingElement;
    return {
      wrapH: w && w.height,
      wrapTop: w && w.top,
      wrapBottom: w && w.bottom,
      wrapBox: w,
      innerH: window.innerHeight,
      rootH: root ? root.scrollHeight : 0,
      mainH: main ? main.scrollHeight : 0,
      docH: document.scrollingElement ? document.scrollingElement.scrollHeight : 0,
      help: visibleHelps[0] || null,
      helps: helps,
      visibleHelps: visibleHelps,
      unitSelf: unitHit.self,
      unitParent: unitHit.parent,
      unitBox: unitHit.box,
      undoSelf: undoHit.self,
      undoParent: undoHit.parent,
      undoOk: undoHit.ok,
      undoBox: undoHit.box,
      handleDisplay: handle ? visibleStyle(handle) : 'missing',
      handleH: handleHit.box && handleHit.box.height,
      handleSelf: handleHit.self,
      handleParent: handleHit.parent,
      handleBox: handleHit.box,
      sheetTop: sheet ? box(sheet).top : null,
      sheetH: sheet ? box(sheet).height : null,
      statusBox: st,
      statusCoversStage: !!(st && stageBox && overlaps(st, stageBox)),
      paletteBox: box(palette),
      barH: bar ? bar.getBoundingClientRect().height : 0,
      menuBtn: menuBtn ? getComputedStyle(menuBtn).display : 'missing',
      bottomHit: bottomHit && ((bottomHit.id || '') + ' ' + (bottomHit.className || bottomHit.tagName)),
      bottomIsHandle: !!(handle && ((bottomHit && (bottomHit === handle || handle.contains(bottomHit))) || (lowHit && (lowHit === handle || handle.contains(lowHit))))),
      bottomIsStrip: isStrip(bottomHit) || isStrip(lowHit),
      projectValue: project ? project.value : '',
      hasKitchen: document.body.innerText.indexOf('Kitchen') !== -1,
    };
  };
}

function assertDesktopLive(geo, vp) {
  assert.ok(geo.projectValue, 'seeded Room Area project did not load at ' + vp.width + 'x' + vp.height);
  assert.ok(geo.wrapH >= 300, 'live canvas is at least 300px at ' + vp.width + 'x' + vp.height + ': ' + JSON.stringify(geo));
  const visible = Math.max(0, Math.min(geo.wrapBottom, geo.innerH) - Math.max(geo.wrapTop, 0));
  const canScroll = geo.rootH > geo.innerH + 4 || geo.mainH > geo.innerH + 4 || geo.docH > geo.innerH + 4;
  assert.ok(
    visible >= 300 || (canScroll && geo.wrapTop < geo.innerH),
    'canvas off-screen at ' + vp.width + 'x' + vp.height + ': ' + JSON.stringify({ visible, canScroll, wrapTop: geo.wrapTop, innerH: geo.innerH })
  );
  assert.ok(geo.undoOk, 'overlay over Undo at ' + vp.width + ': ' + JSON.stringify(geo));
  assert.ok(geo.unitSelf && !geo.unitParent, 'overlay over the unit buttons at ' + vp.width + ': ' + JSON.stringify(geo));
  assert.ok(geo.handleDisplay === 'hidden' || geo.handleDisplay === 'zero', 'desktop drag strip / handle must stay hidden at ' + vp.width + ': ' + JSON.stringify(geo));
  assert.ok(!geo.bottomIsHandle && !geo.bottomIsStrip, 'invisible desktop drag strip at the canvas bottom: ' + JSON.stringify(geo));
  const visibleHelps = geo.visibleHelps || [];
  if (vp.width >= 1024) {
    assert.ok(visibleHelps.length >= 2, 'desktop must keep more than one ⓘ visible: ' + JSON.stringify(visibleHelps));
    visibleHelps.forEach(function (help) {
      assert.ok(help.h > 8 && help.display !== 'hidden', 'ⓘ hidden after the first: ' + JSON.stringify(help));
      assert.ok(help.self && !help.parent, 'ⓘ elementFromPoint must hit the control itself: ' + JSON.stringify(help));
    });
  }
}

function assertPhoneLive(geo, phone) {
  assert.ok(geo.menuBtn !== 'none' && geo.menuBtn !== 'missing', 'phone project picker menu is visible at ' + phone.width + ': ' + JSON.stringify(geo));
  assert.ok(geo.barH <= 72, 'phone project/client block is a single compact line at ' + phone.width + ': ' + geo.barH);
  assert.ok(geo.wrapH >= 160 && geo.wrapTop >= 0 && geo.wrapBottom <= geo.innerH + 1, 'live ' + phone.width + ' canvas is fully visible: ' + JSON.stringify(geo));
  assert.ok(geo.wrapBottom <= geo.sheetTop + 1, '320 canvas under the bottom bar at ' + phone.width + ': ' + JSON.stringify(geo));
  assert.ok(!geo.statusCoversStage, 'phone status covering the canvas at ' + phone.width + ': ' + JSON.stringify(geo));
  assert.ok(geo.handleDisplay !== 'hidden' && geo.handleDisplay !== 'missing' && geo.handleH > 8, 'phone sheet handle hidden at ' + phone.width + ': ' + JSON.stringify(geo));
  assert.ok(geo.handleSelf && !geo.handleParent, 'phone sheet handle overlaid at ' + phone.width + ': ' + JSON.stringify(geo));
  if (phone.width === 320 && geo.statusBox && geo.paletteBox && geo.paletteBox.height > 0) {
    const covers = geo.paletteBox.top < geo.statusBox.bottom - 2 && geo.paletteBox.bottom > geo.statusBox.top + 2;
    assert.ok(!covers, '320 palette toggle must not cover the status: ' + JSON.stringify(geo));
  }
}

async function clearMutants(page) {
  await page.evaluate(function () {
    const style = document.getElementById('ra-mutant-style');
    if (style) style.remove();
    const dom = document.getElementById('ra-mutant-dom');
    if (dom) dom.remove();
  });
}

async function injectCss(page, css) {
  await page.evaluate(function (text) {
    let style = document.getElementById('ra-mutant-style');
    if (!style) {
      style = document.createElement('style');
      style.id = 'ra-mutant-style';
      document.head.appendChild(style);
    }
    style.textContent = text;
  }, css);
}

async function injectDom(page, html) {
  await page.evaluate(function (markup) {
    let wrap = document.getElementById('ra-mutant-dom');
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.id = 'ra-mutant-dom';
      document.body.appendChild(wrap);
    }
    wrap.innerHTML = markup;
  }, html);
}

async function assertInjectedFaultDies(page, name, setup, check) {
  await setup();
  await new Promise(function (resolve) { setTimeout(resolve, 80); });
  let killed = false;
  try {
    await check();
  } catch (err) {
    killed = true;
  }
  await clearMutants(page);
  assert.ok(killed, 'injected fault survived: ' + name);
}

async function measureLiveEditor() {
  const base = process.env.RA_LIVE_URL || process.env.APP_URL || 'http://127.0.0.1:8000';
  const email = process.env.ADMIN_EMAIL || 'admin@zanini.ca';
  const password = process.env.ADMIN_PASSWORD || 'password';
  const projectId = process.env.RA_PROJECT_ID || '1';
  const roomId = process.env.RA_ROOM_ID || '1';
  let probe;
  try {
    probe = await fetch(base + '/admin/login');
  } catch (err) {
    throw new Error('live Room Area page is required: ' + base + ' (' + err.message + ')');
  }
  if (!probe.ok) throw new Error('live login page returned ' + probe.status);

  const browser = await require('puppeteer').launch({ headless: 'new', args: ['--no-sandbox', '--disable-setuid-sandbox'] });
  const page = await browser.newPage();
  try {
    await page.goto(base + '/admin/login', { waitUntil: 'domcontentloaded', timeout: 45000 });
    await page.type('input[name="email"]', email);
    await page.type('input[name="password"]', password);
    await Promise.all([
      page.click('button[type="submit"]'),
      page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 45000 }),
    ]);
    await page.evaluate(() => {
      try { localStorage.setItem('zr-room-area-guide-tip-v1', '1'); } catch (e) {}
    });
    await page.goto(base + '/admin/utilities/room-area?ra_project=' + projectId + '&ra_room=' + roomId, { waitUntil: 'domcontentloaded', timeout: 45000 });
    await page.waitForSelector('.util-ra--fullpage', { timeout: 20000 });
    await page.waitForSelector('.util-ra-canvas-wrap', { timeout: 20000 });
    await page.waitForFunction(() => typeof window.Konva !== 'undefined', { timeout: 20000 }).catch(function () {});
    const snap = liveSnapshotScript();

    for (const vp of [
      { width: 901, height: 768, mobile: false },
      { width: 960, height: 768, mobile: false },
      { width: 960, height: 600, mobile: false },
      { width: 1000, height: 768, mobile: false },
      { width: 1024, height: 768, mobile: false },
      { width: 1025, height: 600, mobile: false },
      { width: 1366, height: 657, mobile: false },
    ]) {
      await page.setViewport({ width: vp.width, height: vp.height, deviceScaleFactor: 1, isMobile: false, hasTouch: false });
      await new Promise((resolve) => setTimeout(resolve, 280));
      const geo = await page.evaluate(snap);
      assertDesktopLive(geo, vp);
      if (geo.unitBox) await page.mouse.click(geo.unitBox.x, geo.unitBox.y);
      if (vp.width === 1024 && geo.visibleHelps[0] && geo.visibleHelps[0].box) {
        await page.mouse.click(geo.visibleHelps[0].box.x, geo.visibleHelps[0].box.y);
      }
    }

    await page.setViewport({ width: 1024, height: 768, deviceScaleFactor: 1, isMobile: false, hasTouch: false });
    await new Promise((resolve) => setTimeout(resolve, 200));
    const desktopVp = { width: 1024, height: 768 };
    await assertInjectedFaultDies(page, 'canvas wrap capped at 200px', function () {
      return injectCss(page, '.util-ra-canvas-wrap,[data-ra-canvas-wrap]{min-height:200px !important;height:200px !important;max-height:200px !important;}');
    }, async function () {
      assertDesktopLive(await page.evaluate(snap), desktopVp);
    });
    await assertInjectedFaultDies(page, 'overlay over Undo', async function () {
      await page.evaluate(function () {
        const undo = document.querySelector('[data-ra-undo-vertex]');
        if (!undo) return;
        const r = undo.getBoundingClientRect();
        let wrap = document.getElementById('ra-mutant-dom');
        if (!wrap) {
          wrap = document.createElement('div');
          wrap.id = 'ra-mutant-dom';
          document.body.appendChild(wrap);
        }
        wrap.innerHTML = '<div id="ra-mutant-overlay" style="position:fixed;left:' + r.left + 'px;top:' + r.top + 'px;width:' + Math.max(r.width, 24) + 'px;height:' + Math.max(r.height, 24) + 'px;z-index:9999;"></div>';
      });
    }, async function () {
      assertDesktopLive(await page.evaluate(snap), desktopVp);
    });
    await assertInjectedFaultDies(page, 'canvas pushed 1500px down', function () {
      return injectCss(page, '.util-ra-canvas-wrap,[data-ra-canvas-wrap]{margin-top:1500px !important;}');
    }, async function () {
      assertDesktopLive(await page.evaluate(snap), desktopVp);
    });
    await assertInjectedFaultDies(page, 'desktop drag strip', async function () {
      await injectDom(page, '<div id="ra-mutant-strip" class="ra-mutant-strip" style="position:fixed;left:0;right:0;bottom:0;height:40%;background:transparent;z-index:9999;"></div>');
    }, async function () {
      assertDesktopLive(await page.evaluate(snap), desktopVp);
    });
    await assertInjectedFaultDies(page, 'ⓘ hidden after the first', function () {
      return page.evaluate(function () {
        document.querySelectorAll('.util-ra-help-q').forEach(function (el, i) {
          if (i > 0) el.style.display = 'none';
        });
      });
    }, async function () {
      assertDesktopLive(await page.evaluate(snap), desktopVp);
    });
    await page.evaluate(function () {
      document.querySelectorAll('.util-ra-help-q').forEach(function (el) { el.style.display = ''; });
    });

    for (const phone of [
      { width: 320, height: 568 },
      { width: 360, height: 640 },
      { width: 414, height: 736 },
    ]) {
      await page.setViewport({ width: phone.width, height: phone.height, deviceScaleFactor: 1, isMobile: true, hasTouch: true });
      await new Promise((resolve) => setTimeout(resolve, 350));
      const phoneGeo = await page.evaluate(snap);
      assertPhoneLive(phoneGeo, phone);
      assert.ok(phoneGeo.handleBox, 'live ' + phone.width + ' sheet handle has a box');
      await page.evaluate(function () {
        const root = document.querySelector('[data-room-area]');
        if (root) {
          root.classList.remove('is-sheet-expanded');
          root.style.setProperty('--ra-sheet-h', 'auto');
        }
        const handle = document.querySelector('[data-ra-sheet-handle]');
        if (handle) handle.setAttribute('aria-expanded', 'false');
      });
      await new Promise((resolve) => setTimeout(resolve, 80));
      const handleBox = await page.evaluate(snap);
      assert.ok(handleBox.handleBox, 'live ' + phone.width + ' sheet handle box after reset');
      await page.touchscreen.tap(handleBox.handleBox.x, handleBox.handleBox.y);
      await new Promise((resolve) => setTimeout(resolve, 200));
      const opened = await page.evaluate(function () {
        const root = document.querySelector('[data-room-area]');
        return !!(root && root.classList.contains('is-sheet-expanded'));
      });
      assert.ok(opened, 'live ' + phone.width + ' sheet handle is clickable via CDP');
      const afterSheet = await page.evaluate(snap);
      assert.ok(afterSheet.wrapH >= 160, 'live ' + phone.width + ' keeps 160px canvas with sheet open: ' + JSON.stringify(afterSheet));
      assert.ok(afterSheet.sheetH <= 280 + 1, 'taller sheet at ' + phone.width + ': ' + JSON.stringify(afterSheet));
      assert.ok(afterSheet.wrapBottom <= afterSheet.sheetTop + 1, 'open sheet must stay below the canvas at ' + phone.width + ': ' + JSON.stringify(afterSheet));
      await page.mouse.click(phoneGeo.handleBox.x, phoneGeo.handleBox.y);
    }

    await page.setViewport({ width: 320, height: 568, deviceScaleFactor: 1, isMobile: true, hasTouch: true });
    await new Promise((resolve) => setTimeout(resolve, 250));
    const phoneVp = { width: 320, height: 568 };
    await assertInjectedFaultDies(page, 'phone status covering the canvas', function () {
      return injectCss(page, '[data-ra-feedback],.util-ra-feedback{display:block !important;position:absolute !important;top:40px !important;left:0;right:0;height:90px !important;background:#000 !important;opacity:1 !important;z-index:20;}');
    }, async function () {
      await page.evaluate(function () {
        const status = document.querySelector('[data-ra-feedback], .util-ra-feedback');
        if (status) {
          status.hidden = false;
          status.textContent = 'Saved';
        }
      });
      assertPhoneLive(await page.evaluate(snap), phoneVp);
    });
    await page.evaluate(function () {
      const status = document.querySelector('[data-ra-feedback], .util-ra-feedback');
      if (status) {
        status.hidden = true;
        status.textContent = '';
      }
    });
    await assertInjectedFaultDies(page, 'phone sheet handle hidden', function () {
      return injectCss(page, '[data-ra-sheet-handle]{display:none !important;}');
    }, async function () {
      assertPhoneLive(await page.evaluate(snap), phoneVp);
    });
    await assertInjectedFaultDies(page, 'overlay over the phone handle', async function () {
      await page.evaluate(function () {
        const handle = document.querySelector('[data-ra-sheet-handle]');
        if (!handle) return;
        const r = handle.getBoundingClientRect();
        let wrap = document.getElementById('ra-mutant-dom');
        if (!wrap) {
          wrap = document.createElement('div');
          wrap.id = 'ra-mutant-dom';
          document.body.appendChild(wrap);
        }
        wrap.innerHTML = '<div id="ra-mutant-overlay" style="position:fixed;left:' + r.left + 'px;top:' + r.top + 'px;width:' + Math.max(r.width, 24) + 'px;height:' + Math.max(r.height, 24) + 'px;z-index:9999;"></div>';
      });
    }, async function () {
      assertPhoneLive(await page.evaluate(snap), phoneVp);
    });
  } finally {
    await browser.close();
  }
}

assertCssGeometry();
const watchdog = setTimeout(function () {
  console.error('room-area phone geometry tests: timed out');
  process.exit(1);
}, liveGeometryEnabled() ? 180000 : TEST_TIMEOUT_MS);
const start = liveOnly() ? Promise.resolve() : measureViewports();
start.then(function () {
  if (liveGeometryEnabled()) {
    return measureLiveEditor();
  }
  console.log('room-area phone geometry tests: skipped live page (set RA_LIVE=1)');
}).then(function () {
  console.log('room-area phone geometry tests: ok');
  clearTimeout(watchdog);
  process.exit(0);
}).catch(function (err) {
  console.error(err);
  clearTimeout(watchdog);
  process.exit(1);
});
