/**
 * Admin date / time / datetime-local fields: open the native picker on tap
 * or click without blocking keyboard entry.
 */
(function (root, factory) {
  var api = factory();
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
  }
  root.AdminDatePicker = api;
})(typeof window !== 'undefined' ? window : globalThis, function () {
  var SEL = 'input[type="date"], input[type="time"], input[type="datetime-local"]';

  function isDateTimeInput(el) {
    return !!(el && el.matches && el.matches(SEL));
  }

  function openPicker(el) {
    if (!isDateTimeInput(el) || el.disabled || el.readOnly) return false;
    if (typeof el.showPicker !== 'function') return false;
    try {
      el.showPicker();
      return true;
    } catch (err) {
      return false;
    }
  }

  function onActivate(e) {
    if (!e || e.defaultPrevented) return;
    if (e.ctrlKey || e.metaKey || e.altKey || e.shiftKey) return;
    openPicker(e.target);
  }

  function bind(doc) {
    doc = doc || (typeof document !== 'undefined' ? document : null);
    if (!doc || !doc.addEventListener) return;
    doc.addEventListener('click', onActivate, false);
  }

  if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function () { bind(document); });
    } else {
      bind(document);
    }
  }

  return {
    SEL: SEL,
    isDateTimeInput: isDateTimeInput,
    openPicker: openPicker,
    onActivate: onActivate,
    bind: bind,
  };
});
