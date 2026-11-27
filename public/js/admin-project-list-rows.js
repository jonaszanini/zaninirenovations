/**
 * Project List rows open on one click or tap.
 * Contact icons and the status dropdown stay interactive.
 * A stretched link covers the rest of the row; this backup handles a
 * click that does not land on that link (and a touch tap that never
 * becomes a click).
 */
(function (root, factory) {
  var api = factory();
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
  }
  if (root) root.AdminProjectListRows = api;
  if (typeof document !== 'undefined') {
    var boot = function () { api.bind(document); };
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', boot);
    } else {
      boot();
    }
  }
})(typeof window !== 'undefined' ? window : globalThis, function () {
  var IGNORE = '.cp-client-action-btn, .cp-inline-status, .cp-status-menu, [data-cp-status-trigger]';

  function node(el) {
    if (!el) return null;
    return el.nodeType === 3 ? (el.parentElement || el.parentNode || null) : el;
  }

  function closest(el, sel) {
    var start = node(el);
    return start && start.closest ? start.closest(sel) : null;
  }

  function actionFor(target, href) {
    if (!target || !href) return 'ignore';
    if (closest(target, IGNORE)) return 'ignore';
    var link = closest(target, 'a[href]');
    if (link && link.getAttribute('href') === href) return 'native';
    return 'navigate';
  }

  function go(url, blank) {
    if (!url || typeof window === 'undefined') return;
    if (blank) window.open(url, '_blank');
    else window.location.href = url;
  }

  function wantsBlank(ev) {
    return !!(ev && (ev.ctrlKey || ev.metaKey || ev.shiftKey));
  }

  function bind(doc) {
    if (!doc || typeof doc.querySelectorAll !== 'function') return;
    var rows = doc.querySelectorAll('.cp-row-link[data-href]');
    Array.prototype.forEach.call(rows, function (row) {
      if (row.getAttribute('data-cp-row-bound') === '1') return;
      row.setAttribute('data-cp-row-bound', '1');
      var url = row.getAttribute('data-href');
      var start = null;
      var handled = false;

      row.addEventListener('pointerdown', function (ev) {
        if (ev.button != null && ev.button !== 0) {
          start = null;
          return;
        }
        if (actionFor(ev.target, url) === 'ignore') {
          start = null;
          return;
        }
        start = { x: ev.clientX || 0, y: ev.clientY || 0 };
      });

      row.addEventListener('pointerup', function (ev) {
        if (!start || (ev.button != null && ev.button !== 0)) return;
        var dx = (ev.clientX || 0) - start.x;
        var dy = (ev.clientY || 0) - start.y;
        start = null;
        if ((dx * dx + dy * dy) > 64) return;
        if (actionFor(ev.target, url) !== 'navigate') return;
        handled = true;
        go(url, wantsBlank(ev));
      });

      row.addEventListener('click', function (ev) {
        if (handled) {
          handled = false;
          return;
        }
        if (actionFor(ev.target, url) !== 'navigate') return;
        if (wantsBlank(ev)) ev.preventDefault();
        go(url, wantsBlank(ev));
      });

      row.addEventListener('auxclick', function (ev) {
        if (ev.button !== 1) return;
        if (actionFor(ev.target, url) !== 'navigate') return;
        ev.preventDefault();
        go(url, true);
      });
    });
  }

  return {
    actionFor: actionFor,
    bind: bind,
  };
});
