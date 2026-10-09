/**
 * Admin list Filters accordion — persist open/closed per page.
 * Auto-opens when filters are active unless the user closed it here.
 */
(function () {
  'use strict';

  function storageGet(key) {
    try {
      return window.localStorage.getItem(key);
    } catch (e) {
      return null;
    }
  }

  function storageSet(key, value) {
    try {
      window.localStorage.setItem(key, value);
    } catch (e) { /* private mode */ }
  }

  function init(details) {
    var key = details.getAttribute('data-storage-key');
    var stored = key ? storageGet(key) : null;
    var activeCount = parseInt(details.getAttribute('data-active-count') || '0', 10);

    if (stored === 'closed') {
      details.open = false;
    } else if (stored === 'open' || activeCount > 0) {
      details.open = true;
    }

    details.addEventListener('toggle', function () {
      if (key) {
        storageSet(key, details.open ? 'open' : 'closed');
      }
    });
  }

  document.querySelectorAll('[data-admin-filters-accordion]').forEach(init);
})();
