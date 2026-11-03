'use strict';

/**
 * Extract and simulate canvas Kr() from patched editor chunk (for unit tests).
 */

const INCH = 2.54;

function extractKrSource(editorJs) {
  const m = editorJs.match(/function Kr\(w,st\)\{if\(!t\(ln\)\)return st;[\s\S]*?\}function tn\(\)/);
  if (!m) throw new Error('Kr(w,st) not found in editor chunk');
  return m[0].replace(/function tn\(\)$/, '');
}

function makeKrRunner(krSource) {
  const ln = { v: true };
  const nn = { v: true };
  const pn = { v: INCH };
  const Gr = 1;
  const t = (store) => store.v;
  const cr = (w) => {
    if (!t(ln)) return w;
    const st = t(nn) ? t(pn) : Gr;
    return Math.round(w / st) * st;
  };
  const fn = new Function('t', 'ln', 'nn', 'pn', 'Gr', 'cr', `${krSource} return Kr;`)(t, ln, nn, pn, Gr, cr);
  return {
    Kr: fn,
    setSnap(on) {
      ln.v = on;
    },
    setImperial(on) {
      nn.v = on;
    },
  };
}

function onWholeInch(v) {
  return Math.abs(v / INCH - Math.round(v / INCH)) < 0.02;
}

function orthoLengthCm(anchor, point) {
  return Math.hypot(point.x - anchor.x, point.y - anchor.y);
}

module.exports = {
  INCH,
  extractKrSource,
  makeKrRunner,
  onWholeInch,
  orthoLengthCm,
};
