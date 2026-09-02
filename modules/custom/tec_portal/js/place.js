/**
 * Live Item total and VAT foot on /my/order and /o/order.
 *
 * Lines stay net. The foot is Subtotal, VAT, Total when drupalSettings
 * has tecPortalVat.rate; otherwise the single Total it used to be.
 */
(function (Drupal, once) {
  'use strict';

  function money(amount) {
    return '\u0E3F ' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  function pieces(amount) {
    return String(Math.round(amount)).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  function vatOn(net, rate) {
    return Math.round((net * rate / 100) * 100) / 100;
  }

  function vatRate() {
    const given = (window.drupalSettings || {}).tecPortalVat;
    if (!given || given.rate === undefined || given.rate === null || given.rate === '') {
      return null;
    }
    const rate = parseFloat(given.rate);
    return isNaN(rate) ? null : rate;
  }

  function priceOf(row, qty) {
    const priceInput = row.querySelector('.tec-portal__charge-price');
    if (priceInput) {
      const typed = parseFloat(priceInput.value);
      return isNaN(typed) ? 0 : typed;
    }
    const stamped = parseFloat(qty.getAttribute('data-price') || row.getAttribute('data-price'));
    if (!isNaN(stamped)) {
      return stamped;
    }
    const cell = row.querySelector('.tec-portal__col-price');
    const parsed = parseFloat((cell ? cell.textContent : '').replace(/[^\d.-]/g, ''));
    return isNaN(parsed) ? 0 : parsed;
  }

  function quantityOf(row) {
    const qty = row.querySelector('.tec-portal__charge-qty, .tec-portal__qty input, input[type="number"]');
    if (qty) {
      return parseFloat(qty.value) || 0;
    }
    const stamped = parseFloat(row.getAttribute('data-qty'));
    return isNaN(stamped) ? 0 : stamped;
  }

  function recount(form) {
    const rate = vatRate();
    form.querySelectorAll('.tec-portal__table').forEach(function (table) {
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
      if (qEl) {
        qEl.textContent = pieces(qtySum);
      }
      if (rate === null) {
        const aEl = table.querySelector('.tec-portal__sum-amount');
        if (aEl) {
          aEl.textContent = money(moneySum);
        }
        return;
      }
      const vat = vatOn(moneySum, rate);
      const netEl = table.querySelector('.tec-portal__sum-net');
      const vatEl = table.querySelector('.tec-portal__sum-vat');
      const grossEl = table.querySelector('.tec-portal__sum-gross');
      if (netEl) {
        netEl.textContent = money(moneySum);
      }
      if (vatEl) {
        vatEl.textContent = money(vat);
      }
      if (grossEl) {
        grossEl.textContent = money(moneySum + vat);
      }
    });
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
