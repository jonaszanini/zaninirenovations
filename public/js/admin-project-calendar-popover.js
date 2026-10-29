/**
 * "+N more" popover: keep inside the calendar card, Esc + outside tap to close.
 */
(function (root, factory) {
  var api = factory(root);
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
  }
  root.AdminProjectCalendarPopover = api;
})(typeof window !== 'undefined' ? window : globalThis, function () {
  var POPOVER_CLASS = 'cp-calendar-more-popover';

  /**
   * @param {HTMLElement} cardEl .cp-calendar-card
   * @param {HTMLElement} calendarRootEl #cp-calendar
   */
  function attach(cardEl, calendarRootEl) {
    if (!cardEl || !calendarRootEl) {
      return { destroy: function () {} };
    }

    var openPopover = null;

    function popoverFromTarget(target) {
      if (!target || !target.closest) {
        return null;
      }
      return target.closest('.' + POPOVER_CLASS);
    }

    function findOpenPopover() {
      return calendarRootEl.querySelector('.' + POPOVER_CLASS);
    }

    function clickCloseButton(pop) {
      if (!pop) {
        return false;
      }
      var btn =
        pop.querySelector('[data-fc-popover-close]') ||
        pop.querySelector('button[aria-label*="Close"]') ||
        pop.querySelector('button');
      if (btn && typeof btn.click === 'function') {
        btn.click();
        return true;
      }
      return false;
    }

    function clampPopover(pop) {
      if (!pop || !cardEl) {
        return;
      }
      var cardRect = cardEl.getBoundingClientRect();
      var popRect = pop.getBoundingClientRect();
      var margin = 8;
      var maxLeft = cardRect.right - popRect.width - margin;
      var minLeft = cardRect.left + margin;
      var maxTop = cardRect.bottom - popRect.height - margin;
      var minTop = cardRect.top + margin;

      var left = popRect.left;
      var top = popRect.top;

      if (left < minLeft) {
        left = minLeft;
      } else if (left > maxLeft) {
        left = Math.max(minLeft, maxLeft);
      }
      if (top < minTop) {
        top = minTop;
      } else if (top > maxTop) {
        top = Math.max(minTop, maxTop);
      }

      if (left !== popRect.left || top !== popRect.top) {
        pop.style.position = 'fixed';
        pop.style.left = left + 'px';
        pop.style.top = top + 'px';
      }
    }

    function onDocumentKeyDown(e) {
      if (e.key !== 'Escape' && e.key !== 'Esc') {
        return;
      }
      var pop = findOpenPopover();
      if (!pop) {
        return;
      }
      if (clickCloseButton(pop)) {
        e.preventDefault();
        e.stopPropagation();
      }
    }

    function onDocumentPointerDown(e) {
      var pop = findOpenPopover();
      if (!pop) {
        openPopover = null;
        return;
      }
      if (pop.contains(e.target)) {
        return;
      }
      if (e.target.closest && e.target.closest('.cp-calendar-more-link')) {
        return;
      }
      clickCloseButton(pop);
    }

    var observer = new MutationObserver(function () {
      var pop = findOpenPopover();
      if (pop && pop !== openPopover) {
        openPopover = pop;
        requestAnimationFrame(function () {
          clampPopover(pop);
        });
      }
      if (!pop) {
        openPopover = null;
      }
    });

    observer.observe(calendarRootEl, { childList: true, subtree: true });

    document.addEventListener('keydown', onDocumentKeyDown, true);
    document.addEventListener('pointerdown', onDocumentPointerDown, true);
    window.addEventListener('resize', function () {
      var pop = findOpenPopover();
      if (pop) {
        clampPopover(pop);
      }
    });

    return {
      destroy: function () {
        observer.disconnect();
        document.removeEventListener('keydown', onDocumentKeyDown, true);
        document.removeEventListener('pointerdown', onDocumentPointerDown, true);
      },
      /** @internal test hook */
      _popoverClass: POPOVER_CLASS,
      _popoverFromTarget: popoverFromTarget,
    };
  }

  return {
    attach: attach,
  };
});
