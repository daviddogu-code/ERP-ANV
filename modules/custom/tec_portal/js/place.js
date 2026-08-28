/**
 * Live Item total on /my/order/new: qty × price, same as the factory draft.
 */
(function (Drupal, once) {
  'use strict';

  function money(amount) {
    return '\u0E3F ' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  function pieces(amount) {
    return String(Math.round(amount)).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  function priceOf(row, qty) {
    const stamped = parseFloat(qty.getAttribute('data-price') || row.getAttribute('data-price'));
    if (!isNaN(stamped)) {
      return stamped;
    }
    const cell = row.querySelector('.tec-portal__col-price');
    const parsed = parseFloat((cell ? cell.textContent : '').replace(/[^\d.-]/g, ''));
    return isNaN(parsed) ? 0 : parsed;
  }

  function quantityOf(row) {
    const qty = row.querySelector('.tec-portal__qty input, input[type="number"]');
    if (qty) {
      return parseFloat(qty.value) || 0;
    }
    const stamped = parseFloat(row.getAttribute('data-qty'));
    return isNaN(stamped) ? 0 : stamped;
  }

  function recount(form) {
    let allQty = 0;
    let allMoney = 0;

    form.querySelectorAll('.tec-portal__group:not(.tec-portal__group--grand) .tec-portal__table').forEach(function (table) {
      let qtySum = 0;
      let moneySum = 0;
      table.querySelectorAll('tbody tr').forEach(function (row) {
        const qty = row.querySelector('.tec-portal__qty input, input[type="number"]');
        const cell = row.querySelector('.tec-portal__item-total');
        const quantity = quantityOf(row);
        const line = priceOf(row, qty || row) * quantity;
        if (cell) {
          cell.textContent = money(line);
        }
        qtySum += quantity;
        moneySum += line;
      });
      const qEl = table.querySelector('.tec-portal__sum-qty');
      const aEl = table.querySelector('.tec-portal__sum-amount');
      if (qEl) {
        qEl.textContent = pieces(qtySum);
      }
      if (aEl) {
        aEl.textContent = money(moneySum);
      }
      allQty += qtySum;
      allMoney += moneySum;
    });

    const gq = form.querySelector('.tec-portal__grand-qty');
    const ga = form.querySelector('.tec-portal__grand-amount');
    if (gq) {
      gq.textContent = pieces(allQty);
    }
    if (ga) {
      ga.textContent = money(allMoney);
    }
  }

  Drupal.behaviors.tecPortalPlace = {
    attach(context) {
      once('tec-portal-place', '.tec-portal--place', context).forEach(function (form) {
        recount(form);
        form.addEventListener('input', function () {
          recount(form);
        });
        form.addEventListener('change', function () {
          recount(form);
        });
      });
    },
  };
})(Drupal, once);
