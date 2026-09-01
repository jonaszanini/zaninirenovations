(function () {
  var form = document.getElementById('bi-form');
  if (!form) return;

  function isHidden(el) {
    return !!(el && el.closest('[hidden]'));
  }

  function syncReveals() {
    form.querySelectorAll('[data-bi-reveal]').forEach(function (el) {
      var spec = el.getAttribute('data-bi-reveal');
      if (!spec) return;
      var parts = spec.split('=');
      var name = parts[0];
      var want = parts.slice(1).join('=');
      var matched = false;
      form.querySelectorAll('[name="' + name + '"]').forEach(function (input) {
        if (input.type === 'radio' || input.type === 'checkbox') {
          if (input.checked && String(input.value) === want) matched = true;
        } else if (String(input.value) === want) {
          matched = true;
        }
      });
      el.hidden = !matched;
    });
    form.querySelectorAll('[data-bi-reveal-any]').forEach(function (el) {
      var names = (el.getAttribute('data-bi-reveal-any') || '').split(',');
      var on = names.some(function (name) {
        var input = form.querySelector('[name="' + name.trim() + '"]:checked');
        return input && String(input.value) === '1';
      });
      el.hidden = !on;
    });
  }

  function syncAreaMode(prefix) {
    var modeName = prefix === 'area_entry' ? 'area_entry_mode' : prefix + '_mode';
    var mode = form.querySelector('[name="' + modeName + '"]:checked');
    var wrap = form.querySelector('[data-bi-area="' + prefix + '"]');
    if (!wrap) return;
    var isDim = mode && mode.value === 'dimensions';
    var isSqft = mode && mode.value === 'sqft';
    wrap.querySelectorAll('[data-bi-sqft]').forEach(function (el) { el.hidden = !isSqft; });
    wrap.querySelectorAll('[data-bi-dims]').forEach(function (el) { el.hidden = !isDim; });
  }

  function syncHideWhen() {
    form.querySelectorAll('[data-bi-hide-when]').forEach(function (el) {
      var spec = el.getAttribute('data-bi-hide-when');
      if (!spec) return;
      var parts = spec.split('=');
      var name = parts[0];
      var want = parts.slice(1).join('=');
      var matched = false;
      form.querySelectorAll('[name="' + name + '"]').forEach(function (input) {
        if ((input.type === 'radio' || input.type === 'checkbox') && input.checked && String(input.value) === want) {
          matched = true;
        }
      });
      el.hidden = matched;
    });
  }

  function syncDisabled() {
    form.querySelectorAll('input, select, textarea').forEach(function (input) {
      if (input.type === 'hidden' || input.type === 'submit') return;
      input.disabled = isHidden(input);
    });
  }

  function syncAll() {
    syncReveals();
    syncHideWhen();
    syncAreaMode('area_entry');
    syncAreaMode('tile_floor');
    syncAreaMode('tile_shower');
    syncDisabled();
  }

  form.addEventListener('change', syncAll);

  form.addEventListener('submit', function (e) {
    syncAll();
    var groups = form.querySelectorAll('.bi-choices');
    for (var i = 0; i < groups.length; i++) {
      var group = groups[i];
      if (isHidden(group)) continue;
      var radios = group.querySelectorAll('input[type="radio"]:not(:disabled)');
      if (!radios.length) continue;
      var checked = false;
      radios.forEach(function (radio) {
        if (radio.checked) checked = true;
      });
      if (!checked) {
        e.preventDefault();
        radios[0].focus();
        if (typeof radios[0].reportValidity === 'function') {
          radios[0].reportValidity();
        }
        return;
      }
    }
  });

  syncAll();
})();
