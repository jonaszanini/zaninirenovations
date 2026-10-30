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

  /**
   * Measure link + popover once after FC opens; flip/clamp in document coordinates.
   */
  function positionMorePopoverOnce(linkEl, cardEl) {
    if (!linkEl || !cardEl) {
      return;
    }

    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        var dialog = findMorePopoverDialog();
        if (!dialog) {
          return;
        }

        dialog.classList.add(MORE_DIALOG_CLASS);

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
        var placeBelow = spaceBelow >= spaceAbove || spaceBelow >= 96;

        var maxH = placeBelow ? spaceBelow : spaceAbove;
        maxH = clamp(maxH, 80, vp.bottom - vp.top);
        dialog.style.maxHeight = maxH + 'px';

        var popW = dialog.getBoundingClientRect().width;
        var leftVP = link.left;
        leftVP = clamp(leftVP, boundLeft, boundRight - popW);
        leftVP = clamp(leftVP, vp.left, vp.right - popW);

        var popH = dialog.getBoundingClientRect().height;
        var topVP;

        if (placeBelow) {
          topVP = link.bottom + LINK_GAP;
          if (topVP + popH > vp.bottom && spaceAbove > spaceBelow) {
            placeBelow = false;
            maxH = clamp(spaceAbove, 80, vp.bottom - vp.top);
            dialog.style.maxHeight = maxH + 'px';
            popH = dialog.getBoundingClientRect().height;
            topVP = link.top - LINK_GAP - popH;
          } else if (topVP + popH > vp.bottom) {
            topVP = Math.max(vp.top, vp.bottom - popH);
          }
        }

        if (!placeBelow) {
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

    document.addEventListener('pointerdown', onDocumentPointerDown, true);

    return {
      destroy: function () {
        document.removeEventListener('pointerdown', onDocumentPointerDown, true);
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
  };
});
