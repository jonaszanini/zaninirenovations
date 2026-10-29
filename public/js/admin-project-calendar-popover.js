/**
 * FullCalendar 7 "+N more" popover (native [popover].re-popover): clamp to card + viewport,
 * Esc and outside tap to close via hidePopover().
 */
(function (root, factory) {
  var api = factory(root);
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
  }
  root.AdminProjectCalendarPopover = api;
})(typeof window !== 'undefined' ? window : globalThis, function () {
  var MORE_LINK_CLASS = 'cp-calendar-more-link';

  function isPopoverOpen(el) {
    if (!el || !el.matches) {
      return false;
    }
    try {
      return el.matches(':popover-open');
    } catch (err) {
      return el.hasAttribute('open') || el.getAttribute('data-state') === 'open';
    }
  }

  /**
   * @param {ParentNode} scope
   * @returns {HTMLElement|null}
   */
  function findOpenPopover(scope) {
    if (!scope || !scope.querySelectorAll) {
      return null;
    }
    var nodes = scope.querySelectorAll('[popover]');
    for (var i = 0; i < nodes.length; i++) {
      if (isPopoverOpen(nodes[i])) {
        return nodes[i];
      }
    }
    return null;
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
      pop.querySelector('[popovertargetaction="hide"]') ||
      pop.querySelector('button[aria-label*="Close"]') ||
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
    var margin = 8;
    var cardRect = cardEl.getBoundingClientRect();
    var vw = window.innerWidth;
    var vh = window.innerHeight;
    var popRect = pop.getBoundingClientRect();

    var minLeft = Math.max(margin, cardRect.left + margin);
    var maxLeft = Math.min(vw - popRect.width - margin, cardRect.right - popRect.width - margin);
    var minTop = Math.max(margin, cardRect.top + margin);
    var maxTop = Math.min(vh - popRect.height - margin, cardRect.bottom - popRect.height - margin);

    if (maxLeft < minLeft) {
      maxLeft = minLeft = Math.max(margin, Math.min(cardRect.left + margin, vw - popRect.width - margin));
    }
    if (maxTop < minTop) {
      maxTop = minTop = Math.max(margin, Math.min(cardRect.top + margin, vh - popRect.height - margin));
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
    pop.style.left = left + 'px';
    pop.style.top = top + 'px';
    pop.style.maxWidth = Math.min(340, vw - margin * 2) + 'px';
    pop.style.maxHeight = Math.max(120, Math.min(vh - top - margin, cardRect.bottom - top - margin)) + 'px';
  }

  /**
   * @param {HTMLElement} cardEl .cp-calendar-card
   * @param {HTMLElement} calendarRootEl #cp-calendar
   */
  function attach(cardEl, calendarRootEl) {
    if (!cardEl || !calendarRootEl) {
      return { destroy: function () {} };
    }

    function openPopoverEl() {
      return findOpenPopover(calendarRootEl) || findOpenPopover(document);
    }

    function scheduleClamp() {
      var pop = openPopoverEl();
      if (!pop) {
        return;
      }
      requestAnimationFrame(function () {
        clampPopover(pop, cardEl);
      });
    }

    function onDocumentKeyDown(e) {
      if (e.key !== 'Escape' && e.key !== 'Esc') {
        return;
      }
      var pop = openPopoverEl();
      if (!pop) {
        return;
      }
      if (closePopover(pop)) {
        e.preventDefault();
        e.stopPropagation();
      }
    }

    function onDocumentPointerDown(e) {
      var pop = openPopoverEl();
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
      attributeFilter: ['open', 'popover'],
    });

    document.addEventListener('keydown', onDocumentKeyDown, true);
    document.addEventListener('pointerdown', onDocumentPointerDown, true);
    calendarRootEl.addEventListener('beforetoggle', scheduleClamp);
    window.addEventListener('resize', scheduleClamp);

    return {
      destroy: function () {
        observer.disconnect();
        document.removeEventListener('keydown', onDocumentKeyDown, true);
        document.removeEventListener('pointerdown', onDocumentPointerDown, true);
        calendarRootEl.removeEventListener('beforetoggle', scheduleClamp);
      },
      findOpenPopover: function () {
        return openPopoverEl();
      },
      clampPopover: function (pop) {
        clampPopover(pop, cardEl);
      },
      closePopover: closePopover,
      MORE_LINK_CLASS: MORE_LINK_CLASS,
    };
  }

  return {
    attach: attach,
    findOpenPopover: findOpenPopover,
    clampPopover: clampPopover,
    closePopover: closePopover,
    isPopoverOpen: isPopoverOpen,
  };
});
