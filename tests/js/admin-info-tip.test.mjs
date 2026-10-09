/**
 * Minimal DOM + behaviour tests for public/js/admin-info-tip.js.
 * Covers scroll reposition, server-side (sibling) placement hoist fallback,
 * and fresh ids after clone.
 */
import assert from 'node:assert/strict';
import { createEnv, makeTip } from './admin-info-tip-harness.mjs';

// --- scroll repositions an open tip ---
{
  const { document, body, listeners, AdminInfoTip } = createEnv();
  const { wrap, btn, bubble } = makeTip(document, 'admin-info-tip-dup', 'Board style');
  body.appendChild(wrap);
  btn.dispatchEvent = function () {};
  const click = listeners.document.click.find((l) => l.opts === true || (l.opts && l.opts.capture));
  assert.ok(click, 'capture-phase click listener');
  click.fn({
    target: {
      closest(sel) {
        if (sel === '.admin-info-tip') return btn;
        if (sel === '.admin-info-tip__bubble') return null;
        if (sel === '.admin-info-tip-wrap') return wrap;
        return null;
      },
    },
    preventDefault() {},
    stopPropagation() {},
  });
  assert.equal(wrap.classList.contains('is-open'), true);
  assert.equal(bubble.style.position, 'fixed');
  const firstTop = bubble.style.top;
  btn._rect = { top: 300, bottom: 344, left: 40, right: 84, width: 44, height: 44 };
  const scroll = listeners.document.scroll.find((l) => l.opts && l.opts.capture && l.opts.passive);
  assert.ok(scroll, 'capture-phase passive scroll listener');
  scroll.fn();
  assert.equal(bubble.style.position, 'fixed');
  assert.notEqual(bubble.style.top, firstTop);

  btn._rect = { top: -80, bottom: -36, left: 40, right: 84, width: 44, height: 44 };
  scroll.fn();
  assert.equal(wrap.classList.contains('is-open'), false);
  AdminInfoTip.closeAll();
}

// --- hoist leftover in-label tips (fallback for server-side placement) ---
{
  const { document, body, AdminInfoTip } = createEnv();
  const group = document.createElement('div');
  group.className = 'admin-form-group';
  const label = document.createElement('label');
  label.setAttribute('for', 'sku');
  label.htmlFor = 'sku';
  const input = document.createElement('input');
  input.id = 'sku';
  const { wrap } = makeTip(document, 'admin-info-tip-inlabel', 'SKU');
  label.appendChild(wrap);
  group.appendChild(label);
  group.appendChild(input);
  body.appendChild(group);
  label.control = input;
  AdminInfoTip.hoist(document);
  const row = wrap.closest('.admin-label-with-tip');
  assert.ok(row, 'tip is in a hoisted row');
  assert.ok(!label.contains(wrap), 'tip is no longer inside the label');
  assert.ok(row.contains(label), 'label stays in the hoisted row');
}

// --- cloned rows get fresh ids ---
{
  const { document, body, AdminInfoTip } = createEnv();
  const { wrap } = makeTip(document, 'admin-info-tip-shared', 'Materials');
  body.appendChild(wrap);
  const clone = wrap.cloneNode(true);
  body.appendChild(clone);
  const firstId = wrap.querySelector('.admin-info-tip__bubble').id;
  const cloneIdBefore = clone.querySelector('.admin-info-tip__bubble').id;
  assert.equal(firstId, cloneIdBefore);
  AdminInfoTip.refreshIds(clone);
  const cloneIdAfter = clone.querySelector('.admin-info-tip__bubble').id;
  const cloneBtn = clone.querySelector('.admin-info-tip');
  assert.notEqual(cloneIdAfter, firstId);
  assert.equal(cloneBtn.getAttribute('aria-controls'), cloneIdAfter);
  assert.equal(cloneBtn.getAttribute('aria-describedby'), cloneIdAfter);
  assert.equal(wrap.querySelector('.admin-info-tip__bubble').id, firstId);
}

// --- document-wide dedupe when a cloned subtree repeats an existing id ---
{
  const { document, body, AdminInfoTip } = createEnv();
  const { wrap } = makeTip(document, 'admin-info-tip-shared', 'Materials');
  body.appendChild(wrap);
  const added = wrap.cloneNode(true);
  body.appendChild(added);
  const firstId = wrap.querySelector('.admin-info-tip__bubble').id;
  assert.equal(added.querySelector('.admin-info-tip__bubble').id, firstId);
  AdminInfoTip.refreshIds(added);
  const addedId = added.querySelector('.admin-info-tip__bubble').id;
  assert.notEqual(addedId, firstId);
  assert.equal(wrap.querySelector('.admin-info-tip__bubble').id, firstId);
}

