(function () {
  var PUBLISH_ONLY_REQUIRED = 'input[name="payment_installments"], input[name="include_debris_removal"], input[name="debris_removal_amount"], input[name="ask_reply_to_accept"]';

  function relaxPublishOnlyRequired(form) {
    if (!form || !form.querySelectorAll) {
      return;
    }
    form.querySelectorAll(PUBLISH_ONLY_REQUIRED).forEach(function (el) {
      if (el.required) {
        el.setAttribute('data-qh-publish-required', '1');
        el.required = false;
      }
    });
  }

  function isDraftSubmit(form) {
    var intent = form.querySelector('input[name="save_intent"]');
    if (intent && intent.value === 'draft') {
      return true;
    }
    var isDraft = form.querySelector('input[name="is_draft"]');
    return !!(isDraft && (isDraft.value === '1' || isDraft.value === 'true'));
  }

  window.AdminQuoteDraftValidation = {
    relax: relaxPublishOnlyRequired,
    isDraftSubmit: isDraftSubmit,
  };

  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('.qh-actions__draft, [id$="-btn-draft"]');
    if (!btn) {
      return;
    }
    var form = btn.form
      || (btn.getAttribute('form') ? document.getElementById(btn.getAttribute('form')) : null)
      || editorForm();
    if (form) {
      relaxPublishOnlyRequired(form);
    }
  }, true);

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || !form.matches || !form.matches('form[data-quotation-editor]')) {
      return;
    }
    if (isDraftSubmit(form)) {
      relaxPublishOnlyRequired(form);
    }
  }, true);

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

  function fieldNumber(form, name) {
    var el = form.querySelector('[name="' + name + '"]');
    if (!el) {
      return 0;
    }
    return parseFloat(String(el.value || '').replace(/,/g, '')) || 0;
  }

  function fieldValue(form, name) {
    var el = form.querySelector('[name="' + name + '"]');
    return el ? String(el.value || '').trim() : '';
  }

  function anyNumberAtLeast(form, selector, min) {
    var nodes = form.querySelectorAll(selector);
    for (var i = 0; i < nodes.length; i++) {
      if ((parseFloat(String(nodes[i].value || '').replace(/,/g, '')) || 0) >= min) {
        return true;
      }
    }
    return false;
  }

  function markFormEdited(form) {
    if (form) {
      form.setAttribute('data-quotation-user-edited', '1');
    }
  }

  function canAutosave(form) {
    if (typeof form.checkValidity === 'function') {
      relaxPublishOnlyRequired(form);
      if (!form.checkValidity()) {
        return false;
      }
    }

    // Untouched new quotes (including ones prefilled from a project) should not
    // POST — confirm leave instead of creating a quote the admin never filled in.
    if (form.getAttribute('data-quotation-user-edited') !== '1') {
      return false;
    }

    var client = form.querySelector('[name="client_name"]');
    var clientFilled = !!(client && String(client.value || '').trim());

    // Tile: client name is optional; measurements are required to persist.
    if (form.querySelector('[name="total_area_sqft"]')) {
      var area = fieldNumber(form, 'total_area_sqft');
      var length = fieldNumber(form, 'length_ft');
      var width = fieldNumber(form, 'width_ft');
      return area > 0 || (length > 0 && width > 0);
    }

    if (client && !clientFilled) {
      return false;
    }

    // Flooring
    if (form.querySelector('[name="flooring_type_id"]')) {
      if (!fieldValue(form, 'flooring_type_id') || fieldNumber(form, 'square_footage') <= 0) {
        return false;
      }
    }

    // Painting
    if (form.querySelector('[name="total_property_sqf"]')) {
      if (fieldNumber(form, 'total_property_sqf') <= 0 || !fieldValue(form, 'paint_line_id')) {
        return false;
      }
    }

    // Stairs
    if (form.querySelector('[name="total_steps"]')) {
      if (fieldNumber(form, 'total_steps') < 1) {
        return false;
      }
      if (!form.querySelector('[name="material_type_id"]:checked')) {
        return false;
      }
      if (!anyNumberAtLeast(form, '[name^="segments["][name$="[qty]"]', 1)) {
        return false;
      }
    }

    // Fence
    if (form.querySelector('[name^="sides["][name$="[length_ft]"]')) {
      if (!anyNumberAtLeast(form, '[name^="sides["][name$="[length_ft]"]', 0.5)) {
        return false;
      }
    }

    // Low deck + shed
    if (form.querySelector('#ld-width, #sh-length')) {
      if (fieldNumber(form, 'width_ft') <= 0 || fieldNumber(form, 'length_ft') <= 0) {
        return false;
      }
    }

    // Paving
    if (form.querySelector('[name^="stone_entries["]')) {
      var stoneOk = anyNumberAtLeast(form, '[name^="stone_entries["][name$="[qty]"]', 1)
        || anyNumberAtLeast(form, '[name^="stone_entries["][name$="[area_sq_ft]"]', 0.01)
        || (
          anyNumberAtLeast(form, '[name^="stone_entries["][name$="[length_ft]"]', 0.01)
          && anyNumberAtLeast(form, '[name^="stone_entries["][name$="[width_ft]"]', 0.01)
        );
      if (!stoneOk) {
        return false;
      }
    }

    // Basement
    if (form.querySelector('[name="area_sqft"], [name="partition_lf"]')) {
      var preset = form.querySelector('[name="scope_preset"]:checked');
      var isWall = !!(preset && preset.value === 'wall');
      if (isWall) {
        if (fieldNumber(form, 'partition_lf') <= 0) {
          return false;
        }
      } else if (
        fieldNumber(form, 'area_sqft') <= 0
        && !(fieldNumber(form, 'area_length_ft') > 0 && fieldNumber(form, 'area_width_ft') > 0)
      ) {
        return false;
      }
    }

    // Millwork
    if (form.querySelector('[name^="pieces["]')) {
      var hasPieceDims = anyNumberAtLeast(form, '[name^="pieces["][name$="[width]"]', 0.01)
        || anyNumberAtLeast(form, '[name^="pieces["][name$="[length]"]', 0.01)
        || anyNumberAtLeast(form, '[name^="pieces["][name$="[height]"]', 0.01)
        || anyNumberAtLeast(form, '[name^="pieces["][name$="[depth]"]', 0.01)
        || anyNumberAtLeast(form, '[name^="pieces["][name*="[sets]"][name$="[length]"]', 0.01)
        || anyNumberAtLeast(form, '[name^="pieces["][name*="[sets]"][name$="[width]"]', 0.01);
      if (!hasPieceDims) {
        return false;
      }
    }

    // Trimming
    if (form.querySelector('[name="enabled_families[]"]')) {
      if (!form.querySelector('[name="enabled_families[]"]:checked')) {
        return false;
      }
      if (
        fieldNumber(form, 'door_qty') <= 0
        && fieldNumber(form, 'window_qty') <= 0
        && !anyNumberAtLeast(form, '.tm-family-body input[type="number"]', 0.01)
      ) {
        return false;
      }
    }

    // Standard quotation: HTML required client + title is enough to persist.
    return true;
  }

  function confirmDialog(message, options) {
    if (window.AdminDialog && typeof window.AdminDialog.confirm === 'function') {
      return window.AdminDialog.confirm(message, options);
    }
    return Promise.resolve(window.confirm(message));
  }

  function confirmLeaveUnsaved() {
    return confirmDialog('This quotation is missing required information and has not been saved. Still want to exit?', {
      title: 'Quotation not saved yet',
      confirmLabel: 'Yes, exit',
      cancelLabel: 'No, stay',
    });
  }

  function confirmDiscardChanges() {
    return confirmDialog('Discard changes?', {
      title: 'Discard changes?',
      confirmLabel: 'Discard',
      cancelLabel: 'Keep editing',
    });
  }

  function isFormDirty(form) {
    return !!(form && form.getAttribute('data-quotation-user-edited') === '1');
  }

  function leaveWithoutSaving(dest) {
    window.location.assign(dest.href);
  }

  function submitLeave(form, dest, attachReturn) {
    if (isNewQuotation(form) && !canAutosave(form)) {
      confirmLeaveUnsaved().then(function (ok) {
        if (ok) {
          leaveWithoutSaving(dest);
        }
      });
      return;
    }
    if (typeof tinymce !== 'undefined' && typeof tinymce.triggerSave === 'function') {
      tinymce.triggerSave();
    }
    preserveDraft(form);
    relaxPublishOnlyRequired(form);
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

    var cancelBtn = e.target.closest('[data-qh-cancel]');
    if (cancelBtn) {
      var cancelHref = cancelBtn.getAttribute('data-qh-cancel');
      var cancelDest = destination(cancelHref);
      if (!cancelDest) {
        return;
      }
      e.preventDefault();
      if (isFormDirty(form)) {
        confirmDiscardChanges().then(function (ok) {
          if (ok) {
            leaveWithoutSaving(cancelDest);
          }
        });
        return;
      }
      leaveWithoutSaving(cancelDest);
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

  function markEditedFromEvent(e) {
    if (!e.isTrusted) {
      return;
    }
    var form = editorForm();
    if (!form || !e.target || !form.contains(e.target)) {
      return;
    }
    markFormEdited(form);
  }

  document.addEventListener('input', markEditedFromEvent, true);
  document.addEventListener('change', markEditedFromEvent, true);
  document.addEventListener('admin-search-select:change', function () {
    markFormEdited(editorForm());
  });
})();
