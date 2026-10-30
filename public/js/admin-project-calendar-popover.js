/**
 * FullCalendar 7.1.3 "+N more" popover: div[role=dialog] portaled to document.body.
 * Clamp to .cp-calendar-card + viewport; outside tap closes via close button.
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

  /**
   * FC 7.1.3 more-link popover (mounted on body, not #cp-calendar).
   *
   * @returns {HTMLElement|null}
   */
  function findMorePopoverDialog() {
    var calendarRoot = document.getElementById('cp-calendar');
    if (!calendarRoot) {
      return null;
    }

    var tagged = document.querySelector('div[role="dialog"].' + MORE_DIALOG_CLASS);
    if (tagged && isVisible(tagged) && !isExcludedDialog(tagged)) {
      return tagged;
    }

    var dialogs = document.querySelectorAll('div[role="dialog"]');
    for (var i = 0; i < dialogs.length; i++) {
      var dialog = dialogs[i];
      if (!isVisible(dialog) || isExcludedDialog(dialog)) {
        continue;
      }
      if (dialog.closest('#cp-calendar')) {
        return dialog;
      }
      if (dialog.parentElement === document.body) {
        return dialog;
      }
      if (dialog.parentElement && dialog.parentElement.tagName === 'BODY') {
        return dialog;
      }
    }
    return null;
  }

  /** @deprecated */
  function findOpenPopover() {
    return findMorePopoverDialog();
  }

  function closePopover(pop) {
    if (!pop) {
      return false;
    }
    if (typeof pop.hidePopover === 'function') {
      pop.hidePopover();
      return true;
    }
    var btn =
      pop.querySelector('[data-fc-popover-close]') ||
      pop.querySelector('button[aria-label*="Close" i]') ||
      pop.querySelector('button[type="button"]') ||
      pop.querySelector('button');
    if (btn && typeof btn.click === 'function') {
      btn.click();
      return true;
    }
    return false;
  }

  /**
   * @param {HTMLElement} pop
   * @param {HTMLElement} cardEl
   */
  function clampPopover(pop, cardEl) {
    if (!pop || !cardEl) {
      return;
    }
    pop.classList.add(MORE_DIALOG_CLASS);

    var margin = 8;
    var cardRect = cardEl.getBoundingClientRect();
    var vw = window.innerWidth;
    var vh = window.innerHeight;
    var popRect = pop.getBoundingClientRect();

    var minLeft = Math.max(margin, cardRect.left + margin);
    var maxRight = Math.min(vw - margin, cardRect.right - margin);
    var minTop = Math.max(margin, cardRect.top + margin);
    var maxBottom = Math.min(vh - margin, cardRect.bottom - margin);

    var width = popRect.width;
    if (width > maxRight - minLeft) {
      width = Math.max(120, maxRight - minLeft);
      pop.style.width = width + 'px';
      pop.style.maxWidth = width + 'px';
    }

    var height = popRect.height;
    var maxLeft = maxRight - width;
    var maxTop = maxBottom - height;
    if (maxLeft < minLeft) {
      maxLeft = minLeft;
    }
    if (maxTop < minTop) {
      maxTop = minTop;
    }

    var left = popRect.left;
    var top = popRect.top;
    if (left < minLeft) {
      left = minLeft;
    } else if (left > maxLeft) {
      left = maxLeft;
    }
    if (top < minTop) {
      top = minTop;
    } else if (top > maxTop) {
      top = maxTop;
    }

    pop.style.position = 'fixed';
    pop.style.left = Math.round(left) + 'px';
    pop.style.top = Math.round(top) + 'px';
    pop.style.margin = '0';
    pop.style.boxSizing = 'border-box';
    pop.style.maxWidth = Math.min(340, vw - margin * 2) + 'px';
    pop.style.maxHeight = Math.max(100, maxBottom - top) + 'px';
    pop.style.overflow = 'auto';
  }

  /**
   * @param {HTMLElement} cardEl .cp-calendar-card
   * @param {HTMLElement} calendarRootEl #cp-calendar
   */
  function attach(cardEl, calendarRootEl) {
    if (!cardEl || !calendarRootEl) {
      return { destroy: function () {} };
    }

    function scheduleClamp() {
      var pop = findMorePopoverDialog();
      if (!pop) {
        return;
      }
      requestAnimationFrame(function () {
        clampPopover(pop, cardEl);
        requestAnimationFrame(function () {
          clampPopover(pop, cardEl);
        });
      });
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

    var observer = new MutationObserver(scheduleClamp);
    observer.observe(document.body, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['style', 'class', 'hidden', 'aria-hidden', 'open'],
    });

    document.addEventListener('pointerdown', onDocumentPointerDown, true);
    window.addEventListener('resize', scheduleClamp);

    return {
      destroy: function () {
        observer.disconnect();
        document.removeEventListener('pointerdown', onDocumentPointerDown, true);
      },
      scheduleClamp: scheduleClamp,
      findOpenPopover: findMorePopoverDialog,
      findMorePopoverDialog: findMorePopoverDialog,
      clampPopover: function (pop) {
        clampPopover(pop, cardEl);
      },
      closePopover: closePopover,
      MORE_LINK_CLASS: MORE_LINK_CLASS,
      MORE_DIALOG_CLASS: MORE_DIALOG_CLASS,
    };
  }

  return {
    attach: attach,
    findMorePopoverDialog: findMorePopoverDialog,
    findOpenPopover: findOpenPopover,
    clampPopover: clampPopover,
    closePopover: closePopover,
    isVisible: isVisible,
  };
});
