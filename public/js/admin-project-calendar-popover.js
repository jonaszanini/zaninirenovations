/**
 * FullCalendar 7.1.3 "+N more" popover helpers (body-portaled role=dialog).
 * One-shot positioning on open; no MutationObserver.
 */
(function (root, factory) {
  var api = factory(root);
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
  }
  root.AdminProjectCalendarPopover = api;
})(typeof window !== 'undefined' ? window : globalThis, function () {
  var MORE_LINK_CLASS = 'cp-calendar-more-link';
  var MORE_DIALOG_CLASS = 'cp-calendar-more-dialog';
  var VIEWPORT_MARGIN = 12;
  var MAX_POPOVER_WIDTH = 320;
  var LINK_GAP = 4;

  /** Scroll at more-link pointer down (before FC open/focus). */
  var scrollBeforeMoreOpen = null;

  function isVisible(el) {
    if (!el || !el.getBoundingClientRect) {
      return false;
    }
    var rect = el.getBoundingClientRect();
    return rect.width > 1 && rect.height > 1;
  }

  function isExcludedDialog(dialog) {
    if (!dialog || !dialog.closest) {
      return true;
    }
    if (dialog.closest('#cp-personal-event-modal')) {
      return true;
    }
    if (dialog.closest('#admin-dialog')) {
      return true;
    }
    if (dialog.closest('#admin-photo-edit-modal')) {
      return true;
    }
    return false;
  }

  function findMorePopoverDialog() {
    if (!document.getElementById('cp-calendar')) {
      return null;
    }

    var tagged = document.querySelector('body > div[role="dialog"].' + MORE_DIALOG_CLASS);
    if (tagged && isVisible(tagged) && !isExcludedDialog(tagged)) {
      return tagged;
    }

    var dialogs = document.querySelectorAll('body > div[role="dialog"]');
    for (var i = 0; i < dialogs.length; i++) {
      var dialog = dialogs[i];
      if (isVisible(dialog) && !isExcludedDialog(dialog)) {
        return dialog;
      }
    }
    return null;
  }

  function findOpenPopover() {
    return findMorePopoverDialog();
  }

  function closePopover(pop) {
    if (!pop) {
      return false;
    }
    var btn =
      pop.querySelector('button[aria-label*="Close" i]') ||
      pop.querySelector('button[type="button"]') ||
      pop.querySelector('button');
    if (btn && typeof btn.click === 'function') {
      btn.click();
      return true;
    }
    return false;
  }

  function clamp(n, min, max) {
    return Math.max(min, Math.min(max, n));
  }

  function restoreScroll(saved) {
    if (!saved) {
      return;
    }
    if (window.scrollX !== saved.x || window.scrollY !== saved.y) {
      window.scrollTo(saved.x, saved.y);
    }
  }

  function patchFocusPreventScroll(el) {
    if (!el || el.__cpPopoverNoScrollFocus) {
      return;
    }
    el.__cpPopoverNoScrollFocus = true;
    var nativeFocus = el.focus;
    if (typeof nativeFocus !== 'function') {
      return;
    }
    el.focus = function (options) {
      var opts = options || {};
      if (opts.preventScroll !== true) {
        opts = Object.assign({}, opts, { preventScroll: true });
      }
      return nativeFocus.call(this, opts);
    };
  }

  function patchPopoverFocusPreventScroll(dialog) {
    if (!dialog) {
      return;
    }
    patchFocusPreventScroll(dialog);
    var nodes = dialog.querySelectorAll('button, a, input, select, textarea, [tabindex]');
    for (var i = 0; i < nodes.length; i++) {
      patchFocusPreventScroll(nodes[i]);
    }
  }

  function layoutMorePopover(linkEl, cardEl, dialog) {
    dialog.classList.add(MORE_DIALOG_CLASS);
    patchPopoverFocusPreventScroll(dialog);

    var scrollX = window.scrollX || window.pageXOffset || 0;
    var scrollY = window.scrollY || window.pageYOffset || 0;
    var vp = {
      left: VIEWPORT_MARGIN,
      top: VIEWPORT_MARGIN,
      right: window.innerWidth - VIEWPORT_MARGIN,
      bottom: window.innerHeight - VIEWPORT_MARGIN,
    };

    var link = linkEl.getBoundingClientRect();
    var card = cardEl.getBoundingClientRect();
    var boundLeft = Math.max(vp.left, card.left);
    var boundRight = Math.min(vp.right, card.right);
    var maxW = Math.min(MAX_POPOVER_WIDTH, vp.right - vp.left, boundRight - boundLeft);
    if (maxW < 120) {
      maxW = Math.min(MAX_POPOVER_WIDTH, vp.right - vp.left);
      boundLeft = vp.left;
      boundRight = vp.right;
    }

    dialog.style.boxSizing = 'border-box';
    dialog.style.maxWidth = maxW + 'px';
    dialog.style.width = maxW + 'px';
    dialog.style.overflowY = 'auto';
    dialog.style.overflowX = 'hidden';

    var spaceBelow = vp.bottom - (link.bottom + LINK_GAP);
    var spaceAbove = link.top - LINK_GAP - vp.top;
    var placeBelow = spaceBelow >= spaceAbove;

    var maxH = placeBelow ? spaceBelow : spaceAbove;
    maxH = clamp(maxH, 80, vp.bottom - vp.top);
    dialog.style.maxHeight = maxH + 'px';

    var popW = dialog.getBoundingClientRect().width;
    var leftVP = link.left;
    leftVP = clamp(leftVP, boundLeft, boundRight - popW);
    leftVP = clamp(leftVP, vp.left, vp.right - popW);

    var popH = dialog.getBoundingClientRect().height;
    var topVP = placeBelow ? link.bottom + LINK_GAP : link.top - LINK_GAP - popH;
    var fitsBelow = topVP + popH <= vp.bottom;
    var fitsAbove = topVP >= vp.top;

    if (placeBelow && !fitsBelow) {
      if (spaceAbove > spaceBelow) {
        placeBelow = false;
      }
    } else if (!placeBelow && !fitsAbove) {
      if (spaceBelow > spaceAbove) {
        placeBelow = true;
      }
    }

    if (placeBelow) {
      maxH = clamp(spaceBelow, 80, vp.bottom - vp.top);
      dialog.style.maxHeight = maxH + 'px';
      popH = dialog.getBoundingClientRect().height;
      topVP = link.bottom + LINK_GAP;
      if (topVP + popH > vp.bottom) {
        topVP = Math.max(vp.top, vp.bottom - popH);
      }
    } else {
      maxH = clamp(spaceAbove, 80, vp.bottom - vp.top);
      dialog.style.maxHeight = maxH + 'px';
      popH = dialog.getBoundingClientRect().height;
      topVP = link.top - LINK_GAP - popH;
      if (topVP < vp.top) {
        topVP = vp.top;
        maxH = clamp(link.top - LINK_GAP - vp.top, 80, vp.bottom - vp.top);
        dialog.style.maxHeight = maxH + 'px';
        popH = dialog.getBoundingClientRect().height;
        topVP = clamp(link.top - LINK_GAP - popH, vp.top, vp.bottom - popH);
      }
    }

    dialog.style.position = 'absolute';
    dialog.style.left = leftVP + scrollX + 'px';
    dialog.style.top = topVP + scrollY + 'px';
  }

  function positionMorePopoverOnce(linkEl, cardEl) {
    if (!linkEl || !cardEl) {
      return;
    }

    var saved =
      scrollBeforeMoreOpen ||
      { x: window.scrollX || 0, y: window.scrollY || 0 };

    function runLayout() {
      var dialog = findMorePopoverDialog();
      if (!dialog) {
        return false;
      }
      layoutMorePopover(linkEl, cardEl, dialog);
      restoreScroll(saved);
      var closeBtn =
        dialog.querySelector('button[aria-label*="Close" i]') ||
        dialog.querySelector('button[type="button"]');
      if (closeBtn && typeof closeBtn.focus === 'function') {
        closeBtn.focus({ preventScroll: true });
      }
      restoreScroll(saved);
      return true;
    }

    if (runLayout()) {
      scrollBeforeMoreOpen = null;
      return;
    }

    if (typeof queueMicrotask === 'function') {
      queueMicrotask(function () {
        if (runLayout()) {
          scrollBeforeMoreOpen = null;
        }
      });
    }

    requestAnimationFrame(function () {
      if (runLayout()) {
        scrollBeforeMoreOpen = null;
        return;
      }
      requestAnimationFrame(function () {
        runLayout();
        scrollBeforeMoreOpen = null;
      });
    });
  }

  function tagMoreDialogOnce() {
    positionMorePopoverOnce(null, null);
  }

  function attach(cardEl, calendarRootEl) {
    if (!cardEl || !calendarRootEl) {
      return { destroy: function () {} };
    }

    function rememberScrollBeforeMoreOpen(e) {
      if (!e.target.closest || !e.target.closest('.' + MORE_LINK_CLASS)) {
        return;
      }
      if (!document.getElementById('cp-calendar')) {
        return;
      }
      scrollBeforeMoreOpen = {
        x: window.scrollX || window.pageXOffset || 0,
        y: window.scrollY || window.pageYOffset || 0,
      };
    }

    function onDocumentPointerDown(e) {
      var pop = findMorePopoverDialog();
      if (!pop) {
        return;
      }
      if (pop.contains(e.target)) {
        return;
      }
      if (e.target.closest && e.target.closest('.' + MORE_LINK_CLASS)) {
        return;
      }
      closePopover(pop);
    }

    function onViewportChange() {
      var pop = findMorePopoverDialog();
      if (pop) {
        closePopover(pop);
      }
    }

    document.addEventListener('pointerdown', rememberScrollBeforeMoreOpen, true);
    document.addEventListener('pointerdown', onDocumentPointerDown, true);
    window.addEventListener('resize', onViewportChange);
    window.addEventListener('orientationchange', onViewportChange);

    return {
      destroy: function () {
        document.removeEventListener('pointerdown', rememberScrollBeforeMoreOpen, true);
        document.removeEventListener('pointerdown', onDocumentPointerDown, true);
        window.removeEventListener('resize', onViewportChange);
        window.removeEventListener('orientationchange', onViewportChange);
      },
      findOpenPopover: findMorePopoverDialog,
      findMorePopoverDialog: findMorePopoverDialog,
      closePopover: closePopover,
      positionMorePopoverOnce: positionMorePopoverOnce,
      tagMoreDialogOnce: tagMoreDialogOnce,
      MORE_LINK_CLASS: MORE_LINK_CLASS,
      MORE_DIALOG_CLASS: MORE_DIALOG_CLASS,
    };
  }

  return {
    attach: attach,
    findMorePopoverDialog: findMorePopoverDialog,
    findOpenPopover: findOpenPopover,
    closePopover: closePopover,
    positionMorePopoverOnce: positionMorePopoverOnce,
    tagMoreDialogOnce: tagMoreDialogOnce,
    isVisible: isVisible,
    patchPopoverFocusPreventScroll: patchPopoverFocusPreventScroll,
  };
});
