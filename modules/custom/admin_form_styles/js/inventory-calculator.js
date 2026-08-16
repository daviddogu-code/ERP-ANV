// modules/custom/admin_form_styles/js/inventory-calculator.js
// Inventory Cost and Consumption Cost on the material form are derived from
// the purchase cost and the two conversion factors, and never typed in.
// tec_inventory_taxonomy_term_presave() does the same arithmetic on save, which
// is what makes the figures exist for materials created by code. This is only
// here so the two fields move while the purchase cost is being typed.
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.inventoryCostCalculator = {
    attach: function (context) {
      const form = once('inventory-cost-form', '.taxonomy-term-tec-inventory-form', context).shift();
      if (!form) {
        return;
      }

      const numberField = (name) => form.querySelector('#edit-' + name + '-0-value');

      const purchaseCost = numberField('field-tec-cost');
      const purchaseToInventory = numberField('field-tec-units');
      const inventoryToConsumption = numberField('field-tec-split-into');
      const inventoryCost = numberField('field-tec-price-uos');
      const consumptionCost = numberField('field-tec-price');

      if (!purchaseCost || !purchaseToInventory || !inventoryToConsumption || !inventoryCost || !consumptionCost) {
        return;
      }

      [inventoryCost, consumptionCost].forEach((target) => {
        target.setAttribute('readonly', 'readonly');
        target.setAttribute('title', Drupal.t('Calculated from the purchase cost and the two conversion factors.'));
        target.style.backgroundColor = '#f8f9fa';
        target.style.color = '#6c757d';
        target.style.border = '1px dashed #dee2e6';
        target.style.cursor = 'not-allowed';
      });

      purchaseCost.style.backgroundColor = '#fff';
      purchaseCost.style.border = '2px solid #007bff';

      // Each field gets as many decimals as its column can hold, so that this
      // stays in step with the field the day someone changes its scale.
      //
      // The scale arrives as a data attribute on every decimal box. Their step
      // had to be set to 'any' because Drupal's own step check cannot cope with
      // more than three decimals and was refusing perfectly good figures — see
      // _admin_form_styles_fix_decimal_steps(). Reading the step is kept
      // as the fallback for any field that still has a real one: Drupal writes
      // it as a power of ten, and it has to be read as a number rather than
      // counted as characters, because six decimals come out as step="1.0E-6"
      // and counting the digits after the dot gives four.
      const decimalsOf = (field) => {
        const declared = parseInt(field.getAttribute('data-tec-decimals'), 10);
        if (!isNaN(declared)) {
          return Math.max(0, declared);
        }
        const step = parseFloat(field.getAttribute('step'));
        return step > 0 ? Math.max(0, Math.round(-Math.log10(step))) : null;
      };

      const inventoryDecimals = decimalsOf(inventoryCost);
      const consumptionDecimals = decimalsOf(consumptionCost);

      // With no step to read, the whole figure goes in and the server rounds it
      // on save, which is now the one that decides.
      const show = (value, places) => (places === null ? String(value) : value.toFixed(places));

      const calculate = () => {
        const cost = parseFloat(purchaseCost.value);
        const toInventory = parseFloat(purchaseToInventory.value);
        const toConsumption = parseFloat(inventoryToConsumption.value);

        // A half-filled form has no cost to show. Treating a missing factor as
        // 1 would store the purchase cost as if it were the unit cost, which
        // reads as a real figure and is wrong by the whole conversion.
        if (!(cost > 0) || !(toInventory > 0) || !(toConsumption > 0)) {
          inventoryCost.value = '';
          consumptionCost.value = '';
          return;
        }

        // The consumption cost divides the unrounded figure, not what just went
        // into the inventory cost box: rounding first and then dividing would
        // multiply that rounding by the second factor.
        const perInventoryUnit = cost / toInventory;
        inventoryCost.value = show(perInventoryUnit, inventoryDecimals);
        consumptionCost.value = show(perInventoryUnit / toConsumption, consumptionDecimals);
      };

      form.addEventListener('input', calculate);
      form.addEventListener('change', calculate);
      calculate();
    }
  };
})(Drupal, once);