// --- Esc returns focus to the button when focus is in the bubble ---
{
  const { document, body, listeners } = createEnv();
  const { wrap, btn, bubble } = makeTip(document, 'admin-info-tip-esc', 'Invoices');
  body.appendChild(wrap);
  wrap.classList.add('is-open');
  btn.setAttribute('aria-expanded', 'true');
  document.activeElement = bubble;
  const key = listeners.document.keydown[0];
  assert.ok(key, 'keydown listener');
  key.fn({ key: 'Escape' });
  assert.equal(wrap.classList.contains('is-open'), false);
  assert.equal(document.activeElement, btn);
}

// --- resize is rAF-throttled and repositions ---
{
  const { document, body, listeners } = createEnv();
  const { wrap, btn, bubble } = makeTip(document, 'admin-info-tip-resize', 'Owner rate');
  body.appendChild(wrap);
  wrap.classList.add('is-open');
  btn.setAttribute('aria-expanded', 'true');
  bubble.style.position = 'fixed';
  bubble.style.top = '10px';
  btn._rect = { top: 80, bottom: 124, left: 200, right: 244, width: 44, height: 44 };
  const resize = listeners.window.resize[0];
  assert.ok(resize, 'resize listener');
  resize.fn();
  assert.equal(bubble.style.position, 'fixed');
  assert.notEqual(bubble.style.top, '10px');
}

// --- focusout closes the open tip ---
{
  const { document, body, listeners } = createEnv();
  const { wrap, btn, bubble } = makeTip(document, 'admin-info-tip-focusout', 'Finished rooms');
  body.appendChild(wrap);
  wrap.classList.add('is-open');
  btn.setAttribute('aria-expanded', 'true');
  const fo = listeners.document.focusout.find((l) => l.opts === true || (l.opts && l.opts.capture));
  assert.ok(fo, 'capture-phase focusout listener');
  fo.fn({
    target: {
      closest(sel) {
        return sel === '.admin-info-tip-wrap' ? wrap : null;
      },
    },
    relatedTarget: null,
  });
  assert.equal(wrap.classList.contains('is-open'), false);

  wrap.classList.add('is-open');
  btn.setAttribute('aria-expanded', 'true');
  fo.fn({
    target: {
      closest(sel) {
        return sel === '.admin-info-tip-wrap' ? wrap : null;
      },
    },
    relatedTarget: bubble,
  });
  assert.equal(wrap.classList.contains('is-open'), true, 'stay open when focus moves into the bubble');
}

