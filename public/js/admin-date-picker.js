/**
 * Admin date / time / datetime-local fields: open the native picker on a
 * touch/pen tap. Mouse clicks stay with the browser so desktop typing works.
 */
(function (root, factory) {
  var api = factory();
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
  }
  root.AdminDatePicker = api;
})(typeof window !== 'undefined' ? window : globalThis, function () {
  var SEL = 'input[type="date"], input[type="time"], input[type="datetime-local"]';
  var lastPointerType = '';

  function isDateTimeInput(el) {
    return !!(el && el.matches && el.matches(SEL));
  }

  function rememberPointer(e) {
    if (!e) return;
    lastPointerType = e.pointerType || '';
  }

  function isTouchLikePointer(type) {
    type = type || lastPointerType;
    return type === 'touch' || type === 'pen';
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
    var pointerType = lastPointerType;
    lastPointerType = '';
    if (!e || e.defaultPrevented) return;
    if (e.ctrlKey || e.metaKey || e.altKey || e.shiftKey) return;
    if (!isTouchLikePointer(pointerType)) return;
    openPicker(e.target);
  }

  function bind(doc) {
    doc = doc || (typeof document !== 'undefined' ? document : null);
    if (!doc || !doc.addEventListener) return;
    doc.addEventListener('pointerdown', rememberPointer, true);
    doc.addEventListener('pointerup', rememberPointer, true);
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
    isTouchLikePointer: isTouchLikePointer,
    rememberPointer: rememberPointer,
    openPicker: openPicker,
    onActivate: onActivate,
    bind: bind,
  };
});
