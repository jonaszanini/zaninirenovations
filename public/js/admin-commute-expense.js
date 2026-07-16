(function () {
  'use strict';

  function formatMoney(n) {
    var v = Number(n || 0);
    return 'CA$' + v.toLocaleString('en-CA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function parseDecimal(raw) {
    var s = String(raw == null ? '' : raw).replace(/[^\d.]/g, '');
    if (s === '' || s === '.') return 0;
    var n = parseFloat(s);
    return isFinite(n) ? n : 0;
  }

  function parseIntTrips(raw) {
    var s = String(raw == null ? '' : raw).replace(/\D/g, '');
    if (s === '') return 1;
    var n = parseInt(s, 10);
    return isFinite(n) && n > 0 ? n : 1;
  }

  function initCommuteExpense(card) {
    var includeYes = card.querySelector('[data-cp-commute-include-yes]');
    var includeNo = card.querySelector('[data-cp-commute-include-no]');
    // Back-compat: older checkbox markup
    var includeCb = card.querySelector('[data-cp-commute-include]');
    var bodyEl = card.querySelector('[data-cp-commute-body]');
    var percentInput = card.querySelector('[data-cp-commute-percent]');
    var tripsInput = card.querySelector('[data-cp-commute-trips]');
    var roundTripEl = card.querySelector('[data-cp-commute-round-trip]');
    var roundTripHelp = card.querySelector('[data-cp-commute-round-trip-help]');
    var addedEl = card.querySelector('[data-cp-commute-added]');
    var hiddenRoundTrip = card.querySelector('[data-cp-commute-round-trip-hidden]');
    var statusEl = card.querySelector('[data-cp-commute-status]');
    var urlTemplate = card.getAttribute('data-travel-url-template') || '';
    var projectId = card.getAttribute('data-initial-project-id') || '';
    var roundTripCost = null;
    var loading = false;

    function isIncluded() {
      if (includeYes) return !!includeYes.checked;
      if (includeCb) return !!includeCb.checked;
      return false;
    }

    function travelUrl(id) {
      if (!urlTemplate || !id) return '';
      return urlTemplate.replace('__ID__', String(id));
    }

    function resolveProjectId() {
      if (projectId) return projectId;
      var hiddenLink = document.querySelector('input[name="link_client_project_id"]');
      if (hiddenLink && hiddenLink.value) return hiddenLink.value;
      var select = document.querySelector('[name="client_project_id"]');
      if (select && select.value) return select.value;
      return '';
    }

    function setStatus(msg, isError) {
      if (!statusEl) return;
      statusEl.textContent = msg || '';
      statusEl.classList.toggle('admin-form-error', !!isError);
    }

    function computeAmount() {
      if (!isIncluded()) return 0;
      if (roundTripCost === null || roundTripCost <= 0) return 0;
      var pct = parseDecimal(percentInput ? percentInput.value : 100);
      pct = Math.max(0, Math.min(999, pct));
      var trips = parseIntTrips(tripsInput ? tripsInput.value : 1);
      return Math.round(roundTripCost * (pct / 100) * trips * 100) / 100;
    }

    function emitChange() {
      var amount = computeAmount();
      var detail = {
        amount: amount,
        included: isIncluded(),
        roundTrip: roundTripCost,
        percent: parseDecimal(percentInput ? percentInput.value : 100),
        trips: parseIntTrips(tripsInput ? tripsInput.value : 1)
      };
      if (addedEl) addedEl.textContent = formatMoney(amount);
      document.dispatchEvent(new CustomEvent('commute-expense:change', { detail: detail }));
      if (window.CommuteExpense && typeof window.CommuteExpense._notify === 'function') {
        window.CommuteExpense._notify(detail);
      }
    }

    function showRoundTrip() {
      if (!roundTripEl) return;
      if (roundTripCost !== null && roundTripCost > 0) {
        roundTripEl.textContent = formatMoney(roundTripCost);
        if (roundTripHelp) roundTripHelp.hidden = true;
      } else if (loading) {
        roundTripEl.textContent = 'Calculating…';
      } else {
        roundTripEl.textContent = '—';
      }
    }

    function loadEstimate() {
      var id = resolveProjectId();
      projectId = id;
      if (!id || !travelUrl(id)) {
        roundTripCost = null;
        var saved = card.getAttribute('data-saved-round-trip');
        if (saved !== '' && isFinite(parseFloat(saved))) {
          roundTripCost = parseFloat(saved);
          if (hiddenRoundTrip) hiddenRoundTrip.value = roundTripCost.toFixed(2);
        }
        showRoundTrip();
        if (!id) {
          setStatus('Link this quotation to a project with a geocoded service address to load the round-trip fuel estimate.', false);
        } else {
          setStatus('Could not load travel estimate for this project.', true);
        }
        emitChange();
        return;
      }

      loading = true;
      showRoundTrip();
      setStatus('', false);

      fetch(travelUrl(id), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          loading = false;
          if (data.ok && data.fuel_cost != null) {
            roundTripCost = Number(data.fuel_cost);
            if (hiddenRoundTrip) hiddenRoundTrip.value = roundTripCost.toFixed(2);
            card.setAttribute('data-saved-round-trip', roundTripCost.toFixed(2));
            setStatus('', false);
          } else {
            roundTripCost = null;
            setStatus(data.message || 'Travel estimate unavailable.', true);
          }
          showRoundTrip();
          emitChange();
        })
        .catch(function () {
          loading = false;
          roundTripCost = null;
          showRoundTrip();
          setStatus('Could not load travel estimate.', true);
          emitChange();
        });
    }

    function syncBodyVisibility() {
      if (!bodyEl) return;
      bodyEl.hidden = !isIncluded();
      card.querySelectorAll('.q-commute-choice__option').forEach(function (opt) {
        var input = opt.querySelector('input');
        opt.classList.toggle('is-selected', !!(input && input.checked));
      });
    }

    function bindNumericInput(input, sanitizer) {
      if (!input) return;
      input.addEventListener('input', function () {
        var cleaned = sanitizer(input.value);
        if (input.value !== cleaned) input.value = cleaned;
        emitChange();
      });
      input.addEventListener('change', emitChange);
    }

    function onIncludeChange() {
      syncBodyVisibility();
      emitChange();
    }

    if (includeYes) includeYes.addEventListener('change', onIncludeChange);
    if (includeNo) includeNo.addEventListener('change', onIncludeChange);
    if (includeCb) includeCb.addEventListener('change', onIncludeChange);

    bindNumericInput(percentInput, function (v) {
      var s = String(v).replace(/[^\d.]/g, '');
      var parts = s.split('.');
      if (parts.length > 2) s = parts[0] + '.' + parts.slice(1).join('');
      return s;
    });

    bindNumericInput(tripsInput, function (v) {
      return String(v).replace(/\D/g, '');
    });

    document.addEventListener('change', function (e) {
      if (!e.target) return;
      if (e.target.matches('[name="client_project_id"]')) {
        projectId = e.target.value || '';
        loadEstimate();
      }
    });

    window.CommuteExpense = window.CommuteExpense || {
      getAmount: function () { return computeAmount(); },
      isIncluded: function () { return isIncluded(); },
      _notify: null,
      onChange: function (fn) {
        this._notify = fn;
        fn({
          amount: computeAmount(),
          included: isIncluded(),
          roundTrip: roundTripCost,
          percent: parseDecimal(percentInput ? percentInput.value : 100),
          trips: parseIntTrips(tripsInput ? tripsInput.value : 1)
        });
      }
    };

    syncBodyVisibility();
    loadEstimate();
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-cp-commute-expense]').forEach(initCommuteExpense);
  });
})();