// --- tip inside <summary> does not toggle details; bubble links stay clickable ---
{
  const { document, body, listeners, AdminInfoTip } = createEnv();
  const details = document.createElement('details');
  const summary = document.createElement('summary');
  const { wrap, btn, bubble } = makeTip(document, 'admin-info-tip-summary', 'Filters');
  const link = document.createElement('a');
  link.setAttribute('href', '/admin/materials/config');
  bubble.appendChild(link);
  summary.appendChild(wrap);
  details.appendChild(summary);
  body.appendChild(details);

  const click = listeners.document.click.find((l) => l.opts === true || (l.opts && l.opts.capture));
  assert.ok(click, 'capture-phase click listener');

  let iconPrevented = false;
  let iconStopped = false;
  click.fn({
    target: {
      closest(sel) {
        if (sel === '.admin-info-tip') return btn;
        if (sel === '.admin-info-tip__bubble') return null;
        if (sel === '.admin-info-tip-wrap') return wrap;
        if (sel === 'summary') return summary;
        if (sel === 'a') return null;
        return null;
      },
    },
    preventDefault() { iconPrevented = true; },
    stopPropagation() { iconStopped = true; },
  });
  assert.equal(wrap.classList.contains('is-open'), true);
  assert.equal(iconPrevented, true);
  assert.equal(iconStopped, true);

  AdminInfoTip.hoist(document);

  let linkPrevented = false;
  let linkStopped = false;
  click.fn({
    target: {
      closest(sel) {
        if (sel === '.admin-info-tip__bubble') return bubble;
        if (sel === '.admin-info-tip-wrap') return wrap;
        if (sel === 'summary') return summary;
        if (sel === 'a') return link;
        if (sel === '.admin-info-tip') return null;
        return null;
      },
    },
    preventDefault() { linkPrevented = true; },
    stopPropagation() { linkStopped = true; },
  });
  assert.equal(linkPrevented, false, 'capture does not preventDefault on a bubble link');
  assert.equal(linkStopped, false, 'capture does not stop a bubble link');

  let wrapPrevented = false;
  let wrapStopped = false;
  wrap.dispatchEvent('click', {
    target: {
      closest(sel) {
        if (sel === '.admin-info-tip__bubble') return bubble;
        if (sel === '.admin-info-tip-wrap') return wrap;
        if (sel === 'summary') return summary;
        if (sel === 'a') return link;
        return null;
      },
    },
    preventDefault() { wrapPrevented = true; },
    stopPropagation() { wrapStopped = true; },
  });
  assert.equal(wrapStopped, true, 'wrap stops the click so <details> does not toggle');
  assert.equal(wrapPrevented, false, 'wrap does not cancel the link default (navigation)');
  assert.equal(details.open || false, false, 'details stays closed');

  const innerBtn = document.createElement('button');
  bubble.appendChild(innerBtn);
  let innerBtnFired = false;
  let innerStopped = false;
  innerBtn.addEventListener('click', function () { innerBtnFired = true; });
  click.fn({
    target: {
      closest(sel) {
        if (sel === '.admin-info-tip__bubble') return bubble;
        if (sel === '.admin-info-tip-wrap') return wrap;
        if (sel === 'summary') return summary;
        if (sel === '.admin-info-tip') return null;
        if (sel === 'a') return null;
        if (sel === 'button') return innerBtn;
        return null;
      },
    },
    preventDefault() {},
    stopPropagation() { innerStopped = true; },
  });
  if (!innerStopped) {
    innerBtn.dispatchEvent('click', {
      target: innerBtn,
      preventDefault() {},
      stopPropagation() {},
    });
  }
  assert.equal(innerStopped, false, 'capture does not stop a button inside a summary tip');
  assert.equal(innerBtnFired, true, 'a <button> inside a summary tip still receives click');

  let linkHandlerFired = false;
  link.addEventListener('click', function () { linkHandlerFired = true; });
  link.dispatchEvent('click', {
    target: link,
    preventDefault() {},
    stopPropagation() {},
  });
  assert.equal(linkHandlerFired, true, 'the link navigates / its own click handler fires');

  const down = listeners.document.mousedown.find((l) => l.opts === true || (l.opts && l.opts.capture));
  assert.ok(down, 'capture-phase mousedown listener');
  let downPrevented = false;
  down.fn({
    target: {
      closest(sel) {
        if (sel === '.admin-info-tip-wrap') return wrap;
        if (sel === 'a') return link;
        return null;
      },
    },
    preventDefault() { downPrevented = true; },
  });
  assert.equal(downPrevented, false, 'mousedown on a bubble link is not cancelled');
}

// --- JS label fallback: "More info" becomes "About {adjacent label}" ---
{
  const { document, body, AdminInfoTip } = createEnv();
  const row = document.createElement('span');
  row.className = 'admin-label-with-tip';
  const label = document.createElement('label');
  label.textContent = 'Owner rate (CAD$)';
  const { wrap, btn } = makeTip(document, 'admin-info-tip-fallback', 'ignored');
  btn.setAttribute('aria-label', 'More info');
  row.appendChild(label);
  row.appendChild(wrap);
  body.appendChild(row);
  AdminInfoTip.hoist(document);
  assert.equal(btn.getAttribute('aria-label'), 'About Owner rate (CAD$)');
}

// --- flip up when the full bubble does not fit below ---
{
  const { document, body, listeners } = createEnv();
  const { wrap, btn, bubble } = makeTip(document, 'admin-info-tip-flip', 'Owner rate');
  bubble._rect = { top: 0, bottom: 230, left: 0, right: 200, width: 200, height: 230 };
  btn._rect = { top: 600, bottom: 644, left: 40, right: 84, width: 44, height: 44 };
  body.appendChild(wrap);
  wrap.classList.add('is-open');
  btn.setAttribute('aria-expanded', 'true');
  listeners.window.resize[0].fn();
  const top = parseInt(bubble.style.top, 10);
  assert.ok(top <= 600 - 230 + 8, 'flips above when it cannot fully fit below, got ' + top);
  assert.notEqual(bubble.style.overflowY, 'auto');
}

// --- clamp with max-height when it fits neither above nor below ---
{
  const { document, body, listeners, window } = createEnv();
  window.innerHeight = 400;
  const { wrap, btn, bubble } = makeTip(document, 'admin-info-tip-clamp', 'Notes');
  bubble._rect = { top: 0, bottom: 280, left: 0, right: 200, width: 200, height: 280 };
  btn._rect = { top: 160, bottom: 204, left: 40, right: 84, width: 44, height: 44 };
  const bar = document.createElement('div');
  bar.className = 'br-sticky-total';
  bar._rect = { top: 348, bottom: 400, left: 0, right: 390, width: 390, height: 52 };
  body.appendChild(bar);
  body.appendChild(wrap);
  wrap.classList.add('is-open');
  btn.setAttribute('aria-expanded', 'true');
  listeners.window.resize[0].fn();
  assert.equal(bubble.style.overflowY, 'auto');
  assert.ok(parseInt(bubble.style.maxHeight, 10) > 0, 'clamps with a viewport max-height');
  const top = parseInt(bubble.style.top, 10);
  assert.ok(top >= 12, 'stays on-screen, got ' + top);
}

