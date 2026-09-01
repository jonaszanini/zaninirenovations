(function () {
  var form = document.getElementById('bi-form');
  if (!form) return;

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
    var mode = form.querySelector('[name="' + prefix + '_mode"]:checked, [name="' + prefix + '"]');
    if (prefix === 'area_entry') {
      mode = form.querySelector('[name="area_entry_mode"]:checked');
    }
    var wrap = form.querySelector('[data-bi-area="' + prefix + '"]');
    if (!mode || !wrap) return;
    var isDim = mode.value === 'dimensions';
    wrap.querySelectorAll('[data-bi-sqft]').forEach(function (el) { el.hidden = isDim; });
    wrap.querySelectorAll('[data-bi-dims]').forEach(function (el) { el.hidden = !isDim; });
  }

  form.addEventListener('change', function () {
    syncReveals();
    syncHideWhen();
    syncAreaMode('area_entry');
    syncAreaMode('tile_floor');
    syncAreaMode('tile_shower');
  });

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

  syncReveals();
  syncHideWhen();
  syncAreaMode('area_entry');
  syncAreaMode('tile_floor');
  syncAreaMode('tile_shower');
})();
