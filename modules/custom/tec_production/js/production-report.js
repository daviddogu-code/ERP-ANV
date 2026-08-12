/**
 * Production Report: toggle the per-day drill-down rows.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.tecProductionReport = {
    attach(context) {
      once('tec-report', '#tec-production-report', context).forEach(function (table) {
        table.querySelectorAll('tr.tec-report__row--has-data').forEach(function (row) {
          row.addEventListener('click', function () {
            const day = row.getAttribute('data-day');
            const detail = table.querySelector('tr[data-detail-for="' + day + '"]');
            if (!detail) {
              return;
            }
            detail.classList.toggle('is-open');
            row.classList.toggle('is-expanded');
          });
        });
      });
    },
  };
})(Drupal, once);
