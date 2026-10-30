/**
 * FullCalendar 7.1.3 "+N more" popover helpers (body-portaled role=dialog).
 * Positioning is CSS-only; no observer loop or clamp writes.
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

  function tagMoreDialogOnce() {
    requestAnimationFrame(function () {
      var dialog = findMorePopoverDialog();
      if (dialog) {
        dialog.classList.add(MORE_DIALOG_CLASS);
      }
    });
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
    tagMoreDialogOnce: tagMoreDialogOnce,
    isVisible: isVisible,
  };
});
