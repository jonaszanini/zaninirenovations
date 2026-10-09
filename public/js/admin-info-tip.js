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

  /**
   * Markup for JS-rendered rows (fence run cards, etc.).
   * text is escaped. label becomes aria-label="About {label}".
   */
  function html(text, label) {
    var id = 'admin-info-tip-' + Math.random().toString(36).slice(2, 12);
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
   * Move tips that were nested in <label for> / wrapping labels out so the
   * ⓘ is not part of the input's accessible name.
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

  document.addEventListener('mousedown', function (e) {
    if (e.target.closest && e.target.closest('.admin-info-tip')) {
      e.preventDefault();
    }
  }, true);

  document.addEventListener('click', function (e) {
    var root = rootOf(e.target);
    if (!root) {
      closeTips(null);
      return;
    }
    if (e.target.closest('.admin-info-tip__bubble')) {
      return;
    }
    if (!e.target.closest('.admin-info-tip')) {
      return;
    }
    e.preventDefault();
    e.stopPropagation();
    toggleTip(root);
  }, true);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeTips(null);
    }
  });

  window.addEventListener('resize', function () {
    document.querySelectorAll('.admin-info-tip-wrap.' + OPEN).forEach(function (root) {
      positionBubble(buttonOf(root), bubbleOf(root));
    });
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { hoistFromLabels(document); });
  } else {
    hoistFromLabels(document);
  }

  global.AdminInfoTip = {
    html: html,
    hoist: hoistFromLabels,
    closeAll: function () { closeTips(null); },
  };
})(window);
