/**
 * Fill quotation client_name when a project is chosen in the link-to-project picker.
 */
(function () {
  function findQuotationClientNameInput() {
    var inputs = document.querySelectorAll('input[name="client_name"]');
    for (var i = 0; i < inputs.length; i++) {
      var inp = inputs[i];
      if (inp.closest('.fq-create-project-form')) {
        continue;
      }
      return inp;
    }
    return null;
  }

  function fillQuotationClientName(clientName, force) {
    var name = String(clientName || '').trim();
    if (!name) return;
    var input = findQuotationClientNameInput();
    if (!input) return;
    if (!force && String(input.value || '').trim() !== '') return;
    input.value = name;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function pickerRoot(el) {
    return el && el.closest ? el.closest('[data-fq-project-picker]') : null;
  }

  document.addEventListener('admin-search-select:change', function (e) {
    var root = pickerRoot(e.target);
    if (!root) return;
    var opt = e.detail && e.detail.option;
    fillQuotationClientName(opt && opt.client_name, false);
  });

  document.querySelectorAll('[data-fq-project-picker]').forEach(function (root) {
    var form = root.closest('form');
    if (!form) return;
    form.addEventListener('submit', function (e) {
      var val = window.AdminSearchSelect ? AdminSearchSelect.getValue(root) : '';
      if (!val) {
        e.preventDefault();
        var input = root.querySelector('.admin-search-select__input');
        if (input) input.focus();
        return;
      }
      var opt = window.AdminSearchSelect ? AdminSearchSelect.getOption(root) : null;
      if (opt) fillQuotationClientName(opt.client_name, true);
    });
  });
})();
