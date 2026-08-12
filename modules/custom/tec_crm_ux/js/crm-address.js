/**
 * @file
 * Rebuild CRM address fields without Address module AJAX (unstable on this host).
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.tecCrmAddressRebuild = {
    attach: function (context) {
      once('tec-crm-address-rebuild', 'form.tec-crm-contact-form', context).forEach(function (form) {
        const country = form.querySelector(
          'select[name="field_tec_address[0][address][country_code]"]'
        );
        const rebuild = form.querySelector('input.tec-crm-address-rebuild, button.tec-crm-address-rebuild');
        if (!country || !rebuild) {
          return;
        }
        country.addEventListener('change', function () {
          rebuild.click();
        });

        // Province/area changes that used to AJAX-rebuild subordinate fields.
        form.querySelectorAll(
          'select[name^="field_tec_address[0][address][administrative_area]"], select[name^="field_tec_address[0][address][locality]"]'
        ).forEach(function (select) {
          select.addEventListener('change', function () {
            rebuild.click();
          });
        });
      });
    }
  };
})(Drupal, once);
