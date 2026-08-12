(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.stockDisplay = {
    attach: function (context, settings) {
      var stockSettings = (settings.adminFormStyles && settings.adminFormStyles.stockDisplay) || {};
      var uom = stockSettings.uom || 'units';
      var dialogTitle = stockSettings.title || '';

      var forms = $(context).hasClass('tec-inventory-tec-inventory-transaction-form')
        ? $(context)
        : $(context).find('.tec-inventory-tec-inventory-transaction-form');

      forms.each(function () {
        var form = $(this);

        if (dialogTitle) {
          var $dialogContent = form.closest('.ui-dialog-content');
          if ($dialogContent.length && $dialogContent.data('ui-dialog')) {
            $dialogContent.dialog('option', 'title', dialogTitle);
          }
          else {
            form.closest('.ui-dialog').find('.ui-dialog-title').first().text(dialogTitle);
          }
        }

        once('stock-display', form.get(0)).forEach(function () {
          var currentStock = null;
          if (typeof stockSettings.currentStock === 'number' && !isNaN(stockSettings.currentStock)) {
            currentStock = stockSettings.currentStock;
          }
          else {
            var actionUrl = form.attr('action') || window.location.href;
            if (actionUrl.indexOf('current_stock=') !== -1) {
              var match = actionUrl.match(/current_stock=([^&]+)/);
              if (match && match[1]) {
                currentStock = parseFloat(decodeURIComponent(match[1]));
              }
            }
          }

          if (currentStock === null || isNaN(currentStock)) {
            return;
          }

          var quantityField = form.find('.field--name-field-tec-quantity');
          if (!quantityField.length) {
            return;
          }

          // Keep field suffix in sync if PHP render was cached without UoM.
          quantityField.find('.field-suffix, .form-item__suffix').text(uom);

          var stockHtml = '<div class="current-stock-display">' +
            '<div class="stock-info">' +
            '<span class="current">📦 Current: ' + currentStock + ' ' + uom + '</span>' +
            '<span class="new-stock" style="display:none;">→ New: <span class="value">' + currentStock + '</span> ' + uom + '</span>' +
            '</div>' +
            '</div>';

          var formItem = quantityField.find('.js-form-item').first();
          if (formItem.length > 0) {
            formItem.append(stockHtml);
          }
          else {
            quantityField.prepend(stockHtml);
          }

          var quantityInput = quantityField.find('input[type="number"]');
          var newStockSpan = form.find('.new-stock');
          var newStockValue = form.find('.new-stock .value');

          quantityInput.attr('step', '1');

          quantityInput.on('input', function () {
            var variation = parseFloat($(this).val()) || 0;
            var newStock = currentStock + variation;

            if (variation !== 0) {
              newStockValue.text(newStock.toFixed(2));
              newStockSpan.show();
            }
            else {
              newStockSpan.hide();
            }
          });
        });
      });
    }
  };

})(jQuery, Drupal, once);
