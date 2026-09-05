/**
 * Repeatable document-link rows on project Photos & files.
 */
(function (global) {
  'use strict';

  function nextIndex(rows) {
    var max = -1;
    rows.querySelectorAll('[data-cp-doc-link-row]').forEach(function (row) {
      var input = row.querySelector('input[name*="[url]"]');
      if (!input) return;
      var match = String(input.name).match(/document_links\[(\d+)\]/);
      if (match) max = Math.max(max, parseInt(match[1], 10));
    });
    return max + 1;
  }

  function reindexIds(row, index) {
    row.querySelectorAll('[id]').forEach(function (el) {
      el.id = String(el.id).replace(/__INDEX__|\d+$/, String(index));
    });
    row.querySelectorAll('label[for]').forEach(function (el) {
      el.setAttribute('for', String(el.getAttribute('for') || '').replace(/__INDEX__|\d+$/, String(index)));
    });
    row.querySelectorAll('[name]').forEach(function (el) {
      el.name = String(el.name).replace(/document_links\[(?:__INDEX__|\d+)\]/, 'document_links[' + index + ']');
    });
  }

  function syncRemoveButtons(rows) {
    var list = rows.querySelectorAll('[data-cp-doc-link-row]');
    list.forEach(function (row) {
      var btn = row.querySelector('[data-cp-doc-link-remove]');
      if (!btn) return;
      btn.hidden = list.length <= 1;
    });
  }

  function init(rootId) {
    var form = document.getElementById(rootId + '-form');
    var template = document.getElementById(rootId + '-row-template');
    if (!form || !template) return;

    var rows = form.querySelector('[data-cp-doc-link-rows]');
    var addBtn = form.querySelector('[data-cp-doc-link-add]');
    if (!rows || !addBtn) return;

    addBtn.addEventListener('click', function () {
      var index = nextIndex(rows);
      var wrap = document.createElement('div');
      wrap.innerHTML = template.innerHTML.replace(/__INDEX__/g, String(index));
      var row = wrap.querySelector('[data-cp-doc-link-row]');
      if (!row) return;
      reindexIds(row, index);
      rows.appendChild(row);
      syncRemoveButtons(rows);
      var urlInput = row.querySelector('input[name*="[url]"]');
      if (urlInput) urlInput.focus();
    });

    rows.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-cp-doc-link-remove]');
      if (!btn) return;
      var row = btn.closest('[data-cp-doc-link-row]');
      if (!row) return;
      if (rows.querySelectorAll('[data-cp-doc-link-row]').length <= 1) return;
      row.remove();
      syncRemoveButtons(rows);
    });

    syncRemoveButtons(rows);
  }

  global.AdminDocumentLinks = { init: init };
})(window);