// --- flip/clamp stays above a fixed bottom bar even if the ⓘ sits on it ---
{
  const { document, body, listeners, window } = createEnv();
  window.innerHeight = 844;
  const { wrap, btn, bubble } = makeTip(document, 'admin-info-tip-sticky-flip', 'Existing condition notes');
  bubble._rect = { top: 0, bottom: 120, left: 0, right: 200, width: 200, height: 120 };
  btn._rect = { top: 800, bottom: 844, left: 40, right: 84, width: 44, height: 44 };
  const bar = document.createElement('div');
  bar.className = 'br-sticky-total';
  bar._rect = { top: 780, bottom: 844, left: 0, right: 390, width: 390, height: 64 };
  body.appendChild(bar);
  body.appendChild(wrap);
  wrap.classList.add('is-open');
  btn.setAttribute('aria-expanded', 'true');
  listeners.window.resize[0].fn();
  const top = parseInt(bubble.style.top, 10);
  const floor = 844 - (844 - 780 + 12);
  assert.ok(top + 120 <= floor + 1, 'bubble stays above the sticky bar, top=' + top + ' floor=' + floor);
  assert.ok(top >= 12, 'stays on-screen, got ' + top);
}

// --- left/right clamp and viewport width ---
{
  const { document, body, listeners, window } = createEnv();
  window.innerWidth = 390;
  window.innerHeight = 844;
  const { wrap, btn, bubble } = makeTip(document, 'admin-info-tip-clamp-x', 'Labour rate');
  bubble._rect = { top: 0, bottom: 80, left: 0, right: 273, width: 273, height: 80 };
  btn._rect = { top: 80, bottom: 124, left: -40, right: 4, width: 44, height: 44 };
  body.appendChild(wrap);
  wrap.classList.add('is-open');
  btn.setAttribute('aria-expanded', 'true');
  listeners.window.resize[0].fn();
  const left = parseInt(bubble.style.left, 10);
  assert.ok(left >= 12, 'left clamp keeps the bubble on-screen, got ' + left);
  assert.notEqual(bubble.style.left, '', 'left is set (removing the left clamp fails this)');
  assert.notEqual(left, -40, 'removing the left clamp leaves left at the button (−40)');
  const maxW = parseInt(bubble.style.maxWidth, 10);
  assert.ok(maxW <= 390 - 24, 'maxWidth respects viewport width, got ' + maxW);

  btn._rect = { top: 80, bottom: 124, left: 360, right: 404, width: 44, height: 44 };
  listeners.window.resize[0].fn();
  const rightLeft = parseInt(bubble.style.left, 10);
  assert.ok(rightLeft + 273 <= 390 - 12 + 1, 'right clamp keeps the bubble on-screen, got ' + rightLeft);
  assert.notEqual(String(rightLeft), '360', 'removing the right clamp leaves left at the button (360)');

  window.innerWidth = 200;
  btn._rect = { top: 80, bottom: 124, left: 40, right: 84, width: 44, height: 44 };
  listeners.window.resize[0].fn();
  const narrowMax = parseInt(bubble.style.maxWidth, 10);
  assert.ok(narrowMax <= 200 - 24, 'maxWidth uses viewport width (not a fixed 273), got ' + narrowMax);
  assert.ok(narrowMax < 273, 'ignoring the viewport width would keep maxWidth at 273');
}

// --- flip offset is relative to the button, not a huge negative ---
{
  const { document, body, listeners, window } = createEnv();
  window.innerHeight = 700;
  const { wrap, btn, bubble } = makeTip(document, 'admin-info-tip-flip-offset', 'Owner rate');
  bubble._rect = { top: 0, bottom: 230, left: 0, right: 200, width: 200, height: 230 };
  btn._rect = { top: 600, bottom: 644, left: 40, right: 84, width: 44, height: 44 };
  body.appendChild(wrap);
  wrap.classList.add('is-open');
  btn.setAttribute('aria-expanded', 'true');
  listeners.window.resize[0].fn();
  const top = parseInt(bubble.style.top, 10);
  assert.ok(top > 200, 'flip offset is not wildly negative (e.g. −300px), got ' + top);
  assert.ok(top !== -300, 'a wrong flip offset of −300px must fail');
  assert.ok(top <= 600 - 230 + 8, 'flips above the button, got ' + top);
}

