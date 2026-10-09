/**
 * Admin info-tip: click/tap ⓘ toggles a popover. Event delegation so
 * dynamically added rows work. No hover-only behaviour.
 */
(function (global) {
  var OPEN = 'is-open';
  var PAD = 12;

  function rootOf(el) {
    if (!el || !el.closest) return null;
    return el.closest('.admin-info-tip-wrap') || el.closest('.admin-info-tip');
  }

  function buttonOf(root) {
    if (!root) return null;
    if (root.classList.contains('admin-info-tip')) return root;
    return root.querySelector('.admin-info-tip');
  }

  function bubbleOf(root) {
    return root ? root.querySelector('.admin-info-tip__bubble') : null;
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
    var maxW = Math.min(320, Math.max(160, window.innerWidth - PAD * 2));
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
    document.querySelectorAll('.admin-info-tip-wrap.' + OPEN + ', .admin-info-tip[aria-expanded="true"]').forEach(function (node) {
      var root = rootOf(node);
      if (root && root !== exceptRoot) {
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

  function html(text) {
    var id = 'admin-info-tip-' + Math.random().toString(36).slice(2, 10);
    return (
      '<span class="admin-info-tip-wrap">' +
        '<button type="button" class="admin-info-tip" aria-label="More info" aria-expanded="false" aria-controls="' + id + '">' +
          '<i class="fa fa-info-circle" aria-hidden="true"></i>' +
        '</button>' +
        '<span id="' + id + '" class="admin-info-tip__bubble" role="tooltip">' + escapeHtml(text) + '</span>' +
      '</span>'
    );
  }

  document.addEventListener('mousedown', function (e) {
    if (e.target.closest && e.target.closest('.admin-info-tip')) {
      e.preventDefault();
    }
  }, true);

  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('.admin-info-tip') : null;
    if (!btn) {
      closeTips(null);
      return;
    }
    e.preventDefault();
    e.stopPropagation();
    toggleTip(rootOf(btn));
  }, true);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeTips(null);
    }
  });

  window.addEventListener('resize', function () {
    document.querySelectorAll('.admin-info-tip-wrap.' + OPEN + ', .admin-info-tip[aria-expanded="true"]').forEach(function (node) {
      var root = rootOf(node);
      positionBubble(buttonOf(root), bubbleOf(root));
    });
  });

  global.AdminInfoTip = {
    html: html,
    closeAll: function () { closeTips(null); },
  };
})(window);
