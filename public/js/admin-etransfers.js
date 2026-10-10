(function () {
  function openPaste() {
    var modal = document.getElementById('et-paste-modal');
    if (!modal) return;
    modal.hidden = false;
    document.body.classList.add('cp-modal-open');
    var ta = document.getElementById('et-raw-text');
    if (ta) ta.focus();
  }

  function closePaste() {
    var modal = document.getElementById('et-paste-modal');
    if (!modal) return;
    modal.hidden = true;
    document.body.classList.remove('cp-modal-open');
  }

  document.addEventListener('click', function (e) {
    var openBtn = e.target.closest('[data-et-open-paste]');
    if (openBtn) {
      e.preventDefault();
      openPaste();
      return;
    }
    var closeBtn = e.target.closest('[data-et-close-paste]');
    if (closeBtn) {
      e.preventDefault();
      closePaste();
      return;
    }
    var modal = document.getElementById('et-paste-modal');
    if (modal && e.target === modal) {
      closePaste();
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closePaste();
  });

  document.addEventListener('admin-search-select:change', function (e) {
    var root = e.target;
    if (!root || !root.closest) return;
    var form = root.closest('[data-et-record]');
    if (!form) return;
    var btn = form.id
      ? document.querySelector('button[type="submit"][form="' + form.id + '"]')
      : form.querySelector('button[type="submit"]');
    if (!btn) return;
    var id = e.detail && e.detail.id;
    btn.disabled = !id;
    var card = form.closest('.et-card');
    var note = card ? card.querySelector('[data-et-record-note]') : null;
    if (note) {
      note.hidden = !!id;
    }
  });
})();
