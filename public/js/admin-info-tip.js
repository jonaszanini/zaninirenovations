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

  function rootOf(el) {
    return el && el.closest ? el.closest('.admin-info-tip-wrap') : null;
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
    bubble.style.width = '';
  }

  function positionBubble(btn, bubble) {
    if (!btn || !bubble) return;
    var maxW = Math.min(MAX_W, Math.max(120, window.innerWidth - PAD * 2));
    bubble.style.position = 'fixed';
    bubble.style.maxWidth = maxW + 'px';
    bubble.style.width = 'max-content';
    bubble.style.right = 'auto';
    bubble.style.bottom = 'auto';
    bubble.style.top = '0px';
    bubble.style.left = '0px';

    var btnRect = btn.getBoundingClientRect();
    var popRect = bubble.getBoundingClientRect();
    var top = btnRect.bottom - 4;
    var left = btnRect.left;

    if (left + popRect.width > window.innerWidth - PAD) {
      left = window.innerWidth - PAD - popRect.width;
    }
    if (left < PAD) {
      left = PAD;
    }

    if (top + popRect.height > window.innerHeight - PAD) {
      var above = btnRect.top - popRect.height + 4;
      if (above >= PAD) {
        top = above;
      } else {
        top = Math.max(PAD, window.innerHeight - PAD - popRect.height);
      }
    }

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
  function html(text, label) {
    var id = newTipId();
    var aria = label ? ('About ' + String(label)) : 'More info';
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

  var scrollRaf = 0;
  function onScroll() {
    if (scrollRaf) return;
    var raf = window.requestAnimationFrame || function (fn) { return setTimeout(fn, 16); };
    scrollRaf = raf(function () {
      scrollRaf = 0;
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
  }

  function prepareAddedNode(node) {
    if (!node || node.nodeType !== 1) return;
    hoistFromLabels(node);
    refreshDuplicateIds(node);
  }

  document.addEventListener('mousedown', function (e) {
    if (e.target.closest && e.target.closest('.admin-info-tip-wrap')) {
      e.preventDefault();
    }
  }, true);

  document.addEventListener('click', function (e) {
    var root = rootOf(e.target);
    if (!root) {
      closeTips(null);
      return;
    }
    var inSummary = !!(e.target.closest && e.target.closest('summary'));
    if (e.target.closest('.admin-info-tip__bubble')) {
      if (inSummary) {
        e.preventDefault();
        e.stopPropagation();
      }
      return;
    }
    if (!e.target.closest('.admin-info-tip') && !e.target.closest('.admin-info-tip-wrap')) {
      return;
    }
    e.preventDefault();
    e.stopPropagation();
    toggleTip(root);
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

  window.addEventListener('resize', repositionOpenTips);

  document.addEventListener('scroll', onScroll, { capture: true, passive: true });

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
    closeAll: function () { closeTips(null); },
    positionOpen: repositionOpenTips,
  };
})(window);
