/**
 * Searchable project picker for quotation "link to existing project" forms.
 */
(function () {
  function norm(s) {
    return String(s || '').toLowerCase().trim();
  }

  function initPicker(root) {
    if (!root || root.getAttribute('data-fq-picker-init') === '1') {
      return;
    }
    root.setAttribute('data-fq-picker-init', '1');

    var projects = [];
    try {
      projects = JSON.parse(root.getAttribute('data-projects') || '[]');
    } catch (e) {
      projects = [];
    }
    if (!projects.length) {
      return;
    }

    var search = root.querySelector('.fq-project-picker__search');
    var select = root.querySelector('.fq-project-picker__select');
    var list = root.querySelector('.fq-project-picker__list');
    var selectedHint = root.querySelector('.fq-project-picker__selected');
    var selectedText = root.querySelector('.fq-project-picker__selected-text');
    if (!search || !select || !list) {
      return;
    }

    var activeIndex = -1;
    var filtered = projects.slice();

    function labelForId(id) {
      var hit = projects.find(function (p) {
        return String(p.id) === String(id);
      });
      return hit ? hit.label : '';
    }

    function syncSelectedHint() {
      var lab = labelForId(select.value);
      if (!selectedHint || !selectedText) {
        return;
      }
      if (lab) {
        selectedText.textContent = lab;
        selectedHint.hidden = false;
        search.value = lab;
      } else {
        selectedHint.hidden = true;
        selectedText.textContent = '';
      }
    }

    function setExpanded(open) {
      search.setAttribute('aria-expanded', open ? 'true' : 'false');
      list.hidden = !open;
    }

    function renderList() {
      list.innerHTML = '';
      if (!filtered.length) {
        var empty = document.createElement('li');
        empty.className = 'fq-project-picker__empty';
        empty.setAttribute('role', 'option');
        empty.textContent = 'No projects match your search.';
        list.appendChild(empty);
        activeIndex = -1;
        return;
      }

      filtered.forEach(function (p, idx) {
        var li = document.createElement('li');
        li.className = 'fq-project-picker__option';
        li.setAttribute('role', 'option');
        li.setAttribute('data-id', String(p.id));
        li.id = root.id + '-opt-' + p.id;
        li.textContent = p.label;
        if (String(select.value) === String(p.id)) {
          li.classList.add('is-selected');
        }
        if (idx === activeIndex) {
          li.classList.add('is-active');
          search.setAttribute('aria-activedescendant', li.id);
        }
        li.addEventListener('mousedown', function (e) {
          e.preventDefault();
        });
        li.addEventListener('click', function () {
          choose(p);
        });
        list.appendChild(li);
      });
    }

    function filterList() {
      var q = norm(search.value);
      if (!q) {
        filtered = projects.slice();
      } else {
        filtered = projects.filter(function (p) {
          return norm(p.label).indexOf(q) !== -1;
        });
      }
      activeIndex = filtered.length ? 0 : -1;
      renderList();
    }

    function choose(project) {
      select.value = String(project.id);
      search.value = project.label;
      syncSelectedHint();
      setExpanded(false);
      activeIndex = -1;
      search.setAttribute('aria-activedescendant', '');
    }

    function clearChoice() {
      select.value = '';
      syncSelectedHint();
    }

    function openList() {
      filterList();
      setExpanded(true);
    }

    search.addEventListener('focus', function () {
      if (select.value) {
        search.select();
      }
      openList();
    });

    search.addEventListener('input', function () {
      if (select.value && norm(search.value) !== norm(labelForId(select.value))) {
        clearChoice();
      }
      openList();
    });

    search.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (list.hidden) {
          openList();
          return;
        }
        if (filtered.length) {
          activeIndex = Math.min(activeIndex + 1, filtered.length - 1);
          renderList();
        }
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (filtered.length) {
          activeIndex = Math.max(activeIndex - 1, 0);
          renderList();
        }
      } else if (e.key === 'Enter') {
        if (!list.hidden && activeIndex >= 0 && filtered[activeIndex]) {
          e.preventDefault();
          choose(filtered[activeIndex]);
        }
      } else if (e.key === 'Escape') {
        setExpanded(false);
        activeIndex = -1;
        search.setAttribute('aria-activedescendant', '');
      }
    });

    document.addEventListener('click', function (e) {
      if (!root.contains(e.target)) {
        setExpanded(false);
        activeIndex = -1;
        if (select.value) {
          search.value = labelForId(select.value);
        }
        syncSelectedHint();
      }
    });

    var form = root.closest('form');
    if (form) {
      form.addEventListener('submit', function (e) {
        if (!select.value) {
          e.preventDefault();
          search.focus();
          openList();
        }
      });
    }

    syncSelectedHint();
  }

  function initAll() {
    document.querySelectorAll('[data-fq-project-picker]').forEach(initPicker);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }
})();
