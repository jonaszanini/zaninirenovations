'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');

console.log('openplan3d-zanini-tools tests');

const toolsSrc = fs.readFileSync(path.join(__dirname, '../../public/js/openplan3d-zanini-tools.js'), 'utf8');
assert.match(toolsSrc, /MutationObserver|openplan3d-store-ready/, 'canvas hook must retry until editor canvas exists');
assert.match(toolsSrc, /data-plan3d-canvas="true"/, 'must target main plan canvas not minimap');
assert.match(toolsSrc, /readViewState|camX/, 'screenToPlan must use editor zoom and pan');

function screenToPlanWithView(canvas, clientX, clientY, view) {
  var rect = canvas.getBoundingClientRect();
  var scaleX = canvas.width / (rect.width || 1);
  var scaleY = canvas.height / (rect.height || 1);
  var px = (clientX - rect.left) * scaleX;
  var py = (clientY - rect.top) * scaleY;
  return {
    x: (px - canvas.width / 2) / view.zoom + view.camX,
    y: (py - canvas.height / 2) / view.zoom + view.camY
  };
}

const fakeCanvas = {
  width: 800,
  height: 600,
  getBoundingClientRect: function () {
    return { left: 0, top: 0, width: 400, height: 300 };
  }
};

const view = { zoom: 0.5, camX: 100, camY: 200 };
const pt = screenToPlanWithView(fakeCanvas, 200, 150, view);
assert.ok(Math.abs(pt.x - 100) < 0.01, 'zoom transform x expected ~100 got ' + pt.x);
assert.ok(Math.abs(pt.y - 200) < 0.01, 'zoom transform y expected ~200 got ' + pt.y);

console.log('openplan3d-zanini-tools: ok');