// --- repeated millwork / standard cards get unique About labels ---
{
  const { document, body, AdminInfoTip, listeners } = createEnv();
  const piece = document.createElement('article');
  piece.setAttribute('data-mw-piece', '');
  const n = document.createElement('span');
  n.setAttribute('data-mw-piece-n', '');
  n.textContent = '2';
  const name = document.createElement('input');
  name.setAttribute('name', 'pieces[1][name]');
  name.value = 'Pantry';
  const { wrap, btn } = makeTip(document, 'admin-info-tip-piece', 'Type');
  piece.appendChild(n);
  piece.appendChild(name);
  piece.appendChild(wrap);
  body.appendChild(piece);
  AdminInfoTip.refreshRepeatLabels(piece);
  assert.equal(btn.getAttribute('aria-label'), 'About Type — Pantry');

  const card = document.createElement('article');
  card.className = 'sq-work-card';
  const num = document.createElement('span');
  num.className = 'sq-work-number-val';
  num.textContent = '3';
  const title = document.createElement('input');
  title.className = 'sq-work-title';
  title.value = '';
  const { wrap: w2, btn: b2 } = makeTip(document, 'admin-info-tip-job', 'Materials');
  card.appendChild(num);
  card.appendChild(title);
  card.appendChild(w2);
  body.appendChild(card);
  AdminInfoTip.refreshRepeatLabels(card);
  assert.equal(b2.getAttribute('aria-label'), 'About Materials — job 3');

  const { wrap: w3, btn: b3 } = makeTip(document, 'admin-info-tip-job-type', 'Type');
  card.appendChild(w3);
  AdminInfoTip.refreshRepeatLabels(card);
  assert.equal(b3.getAttribute('aria-label'), 'About Type — job 3', 'standard cards rename every tip, not only Materials/Labour/HST');

  title.value = 'Kitchen';
  const inputL = listeners.document.input && listeners.document.input[0];
  assert.ok(inputL, 'input listener updates names when a piece/job title is edited');
  inputL.fn({ target: title });
  assert.equal(b2.getAttribute('aria-label'), 'About Materials — Kitchen');
  assert.equal(b3.getAttribute('aria-label'), 'About Type — Kitchen');

  name.value = 'Wardrobe';
  inputL.fn({ target: name });
  assert.equal(btn.getAttribute('aria-label'), 'About Type — Wardrobe');
}

// --- M18/M22: glue last word; never extract from id'd / JS-updated spans ---
{
  const { document, body, AdminInfoTip } = createEnv();
  const row = document.createElement('span');
  row.className = 'admin-label-with-tip';
  const label = document.createElement('label');
  label.setAttribute('for', 'br-rooms');
  label.textContent = 'Finished rooms';
  const { wrap } = makeTip(document, 'admin-info-tip-glue', 'Finished rooms');
  row.appendChild(label);
  row.appendChild(wrap);
  body.appendChild(row);
  AdminInfoTip.hoist(document);
  const glued = row.querySelector('.admin-label-with-tip__end');
  assert.ok(glued, 'previous sibling is a label, so glue its last word with the tip');
  assert.equal(glued.contains(wrap), true);
  assert.match(String(glued.textContent || ''), /rooms/);
  assert.match(String(label.textContent || ''), /Finished/);
  assert.ok(!String(glued.textContent || '').includes('Finished rooms'), 'do not nowrap the whole label');

  const heading = document.createElement('h4');
  heading.appendChild(document.createTextNode('Finished rooms'));
  const htip = makeTip(document, 'admin-info-tip-glue-h', 'Finished rooms');
  heading.appendChild(htip.wrap);
  body.appendChild(heading);
  AdminInfoTip.hoist(document);
  const end = heading.querySelector('.admin-label-with-tip__end');
  assert.ok(end, 'M18: last text node before ⓘ is split');
  assert.equal(end.contains(htip.wrap), true);
  assert.match(String(end.textContent || ''), /rooms/);
}

{
  const { document, body, AdminInfoTip } = createEnv();
  const row = document.createElement('span');
  row.className = 'admin-label-with-tip';
  const label = document.createElement('label');
  label.setAttribute('for', 'br-lights');
  const span = document.createElement('span');
  span.id = 'br-lights-label';
  span.textContent = 'Pot lights';
  label.appendChild(span);
  const { wrap } = makeTip(document, 'admin-info-tip-lights', 'Ceiling lights');
  row.appendChild(label);
  row.appendChild(wrap);
  body.appendChild(row);
  AdminInfoTip.hoist(document);
  assert.equal(span.textContent, 'Pot lights', 'D2/M18: do not glue words out of #br-lights-label');
  const lightsEnd = row.querySelector('.admin-label-with-tip__end');
  assert.ok(lightsEnd && lightsEnd.contains(label) && lightsEnd.contains(wrap), 'wrap the label unit with the tip; do not split #br-lights-label');
  assert.equal(span.parentNode, label);
  span.textContent = 'Fixtures';
  assert.equal(span.textContent, 'Fixtures', 'D2: JS rewrite stays exact');
  span.textContent = 'Pot lights';
  assert.equal(span.textContent, 'Pot lights');
  assert.ok(!String(row.textContent || '').includes('Fixtures lights'));
  assert.ok(!String(row.textContent || '').includes('Pot lights lights'));
}

