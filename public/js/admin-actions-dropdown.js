/**
 * Admin actions ⋯ menu: open/close, flip upward when there is not
 * enough room below, and portal the menu to <body> so overflow:hidden
 * or overflow:auto ancestors (cards, .admin-table-scroll) cannot clip it.
 *
 * Markup: .admin-actions-dropdown > button.admin-actions-dropdown__toggle
 *         + .admin-actions-dropdown__menu
 */
(function (window, document) {
  var GAP = 4;
  var VIEW_PAD = 8;

  function menuOf(dropdown) {
    return dropdown._portaledMenu || dropdown.querySelector('.admin-actions-dropdown__menu');
  }

  function toggleOf(dropdown) {
    return dropdown.querySelector('.admin-actions-dropdown__toggle');
  }

  function menuItems(menu) {
    if (!menu) return [];
    return Array.prototype.slice.call(
      menu.querySelectorAll('button, a[href], [tabindex]:not([tabindex="-1"])')
    ).filter(function (el) {
      return !el.disabled && el.offsetParent !== null;
    });
  }

  function decorateDropdown(dropdown) {
    var toggle = toggleOf(dropdown);
    if (toggle) {
      if (!toggle.hasAttribute('aria-expanded')) {
        toggle.setAttribute('aria-expanded', 'false');
      }
      if (!toggle.hasAttribute('aria-haspopup')) {
        toggle.setAttribute('aria-haspopup', 'menu');
      }
    }
    var menu = menuOf(dropdown);
    if (!menu) return;
    menu.setAttribute('role', 'menu');
    menu.querySelectorAll('button, a[href]').forEach(function (el) {
      el.setAttribute('role', 'menuitem');
    });
  }

  function decorateAll() {
    document.querySelectorAll('.admin-actions-dropdown').forEach(decorateDropdown);
  }

  function setExpanded(dropdown, open) {
    var toggle = toggleOf(dropdown);
    if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  function restoreMenu(dropdown, returnFocus) {
    var menu = dropdown._portaledMenu || dropdown.querySelector('.admin-actions-dropdown__menu');
    var focusInside = !!(menu && menu.contains(document.activeElement));
    menu = dropdown._portaledMenu;
    if (menu) {
      if (menu.parentNode !== dropdown) {
        dropdown.appendChild(menu);
      }
      menu.classList.remove('is-portaled');
      menu.style.position = '';
      menu.style.top = '';
      menu.style.left = '';
      menu.style.right = '';
      menu.style.bottom = '';
      menu.style.maxHeight = '';
      menu.style.overflowY = '';
      menu.style.zIndex = '';
      menu.style.marginTop = '';
      menu.style.marginBottom = '';
      dropdown._portaledMenu = null;
    }
    dropdown.classList.remove('is-open', 'is-up');
    setExpanded(dropdown, false);
    if (returnFocus && focusInside) {
      var toggle = toggleOf(dropdown);
      if (toggle) toggle.focus();
    }
  }

  function closeAll(except, returnFocus) {
    document.querySelectorAll('.admin-actions-dropdown.is-open').forEach(function (el) {
      if (el !== except) restoreMenu(el, returnFocus);
    });
  }

  function positionMenu(dropdown) {
    var toggle = toggleOf(dropdown);
    var menu = menuOf(dropdown);
    if (!toggle || !menu) return;

    if (menu.parentNode !== document.body) {
      document.body.appendChild(menu);
      dropdown._portaledMenu = menu;
      menu.classList.add('is-portaled');
    }

    menu.style.position = 'fixed';
    menu.style.right = 'auto';
    menu.style.bottom = 'auto';
    menu.style.marginTop = '0';
    menu.style.marginBottom = '0';
    menu.style.maxHeight = 'none';
    menu.style.overflowY = '';
    menu.style.zIndex = '500';

    var toggleRect = toggle.getBoundingClientRect();
    var menuHeight = menu.offsetHeight;
    var menuWidth = menu.offsetWidth;
    var spaceBelow = window.innerHeight - toggleRect.bottom - GAP - VIEW_PAD;
    var spaceAbove = toggleRect.top - GAP - VIEW_PAD;
    var openUp = menuHeight > spaceBelow && spaceAbove > spaceBelow;
    dropdown.classList.toggle('is-up', openUp);

    var available = openUp ? spaceAbove : spaceBelow;
    if (menuHeight > available && available > 72) {
      menu.style.maxHeight = available + 'px';
      menu.style.overflowY = 'auto';
      menuHeight = available;
    }

    var top = openUp
      ? toggleRect.top - GAP - menuHeight
      : toggleRect.bottom + GAP;
    if (top < VIEW_PAD) top = VIEW_PAD;
    if (top + menuHeight > window.innerHeight - VIEW_PAD) {
      top = Math.max(VIEW_PAD, window.innerHeight - menuHeight - VIEW_PAD);
    }

    var left = toggleRect.right - menuWidth;
    if (left < VIEW_PAD) left = VIEW_PAD;
    if (left + menuWidth > window.innerWidth - VIEW_PAD) {
      left = Math.max(VIEW_PAD, window.innerWidth - menuWidth - VIEW_PAD);
    }

    menu.style.top = Math.round(top) + 'px';
    menu.style.left = Math.round(left) + 'px';
  }

  function openDropdown(dropdown) {
    decorateDropdown(dropdown);
    dropdown.classList.add('is-open');
    setExpanded(dropdown, true);
    positionMenu(dropdown);
    var items = menuItems(menuOf(dropdown));
    if (items[0]) {
      window.setTimeout(function () {
        items[0].focus();
      }, 0);
    }
  }

  function openDropdownFrom(dropdown) {
    decorateAll();
    var wasOpen = dropdown.classList.contains('is-open');
    closeAll(null, false);
    if (!wasOpen) openDropdown(dropdown);
  }

  // Capture phase: row wrappers (quotation accordion summary, table cells)
  // call stopPropagation on bubble to keep <details> from toggling. A bubble
  // listener on document never sees those taps, so the menu looks dead on
  // mobile. Capture still runs, preventDefault blocks the summary toggle, and
  // the same path covers invoice/contract overflow menus.
  document.addEventListener('click', function (e) {
    var toggle = e.target.closest('.admin-actions-dropdown__toggle');
    if (toggle) {
      e.preventDefault();
      e.stopPropagation();
      var dropdown = toggle.closest('.admin-actions-dropdown');
      if (!dropdown) return;
      openDropdownFrom(dropdown);
      return;
    }

    if (
      !e.target.closest('.admin-actions-dropdown') &&
      !e.target.closest('.admin-actions-dropdown__menu')
    ) {
      closeAll(null, false);
    }
  }, true);

  document.addEventListener('keydown', function (e) {
    var open = document.querySelector('.admin-actions-dropdown.is-open');
    if (!open) return;

    if (e.key === 'Escape') {
      restoreMenu(open, true);
      return;
    }

    if (e.key === 'Tab') {
      var tabToggle = toggleOf(open);
      restoreMenu(open, false);
      if (tabToggle) tabToggle.focus();
      return;
    }

    if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp' && e.key !== 'Home' && e.key !== 'End') {
      return;
    }

    var items = menuItems(menuOf(open));
    if (!items.length) return;
    e.preventDefault();
    var idx = items.indexOf(document.activeElement);
    if (e.key === 'Home') {
      items[0].focus();
      return;
    }
    if (e.key === 'End') {
      items[items.length - 1].focus();
      return;
    }
    if (idx < 0) {
      items[e.key === 'ArrowUp' ? items.length - 1 : 0].focus();
      return;
    }
    if (e.key === 'ArrowDown') {
      items[(idx + 1) % items.length].focus();
      return;
    }
    items[(idx - 1 + items.length) % items.length].focus();
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', decorateAll);
  } else {
    decorateAll();
  }

  window.addEventListener('resize', function () {
    document.querySelectorAll('.admin-actions-dropdown.is-open').forEach(positionMenu);
  });

  document.addEventListener('scroll', function () {
    document.querySelectorAll('.admin-actions-dropdown.is-open').forEach(positionMenu);
  }, true);
})(window, document);
