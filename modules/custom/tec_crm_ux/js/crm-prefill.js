/**
 * @file
 * Check Contact type boxes from ?tipo= / ?prefill[]= when the server missed it.
 */
(function (Drupal, once) {
  'use strict';

  const TIPO_TO_TID = {
    supplier: '1396',
    customer: '1395'
  };

  function tidsFromUrl() {
    const params = new URLSearchParams(window.location.search);
    const tids = [];
    const tipo = (params.get('tipo') || '').toLowerCase();
    if (TIPO_TO_TID[tipo]) {
      tids.push(TIPO_TO_TID[tipo]);
    }
    const prefill = params.get('prefill[field_tec_contact_type]');
    if (prefill && /^\d+$/.test(prefill)) {
      tids.push(prefill);
    }
    return tids;
  }

  function applyTids(tids) {
    tids.forEach(function (tid) {
      const box = document.querySelector(
        'input[type="checkbox"][name="field_tec_contact_type[' + tid + ']"]'
      );
      if (box && !box.checked) {
        box.checked = true;
        box.dispatchEvent(new Event('input', { bubbles: true }));
        box.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
  }

  Drupal.behaviors.tecCrmPrefill = {
    attach: function (context) {
      const tids = tidsFromUrl();
      if (!tids.length) {
        return;
      }
      once('tec-crm-prefill', 'html', context).forEach(function () {
        applyTids(tids);
      });
      // Also run against AJAX-replaced fragments.
      if (context !== document && context.querySelectorAll) {
        applyTids(tids);
      }
    }
  };
})(Drupal, once);