{
  const { document, body, AdminInfoTip } = createEnv();
  const row = document.createElement('span');
  row.className = 'admin-label-with-tip';
  const label = document.createElement('label');
  label.className = 'admin-checkbox';
  label.textContent = 'Sync prices after import';
  const { wrap } = makeTip(document, 'admin-info-tip-cb', 'Sync prices after import');
  row.appendChild(label);
  row.appendChild(wrap);
  body.appendChild(row);
  AdminInfoTip.hoist(document);
  assert.equal(row.querySelector('.admin-label-with-tip__end'), null, 'M19: glueLastWord skips checkboxes');
  assert.equal(label.textContent.replace(/\s+/g, ' ').trim(), 'Sync prices after import');
}

{
  const { document, body, AdminInfoTip } = createEnv();
  const row = document.createElement('span');
  row.className = 'admin-label-with-tip';
  const label = document.createElement('label');
  label.textContent = 'Secondary contact';
  const small = document.createElement('small');
  small.className = 'admin-form-help cp-inline-help';
  small.textContent = '(optional)';
  const { wrap } = makeTip(document, 'admin-info-tip-small', 'Secondary contact');
  row.appendChild(label);
  row.appendChild(small);
  row.appendChild(wrap);
  body.appendChild(row);
  AdminInfoTip.hoist(document);
  const end = row.querySelector('.admin-label-with-tip__end');
  assert.ok(end && end.contains(small) && end.contains(wrap), 'wrap preceding <small> with the tip');
  assert.equal(small.nextElementSibling, wrap);
  assert.match(String(label.textContent || ''), /Secondary contact/);
}

{
  const { document, body, AdminInfoTip } = createEnv();
  const heading = document.createElement('h4');
  heading.appendChild(document.createTextNode('Pricing engine — $/sq ft by area'));
  const { wrap } = makeTip(document, 'admin-info-tip-heading-glue', 'Pricing engine — $/sq ft by area');
  heading.appendChild(wrap);
  body.appendChild(heading);
  AdminInfoTip.hoist(document);
  const end = heading.querySelector('.admin-label-with-tip__end');
  assert.ok(end, 'heading last word is glued to the tip');
  assert.equal(end.contains(wrap), true);
  assert.match(String(end.textContent || ''), /area/);
  assert.match(String(heading.textContent || ''), /Pricing engine/);
  const copy = heading.querySelector('.admin-heading-copy');
  assert.ok(copy, 'heading text and glued end live in one .admin-heading-copy');
  assert.equal(copy.contains(end), true);
}

{
  const { document, body, AdminInfoTip } = createEnv();
  const summary = document.createElement('summary');
  summary.className = 'admin-accordion__summary';
  const chevron = document.createElement('span');
  chevron.className = 'admin-chevron-down';
  chevron.setAttribute('aria-hidden', 'true');
  const title = document.createElement('span');
  title.className = 'admin-accordion__title';
  title.appendChild(document.createTextNode('Client Email Message'));
  const { wrap } = makeTip(document, 'admin-info-tip-email-msg', 'Client Email Message');
  title.appendChild(wrap);
  summary.appendChild(chevron);
  summary.appendChild(title);
  const h5 = document.createElement('h5');
  h5.className = 'sq-intro-option-title';
  h5.appendChild(document.createTextNode('Client PDF line items'));
  const htip = makeTip(document, 'admin-info-tip-pdf-after-acc', 'Client PDF line items');
  h5.appendChild(htip.wrap);
  body.appendChild(summary);
  body.appendChild(h5);
  assert.doesNotThrow(() => AdminInfoTip.hoist(document));
  assert.equal(summary.firstElementChild, chevron, 'N2: chevron stays first');
  assert.ok(title.querySelector('.admin-label-with-tip__end'), 'last word glued inside the title');
  assert.match(String(title.querySelector('.admin-label-with-tip__end').textContent || ''), /Message/);
  assert.ok(!String(title.querySelector('.admin-label-with-tip__end').textContent || '').includes('Client Email'), 'N2: do not nowrap the whole title');
  assert.ok(title.querySelector('.admin-heading-copy'), 'title text stays in one inline heading-copy');
  assert.ok(h5.querySelector('.admin-label-with-tip__end'), 'later headings still glue the last word');
  assert.match(String(h5.querySelector('.admin-label-with-tip__end').textContent || ''), /items/);
  assert.match(String(h5.textContent || ''), /Client PDF line items/);
  assert.ok(!String(h5.textContent || '').includes('lineitems'), 'flex heading must keep the space');
}

