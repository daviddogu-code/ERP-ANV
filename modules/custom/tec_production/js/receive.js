/**
 * @file
 * Receive form: show what an arriving quantity becomes in inventory units.
 *
 * The order is written in purchase units and the warehouse counts in inventory
 * units, so every receipt carries a multiplication. Doing it on screen while
 * they type is the cheapest way to catch a factor that is set the wrong way
 * round, before it becomes a stock level nobody can explain.
 *
 * The server multiplies again on submit and its figure is the one that is
 * stored; this only previews it.
 */
(function (Drupal, once) {
  'use strict';

  function fmt(value) {
    var rounded = Math.round(value * 100) / 100;
    return rounded.toLocaleString('en-US', { maximumFractionDigits: 2 });
  }

  function recalcRow(row) {
    var units = parseFloat(row.dataset.units || '1') || 1;
    var input = row.querySelector('.tec-receive__qty');
    var target = row.querySelector('.tec-receive__into');
    if (!input || !target) {
      return;
    }
    var quantity = parseFloat(input.value || '0') || 0;
    target.textContent = fmt(quantity * units);
  }

  Drupal.behaviors.tecReceive = {
    attach: function (context) {
      once('tec-receive', '.tec-receive__table', context).forEach(function (table) {
        table.querySelectorAll('.tec-receive__qty').forEach(function (input) {
          input.addEventListener('input', function () {
            recalcRow(input.closest('tr'));
          });
        });
      });
    }
  };
})(Drupal, once);
