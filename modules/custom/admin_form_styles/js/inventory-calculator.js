// modules/custom/admin_form_styles/js/inventory-calculator.js
// Inventory Cost and Consumption Cost on the material form are derived from
// the purchase cost and the two conversion factors, and never typed in.
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

        const perInventoryUnit = cost / toInventory;
        inventoryCost.value = perInventoryUnit.toFixed(2);
        consumptionCost.value = (perInventoryUnit / toConsumption).toFixed(2);
      };

      form.addEventListener('input', calculate);
      form.addEventListener('change', calculate);
      calculate();
    }
  };
})(Drupal, once);
