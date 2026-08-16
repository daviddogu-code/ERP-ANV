/**
 * @file
 * Purchase Control (/supplier-orders/queue).
 *
 * Two small jobs, both about the same thing: the table is edited in place and
 * saved in one go at the bottom.
 *
 * 1. Repaint the status pill the moment it is changed, instead of after the
 *    save. Picking a colour and watching nothing happen makes people press
 *    Save twice.
 * 2. Stop a click on a column heading from throwing away those edits. Sorting
 *    is a link, so it leaves the page; before this, a morning's worth of
 *    expected dates could disappear because someone wanted to sort by supplier.
 */
(function (Drupal, once) {
  'use strict';

  /**
   * Swap the class that carries the pill colour.
   */
  function paint(select) {
    Array.prototype.slice.call(select.classList).forEach(function (name) {
      if (name.indexOf('tec-pq__select--') === 0) {
        select.classList.remove(name);
      }
    });
    select.classList.add('tec-pq__select--' + select.value);
  }

  Drupal.behaviors.tecPurchaseQueue = {
    attach: function (context) {
      var dirty = false;

      once('tec-pq-status', '.tec-pq__status select', context).forEach(function (select) {
        select.addEventListener('change', function () {
          paint(select);
          dirty = true;
        });
      });

      once('tec-pq-date', '.tec-pq__expected input', context).forEach(function (input) {
        input.addEventListener('change', function () {
          dirty = true;
        });
      });

      once('tec-pq-guard', '.tec-pq__table', context).forEach(function () {
        window.addEventListener('beforeunload', function (event) {
          if (dirty) {
            event.preventDefault();
            // Browsers show their own wording; what matters is that they ask.
            event.returnValue = '';
          }
        });
        // Saving is a submit, and a submit unloads the page too.
        document.addEventListener('submit', function () {
          dirty = false;
        });
      });
    }
  };
})(Drupal, once);
