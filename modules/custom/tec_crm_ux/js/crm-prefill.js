/**
 * @file
 * Check Contact type boxes from ?tipo= / ?prefill[]= when the server missed it.
 * Keep Supplier purchase defaults hidden unless Supplier is checked.
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

  function syncSupplierFields(form) {
    const supplier = form.querySelector(
      'input[type="checkbox"][name="field_tec_contact_type[1396]"]'
    );
    const panel = form.querySelector(
      '#supplier-purchase-defaults, fieldset.tec-crm-supplier-fields'
    );
    if (!supplier || !panel) {
      return;
    }
    panel.style.display = supplier.checked ? '' : 'none';
  }

  Drupal.behaviors.tecCrmPrefill = {
    attach: function (context) {
      const tids = tidsFromUrl();
      if (tids.length) {
        once('tec-crm-prefill', 'html', context).forEach(function () {
          applyTids(tids);
        });
        if (context !== document && context.querySelectorAll) {
          applyTids(tids);
        }
      }

      once('tec-crm-supplier-visibility', 'form.tec-crm-contact-form', context).forEach(function (form) {
        const supplier = form.querySelector(
          'input[type="checkbox"][name="field_tec_contact_type[1396]"]'
        );
        if (supplier) {
          supplier.addEventListener('change', function () {
            syncSupplierFields(form);
          });
        }
        syncSupplierFields(form);
      });
    }
  };
})(Drupal, once);
