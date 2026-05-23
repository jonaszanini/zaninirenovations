(function () {
  'use strict';

  function formatMoney(amount) {
    var n = parseFloat(amount);
    if (isNaN(n)) return '—';
    return 'CA$' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  function initVehicleCalc() {
    var root = document.querySelector('[data-cp-vehicle-calc]');
    if (!root || root._cpVehicleCalcWired) return;
    root._cpVehicleCalcWired = true;

    var costPerKm = parseFloat(root.getAttribute('data-cost-per-km'));
    var avgKm = parseInt(root.getAttribute('data-avg-km'), 10) || 0;
    var costPerKmEl = root.querySelector('[data-cp-calc-cost-per-km]');
    var monthlyEl = root.querySelector('[data-cp-calc-monthly]');
    var tripKmInput = root.querySelector('[data-cp-calc-trip-km]');
    var tripResult = root.querySelector('[data-cp-calc-trip-result]');

    if (isNaN(costPerKm)) return;

    if (costPerKmEl) {
      costPerKmEl.textContent = formatMoney(costPerKm) + '/km';
    }
    if (monthlyEl) {
      monthlyEl.textContent = formatMoney(costPerKm * avgKm);
    }

    function updateTrip() {
      if (!tripKmInput || !tripResult) return;
      var km = parseFloat(tripKmInput.value);
      if (isNaN(km) || km <= 0) {
        tripResult.textContent = 'Enter a distance to estimate fuel cost.';
        return;
      }
      tripResult.textContent = 'Estimated fuel cost: ' + formatMoney(costPerKm * km);
    }

    if (tripKmInput) {
      tripKmInput.addEventListener('input', updateTrip);
      updateTrip();
    }

    var avgInput = document.getElementById('average-km-month');
    if (avgInput && monthlyEl) {
      avgInput.addEventListener('input', function () {
        var km = parseInt(avgInput.value, 10);
        if (isNaN(km) || km < 0) {
          monthlyEl.textContent = '—';
          return;
        }
        monthlyEl.textContent = formatMoney(costPerKm * km);
      });
    }
  }

  function initTravelCost() {
    var card = document.querySelector('[data-cp-travel-cost]');
    if (!card || card._cpTravelWired) return;
    card._cpTravelWired = true;

    var url = card.getAttribute('data-url');
    var loadingEl = card.querySelector('[data-cp-travel-loading]');
    var resultEl = card.querySelector('[data-cp-travel-result]');
    var errorEl = card.querySelector('[data-cp-travel-error]');
    var refreshBtn = card.querySelector('[data-cp-travel-refresh');

    function setLoading(on) {
      if (loadingEl) loadingEl.hidden = !on;
      if (refreshBtn) refreshBtn.disabled = on;
    }

    function showError(msg) {
      if (resultEl) resultEl.hidden = true;
      if (errorEl) {
        errorEl.textContent = msg;
        errorEl.hidden = !msg;
      }
    }

    function showResult(data) {
      if (errorEl) errorEl.hidden = true;
      if (!resultEl) return;
      resultEl.hidden = false;
      var duration = data.duration_text ? ' · ~' + data.duration_text + ' one way' : '';
      resultEl.innerHTML =
        '<div class="cp-travel-cost-card__metrics">' +
          '<div class="cp-travel-cost-metric">' +
            '<span class="cp-travel-cost-metric__label">Round trip distance</span>' +
            '<strong class="cp-travel-cost-metric__value">' + data.round_trip_km + ' km</strong>' +
          '</div>' +
          '<div class="cp-travel-cost-metric cp-travel-cost-metric--highlight">' +
            '<span class="cp-travel-cost-metric__label">Estimated fuel cost</span>' +
            '<strong class="cp-travel-cost-metric__value">' + (data.fuel_cost_formatted || '—') + '</strong>' +
          '</div>' +
        '</div>' +
        '<p class="admin-form-help">One way: ' + data.one_way_km + ' km' + duration +
        ' · Vehicle: ' + (data.vehicle_name || 'Active vehicle') +
        ' · ' + data.fuel_consumption + ' L/100km @ ' + formatMoney(data.gas_price) + '/L</p>';
    }

    function loadEstimate() {
      if (!url) return;
      setLoading(true);
      showError('');
      fetch(url, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          setLoading(false);
          if (data.ok) {
            showResult(data);
          } else {
            showError(data.message || 'Could not calculate travel estimate.');
          }
        })
        .catch(function () {
          setLoading(false);
          showError('Could not load travel estimate. Check your connection and try again.');
        });
    }

    if (refreshBtn) {
      refreshBtn.addEventListener('click', loadEstimate);
    }
    loadEstimate();
  }

  document.addEventListener('DOMContentLoaded', function () {
    initVehicleCalc();
    initTravelCost();
  });
})();
