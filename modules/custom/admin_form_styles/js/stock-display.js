console.log('stock-display.js file loaded!');

(function ($, Drupal) {
  'use strict';

  console.log('stock-display.js behavior defined!');

  Drupal.behaviors.stockDisplay = {
    attach: function (context, settings) {
      console.log('Stock display behavior running in context:', context);
      
      // Handle both cases: when context is the form itself OR when we need to find the form
      var forms = $(context).hasClass('tec-inventory-tec-inventory-transaction-form') ? $(context) : $(context).find('.tec-inventory-tec-inventory-transaction-form');
      
      console.log('Forms found:', forms.length);
      
      forms.each(function() {
        var form = $(this);
        
        console.log('Processing form:', form);
        
        // Check if already processed
        if (form.hasClass('stock-display-processed')) {
          console.log('Form already processed, skipping');
          return;
        }
        form.addClass('stock-display-processed');
        
        console.log('Processing inventory transaction form');
        
        var actionUrl = form.attr('action');
        console.log('Action URL:', actionUrl);
        
        // Extract current stock from URL
        if (actionUrl && actionUrl.includes('current_stock=')) {
          var match = actionUrl.match(/current_stock=([^&]+)/);
          console.log('Stock match:', match);
          
          if (match && match[1]) {
            var currentStock = parseFloat(decodeURIComponent(match[1]));
            console.log('Current stock:', currentStock);
            
            // Find the quantity field within this form
            var quantityField = form.find('.field--name-field-tec-quantity');
            console.log('Quantity field found:', quantityField.length);
            console.log('Quantity field element:', quantityField);
            
            if (quantityField.length > 0) {
              // Create stock display
              var stockHtml = '<div class="current-stock-display">' +
                '<div class="stock-info">' +
                '<span class="current">📦 Current: ' + currentStock + ' units</span>' +
                '<span class="new-stock" style="display:none;">→ New: <span class="value">' + currentStock + '</span> units</span>' +
                '</div>' +
                '</div>';
              
              console.log('About to add stock HTML to form item');
              
              // Find the form item div specifically for better positioning
              var formItem = quantityField.find('.js-form-item').first();
              console.log('Form item found:', formItem.length);
              
              if (formItem.length > 0) {
                // Insert the stock display as a sibling to the input/label section
                formItem.append(stockHtml);
                console.log('Stock display added to form item');
              } else {
                // Fallback: prepend to quantity field if form item not found
                quantityField.prepend(stockHtml);
                console.log('Stock display added to quantity field (fallback)');
              }
              
              // Verify it was added
              console.log('Stock display verification:', form.find('.current-stock-display').length);
              
              // Add real-time calculation
              var quantityInput = quantityField.find('input[type="number"]');
              var newStockSpan = form.find('.new-stock');
              var newStockValue = form.find('.new-stock .value');
              
              console.log('Setting up input listener for:', quantityInput.length, 'inputs');
              quantityInput.on('input', function() {
                var variation = parseFloat($(this).val()) || 0;
                var newStock = currentStock + variation;
                console.log('Input changed:', variation, 'New stock:', newStock);
                
                if (variation !== 0) {
                  newStockValue.text(newStock.toFixed(2));
                  newStockSpan.show();
                } else {
                  newStockSpan.hide();
                }
              });
            } else {
              console.log('No quantity field found!');
            }
          }
        }
      });
    }
  };

})(jQuery, Drupal);
