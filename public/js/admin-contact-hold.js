/**
 * Hold to copy on contact icons (data-cp-hold-copy).
 * Touch and pen only. A short tap is left alone so the link can open.
 * A hold of about 500ms copies the value, shows the Copied toast, and
 * suppresses that tap, the context menu, and text selection.
 * Mouse clicks are not handled here.
 * Copy and the "Copied" toast go through AdminCopyValue and AdminToast.
 */
(function (root, factory) {
  var api = factory();
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
  }
  root.AdminContactHold = api;
  if (typeof document !== 'undefined') {
    var boot = function () { api.bind(document); };
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', boot);
    } else {
      boot();
    }
  }
})(typeof window !== 'undefined' ? window : globalThis, function () {
  var HOLD_MS = 500;
  var MOVE_SLOP = 12;

  function createSession(opts) {
    opts = opts || {};
    var holdMs = opts.holdMs || HOLD_MS;
    var moveSlop = opts.moveSlop == null ? MOVE_SLOP : opts.moveSlop;
    var setTimer = opts.setTimer || function (fn, ms) { return setTimeout(fn, ms); };
    var clearTimerFn = opts.clearTimer || function (id) { clearTimeout(id); };
    var onCopy = opts.onCopy || function () {};
    var timer = null;
    var active = false;
    var suppressClick = false;
    var origin = null;

    function clearTimer() {
      if (timer != null) {
        clearTimerFn(timer);
        timer = null;
      }
    }

    return {
      pointerDown: function (point) {
        clearTimer();
        suppressClick = false;
        active = true;
        origin = { x: point.x, y: point.y };
        timer = setTimer(function () {
          timer = null;
          if (!active) return;
          active = false;
          suppressClick = true;
          onCopy();
        }, holdMs);
      },
      pointerMove: function (point) {
        if (!active || !origin || !point) return 'idle';
        var dx = point.x - origin.x;
        var dy = point.y - origin.y;
        if ((dx * dx + dy * dy) > (moveSlop * moveSlop)) {
          clearTimer();
          active = false;
          return 'cancelled';
        }
        return 'holding';
      },
      pointerUp: function () {
        var fired = suppressClick;
        clearTimer();
        active = false;
        return fired ? 'suppress' : 'activate';
      },
      pointerCancel: function () {
        clearTimer();
        active = false;
        suppressClick = false;
      },
      click: function () {
        if (suppressClick) {
          suppressClick = false;
          return 'suppress';
        }
        return 'allow';
      },
      contextMenu: function () {
        return 'suppress';
      },
    };
  }

  function copyAndToast(text, env) {
    var win = (env && env.window) || (typeof window !== 'undefined' ? window : null);
    if (!text || !win) return;
    var copyApi = win.AdminCopyValue;
    var toast = win.AdminToast;
    var done = function () {
      if (toast && typeof toast.show === 'function') {
        toast.show({
          type: 'success',
          title: 'Copied',
          sticky: false,
          duration: 1600,
        });
      }
    };
    if (copyApi && typeof copyApi.copyText === 'function') {
      copyApi.copyText(text, env).then(done).catch(function () {});
      return;
    }
  }

  function pointFromEvent(ev) {
    return { x: ev.clientX || 0, y: ev.clientY || 0 };
  }

  function isHoldPointer(ev) {
    return !!ev && (ev.pointerType === 'touch' || ev.pointerType === 'pen');
  }

  function bind(doc, env) {
    if (!doc || typeof doc.querySelectorAll !== 'function') return;
    env = env || null;
    var fields = doc.querySelectorAll('[data-cp-hold-copy]');
    Array.prototype.forEach.call(fields, function (el) {
      if (el.getAttribute('data-cp-hold-bound') === '1') return;
      el.setAttribute('data-cp-hold-bound', '1');

      var suppressClicksUntil = 0;
      var sessionOpts = {
        onCopy: function () {
          suppressClicksUntil = Date.now() + 700;
          copyAndToast(el.getAttribute('data-cp-hold-copy') || '', env);
        },
      };
      if (env && typeof env.setTimer === 'function') sessionOpts.setTimer = env.setTimer;
      if (env && typeof env.clearTimer === 'function') sessionOpts.clearTimer = env.clearTimer;
      var session = createSession(sessionOpts);

      el.addEventListener('pointerdown', function (ev) {
        if (!isHoldPointer(ev)) {
          session.pointerCancel();
          return;
        }
        if (ev.button != null && ev.button !== 0) return;
        session.pointerDown(pointFromEvent(ev));
        el.classList.add('is-holding');
      });

      el.addEventListener('pointermove', function (ev) {
        if (!isHoldPointer(ev)) return;
        if (session.pointerMove(pointFromEvent(ev)) === 'cancelled') {
          el.classList.remove('is-holding');
        }
      });

      el.addEventListener('pointerup', function (ev) {
        if (!isHoldPointer(ev)) return;
        el.classList.remove('is-holding');
        if (session.pointerUp() === 'suppress') {
          ev.preventDefault();
          ev.stopPropagation();
        }
      });

      el.addEventListener('pointercancel', function (ev) {
        if (ev && !isHoldPointer(ev)) return;
        session.pointerCancel();
        el.classList.remove('is-holding');
      });

      el.addEventListener('contextmenu', function (ev) {
        if (ev.pointerType === 'mouse') return;
        if (session.contextMenu() === 'suppress') {
          ev.preventDefault();
          ev.stopPropagation();
        }
      });

      el.addEventListener('selectstart', function (ev) {
        ev.preventDefault();
      });

      el.addEventListener('click', function (ev) {
        var suppress = session.click() === 'suppress' || Date.now() < suppressClicksUntil;
        ev.stopPropagation();
        if (suppress) {
          ev.preventDefault();
          ev.stopPropagation();
        }
      });
    });
  }

  return {
    HOLD_MS: HOLD_MS,
    createSession: createSession,
    copyAndToast: copyAndToast,
    bind: bind,
  };
});
