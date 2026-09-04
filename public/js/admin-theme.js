/**
 * Admin dark theme — localStorage preference, scoped to authenticated admin layout.
 */
(function (global) {
  'use strict';

  var STORAGE_KEY = 'zanini-admin-theme';
  var CLASS_NAME = 'admin-dark';

  function isDark() {
    return document.documentElement.classList.contains(CLASS_NAME);
  }

  function persist(dark) {
    try {
      localStorage.setItem(STORAGE_KEY, dark ? 'dark' : 'light');
    } catch (e) { /* private mode */ }
  }

  function syncToggle(dark) {
    var btn = document.getElementById('admin-theme-toggle');
    if (!btn) return;
    btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
    btn.setAttribute('aria-checked', dark ? 'true' : 'false');
    btn.setAttribute('aria-label', dark ? 'Switch to light theme' : 'Switch to dark theme');
    var moon = btn.querySelector('[data-theme-icon="moon"]');
    var sun = btn.querySelector('[data-theme-icon="sun"]');
    if (moon) moon.hidden = !!dark;
    if (sun) sun.hidden = !dark;
  }

  function apply(dark) {
    document.documentElement.classList.toggle(CLASS_NAME, dark);
    if (document.body) {
      document.body.classList.toggle(CLASS_NAME, dark);
    }
    persist(dark);
    syncToggle(dark);
  }

  function init() {
    var dark = isDark();
    if (document.body) {
      document.body.classList.toggle(CLASS_NAME, dark);
    }
    syncToggle(dark);

    var btn = document.getElementById('admin-theme-toggle');
    if (!btn) return;
    btn.addEventListener('click', function () {
      apply(!isDark());
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  global.AdminTheme = {
    isDark: isDark,
    setDark: apply
  };
})(window);
