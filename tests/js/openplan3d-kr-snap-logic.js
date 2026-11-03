'use strict';

/**
 * Extract and simulate canvas Kr() / jo() from patched editor chunk (unit tests).
 */

const INCH = 2.54;
const METRIC_GRID_CM = 25;

function extractKrSource(editorJs) {
  const m = editorJs.match(/function Kr\(w,st\)\{if\(!t\(ln\)\)return st;[\s\S]*?\}function tn\(\)/);
  if (!m) throw new Error('Kr(w,st) not found in editor chunk');
  return m[0].replace(/function tn\(\)$/, '');
}

function extractJoSource(editorJs) {
  const m = editorJs.match(/function jo\(w\)\{[\s\S]*?\}function bi\(\)/);
  if (!m) throw new Error('jo(w) not found in editor chunk');
  return m[0].replace(/function bi\(\)$/, '');
}

function makeKrRunner(krSource) {
  const ln = { v: true };
  const nn = { v: true };
  const pn = { v: INCH };
  const Gr = METRIC_GRID_CM;
  const Ce = { v: { units: 'imperial' } };
  const t = (store) => store.v;
  const cr = (w) => {
    if (!t(ln)) return w;
    const st = t(nn) ? t(pn) : Gr;
    return Math.round(w / st) * st;
  };
  const Kr = new Function('t', 'ln', 'nn', 'pn', 'Gr', 'Ce', 'cr', `${krSource} return Kr;`)(
    t,
    ln,
    nn,
    pn,
    Gr,
    Ce,
    cr
  );
  return {
    Kr,
    setSnap(on) {
      ln.v = on;
    },
    setUnits(units) {
      Ce.v = { units };
      pn.v = units === 'imperial' ? INCH : METRIC_GRID_CM;
      nn.v = true;
    },
  };
}

function makeJoRunner(joSource, krRunner) {
  const ln = { v: true };
  const nn = { v: true };
  const pn = { v: INCH };
  const Gr = METRIC_GRID_CM;
  const Ce = { v: { units: 'imperial' } };
  const O = { v: { x: 23.71, y: 31.43 } };
  const gt = { v: true };
  const t = (store) => store.v;
  const cr = (w) => {
    if (!t(ln)) return w;
    const st = t(nn) ? t(pn) : Gr;
    return Math.round(w / st) * st;
  };
  function Br(w) {
    return { x: w.x, y: w.y };
  }
  const Kr = krRunner.Kr;
  const jo = new Function('t', 'ln', 'nn', 'pn', 'Gr', 'Ce', 'O', 'gt', 'Br', 'Kr', 'cr', `${joSource} return jo;`)(
    t,
    ln,
    nn,
    pn,
    Gr,
    Ce,
    O,
    gt,
    Br,
    Kr,
    cr
  );
  return {
    jo,
    setSnap(on) {
      ln.v = on;
    },
    setUnits(units) {
      Ce.v = { units };
      pn.v = units === 'imperial' ? INCH : METRIC_GRID_CM;
      nn.v = true;
    },
    setOrigin(pt) {
      O.v = { ...pt };
    },
  };
}

function onWholeInch(v) {
  return Math.abs(v / INCH - Math.round(v / INCH)) < 0.02;
}

function orthoLengthCm(anchor, point) {
  return Math.hypot(point.x - anchor.x, point.y - anchor.y);
}

function revertKrOrthoPatch(krSource) {
  return krSource
    .replace(
      /let H=K;t\(Ce\)\.units==="imperial"&&\(H=cr\(K\)\);return\{x:w\.x\+H\*Math\.cos\(Zt\),y:w\.y\+H\*Math\.sin\(Zt\)\}/,
      'return{x:w.x+K*Math.cos(Zt),y:w.y+K*Math.sin(Zt)}'
    )
    .replace(/return t\(Ce\)\.units==="imperial"\?\{x:cr\(st\.x\),y:cr\(st\.y\)\}:st$/, 'return st');
}

function revertJoOrthoPatch(joSource) {
  return joSource.replace(
    'let H=K;t(ln)&&t(Ce).units==="imperial"&&(H=cr(K));st={x:t(O).x+H*Math.cos(Ut),y:t(O).y+H*Math.sin(Ut)}',
    'st={x:t(O).x+K*Math.cos(Ut),y:t(O).y+K*Math.sin(Ut)}'
  );
}

module.exports = {
  INCH,
  METRIC_GRID_CM,
  extractKrSource,
  extractJoSource,
  makeKrRunner,
  makeJoRunner,
  onWholeInch,
  orthoLengthCm,
  revertKrOrthoPatch,
  revertJoOrthoPatch,
};