{
  const { document, body, AdminInfoTip } = createEnv();
  const row = document.createElement('span');
  row.className = 'admin-label-with-tip';
  row.id = 'br-area-label';
  const lead = document.createElement('label');
  lead.setAttribute('for', 'br-area');
  lead.textContent = 'Total basement area (sq';
  const end = document.createElement('span');
  end.className = 'admin-label-with-tip__end';
  const last = document.createElement('label');
  last.setAttribute('for', 'br-area');
  last.textContent = 'ft)';
  const { wrap } = makeTip(document, 'admin-info-tip-area', 'Total basement area (sq ft)');
  end.appendChild(last);
  end.appendChild(wrap);
  row.appendChild(lead);
  row.appendChild(end);
  body.appendChild(row);
  const afterFull = AdminInfoTip.setLabelText(row, 'Total basement area (sq ft)');
  assert.equal(afterFull, 'Total basement area (sq ft)', 'D1: full basement label stays exact');
  assert.ok(row.contains(wrap), 'setLabelText must not destroy the tip');
  const afterBath = AdminInfoTip.setLabelText('br-area-label', 'Bathroom / work area (sq ft)');
  assert.equal(afterBath, 'Bathroom / work area (sq ft)', 'D1: bathroom preset label is exact');
  assert.equal(AdminInfoTip.labelCopy(row), 'Bathroom / work area (sq ft)');
  assert.ok(!String(afterBath).includes('ft) ft)'), 'no leftover ft) from the split lead');
  const afterWall = AdminInfoTip.setLabelText(row, 'Basement area (sq ft, optional)');
  assert.equal(afterWall, 'Basement area (sq ft, optional)', 'N6: wall preset last word is optional) not ft)');
  assert.equal(last.textContent, 'optional)');
  assert.ok(!String(last.textContent).includes('ft)'), 'N6: last word must change when the preset does');
  assert.equal(wrap.querySelector('.admin-info-tip').getAttribute('aria-label'), 'About Basement area (sq ft, optional)');
}

{
  const { document, body, AdminInfoTip } = createEnv();
  const legend = document.createElement('legend');
  legend.appendChild(document.createTextNode('Store name '));
  const star = document.createElement('span');
  star.className = 'cp-required';
  star.textContent = '*';
  legend.appendChild(star);
  const { wrap } = makeTip(document, 'admin-info-tip-required', 'Store name');
  legend.appendChild(wrap);
  body.appendChild(legend);
  AdminInfoTip.hoist(document);
  assert.equal(star.textContent, '*', 'N5: asterisk stays in span.cp-required');
  assert.equal(star.className, 'cp-required');
  const starEnd = legend.querySelector('.admin-label-with-tip__end');
  assert.ok(starEnd && starEnd.contains(star) && starEnd.contains(wrap), 'wrap * with the tip so they stay together');
  assert.equal(star.nextElementSibling, wrap, 'N4: * stays before the ⓘ');
  AdminInfoTip.hoist(document);
  assert.equal(wrap.parentNode, starEnd, 'second hoist must not pull the tip out of the glued end');
  const order = String(legend.textContent || '').replace(/\s+/g, ' ').trim();
  assert.match(order, /^Store name\s*\*/);
}

{
  const { document, body, AdminInfoTip } = createEnv();
  const row = document.createElement('span');
  row.className = 'admin-label-with-tip';
  const label = document.createElement('label');
  label.setAttribute('for', 'due');
  label.appendChild(document.createTextNode('Due date'));
  const star = document.createElement('span');
  star.className = 'cp-required';
  star.textContent = '*';
  const { wrap } = makeTip(document, 'admin-info-tip-due-star', 'Due date');
  row.appendChild(label);
  row.appendChild(star);
  row.appendChild(wrap);
  body.appendChild(row);
  const heading = document.createElement('h5');
  heading.className = 'sq-intro-option-title';
  heading.appendChild(document.createTextNode('Client PDF line items'));
  const htip = makeTip(document, 'admin-info-tip-pdf-heading', 'Client PDF line items');
  heading.appendChild(htip.wrap);
  body.appendChild(heading);
  assert.doesNotThrow(() => AdminInfoTip.hoist(document));
  assert.ok(heading.querySelector('.admin-label-with-tip__end'), 'heading last word still glues');
  assert.match(String(heading.querySelector('.admin-label-with-tip__end').textContent || ''), /items/);
  assert.equal(star.className, 'cp-required');
  assert.equal(star.textContent, '*');
  assert.equal(star.nextElementSibling, wrap, 'N1/N4: * stays immediately before the ⓘ');
  const starEnd = row.querySelector('.admin-label-with-tip__end');
  assert.ok(starEnd && starEnd.contains(star) && starEnd.contains(wrap), 'previous sibling * is glued with the tip');
  const order = String(row.textContent || '').replace(/\s+/g, ' ').trim();
  assert.match(order, /^Due date\s*\*/);
  assert.ok(!/^name \*/i.test(order) && !order.includes('NAME'), 'N1: do not reorder to NAME ⓘ *STORE');
}

