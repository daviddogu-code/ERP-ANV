/**
 * Purchase List: keep the line and supplier totals honest while quantities are
 * edited, so the number under the button is the number that will be ordered.
 */
(function (Drupal, once) {
  'use strict';

  function format(value) {
    return value.toLocaleString('en-US', { maximumFractionDigits: 2 });
  }

  function recount(group) {
    let total = 0;

    group.querySelectorAll('tbody tr').forEach(function (row) {
      const quantity = row.querySelector('.tec-buy__qty');
      const cell = row.querySelector('.tec-buy__line-total');
      if (!quantity || !cell) {
        return;
      }

      const box = row.querySelector('.tec-buy__chk');
      const included = !box || box.checked;
      row.classList.toggle('is-out', !included);

      // A material with no cost has no line total to show, ticked or not.
      const cost = parseFloat(row.getAttribute('data-cost'));
      if (isNaN(cost)) {
        return;
      }

      const line = included ? cost * (parseFloat(quantity.value) || 0) : 0;
      cell.textContent = included ? format(line) : '\u2014';
      total += line;
    });

    const sum = group.querySelector('.tec-buy__group-total');
    if (sum) {
      sum.textContent = format(total);
    }
  }

  Drupal.behaviors.tecPurchaseList = {
    attach(context) {
      once('tec-buy', '.tec-buy__group', context).forEach(function (group) {
        group.addEventListener('input', function (event) {
          if (event.target.classList.contains('tec-buy__qty')) {
            recount(group);
          }
        });
        group.addEventListener('change', function (event) {
          if (event.target.classList.contains('tec-buy__chk')) {
            recount(group);
          }
        });
      });
    },
  };
})(Drupal, once);
