(function () {
  'use strict';

  function formatMoney(amount) {
    var n = parseFloat(amount);
    if (isNaN(n)) return '—';
    return 'CA$' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  function costPerKm(consumption, gasPrice) {
    var litersPer100 = parseFloat(consumption);
    var price = parseFloat(String(gasPrice).replace(/,/g, ''));
    if (isNaN(litersPer100) || litersPer100 <= 0 || isNaN(price) || price < 0) {
      return null;
    }
    return Math.round(((litersPer100 / 100) * price) * 10000) / 10000;
  }

  function initVehicleFormNumeric() {
    document.querySelectorAll('[data-zr-numeric="integer"]').forEach(function (input) {
      if (input._zrNumericWired) return;
      input._zrNumericWired = true;

      input.addEventListener('keydown', function (ev) {
        if (ev.ctrlKey || ev.metaKey || ev.altKey) return;
        if (ev.key && ev.key.length > 1) return;
        if (!/^\d$/.test(ev.key)) ev.preventDefault();
      });

      input.addEventListener('input', function () {
        var cleaned = input.value.replace(/\D/g, '');
        if (cleaned !== input.value) input.value = cleaned;
      });
    });

    document.querySelectorAll('[data-zr-numeric="decimal"]').forEach(function (input) {
      if (input._zrNumericWired) return;
      input._zrNumericWired = true;

      var maxDecimals = parseInt(input.getAttribute('data-zr-decimals'), 10);
      if (isNaN(maxDecimals) || maxDecimals < 0) maxDecimals = 2;

      input.addEventListener('keydown', function (ev) {
        if (ev.ctrlKey || ev.metaKey || ev.altKey) return;
        if (ev.key && ev.key.length > 1) return;
        if (ev.key === '.' || ev.key === ',') {
          if (input.value.indexOf('.') !== -1) ev.preventDefault();
          return;
        }
        if (!/^\d$/.test(ev.key)) ev.preventDefault();
      });

      input.addEventListener('input', function () {
        var v = input.value.replace(/,/g, '.').replace(/[^\d.]/g, '');
        var firstDot = v.indexOf('.');
        if (firstDot !== -1) {
          v = v.slice(0, firstDot + 1) + v.slice(firstDot + 1).replace(/\./g, '');
        }
        if (firstDot !== -1 && maxDecimals >= 0) {
          var parts = v.split('.');
          if (parts[1] && parts[1].length > maxDecimals) {
            v = parts[0] + '.' + parts[1].slice(0, maxDecimals);
          }
        }
        if (v !== input.value) input.value = v;
      });
    });
  }

  function initVehicleCalc() {
    var root = document.querySelector('[data-cp-vehicle-calc]');
    if (!root || root._cpVehicleCalcWired) return;
    root._cpVehicleCalcWired = true;

    var consumption = root.getAttribute('data-consumption');
    var gasPriceInput = document.querySelector('[data-cp-gas-price]');
    var costPerKmEl = root.querySelector('[data-cp-calc-cost-per-km]');
    var consumptionEl = root.querySelector('[data-cp-calc-consumption]');
    var tripKmInput = root.querySelector('[data-cp-calc-trip-km]');
    var tripResult = root.querySelector('[data-cp-calc-trip-result]');

    function currentGasPrice() {
      if (gasPriceInput && gasPriceInput.value.trim() !== '') {
        return gasPriceInput.value.trim();
      }
      return root.getAttribute('data-gas-price') || '';
    }

    function currentRates() {
      var gasPrice = currentGasPrice();
      return {
        consumption: consumption,
        gasPrice: gasPrice,
        perKm: costPerKm(consumption, gasPrice)
      };
    }

    function renderRates() {
      var rates = currentRates();
      if (costPerKmEl) {
        costPerKmEl.textContent = rates.perKm !== null ? formatMoney(rates.perKm) + '/km' : '—';
      }
      if (consumptionEl) {
        if (rates.perKm !== null) {
          consumptionEl.textContent = parseFloat(rates.consumption).toFixed(1) + ' L/100km @ ' + formatMoney(rates.gasPrice) + '/L';
        } else {
          consumptionEl.textContent = '—';
        }
      }
      updateTrip();
    }

    function updateTrip() {
      if (!tripKmInput || !tripResult) return;
      var km = parseFloat(tripKmInput.value);
      var perKm = currentRates().perKm;
      if (isNaN(km) || km <= 0 || perKm === null) {
        tripResult.textContent = perKm === null
          ? 'Set L/100 km on the active vehicle and a company gas price to estimate fuel cost.'
          : 'Enter a distance to estimate fuel cost.';
        return;
      }
      tripResult.textContent = 'Estimated fuel cost: ' + formatMoney(perKm * km);
    }

    if (tripKmInput) {
      tripKmInput.addEventListener('input', updateTrip);
    }
    if (gasPriceInput) {
      gasPriceInput.addEventListener('input', renderRates);
    }

    renderRates();
  }

  function initTravelCost() {
    var card = document.querySelector('[data-cp-travel-cost]');
    if (!card || card._cpTravelWired) return;
    card._cpTravelWired = true;

    var url = card.getAttribute('data-url');
    var loadingEl = card.querySelector('[data-cp-travel-loading]');
    var resultEl = card.querySelector('[data-cp-travel-result]');
    var errorEl = card.querySelector('[data-cp-travel-error]');
    var refreshBtn = card.querySelector('[data-cp-travel-refresh]');

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

      function cell(val) {
        return val != null && val !== '' ? val : '—';
      }

      function row(label, sublabel, km, duration, fuel) {
        return '<tr>' +
          '<th scope="row" class="cp-travel-breakdown__leg">' +
            '<span class="cp-travel-breakdown__leg-name">' + label + '</span>' +
            (sublabel ? '<span class="cp-travel-breakdown__leg-sub">' + sublabel + '</span>' : '') +
          '</th>' +
          '<td class="cp-travel-breakdown__num">' + cell(km != null ? km + ' km' : null) + '</td>' +
          '<td class="cp-travel-breakdown__num">' + cell(duration) + '</td>' +
          '<td class="cp-travel-breakdown__num cp-travel-breakdown__fuel">' + cell(fuel) + '</td>' +
        '</tr>';
      }

      resultEl.innerHTML =
        '<div class="cp-travel-breakdown-wrap">' +
          '<table class="cp-travel-breakdown">' +
            '<thead>' +
              '<tr>' +
                '<th scope="col"></th>' +
                '<th scope="col">Distance</th>' +
                '<th scope="col">Drive time</th>' +
                '<th scope="col">Fuel cost</th>' +
              '</tr>' +
            '</thead>' +
            '<tbody>' +
              row('Outbound', 'Home → project', data.one_way_km, data.outbound_duration_text, data.outbound_fuel_cost_formatted) +
              row('Return', 'Project → home', data.return_km, data.return_duration_text, data.return_fuel_cost_formatted) +
            '</tbody>' +
            '<tfoot>' +
              row('Total', null, data.round_trip_km, data.duration_text, data.fuel_cost_formatted) +
            '</tfoot>' +
          '</table>' +
        '</div>' +
        '<p class="admin-form-help cp-travel-breakdown-meta">' +
          'Vehicle: ' + (data.vehicle_name || 'Active vehicle') +
          ' · ' + data.fuel_consumption + ' L/100 km @ ' + formatMoney(data.gas_price) + '/L' +
        '</p>';
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
    initVehicleFormNumeric();
    initVehicleCalc();
    initTravelCost();
  });
})();