{
  const { document, body, AdminInfoTip } = createEnv();
  const summary = document.createElement('summary');
  summary.className = 'admin-filters-accordion__summary';
  const title = document.createElement('span');
  title.className = 'admin-filters-accordion__title';
  title.appendChild(document.createTextNode('Filters'));
  const { wrap } = makeTip(document, 'admin-info-tip-filters', 'Filters');
  title.appendChild(wrap);
  const badge = document.createElement('span');
  badge.className = 'admin-filters-accordion__badge';
  badge.textContent = '3 active';
  summary.appendChild(title);
  summary.appendChild(badge);
  body.appendChild(summary);
  AdminInfoTip.hoist(document);
  assert.equal(title.nextElementSibling, badge, 'N3: badge stays after the title');
  assert.equal(badge.parentNode, summary);
  const end = title.querySelector('.admin-label-with-tip__end');
  assert.ok(end && end.contains(wrap), 'N3: ⓘ glues to Filters, not the badge');
  assert.match(String(end.textContent || ''), /Filters/);
  assert.ok(!String(end.textContent || '').includes('3 active'));
  const order = String(summary.textContent || '').replace(/\s+/g, ' ').trim();
  assert.match(order, /^Filters/);
  assert.match(order, /3 active$/);
}

{
  const { document, body, AdminInfoTip } = createEnv();
  const form = document.createElement('form');
  function pieceRow(index) {
    const row = document.createElement('div');
    row.setAttribute('data-mw-piece', '1');
    const input = document.createElement('input');
    input.setAttribute('type', 'checkbox');
    input.setAttribute('name', 'pieces[' + index + '][edge_banding]');
    input.id = 'pieces-0-edge_banding';
    const lab = document.createElement('label');
    lab.htmlFor = 'pieces-0-edge_banding';
    lab.setAttribute('for', 'pieces-0-edge_banding');
    lab.className = 'admin-checkbox__tail';
    lab.textContent = 'banding';
    row.appendChild(input);
    row.appendChild(lab);
    const { wrap } = makeTip(document, 'admin-info-tip-shared-cb', 'Edge banding');
    row.appendChild(wrap);
    return { row, input, lab };
  }
  const a = pieceRow(0);
  const b = pieceRow(1);
  form.appendChild(a.row);
  body.appendChild(form);
  body.appendChild(b.row);
  assert.equal(a.input.id, b.input.id);
  AdminInfoTip.refreshControlIds(b.row);
  assert.notEqual(b.input.id, a.input.id, 'M1: cloned checkbox gets its own id');
  assert.equal(b.input.id, 'pieces-1-edge_banding', 'id is built from the input name (fails if name is ignored)');
  assert.equal(b.lab.getAttribute('for'), b.input.id);
  assert.equal(a.lab.getAttribute('for'), a.input.id);
  assert.notEqual(b.row.querySelector('.admin-info-tip__bubble').id, a.row.querySelector('.admin-info-tip__bubble').id);
}

// --- M22: millwork totals bar is reserved like other sticky bars ---
{
  const { document, body, listeners, window } = createEnv();
  window.innerHeight = 844;
  const { wrap, btn, bubble } = makeTip(document, 'admin-info-tip-mw-bar', 'Pieces');
  bubble._rect = { top: 0, bottom: 120, left: 0, right: 200, width: 200, height: 120 };
  btn._rect = { top: 800, bottom: 844, left: 40, right: 84, width: 44, height: 44 };
  const bar = document.createElement('div');
  bar.className = 'mw-totals-bar';
  bar._rect = { top: 780, bottom: 844, left: 0, right: 390, width: 390, height: 64 };
  body.appendChild(bar);
  body.appendChild(wrap);
  wrap.classList.add('is-open');
  btn.setAttribute('aria-expanded', 'true');
  listeners.window.resize[0].fn();
  const top = parseInt(bubble.style.top, 10);
  const floor = 844 - (844 - 780 + 12);
  assert.ok(top + 120 <= floor + 1, 'M22: bubble stays above .mw-totals-bar, top=' + top + ' floor=' + floor);
}

console.log('admin-info-tip.test.mjs: ok');
