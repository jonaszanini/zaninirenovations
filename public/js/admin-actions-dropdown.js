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

  function setExpanded(dropdown, open) {
    var toggle = toggleOf(dropdown);
    if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  function restoreMenu(dropdown, returnFocus) {
    var menu = dropdown._portaledMenu;
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
    if (returnFocus) {
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
    menu.style.zIndex = '400';

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
    document.querySelectorAll('.admin-actions-dropdown__toggle').forEach(function (btn) {
      if (!btn.hasAttribute('aria-expanded')) {
        btn.setAttribute('aria-expanded', 'false');
      }
      if (!btn.hasAttribute('aria-haspopup')) {
        btn.setAttribute('aria-haspopup', 'menu');
      }
    });
    var wasOpen = dropdown.classList.contains('is-open');
    closeAll(null, false);
    if (!wasOpen) openDropdown(dropdown);
  }

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
  });

  document.addEventListener('keydown', function (e) {
    var open = document.querySelector('.admin-actions-dropdown.is-open');
    if (e.key === 'Escape') {
      if (open) restoreMenu(open, true);
      return;
    }

    if (e.key !== 'Tab' || !open) return;
    var items = menuItems(menuOf(open));
    if (!items.length) return;
    var first = items[0];
    var last = items[items.length - 1];
    var active = document.activeElement;
    if (e.shiftKey && (active === first || !menuOf(open).contains(active))) {
      e.preventDefault();
      last.focus();
      return;
    }
    if (!e.shiftKey && (active === last || !menuOf(open).contains(active))) {
      e.preventDefault();
      first.focus();
    }
  });

  window.addEventListener('resize', function () {
    document.querySelectorAll('.admin-actions-dropdown.is-open').forEach(positionMenu);
  });

  document.addEventListener('scroll', function () {
    document.querySelectorAll('.admin-actions-dropdown.is-open').forEach(positionMenu);
  }, true);
})(window, document);
