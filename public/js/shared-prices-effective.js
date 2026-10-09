'use strict';

function parseSpMoney(value) {
  var n = parseFloat(String(value || '').replace(/,/g, ''));
  return isFinite(n) ? n : 0;
}

function formatSpMoney(n) {
  return 'CA$' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

/**
 * Live editor hint $/unit. Same blank-Sell rule as sellRate():
 * typed Sell, else the built-in default, else Cost × owner (+ material).
 * Changing Cost must not replace a built-in default before save.
 */
function effectiveUnitPrice(input) {
  var sell = parseSpMoney(input && input.sell);
  var rate = parseSpMoney(input && input.rate);
  var owner = parseSpMoney(input && input.owner);
  var price = parseSpMoney(input && input.price);
  var blankSell = parseSpMoney(input && input.blankSell);
  var kind = String((input && input.kind) || '');
  var matUnit = String((input && input.matUnit) || '');
  var labor = 0;

  if (sell > 0) {
    labor = sell;
    price = 0;
  } else if (blankSell > 0) {
    labor = blankSell;
    price = 0;
  } else if (rate > 0 && owner > 0) {
    labor = kind === 'hours_each' ? rate * owner : owner / rate;
  } else {
    return null;
  }

  if (price > 0 && (matUnit === 'stick_8ft' || matUnit === 'stick')) {
    price = price / 8;
  }

  return Math.round((labor + (price > 0 ? price : 0)) * 100) / 100;
}

function formatEffectiveHint(price, unit) {
  if (price == null) {
    return '';
  }

  return formatSpMoney(price) + '\u200B/' + String(unit || '');
}

var api = {
  parseSpMoney: parseSpMoney,
  formatSpMoney: formatSpMoney,
  effectiveUnitPrice: effectiveUnitPrice,
  formatEffectiveHint: formatEffectiveHint,
};

if (typeof module === 'object' && module.exports) {
  module.exports = api;
}
if (typeof window !== 'undefined') {
  window.SharedPricesEffective = api;
}
