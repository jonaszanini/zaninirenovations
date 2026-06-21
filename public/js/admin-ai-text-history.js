(function (global) {
  'use strict';

  function pad(n) {
    return n < 10 ? '0' + n : String(n);
  }

  function formatWhen(iso) {
    if (!iso) return '';
    var d = new Date(iso);
    if (isNaN(d.getTime())) return '';
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())
      + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
  }

  function escapeHtml(text) {
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function defaultPreview(text) {
    var t = String(text || '').replace(/\s+/g, ' ').trim();
    if (t.length > 220) {
      return t.slice(0, 219) + '…';
    }
    return t;
  }

  function loadState(storageKey) {
    try {
      var raw = sessionStorage.getItem(storageKey);
      if (!raw) return { versions: [], activeId: null };
      var parsed = JSON.parse(raw);
      if (!parsed || !Array.isArray(parsed.versions)) {
        return { versions: [], activeId: null };
      }
      return parsed;
    } catch (ignore) {
      return { versions: [], activeId: null };
    }
  }

  function saveState(storageKey, state) {
    try {
      sessionStorage.setItem(storageKey, JSON.stringify(state));
    } catch (ignore) {
      /* quota or private mode */
    }
  }

  function nextVersionLabel(state) {
    var aiCount = 0;
    state.versions.forEach(function (v) {
      if (v.id !== 'original') aiCount += 1;
    });
    return 'Version ' + (aiCount + 1);
  }

  function createAiTextHistory(config) {
    var storageKey = config.storageKey || 'ai-text-history';
    var getText = config.getText;
    var setText = config.setText;
    var formatPreview = config.formatPreview || defaultPreview;
    var mountEl = config.mountEl;
    var title = config.title || 'AI version history';

    if (typeof getText !== 'function' || typeof setText !== 'function' || !mountEl) {
      return null;
    }

    var state = loadState(storageKey);
    var modalId = storageKey.replace(/[^a-zA-Z0-9_-]/g, '_') + '-history-modal';
    var backdrop = null;
    var listEl = null;
    var historyBtn = null;

    function persist() {
      saveState(storageKey, state);
      refreshButton();
    }

    function refreshButton() {
      if (!historyBtn) return;
      var count = state.versions.length;
      historyBtn.hidden = count === 0;
      var badge = historyBtn.querySelector('.admin-ai-text-history-btn__count');
      if (badge) {
        badge.textContent = count > 0 ? String(count) : '';
        badge.hidden = count < 2;
      }
    }

    function renderList() {
      if (!listEl) return;
      if (!state.versions.length) {
        listEl.innerHTML = '<p class="admin-ai-text-history-empty">No versions saved yet. Run AI improvement to build history.</p>';
        return;
      }

      var html = '';
      state.versions.forEach(function (version) {
        var isActive = version.id === state.activeId;
        var preview = formatPreview(version.text);
        html += '<article class="admin-ai-text-history-item' + (isActive ? ' is-active' : '') + '" data-version-id="' + escapeHtml(version.id) + '">'
          + '<div class="admin-ai-text-history-item__head">'
          + '<strong class="admin-ai-text-history-item__label">' + escapeHtml(version.label) + '</strong>'
          + '<time class="admin-ai-text-history-item__when">' + escapeHtml(formatWhen(version.at)) + '</time>'
          + '</div>'
          + '<p class="admin-ai-text-history-item__preview">' + escapeHtml(preview) + '</p>'
          + '<button type="button" class="admin-btn admin-btn--ghost admin-btn--sm admin-ai-text-history-item__use" data-version-id="' + escapeHtml(version.id) + '">'
          + (isActive ? 'Current' : 'Use this version')
          + '</button>'
          + '</article>';
      });
      listEl.innerHTML = html;

      listEl.querySelectorAll('.admin-ai-text-history-item__use').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var id = btn.getAttribute('data-version-id');
          restoreVersion(id);
        });
      });
    }

    function openModal() {
      if (!backdrop) return;
      renderList();
      backdrop.hidden = false;
      document.body.classList.add('admin-ai-text-history-open');
      var closeBtn = backdrop.querySelector('[data-ai-history-close]');
      if (closeBtn) closeBtn.focus();
    }

    function closeModal() {
      if (!backdrop) return;
      backdrop.hidden = true;
      document.body.classList.remove('admin-ai-text-history-open');
    }

    function restoreVersion(id) {
      var version = null;
      state.versions.forEach(function (v) {
        if (v.id === id) version = v;
      });
      if (!version) return;
      setText(version.text);
      state.activeId = id;
      persist();
      renderList();
      closeModal();
    }

    function buildUi() {
      historyBtn = document.createElement('button');
      historyBtn.type = 'button';
      historyBtn.className = 'admin-ai-text-history-btn';
      historyBtn.setAttribute('aria-label', 'View AI version history');
      historyBtn.title = 'Version history';
      historyBtn.innerHTML = '<i class="fa fa-history" aria-hidden="true"></i><span class="admin-ai-text-history-btn__count" hidden></span>';
      historyBtn.addEventListener('click', openModal);
      mountEl.appendChild(historyBtn);

      backdrop = document.createElement('div');
      backdrop.className = 'admin-ai-text-history-backdrop';
      backdrop.id = modalId;
      backdrop.hidden = true;
      backdrop.innerHTML = ''
        + '<div class="admin-ai-text-history-modal" role="dialog" aria-modal="true" aria-labelledby="' + modalId + '-title">'
        + '<div class="admin-ai-text-history-modal__header">'
        + '<h3 class="admin-ai-text-history-modal__title" id="' + modalId + '-title">' + escapeHtml(title) + '</h3>'
        + '<button type="button" class="admin-ai-text-history-modal__close" data-ai-history-close aria-label="Close">&times;</button>'
        + '</div>'
        + '<div class="admin-ai-text-history-modal__body">'
        + '<p class="admin-form-help admin-ai-text-history-modal__lede">Original text and each AI result are saved for this session. Restore any version into the field.</p>'
        + '<div class="admin-ai-text-history-list"></div>'
        + '</div>'
        + '<div class="admin-ai-text-history-modal__footer">'
        + '<button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-ai-history-close>Close</button>'
        + '</div>'
        + '</div>';
      document.body.appendChild(backdrop);
      listEl = backdrop.querySelector('.admin-ai-text-history-list');

      backdrop.addEventListener('click', function (e) {
        if (e.target === backdrop || (e.target.matches && e.target.matches('[data-ai-history-close]'))) {
          closeModal();
        }
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && backdrop && !backdrop.hidden) {
          closeModal();
        }
      });

      refreshButton();
    }

    buildUi();

    return {
      captureOriginalIfNeeded: function () {
        if (state.versions.some(function (v) { return v.id === 'original'; })) {
          return;
        }
        var text = getText();
        if (!String(text || '').trim()) {
          return;
        }
        state.versions.unshift({
          id: 'original',
          label: 'Original',
          text: text,
          at: new Date().toISOString(),
          source: 'user',
        });
        state.activeId = 'original';
        persist();
      },
      addVersion: function (text, label) {
        if (!String(text || '').trim()) {
          return null;
        }
        var id = 'v' + Date.now() + '-' + Math.random().toString(36).slice(2, 7);
        var version = {
          id: id,
          label: label || nextVersionLabel(state),
          text: text,
          at: new Date().toISOString(),
          source: 'ai',
        };
        state.versions.push(version);
        state.activeId = id;
        persist();
        return version;
      },
      setActiveFromField: function () {
        var current = getText();
        var match = null;
        state.versions.forEach(function (v) {
          if (v.text === current) match = v;
        });
        if (match) {
          state.activeId = match.id;
          persist();
        }
      },
      open: openModal,
      count: function () {
        return state.versions.length;
      },
      clear: function () {
        state.versions = [];
        state.activeId = null;
        persist();
      },
    };
  }

  global.AdminAiTextHistory = {
    create: createAiTextHistory,
    defaultPreview: defaultPreview,
  };
})(window);
