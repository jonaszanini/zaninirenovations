/**
 * Admin info-tip: click/tap ⓘ toggles a popover. Event delegation so
 * dynamically added rows work. No hover-only behaviour.
 *
 * Markup: wrap > button.admin-info-tip + sibling .admin-info-tip__bubble
 * (bubble is never nested inside the button).
 */
(function (global) {
  var OPEN = 'is-open';
  var PAD = 12;
  var MAX_W = 273;

  function eventElement(el) {
    if (!el) return null;
    if (el.nodeType === 3) return el.parentElement || el.parentNode || null;
    return el;
  }

  function closestFrom(el, sel) {
    var node = eventElement(el);
    return node && node.closest ? node.closest(sel) : null;
  }

  function rootOf(el) {
    return closestFrom(el, '.admin-info-tip-wrap');
  }

  function buttonOf(root) {
    return root ? root.querySelector(':scope > .admin-info-tip') : null;
  }

  function bubbleOf(root) {
    return root ? root.querySelector(':scope > .admin-info-tip__bubble') : null;
  }

  function wrapsIn(scope) {
    var root = scope && scope.querySelectorAll ? scope : document;
    var list = [];
    if (root.matches && root.matches('.admin-info-tip-wrap')) {
      list.push(root);
    }
    if (root.querySelectorAll) {
      root.querySelectorAll('.admin-info-tip-wrap').forEach(function (wrap) {
        list.push(wrap);
      });
    }
    return list;
  }

  function resetBubble(bubble) {
    if (!bubble) return;
    bubble.style.position = '';
    bubble.style.top = '';
    bubble.style.left = '';
    bubble.style.right = '';
    bubble.style.bottom = '';
    bubble.style.maxWidth = '';
    bubble.style.maxHeight = '';
    bubble.style.width = '';
    bubble.style.overflow = '';
    bubble.style.overflowY = '';
  }

  function bottomReserve() {
    var reserve = PAD;
    if (!document.querySelectorAll) return reserve;
    ['.br-sticky-total', '.br-sticky-bar', '.admin-sticky-footer', '.mw-totals-bar'].forEach(function (sel) {
      document.querySelectorAll(sel).forEach(function (el) {
        if (!el.getBoundingClientRect) return;
        var r = el.getBoundingClientRect();
        if (!r || r.height <= 0) return;
        if (r.bottom >= (window.innerHeight || 0) - 4) {
          reserve = Math.max(reserve, (window.innerHeight || 0) - r.top + PAD);
        }
      });
    });
    return reserve;
  }

  function positionBubble(btn, bubble) {
    if (!btn || !bubble) return;
    var maxW = Math.min(MAX_W, Math.max(120, Math.min(window.innerWidth - PAD * 2, window.innerWidth * 0.7)));
    bubble.style.position = 'fixed';
    bubble.style.maxWidth = maxW + 'px';
    bubble.style.maxHeight = '';
    bubble.style.overflowY = '';
    bubble.style.width = 'max-content';
    bubble.style.right = 'auto';
    bubble.style.bottom = 'auto';
    bubble.style.top = '0px';
    bubble.style.left = '0px';

    var btnRect = btn.getBoundingClientRect();
    var popRect = bubble.getBoundingClientRect();
    var floor = (window.innerHeight || 0) - bottomReserve();
    var preferBelow = btnRect.bottom - 4;
    var top = preferBelow;
    var left = btnRect.left;
    var need = popRect.height;
    var spaceBelow = floor - preferBelow;
    var aboveEdge = Math.min(btnRect.top, floor);
    var spaceAbove = aboveEdge - PAD;

    if (left + popRect.width > window.innerWidth - PAD) {
      left = window.innerWidth - PAD - popRect.width;
    }
    if (left < PAD) {
      left = PAD;
    }

    if (spaceBelow >= need) {
      top = preferBelow;
    } else if (spaceAbove >= need) {
      top = aboveEdge - need + 4;
    } else {
      var useAbove = spaceAbove > spaceBelow;
      var avail = Math.max(80, useAbove ? spaceAbove : Math.max(0, spaceBelow));
      avail = Math.min(Math.max(80, floor - PAD), Math.max(avail, 80));
      bubble.style.maxHeight = Math.floor(avail) + 'px';
      bubble.style.overflowY = 'auto';
      need = Math.min(need, avail);
      top = useAbove ? (aboveEdge - need + 4) : preferBelow;
    }

    top = Math.max(PAD, Math.min(top, floor - need));

    bubble.style.top = Math.round(top) + 'px';
    bubble.style.left = Math.round(left) + 'px';
  }

  function repositionOpenTips() {
    document.querySelectorAll('.admin-info-tip-wrap.' + OPEN).forEach(function (root) {
      positionBubble(buttonOf(root), bubbleOf(root));
    });
  }

  function closeTip(root) {
    if (!root) return;
    var btn = buttonOf(root);
    root.classList.remove(OPEN);
    if (btn) {
      btn.setAttribute('aria-expanded', 'false');
    }
    resetBubble(bubbleOf(root));
  }

  function closeTips(exceptRoot) {
    document.querySelectorAll('.admin-info-tip-wrap.' + OPEN).forEach(function (root) {
      if (root !== exceptRoot) {
        closeTip(root);
      }
    });
  }

  function openTip(root) {
    var btn = buttonOf(root);
    var bubble = bubbleOf(root);
    if (!btn || !bubble) return;
    root.classList.add(OPEN);
    btn.setAttribute('aria-expanded', 'true');
    positionBubble(btn, bubble);
  }

  function toggleTip(root) {
    var btn = buttonOf(root);
    var open = btn && btn.getAttribute('aria-expanded') === 'true';
    closeTips(root);
    if (!open) {
      openTip(root);
    } else {
      closeTip(root);
    }
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function newTipId() {
    return 'admin-info-tip-' + Math.random().toString(36).slice(2, 12);
  }

  /**
   * Markup for JS-rendered rows (fence run cards, lumber tools, etc.).
   * text is escaped. label becomes aria-label="About {label}".
   */
  function aboutLabel(label) {
    var t = String(label == null ? '' : label).replace(/^\s*About\s+/i, '').trim();
    return t ? ('About ' + t) : 'More info';
  }

  function html(text, label) {
    var id = newTipId();
    var aria = aboutLabel(label);
    return (
      '<span class="admin-info-tip-wrap">' +
        '<button type="button" class="admin-info-tip" aria-label="' + escapeHtml(aria) + '" aria-expanded="false" aria-controls="' + id + '" aria-describedby="' + id + '">' +
          '<i class="fa fa-info-circle" aria-hidden="true"></i>' +
        '</button>' +
        '<span id="' + id + '" class="admin-info-tip__bubble" role="tooltip">' + escapeHtml(text) + '</span>' +
      '</span>'
    );
  }

  /**
   * Give every tip in scope a fresh bubble id and matching aria-controls /
   * aria-describedby. Call after cloneNode / template insert so uniqid
   * attributes from the source row are not reused.
   */
  function refreshIds(scope) {
    wrapsIn(scope).forEach(function (wrap) {
      var btn = buttonOf(wrap);
      var bubble = bubbleOf(wrap);
      if (!btn || !bubble) return;
      var id = newTipId();
      bubble.id = id;
      btn.setAttribute('aria-controls', id);
      btn.setAttribute('aria-describedby', id);
    });
    return wrapsIn(scope).length;
  }

  function idsAlreadyInDocument(exceptScope) {
    var seen = Object.create(null);
    if (!document.querySelectorAll) return seen;
    document.querySelectorAll('.admin-info-tip__bubble[id]').forEach(function (bubble) {
      if (!bubble.id) return;
      if (exceptScope && exceptScope.nodeType === 1 && exceptScope.contains(bubble)) {
        return;
      }
      seen[bubble.id] = true;
    });
    return seen;
  }

  function refreshDuplicateIds(scope) {
    var seen = idsAlreadyInDocument(scope);
    wrapsIn(scope).forEach(function (wrap) {
      var btn = buttonOf(wrap);
      var bubble = bubbleOf(wrap);
      if (!btn || !bubble) return;
      var id = bubble.id || '';
      if (!id || seen[id]) {
        var next = newTipId();
        bubble.id = next;
        btn.setAttribute('aria-controls', next);
        btn.setAttribute('aria-describedby', next);
        seen[next] = true;
        return;
      }
      seen[id] = true;
    });
  }

  function cssEscapeId(id) {
    if (global.CSS && typeof global.CSS.escape === 'function') {
      return global.CSS.escape(id);
    }
    return String(id).replace(/[^a-zA-Z0-9_-]/g, '\\$&');
  }

  function idFromControlName(name) {
    if (!name) return '';
    return String(name).replace(/[^A-Za-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '');
  }

  function collectScoped(scope, sel) {
    var root = scope && scope.querySelectorAll ? scope : document;
    var list = [];
    if (root.matches && root.matches(sel)) {
      list.push(root);
    }
    if (root.querySelectorAll) {
      root.querySelectorAll(sel).forEach(function (el) {
        list.push(el);
      });
    }
    return list;
  }

  function rewriteLabelsFor(scope, oldId, nextId) {
    if (!oldId || !nextId || oldId === nextId) return;
    var sel = 'label[for="' + cssEscapeId(oldId) + '"]';
    collectScoped(scope, sel).forEach(function (lab) {
      lab.htmlFor = nextId;
      if (lab.setAttribute) lab.setAttribute('for', nextId);
    });
    if (document.querySelectorAll && scope && scope.nodeType === 1) {
      document.querySelectorAll(sel).forEach(function (lab) {
        if (scope.contains && !scope.contains(lab) && lab.htmlFor === oldId) {
          return;
        }
        if (lab.htmlFor === oldId || (lab.getAttribute && lab.getAttribute('for') === oldId)) {
          lab.htmlFor = nextId;
          if (lab.setAttribute) lab.setAttribute('for', nextId);
        }
      });
    }
  }

  function uniquifyTipLabelIds(scope) {
    if (!scope || scope.nodeType !== 1) return;
    var seen = Object.create(null);
    if (document.querySelectorAll) {
      document.querySelectorAll('[id^="admin-tip-lab-"]').forEach(function (el) {
        if (!el.id) return;
        if (scope.contains && scope.contains(el)) {
          return;
        }
        seen[el.id] = true;
      });
    }
    var map = Object.create(null);
    collectScoped(scope, '[id^="admin-tip-lab-"]').forEach(function (el) {
      var oldId = el.id || '';
      if (!oldId) return;
      var clash = seen[oldId] || (document.getElementById && document.getElementById(oldId) && document.getElementById(oldId) !== el);
      if (!clash) {
        seen[oldId] = true;
        return;
      }
      var next = 'admin-tip-lab-' + Math.random().toString(36).slice(2, 10);
      while (seen[next] || (document.getElementById && document.getElementById(next) && document.getElementById(next) !== el)) {
        next = 'admin-tip-lab-' + Math.random().toString(36).slice(2, 10);
      }
      map[oldId] = next;
      el.id = next;
      if (el.setAttribute) el.setAttribute('id', next);
      seen[next] = true;
    });
    collectScoped(scope, '[aria-labelledby]').forEach(function (el) {
      var raw = el.getAttribute('aria-labelledby') || '';
      if (!raw) return;
      var changed = false;
      var next = raw.split(/\s+/).map(function (id) {
        if (map[id]) {
          changed = true;
          return map[id];
        }
        return id;
      }).filter(Boolean).join(' ');
      if (changed) el.setAttribute('aria-labelledby', next);
    });
  }

  function uniquifyControlIds(scope) {
    var seen = Object.create(null);
    var scoped = !!(scope && scope.nodeType === 1);
    if (scoped && document.querySelectorAll) {
      document.querySelectorAll('input[id], select[id], textarea[id]').forEach(function (el) {
        if (!el.id) return;
        if (scope.contains && scope.contains(el)) {
          return;
        }
        seen[el.id] = true;
      });
    }
    collectScoped(scope, 'input[id], select[id], textarea[id]').forEach(function (input) {
      var oldId = input.id || '';
      if (!oldId) return;
      var clash = seen[oldId] || (document.getElementById && document.getElementById(oldId) && document.getElementById(oldId) !== input);
      if (!clash) {
        seen[oldId] = true;
        return;
      }
      var next = idFromControlName(input.getAttribute && input.getAttribute('name')) || (oldId + '-' + Math.random().toString(36).slice(2, 8));
      if (!next || seen[next] || (document.getElementById && document.getElementById(next) && document.getElementById(next) !== input)) {
        next = (next || oldId) + '-' + Math.random().toString(36).slice(2, 8);
      }
      input.id = next;
      if (input.setAttribute) input.setAttribute('id', next);
      rewriteLabelsFor(scope, oldId, next);
      seen[next] = true;
    });
  }

  /**
   * After cloneNode / template insert: unique tip bubble ids AND unique
   * input/select/textarea ids with matching label[for]. Requires an
   * element scope — never rewrite the live document when called bare.
   */
  function refreshControlIds(scope) {
    if (!scope || scope.nodeType !== 1) {
      if (typeof console !== 'undefined' && console.warn) {
        console.warn('AdminInfoTip.refreshControlIds requires an element scope');
      }
      return 0;
    }
    refreshIds(scope);
    uniquifyControlIds(scope);
    uniquifyTipLabelIds(scope);
    return wrapsIn(scope).length;
  }

  function isIconVisibleInClip(btn) {
    if (!btn || typeof btn.getBoundingClientRect !== 'function') return false;
    var rect = btn.getBoundingClientRect();
    var vw = window.innerWidth || 0;
    var vh = window.innerHeight || 0;
    if (rect.bottom < 0 || rect.top > vh || rect.right < 0 || rect.left > vw) {
      return false;
    }
    var el = btn.parentElement;
    var view = window;
    while (el && el !== document.body && el !== document.documentElement) {
      var style = view.getComputedStyle ? view.getComputedStyle(el) : null;
      if (style) {
        var overflow = String(style.overflow + ' ' + style.overflowX + ' ' + style.overflowY);
        if (/(auto|scroll|hidden)/.test(overflow)) {
          var crect = el.getBoundingClientRect();
          if (rect.bottom < crect.top || rect.top > crect.bottom || rect.right < crect.left || rect.left > crect.right) {
            return false;
          }
        }
      }
      el = el.parentElement;
    }
    return true;
  }

  var moveRaf = 0;
  function onViewportMove() {
    if (moveRaf) return;
    var raf = window.requestAnimationFrame || function (fn) { return setTimeout(fn, 16); };
    moveRaf = raf(function () {
      moveRaf = 0;
      document.querySelectorAll('.admin-info-tip-wrap.' + OPEN).forEach(function (root) {
        var btn = buttonOf(root);
        if (!isIconVisibleInClip(btn)) {
          closeTip(root);
          return;
        }
        positionBubble(btn, bubbleOf(root));
      });
    });
  }

  function labelTextNear(wrap) {
    var row = wrap.closest ? wrap.closest('.admin-label-with-tip') : null;
    var el = row
      ? row.querySelector('label, legend, h2, h3, h4, h5, strong, .admin-form-group-label, .mw-piece__sets-title')
      : null;
    if (!el) {
      var prev = wrap.previousElementSibling;
      if (prev && /^(LABEL|LEGEND|H2|H3|H4|H5|STRONG|SPAN)$/.test(prev.tagName)) {
        el = prev;
      }
    }
    if (!el) return '';
    var text = String(el.textContent || '').replace(/\s+/g, ' ').trim();
    if (text.length > 80) {
      text = text.slice(0, 77) + '…';
    }
    return text;
  }

  function deriveMissingLabels(scope) {
    wrapsIn(scope).forEach(function (wrap) {
      var btn = buttonOf(wrap);
      if (!btn) return;
      var current = btn.getAttribute('aria-label');
      if (current && current !== 'More info') return;
      var text = labelTextNear(wrap);
      if (text) {
        btn.setAttribute('aria-label', aboutLabel(text));
      }
    });
  }

  /**
   * Fallback: move tips that were nested in <label for> / wrapping labels
   * so the ⓘ is not part of the input's accessible name. Preferred markup
   * is already a sibling via .admin-label-with-tip (form_field / checkbox).
   */
  function hoistFromLabels(scope) {
    var root = scope && scope.querySelectorAll ? scope : document;
    root.querySelectorAll('label .admin-info-tip-wrap').forEach(function (wrap) {
      var label = wrap.closest('label');
      if (!label || !label.parentNode) return;
      var control = label.control || (label.htmlFor ? document.getElementById(label.htmlFor) : null);
      if (!control && !label.querySelector('input, select, textarea')) {
        return;
      }
      if (label.parentNode.classList.contains('admin-label-with-tip')) {
        label.parentNode.appendChild(wrap);
        return;
      }
      var row = document.createElement('span');
      row.className = 'admin-label-with-tip';
      label.parentNode.insertBefore(row, label);
      row.appendChild(label);
      row.appendChild(wrap);
    });
    deriveMissingLabels(root);
    glueLastWord(root);
    bindSummaryGuard(root);
    refreshRepeatLabels(root);
  }

  function bindSummaryGuard(scope) {
    wrapsIn(scope).forEach(function (wrap) {
      if (wrap.getAttribute('data-tip-summary-bound') === '1') return;
      wrap.setAttribute('data-tip-summary-bound', '1');
      wrap.addEventListener('click', function (e) {
        if (!wrap.closest || !wrap.closest('summary')) return;
        if (!closestFrom(e.target, '.admin-info-tip__bubble')) return;
        e.stopPropagation();
        if (!isLinkTarget(e.target)) {
          e.preventDefault();
        }
      });
    });
  }

  function refreshRepeatLabels(scope) {
    wrapsIn(scope).forEach(function (wrap) {
      var btn = buttonOf(wrap);
      if (!btn) return;
      var current = btn.getAttribute('aria-label') || '';
      if (current.indexOf('About ') !== 0) return;
      var piece = wrap.closest ? wrap.closest('[data-mw-piece]') : null;
      if (piece) {
        var nEl = piece.querySelector('[data-mw-piece-n]');
        var nameInput = piece.querySelector('input[name$="[name]"]');
        var name = nameInput && String(nameInput.value || '').trim();
        var suffix = name || ('piece ' + ((nEl && nEl.textContent) || '1'));
        var base = current.slice(6).replace(/\s+[—-]\s+.*$/, '');
        btn.setAttribute('aria-label', 'About ' + base + ' — ' + suffix);
        return;
      }
      var card = wrap.closest ? wrap.closest('.sq-work-card') : null;
      if (card) {
        var num = card.querySelector('.sq-work-number-val');
        var title = card.querySelector('.sq-work-title');
        var suffix = (title && String(title.value || '').trim()) || ('job ' + ((num && num.textContent) || '1'));
        var base = current.slice(6).replace(/\s+[—-]\s+.*$/, '');
        btn.setAttribute('aria-label', 'About ' + base + ' — ' + suffix);
      }
    });
  }

  function prepareAddedNode(node) {
    if (!node || node.nodeType !== 1) return;
    hoistFromLabels(node);
    refreshDuplicateIds(node);
    uniquifyControlIds(node);
    deriveMissingLabels(node);
    bindSummaryGuard(node);
    refreshRepeatLabels(node);
  }

  function isLinkTarget(el) {
    return !!closestFrom(el, 'a');
  }

  function previousNonEmptyNode(el) {
    var n = el ? el.previousSibling : null;
    while (n && n.nodeType === 3 && !String(n.textContent || '').trim()) {
      n = n.previousSibling;
    }
    return n;
  }

  function isHeadingChrome(el) {
    if (!el || el.nodeType !== 1 || !el.classList) return false;
    return el.classList.contains('br-acc__num')
      || el.classList.contains('admin-chevron-down')
      || el.classList.contains('admin-filters-accordion__badge')
      || el.classList.contains('admin-filters-accordion__icon');
  }

  function isGlueSkipElement(el) {
    if (!el || el.nodeType !== 1 || !el.classList) return true;
    if (isHeadingChrome(el)) return true;
    if (el.classList.contains('admin-info-tip-wrap')) return true;
    if (el.classList.contains('admin-label-with-tip__end')) return true;
    if (el.classList.contains('admin-heading-copy')) return true;
    if (el.classList.contains('admin-checkbox')) return true;
    return false;
  }

  function lastUnlockedTextNode(el) {
    var found = null;
    function walk(node) {
      if (!node) return;
      if (node.nodeType === 1) {
        if (node !== el && node.id) return;
        if (node.classList && (
          node.classList.contains('admin-info-tip-wrap')
          || node.classList.contains('admin-label-with-tip__end')
        )) return;
        var kids = node.childNodes || [];
        for (var i = 0; i < kids.length; i++) walk(kids[i]);
        return;
      }
      if (node.nodeType === 3 && String(node.textContent || '').replace(/\s+/g, ' ').trim()) {
        found = node;
      }
    }
    walk(el);
    return found;
  }

  function splitTextNodeLastWord(textNode) {
    if (!textNode || textNode.nodeType !== 3) return '';
    var raw = String(textNode.textContent || '');
    var trimmed = raw.replace(/\s+$/g, '');
    if (!trimmed) return '';
    var space = trimmed.lastIndexOf(' ');
    var last = space === -1 ? trimmed : trimmed.slice(space + 1);
    var keep = space === -1
      ? raw.slice(trimmed.length)
      : (trimmed.slice(0, space + 1) + raw.slice(trimmed.length));
    if (!last) return '';
    textNode.textContent = keep;
    return last;
  }

  function makeEnd(extraClass) {
    var end = document.createElement('span');
    end.className = extraClass || 'admin-label-with-tip__end';
    return end;
  }

  function styleSource(node) {
    var el = node;
    if (el && el.nodeType === 3) {
      el = el.parentElement || el.parentNode;
    }
    while (el && el.nodeType === 1 && el.classList && (
      el.classList.contains('admin-heading-copy')
      || el.classList.contains('admin-label-with-tip__end')
    )) {
      el = el.parentElement;
    }
    return el && el.nodeType === 1 ? el : null;
  }

  function copyVisibleClasses(word, src) {
    if (!word || !src || !src.classList) return;
    if (/^H[1-6]$/.test(src.tagName) || src.tagName === 'SUMMARY' || src.tagName === 'LEGEND') {
      return;
    }
    var skip = {
      'admin-heading-copy': 1,
      'admin-label-with-tip': 1,
      'admin-label-with-tip__end': 1,
      'admin-info-tip-wrap': 1,
      'admin-checkbox': 1,
      'br-acc__summary': 1,
      'admin-accordion__summary': 1,
      'admin-filters-accordion__summary': 1,
      'sp-heading': 1,
    };
    var kept = [];
    String(src.className || '').split(/\s+/).forEach(function (cls) {
      if (cls && !skip[cls]) kept.push(cls);
    });
    if (kept.length) {
      word.className = (String(word.className || '').trim() + ' ' + kept.join(' ')).trim();
    }
  }

  function ensureLabelId(el) {
    if (!el) return '';
    if (el.id) return el.id;
    el.id = 'admin-tip-lab-' + Math.random().toString(36).slice(2, 10);
    return el.id;
  }

  function linkSplitLabels(leadLabel, tailLabel) {
    if (!leadLabel || !tailLabel) return;
    var forId = tailLabel.htmlFor || (tailLabel.getAttribute && tailLabel.getAttribute('for'))
      || leadLabel.htmlFor || (leadLabel.getAttribute && leadLabel.getAttribute('for')) || '';
    var input = forId && document.getElementById ? document.getElementById(forId) : null;
    if (!input || !input.setAttribute) return;
    var ids = [];
    function take(el) {
      var id = ensureLabelId(el);
      if (id && ids.indexOf(id) === -1) ids.push(id);
    }
    take(leadLabel);
    take(tailLabel);
    String(input.getAttribute('aria-labelledby') || '').split(/\s+/).forEach(function (id) {
      if (id && ids.indexOf(id) === -1) ids.push(id);
    });
    input.setAttribute('aria-labelledby', ids.join(' '));
  }

  function makeWordEl(last, source) {
    var src = styleSource(source);
    var forId = '';
    if (src && src.tagName === 'LABEL' && !isGlueSkipElement(src)) {
      forId = src.htmlFor || (src.getAttribute && src.getAttribute('for')) || '';
    }
    var el;
    if (forId) {
      el = document.createElement('label');
      el.htmlFor = forId;
      if (el.setAttribute) el.setAttribute('for', forId);
      el.className = 'admin-label-with-tip__tail';
      copyVisibleClasses(el, src);
      linkSplitLabels(src, el);
    } else {
      el = document.createElement('span');
      copyVisibleClasses(el, src);
    }
    el.textContent = last;
    if (String(last).trim() === '*' || (src && src.classList && src.classList.contains('cp-required'))) {
      var cls = String(el.className || '');
      if (cls.indexOf('cp-required') === -1) {
        el.className = (cls + ' cp-required').trim();
      }
    }
    return el;
  }

  function wrapWordAndTip(tip, last, extraClass, source) {
    if (!tip || !tip.parentNode || !last) return null;
    var end = makeEnd(extraClass);
    var word = makeWordEl(last, source);
    end.appendChild(word);
    tip.parentNode.insertBefore(end, tip);
    end.appendChild(tip);
    return end;
  }

  /**
   * Wrap the preceding inline element together with the tip. Visual order
   * stays the same — never reorder siblings (chevron, badge, lead label).
   */
  function wrapElementAndTip(unit, tip, extraClass) {
    if (!unit || !tip || unit === tip) return null;
    if (unit.contains && unit.contains(tip)) return null;
    var parent = tip.parentNode;
    if (!parent || unit.parentNode !== parent || !parent.insertBefore) return null;
    var end = makeEnd(extraClass);
    parent.insertBefore(end, unit);
    end.appendChild(unit);
    end.appendChild(tip);
    return end;
  }

  /**
   * Glue the ⓘ to the last text word or the preceding inline element
   * (*, small, label, span) via a nowrap span. Never reorder children.
   */
  function glueLastTextNode(tip, extraClass) {
    if (!tip || !tip.parentNode) return null;
    if (tip.parentNode.classList && tip.parentNode.classList.contains('admin-label-with-tip__end')) {
      return null;
    }
    extraClass = extraClass || 'admin-label-with-tip__end';
    var prev = previousNonEmptyNode(tip);
    if (!prev) return null;
    if (prev.nodeType === 3) {
      var last = splitTextNodeLastWord(prev);
      return last ? wrapWordAndTip(tip, last, extraClass, prev) : null;
    }
    if (prev.nodeType !== 1 || isGlueSkipElement(prev)) return null;
    var textNode = lastUnlockedTextNode(prev);
    if (textNode) {
      var whole = String(prev.textContent || '').replace(/\s+/g, ' ').trim();
      var raw = String(textNode.textContent || '');
      var trimmed = raw.replace(/\s+$/g, '');
      var space = trimmed.lastIndexOf(' ');
      var lastWord = space === -1 ? trimmed : trimmed.slice(space + 1);
      if (lastWord && lastWord !== whole) {
        var word = splitTextNodeLastWord(textNode);
        if (word) return wrapWordAndTip(tip, word, extraClass, prev);
      }
    }
    return wrapElementAndTip(prev, tip, extraClass);
  }

  function wrapHeadingCopy(heading, tip) {
    if (!heading || !tip) return heading;
    var existing = heading.querySelector ? heading.querySelector(':scope > .admin-heading-copy') : null;
    if (existing) {
      if (tip.parentNode !== existing && !(existing.contains && existing.contains(tip))) {
        existing.appendChild(tip);
      }
      return existing;
    }
    var copy = document.createElement('span');
    copy.className = 'admin-heading-copy';
    var nodes = [];
    var kids = heading.childNodes || [];
    for (var i = 0; i < kids.length; i++) nodes.push(kids[i]);
    var placed = false;
    nodes.forEach(function (n) {
      if (n.nodeType === 1 && isHeadingChrome(n)) return;
      if (!placed) {
        heading.insertBefore(copy, n);
        placed = true;
      }
      copy.appendChild(n);
    });
    if (!copy.parentNode) heading.appendChild(copy);
    return copy;
  }

  function splitLabelText(label) {
    label = String(label || '').replace(/\s+/g, ' ').trim();
    if (!label) return { lead: '', last: '' };
    var pos = label.lastIndexOf(' ');
    if (pos === -1) return { lead: '', last: label };
    return { lead: label.slice(0, pos), last: label.slice(pos + 1) };
  }

  function setElementPlainText(el, text) {
    if (!el) return;
    var kids = el.childNodes || [];
    var textNodes = [];
    for (var i = 0; i < kids.length; i++) {
      if (kids[i].nodeType === 3) textNodes.push(kids[i]);
    }
    if (textNodes.length) {
      textNodes[0].textContent = text;
      for (var j = 1; j < textNodes.length; j++) textNodes[j].textContent = '';
      return;
    }
    if (el.ownerDocument && el.ownerDocument.createTextNode) {
      el.appendChild(el.ownerDocument.createTextNode(text));
      return;
    }
    el.textContent = text;
  }

  function labelCopy(el) {
    if (!el) return '';
    var parts = [];
    var labels = el.querySelectorAll ? el.querySelectorAll('label') : [];
    for (var i = 0; i < labels.length; i++) {
      var lab = labels[i];
      if (lab.closest && lab.closest('.admin-info-tip-wrap')) continue;
      var t = String(lab.textContent || '').replace(/\s+/g, ' ').trim();
      if (t) parts.push(t);
    }
    if (parts.length) return parts.join(' ');
    return String(el.textContent || '').replace(/\s+/g, ' ').trim();
  }

  /**
   * Rewrite a tipped label (id on the .admin-label-with-tip wrapper) without
   * destroying the ⓘ. Used by applyScope for #br-area-label.
   */
  function setLabelText(el, text) {
    if (typeof el === 'string') {
      el = document.getElementById(el);
    }
    if (!el) return '';
    var parts = splitLabelText(text);
    var end = el.querySelector ? el.querySelector('.admin-label-with-tip__end') : null;
    var lastLab = end ? end.querySelector('label') : null;
    var leadLab = null;
    var labels = el.querySelectorAll ? el.querySelectorAll('label') : [];
    for (var i = 0; i < labels.length; i++) {
      var lab = labels[i];
      if (lab === lastLab) continue;
      if (lab.classList && (lab.classList.contains('admin-checkbox') || lab.classList.contains('admin-checkbox__tail'))) continue;
      leadLab = lab;
      break;
    }
    if (leadLab && lastLab) {
      if (parts.lead) {
        setElementPlainText(leadLab, parts.lead);
        lastLab.textContent = parts.last;
      } else {
        setElementPlainText(leadLab, '');
        lastLab.textContent = parts.last;
      }
    } else if (lastLab) {
      lastLab.textContent = text;
    } else if (leadLab) {
      setElementPlainText(leadLab, text);
    }
    var btn = el.querySelector ? el.querySelector('.admin-info-tip') : null;
    if (btn) {
      var aria = String(text || '').replace(/\s+/g, ' ').trim();
      if (aria) btn.setAttribute('aria-label', aboutLabel(aria));
    }
    return labelCopy(el);
  }

  function glueLastWord(scope) {
    var root = scope && scope.querySelectorAll ? scope : document;
    if (!root.querySelectorAll) return;
    wrapsIn(root).forEach(function (tip) {
      if (tip.closest && tip.closest('label.admin-checkbox, .admin-checkbox-row > .admin-label-with-tip__end')) {
        return;
      }
      var heading = tip.closest && tip.closest(
        'h1, h2, h3, h4, h5, summary, legend, .br-acc__summary, .admin-accordion__title, .admin-filters-accordion__title, .cp-heading-sm, .cp-heading-md, .q-additional-costs-card__title, .sp-heading, .sq-intro-option-title'
      );
      if (heading) {
        wrapHeadingCopy(heading, tip);
      }
      if (heading && heading.classList && heading.classList.contains('q-additional-costs-card__title')) {
        return;
      }
      glueLastTextNode(tip, 'admin-label-with-tip__end');
    });
  }

  document.addEventListener('mousedown', function (e) {
    if (!closestFrom(e.target, '.admin-info-tip-wrap')) return;
    if (isLinkTarget(e.target)) return;
    e.preventDefault();
  }, true);

  document.addEventListener('click', function (e) {
    var root = rootOf(e.target);
    if (!root) {
      closeTips(null);
      return;
    }
    if (closestFrom(e.target, '.admin-info-tip__bubble')) {
      return;
    }
    if (!closestFrom(e.target, '.admin-info-tip')) {
      return;
    }
    e.preventDefault();
    e.stopPropagation();
    toggleTip(root);
  }, true);

  document.addEventListener('input', function (e) {
    var t = eventElement(e.target);
    if (!t || !t.closest) return;
    var fieldName = t.getAttribute && t.getAttribute('name');
    if (t.tagName === 'INPUT' && fieldName && /\[name\]$/.test(fieldName)) {
      var piece = t.closest('[data-mw-piece]');
      if (piece) {
        refreshRepeatLabels(piece);
        return;
      }
    }
    if (t.classList && t.classList.contains('sq-work-title')) {
      var card = t.closest('.sq-work-card');
      if (card) refreshRepeatLabels(card);
    }
  });

  document.addEventListener('focusout', function (e) {
    var root = rootOf(e.target);
    if (!root || !root.classList.contains(OPEN)) return;
    var next = e.relatedTarget;
    if (next && root.contains(next)) return;
    closeTip(root);
  }, true);

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var active = document.activeElement;
    var fromRoot = rootOf(active);
    closeTips(null);
    if (fromRoot) {
      var btn = buttonOf(fromRoot);
      if (btn && typeof btn.focus === 'function') {
        btn.focus();
      }
    }
  });

  window.addEventListener('resize', onViewportMove);

  document.addEventListener('scroll', onViewportMove, { capture: true, passive: true });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { hoistFromLabels(document); });
  } else {
    hoistFromLabels(document);
  }

  if (typeof MutationObserver === 'function' && document.documentElement) {
    var mo = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        mutation.addedNodes.forEach(prepareAddedNode);
      });
    });
    function observe() {
      if (document.body) {
        mo.observe(document.body, { childList: true, subtree: true });
      }
    }
    if (document.body) {
      observe();
    } else {
      document.addEventListener('DOMContentLoaded', observe);
    }
  }

  global.AdminInfoTip = {
    html: html,
    hoist: hoistFromLabels,
    refreshIds: refreshIds,
    refreshControlIds: refreshControlIds,
    refreshRepeatLabels: refreshRepeatLabels,
    setLabelText: setLabelText,
    labelCopy: labelCopy,
    closeAll: function () { closeTips(null); },
    positionOpen: repositionOpenTips,
  };
})(window);
