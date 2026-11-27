/**
 * Dashboard info tips stay closed until click, hover, or focus.
 * Project List rows open on one click except contact icons and status.
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import { createEnv, makeTip } from './admin-info-tip-harness.mjs';

const require = createRequire(import.meta.url);
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const rows = require(path.join(root, 'public/js/admin-project-list-rows.js'));

function listener(listeners, type) {
  const list = listeners.document[type] || [];
  return list.find((entry) => entry.opts === true || (entry.opts && entry.opts.capture)) || list[0];
}

function tipTarget(btn, wrap) {
  return {
    nodeType: 1,
    closest(sel) {
      if (sel === '.admin-info-tip') return btn;
      if (sel === '.admin-info-tip__bubble') return null;
      if (sel === '.admin-info-tip-wrap') return wrap;
      return null;
    },
  };
}

{
  const css = fs.readFileSync(path.join(root, 'public/css/admin-dashboard.css'), 'utf8');
  const hover = css.match(/\.dash-card--stat:hover\s*\{[^}]*\}/);
  assert.ok(hover, 'stat card hover rule');
  assert.equal(/transform\s*:/.test(hover[0]), false, 'hover transform traps fixed info tips');
}

{
  const { document, body, listeners, window } = createEnv();
  window.matchMedia = (query) => ({ matches: query.includes('hover: hover'), media: query });
  const { wrap, btn, bubble } = makeTip(document, 'dash-active-tip', 'Active projects');
  bubble.textContent = 'Excludes completed, cancelled & rejected';
  body.appendChild(wrap);
  btn.dispatchEvent = function () {};

  assert.equal(btn.getAttribute('aria-expanded'), 'false');
  assert.equal(wrap.classList.contains('is-open'), false);

  const over = listener(listeners, 'pointerover');
  assert.ok(over, 'pointerover opens the tip on a fine pointer');
  over.fn({ target: tipTarget(btn, wrap), pointerType: 'mouse' });
  assert.equal(wrap.classList.contains('is-open'), true, 'hover opens the tip');
  assert.equal(btn.getAttribute('aria-expanded'), 'true');

  const out = listener(listeners, 'pointerout');
  out.fn({ target: tipTarget(btn, wrap), pointerType: 'mouse', relatedTarget: null });
  assert.equal(wrap.classList.contains('is-open'), false, 'leaving the icon closes an unpinned tip');

  over.fn({ target: tipTarget(btn, wrap), pointerType: 'touch' });
  assert.equal(wrap.classList.contains('is-open'), false, 'touch does not use hover');

  const focusin = listener(listeners, 'focusin');
  focusin.fn({ target: tipTarget(btn, wrap) });
  assert.equal(wrap.classList.contains('is-open'), true, 'focus opens the tip');

  const click = listener(listeners, 'click');
  click.fn({
    target: tipTarget(btn, wrap),
    preventDefault() {},
    stopPropagation() {},
  });
  assert.equal(wrap.getAttribute('data-tip-pinned'), '1', 'click pins the tip');
  out.fn({ target: tipTarget(btn, wrap), pointerType: 'mouse', relatedTarget: null });
  assert.equal(wrap.classList.contains('is-open'), true, 'pinned tip stays open when the pointer leaves');

  const key = listeners.document.keydown[0];
  key.fn({ key: 'Escape' });
  assert.equal(wrap.classList.contains('is-open'), false, 'Escape closes the tip');

  click.fn({
    target: tipTarget(btn, wrap),
    preventDefault() {},
    stopPropagation() {},
  });
  click.fn({
    target: { closest() { return null; } },
    preventDefault() {},
    stopPropagation() {},
  });
  assert.equal(wrap.classList.contains('is-open'), false, 'outside click closes the tip');
}

function el(hit) {
  return {
    nodeType: 1,
    closest(sel) {
      if (hit && String(sel).includes(hit)) return { hit: true };
      return null;
    },
  };
}

const href = '/admin/client-projects/9';
const link = {
  nodeType: 1,
  getAttribute(name) { return name === 'href' ? href : null; },
  closest(sel) {
    if (sel === 'a[href]') return link;
    return null;
  },
};

assert.equal(rows.actionFor(el(null), href), 'navigate', 'plain row click opens the project');
assert.equal(rows.actionFor(el('.cp-inline-note'), href), 'navigate', 'note text opens the project');
assert.equal(rows.actionFor(link, href), 'native', 'project link uses the browser');
assert.equal(rows.actionFor(el('.cp-client-action-btn'), href), 'ignore', 'contact icons do not open the project');
assert.equal(rows.actionFor(el('.cp-inline-status'), href), 'ignore', 'status dropdown does not open the project');
assert.equal(rows.actionFor(el('.cp-status-menu'), href), 'ignore', 'status menu does not open the project');
