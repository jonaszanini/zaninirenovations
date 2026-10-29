/**
 * Touch swipe navigation for the admin Project Calendar (FullCalendar 7).
 * Left swipe → next period; right swipe → previous. Touch/coarse devices only.
 *
 * FC7 removed .fc-daygrid-day-frame; pointer events alone get pointercancel on
 * first move when the browser claims vertical scroll — use touch listeners and
 * setPointerCapture when available.
 */
(function (root, factory) {
  var api = factory(root);
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
  }
  root.AdminProjectCalendarSwipe = api;
})(typeof window !== 'undefined' ? window : globalThis, function () {
  var MIN_HORIZONTAL_PX = 50;
  var LONG_SWIPE_PX = 80;
  /** Horizontal distance must be at least 2× vertical (2:1 ratio). */
  var HORIZONTAL_DOMINANCE = 2;
  var MIN_VELOCITY_PX_MS = 0.3;
  var SUPPRESS_CLICK_MS = 400;
  var VERTICAL_SCROLL_BREAK_PX = 12;

  /**
   * @param {number} dx horizontal delta (px)
   * @param {number} dy vertical delta (px)
   * @param {number} durationMs gesture duration
   */
  function qualifiesHorizontalSwipe(dx, dy, durationMs) {
    var adx = Math.abs(dx);
    var ady = Math.abs(dy);
    if (!durationMs || durationMs <= 0) {
      return false;
    }
    if (adx < ady * HORIZONTAL_DOMINANCE) {
      return false;
    }

    var longDistance = adx >= LONG_SWIPE_PX;
    var fastEnough = adx >= MIN_HORIZONTAL_PX && adx / durationMs >= MIN_VELOCITY_PX_MS;

    return longDistance || fastEnough;
  }

  /** @returns {'next'|'prev'|null} */
  function swipeDirection(dx) {
    if (dx <= -MIN_HORIZONTAL_PX) {
      return 'next';
    }
    if (dx >= MIN_HORIZONTAL_PX) {
      return 'prev';
    }

    return null;
  }

  /**
   * @param {{ tracking: boolean }|null} active
   * @param {{ pointerId: number, isPrimary?: boolean }} e
   */
  function shouldCancelSwipeTrackingOnPointerDown(active, e) {
    if (!active) {
      return false;
    }
    if (e.pointerId !== active.id) {
      return true;
    }
    if (e.isPrimary === false) {
      return true;
    }

    return false;
  }

  /**
   * @param {HTMLElement} containerEl
   * @param {{ next: Function, prev: Function }} calendarApi
   * @param {{ touchLike?: boolean }} options
   */
  function attach(containerEl, calendarApi, options) {
    options = options || {};
    var suppressUntil = 0;

    if (!options.touchLike || !containerEl || !calendarApi) {
      return {
        shouldSuppressEventClick: function () {
          return false;
        },
        destroy: function () {},
      };
    }

    /** @type {null|{ x: number, y: number, t: number, tracking: boolean, id?: number, touch?: boolean }} */
    var active = null;

    function shouldSuppressEventClick() {
      return Date.now() < suppressUntil;
    }

    function beginTracking(x, y, meta) {
      active = {
        x: x,
        y: y,
        t: performance.now(),
        tracking: true,
        id: meta.id,
        touch: !!meta.touch,
      };
    }

    function updateTracking(dx, dy) {
      if (!active || !active.tracking) {
        return;
      }
      if (Math.abs(dy) > VERTICAL_SCROLL_BREAK_PX && Math.abs(dy) > Math.abs(dx)) {
        active.tracking = false;
      }
    }

    function finishTracking(clientX, clientY, e) {
      if (!active) {
        return;
      }
      var dx = clientX - active.x;
      var dy = clientY - active.y;
      var dt = performance.now() - active.t;
      var tracking = active.tracking;
      active = null;

      if (!tracking) {
        return;
      }
      if (!qualifiesHorizontalSwipe(dx, dy, dt)) {
        return;
      }

      var dir = swipeDirection(dx);
      if (dir === 'next') {
        calendarApi.next();
      } else if (dir === 'prev') {
        calendarApi.prev();
      } else {
        return;
      }

      suppressUntil = Date.now() + SUPPRESS_CLICK_MS;
      if (e && e.cancelable) {
        e.preventDefault();
      }
      if (e && e.stopPropagation) {
        e.stopPropagation();
      }
    }

    function releaseCapture(e) {
      if (!active || active.id === undefined) {
        return;
      }
      if (containerEl.releasePointerCapture) {
        try {
          containerEl.releasePointerCapture(active.id);
        } catch (err) { /* already released */ }
      }
    }

    function onPointerDown(e) {
      if (e.pointerType === 'mouse') {
        return;
      }

      if (active) {
        if (shouldCancelSwipeTrackingOnPointerDown(active, e)) {
          active.tracking = false;
        }
        return;
      }

      if (typeof e.isPrimary === 'boolean' && !e.isPrimary) {
        return;
      }

      beginTracking(e.clientX, e.clientY, { id: e.pointerId, touch: e.pointerType === 'touch' });
      if (containerEl.setPointerCapture) {
        try {
          containerEl.setPointerCapture(e.pointerId);
        } catch (err) { /* ignore */ }
      }
    }

    function onPointerMove(e) {
      if (!active || e.pointerId !== active.id || !active.tracking) {
        return;
      }
      updateTracking(e.clientX - active.x, e.clientY - active.y);
    }

    function onPointerUp(e) {
      if (!active || e.pointerId !== active.id) {
        return;
      }
      finishTracking(e.clientX, e.clientY, e);
      releaseCapture(e);
    }

    function onPointerCancel(e) {
      if (active && e.pointerId === active.id) {
        active = null;
      }
    }

    function onTouchStart(e) {
      if (active || !e.changedTouches || e.changedTouches.length !== 1) {
        if (e.touches && e.touches.length > 1 && active) {
          active.tracking = false;
        }
        return;
      }
      var t = e.changedTouches[0];
      beginTracking(t.clientX, t.clientY, { touch: true });
    }

    function onTouchMove(e) {
      if (!active || !active.touch || !e.changedTouches || e.changedTouches.length !== 1) {
        return;
      }
      var t = e.changedTouches[0];
      var dx = t.clientX - active.x;
      var dy = t.clientY - active.y;
      updateTracking(dx, dy);
      if (
        active.tracking &&
        Math.abs(dx) > Math.abs(dy) * HORIZONTAL_DOMINANCE &&
        Math.abs(dx) > 10
      ) {
        if (e.cancelable) {
          e.preventDefault();
        }
      }
    }

    function onTouchEnd(e) {
      if (!active || !active.touch || !e.changedTouches || e.changedTouches.length !== 1) {
        return;
      }
      var t = e.changedTouches[0];
      finishTracking(t.clientX, t.clientY, e);
    }

    function onTouchCancel() {
      active = null;
    }

    containerEl.addEventListener('pointerdown', onPointerDown, { passive: true });
    containerEl.addEventListener('pointermove', onPointerMove, { passive: true });
    containerEl.addEventListener('pointerup', onPointerUp);
    containerEl.addEventListener('pointercancel', onPointerCancel);

    containerEl.addEventListener('touchstart', onTouchStart, { passive: true });
    containerEl.addEventListener('touchmove', onTouchMove, { passive: false });
    containerEl.addEventListener('touchend', onTouchEnd, { passive: true });
    containerEl.addEventListener('touchcancel', onTouchCancel, { passive: true });

    return {
      shouldSuppressEventClick: shouldSuppressEventClick,
      destroy: function () {
        containerEl.removeEventListener('pointerdown', onPointerDown);
        containerEl.removeEventListener('pointermove', onPointerMove);
        containerEl.removeEventListener('pointerup', onPointerUp);
        containerEl.removeEventListener('pointercancel', onPointerCancel);
        containerEl.removeEventListener('touchstart', onTouchStart);
        containerEl.removeEventListener('touchmove', onTouchMove);
        containerEl.removeEventListener('touchend', onTouchEnd);
        containerEl.removeEventListener('touchcancel', onTouchCancel);
      },
    };
  }

  return {
    MIN_HORIZONTAL_PX: MIN_HORIZONTAL_PX,
    LONG_SWIPE_PX: LONG_SWIPE_PX,
    HORIZONTAL_DOMINANCE: HORIZONTAL_DOMINANCE,
    MIN_VELOCITY_PX_MS: MIN_VELOCITY_PX_MS,
    qualifiesHorizontalSwipe: qualifiesHorizontalSwipe,
    swipeDirection: swipeDirection,
    shouldCancelSwipeTrackingOnPointerDown: shouldCancelSwipeTrackingOnPointerDown,
    attach: attach,
  };
});
