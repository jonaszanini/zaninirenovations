(function () {
  function editorForm() {
    return document.querySelector('form[data-quotation-editor]');
  }

  function hiddenInput(form, name, value) {
    var el = form.querySelector('input[name="' + name + '"]');
    if (!el) {
      el = document.createElement('input');
      el.type = 'hidden';
      el.name = name;
      form.appendChild(el);
    }
    el.value = value;
  }

  function clearLeaveFields(form) {
    ['after_save_url', 'after_save_attach_return'].forEach(function (name) {
      var el = form.querySelector('input[name="' + name + '"]');
      if (el) {
        el.value = '';
      }
    });
  }

  function isPriceConfigPath(pathname) {
    return /price-configuration(?:\/|$)/.test(pathname)
      || /\/quotations\/fence\/config(?:\/|$)/.test(pathname)
      || /\/quotations\/low-deck\/config(?:\/|$)/.test(pathname);
  }

  function destination(href) {
    try {
      return new URL(href, window.location.href);
    } catch (err) {
      return null;
    }
  }

  function shouldLeaveSave(anchor, form) {
    if (!anchor || !form) {
      return false;
    }
    if (anchor.hasAttribute('data-quotation-leave-skip')) {
      return false;
    }
    if (anchor.target && anchor.target !== '_self') {
      return false;
    }
    if (anchor.hasAttribute('download')) {
      return false;
    }

    var href = anchor.getAttribute('href');
    if (!href || href.charAt(0) === '#' || /^(javascript:|mailto:|tel:)/i.test(href)) {
      return false;
    }

    var dest = destination(href);
    if (!dest || dest.origin !== window.location.origin) {
      return false;
    }
    if (dest.pathname === window.location.pathname && dest.search === window.location.search) {
      return false;
    }

    var nested = anchor.closest('form');
    if (nested && nested !== form) {
      return false;
    }

    if (/purchase-list|layout-diagrams|cut-list/.test(dest.pathname)) {
      return false;
    }

    return true;
  }

  function preserveDraft(form) {
    if (form.getAttribute('data-quotation-is-draft') !== '1') {
      return;
    }
    var intent = form.querySelector('input[name="save_intent"]');
    if (intent) {
      intent.value = 'draft';
    }
    var isDraft = form.querySelector('input[name="is_draft"]');
    if (isDraft) {
      isDraft.value = '1';
    }
  }

  function isNewQuotation(form) {
    if (form.getAttribute('data-br-mode') === 'create') {
      return true;
    }
    if (form.getAttribute('data-br-mode') === 'edit') {
      return false;
    }
    var method = form.querySelector('input[name="_method"]');
    return !(method && String(method.value).toUpperCase() === 'PUT');
  }

  function canAutosave(form) {
    var client = form.querySelector('[name="client_name"]');
    if (client && !String(client.value || '').trim()) {
      return false;
    }
    if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
      return false;
    }
    return true;
  }

  function notifyCannotAutosave() {
    var message = 'This quotation does not have enough information to save yet. Fill in the required fields, then try again.';
    var options = { title: 'Cannot save yet' };
    if (window.AdminDialog && typeof window.AdminDialog.alert === 'function') {
      window.AdminDialog.alert(message, options);
      return;
    }
    window.alert(message);
  }

  function submitLeave(form, dest, attachReturn) {
    if (isNewQuotation(form) && !canAutosave(form)) {
      notifyCannotAutosave();
      return;
    }
    if (typeof tinymce !== 'undefined' && typeof tinymce.triggerSave === 'function') {
      tinymce.triggerSave();
    }
    preserveDraft(form);
    hiddenInput(form, 'after_save_url', dest.pathname + dest.search + dest.hash);
    hiddenInput(form, 'after_save_attach_return', attachReturn ? '1' : '0');
    if (typeof form.requestSubmit === 'function') {
      form.requestSubmit();
    } else {
      form.submit();
    }
  }

  document.addEventListener('click', function (e) {
    var form = editorForm();
    if (!form) {
      return;
    }

    var btn = e.target.closest('button, input[type="submit"]');
    if (btn) {
      var btnForm = btn.form || (btn.getAttribute('form') ? document.getElementById(btn.getAttribute('form')) : btn.closest('form'));
      if (btnForm === form && (btn.type === 'submit' || /btn-draft|btn-publish/.test(btn.id || ''))) {
        clearLeaveFields(form);
      }
      return;
    }

    var anchor = e.target.closest('a[href]');
    if (!anchor || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
      return;
    }

    var content = document.querySelector('.admin-content');
    if (!content || !content.contains(anchor)) {
      return;
    }
    if (!shouldLeaveSave(anchor, form)) {
      return;
    }

    var dest = destination(anchor.getAttribute('href'));
    if (!dest) {
      return;
    }

    e.preventDefault();
    submitLeave(
      form,
      dest,
      anchor.hasAttribute('data-quotation-leave-return') || isPriceConfigPath(dest.pathname)
    );
  });
})();
