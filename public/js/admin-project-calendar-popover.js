/**
 * FullCalendar 7.1.3 "+N more" popover: plain div[role=dialog] inside #cp-calendar.
 * Clamp to calendar card + viewport; outside tap closes via close button.
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

  /**
   * FC 7.1.3 more-link popover (not the personal-event modal).
   *
   * @param {ParentNode} scope
   * @returns {HTMLElement|null}
   */
  function findMorePopoverDialog(scope) {
    if (!scope || !scope.querySelectorAll) {
      return null;
    }
    var dialogs = scope.querySelectorAll('div[role="dialog"]');
    for (var i = 0; i < dialogs.length; i++) {
      var dialog = dialogs[i];
      if (!isVisible(dialog)) {
        continue;
      }
      if (dialog.closest && dialog.closest('#cp-personal-event-modal')) {
        continue;
      }
      return dialog;
    }
    return null;
  }

  /** @deprecated use findMorePopoverDialog */
  function findOpenPopover(scope) {
    return findMorePopoverDialog(scope);
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
    var height = popRect.height;

    if (width > maxRight - minLeft) {
      width = Math.max(120, maxRight - minLeft);
      pop.style.maxWidth = width + 'px';
    }

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

    function openDialog() {
      return findMorePopoverDialog(calendarRootEl);
    }

    function scheduleClamp() {
      var pop = openDialog();
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
      var pop = openDialog();
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
    observer.observe(calendarRootEl, {
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
      findOpenPopover: openDialog,
      findMorePopoverDialog: openDialog,
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
