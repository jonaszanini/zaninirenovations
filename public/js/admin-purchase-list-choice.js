/**
 * Print Shopping List / Print Material List: ask full (with prices) vs simple.
 * Links: [data-purchase-list-choice]
 */
(function () {
  'use strict';

  function withPricesParam(href, showPrices) {
    var url = new URL(href, window.location.origin);
    url.searchParams.set('prices', showPrices ? '1' : '0');
    return url.toString();
  }

  document.addEventListener('click', function (e) {
    var link = e.target && e.target.closest && e.target.closest('[data-purchase-list-choice]');
    if (!link) return;

    var href = link.getAttribute('href');
    if (!href) return;

    e.preventDefault();
    e.stopPropagation();

    var title = link.getAttribute('data-purchase-list-title') || 'Print shopping list';
    var dialog = window.AdminDialog;
    if (!dialog || typeof dialog.choose !== 'function') {
      window.open(withPricesParam(href, true), '_blank', 'noopener');
      return;
    }

    dialog.choose('Include unit price and total, or print a simple list without prices?', {
      title: title,
      choices: [
        { value: 'full', label: 'Full list — with unit price and total' },
        { value: 'simple', label: 'Simple list — no prices' },
      ],
    }).then(function (choice) {
      if (!choice) return;
      var win = window.open(withPricesParam(href, choice === 'full'), '_blank', 'noopener');
      if (!win) {
        dialog.alert('Pop-up blocked. Allow pop-ups for this site to print the list.');
      }
    });
  });
})();
