// modules/custom/admin_form_styles/js/inventory-calculator.js
// Updated Cost Calculator for TEC Inventory System
(function (Drupal) {
  'use strict';
  
  Drupal.behaviors.inventoryCostCalculator = {
    attach: function (context, settings) {
      const form = once('inventory-cost-form', '.taxonomy-term-tec-inventory-form', context).shift();
      if (!form) {
        return;
      }
      
      console.log('✅ TEC Cost Calculator Initialized');
      
      // --- Find Conversion Formula Fields Dynamically ---
      // These might be in different sections of the form
      const findConversionFields = () => {
        const possibleSelectors = [
          '#edit-field-uop-to-uos-0-value',
          '#edit-field-tec-uop-to-uos-0-value', 
          '#edit-field-tec-package-units-0-value',
          '#edit-field-tec-units-0-value',
          '[name*="uop"][name*="uos"]',
          '[name*="conversion"]'
        ];
        
        for (let selector of possibleSelectors) {
          const field = form.querySelector(selector);
          if (field) {
            console.log('Found UoP to UoS field:', selector);
            return field;
          }
        }
        return null;
      };

      const findUoSToUoUField = () => {
        const possibleSelectors = [
          '#edit-field-tec-split-into-0-value',
          '#edit-field-tec-uos-split-qty-0-value',
          '#edit-field-tec-uou-split-qty-0-value',
          '[name*="split"][name*="qty"]',
          '[name*="uos"][name*="uou"]'
        ];
        
        for (let selector of possibleSelectors) {
          const field = form.querySelector(selector);
          if (field) {
            console.log('Found UoS to UoU field:', selector);
            return field;
          }
        }
        return null;
      };

      // --- Updated Field Selectors Based on Actual HTML ---
      const fields = {
        // Main input field (user enters this)
        costUoP: form.querySelector('#edit-field-tec-cost-0-value'), // Cost by UoP (primary input)
        
        // Conversion formulas (find dynamically)
        uopToUoSFormula: findConversionFields(), // UoP to UoS conversion
        uosToUoUFormula: findUoSToUoUField(), // UoS to UoU conversion
        
        // Auto-calculated cost fields
        costUoS: form.querySelector('#edit-field-tec-price-uos-0-value'), // Price by UoS (actually cost)
        costUoU: form.querySelector('#edit-field-tec-price-0-value'), // Cost by UoU
        
        // Price fields
        priceByPackage: form.querySelector('#edit-field-tec-price-by-package-0-value'), // Price by package
        priceUoU: form.querySelector('#edit-field-tec-price-uou-0-value') // Price by UoU
      };
      
      // --- Safety Check ---
      const missingFields = Object.entries(fields)
        .filter(([key, field]) => field === null)
        .map(([key]) => key);
        
      if (missingFields.length > 0) {
        console.warn('⚠️ Some fields not found:', missingFields);
        console.log('Available form fields:', 
          Array.from(form.querySelectorAll('input[type="number"]'))
            .map(input => input.id)
        );
      }
      
      // --- Main Calculation Function ---
      const calculateCosts = () => {
        console.log('🔄 Calculating costs...');
        
        // Get input values
        const costUoP = parseFloat(fields.costUoP?.value) || 0;
        const uopToUoSFormula = parseFloat(fields.uopToUoSFormula?.value) || 1; // Default to 1 if not found
        const uosToUoUFormula = parseFloat(fields.uosToUoUFormula?.value) || 1; // Default to 1 if not found
        
        console.log('📊 Input values:', {
          costUoP,
          uopToUoSFormula,
          uosToUoUFormula,
          uopToUoSField: fields.uopToUoSFormula ? 'Found' : 'Missing',
          uosToUoUField: fields.uosToUoUFormula ? 'Found' : 'Missing'
        });
        
        // Calculate Cost UoS = Cost UoP / (UoP to UoS conversion formula)
        if (costUoP > 0) {
          const calculatedCostUoS = costUoP / uopToUoSFormula;
          if (fields.costUoS) {
            fields.costUoS.value = calculatedCostUoS.toFixed(2);
            console.log('✅ Cost UoS calculated:', calculatedCostUoS.toFixed(2));
          }
          
          // Calculate Cost UoU = Cost UoS / (UoS to UoU conversion formula)
          const calculatedCostUoU = calculatedCostUoS / uosToUoUFormula;
          if (fields.costUoU) {
            fields.costUoU.value = calculatedCostUoU.toFixed(2);
            console.log('✅ Cost UoU calculated:', calculatedCostUoU.toFixed(2));
          }
          
          // For now, set price fields to same as cost (you can modify this logic)
          if (fields.priceByPackage && !fields.priceByPackage.value) {
            fields.priceByPackage.value = costUoP.toFixed(2);
          }
          if (fields.priceUoU && !fields.priceUoU.value) {
            fields.priceUoU.value = calculatedCostUoU.toFixed(2);
          }
          
        } else {
          // Clear calculated fields if input is invalid
          if (fields.costUoS) fields.costUoS.value = '';
          if (fields.costUoU) fields.costUoU.value = '';
          console.log('⚠️ Cost UoP is 0 or invalid, clearing calculated fields');
        }
      };
      
      // --- Style Calculated Fields as Read-Only ---
      const makeCalculatedField = (field, label) => {
        if (field) {
          field.setAttribute('readonly', 'readonly');
          field.style.backgroundColor = '#f8f9fa';
          field.style.color = '#6c757d';
          field.style.border = '1px dashed #dee2e6';
          field.setAttribute('title', `This ${label} is calculated automatically from Cost UoP`);
          field.style.cursor = 'not-allowed';
          // Remove readonly attribute temporarily to allow programmatic updates
          field.removeAttribute('readonly');
          setTimeout(() => field.setAttribute('readonly', 'readonly'), 100);
        }
      };
      
      makeCalculatedField(fields.costUoS, 'Cost by UoS');
      makeCalculatedField(fields.costUoU, 'Cost by UoU');
      
      // --- Style Input Fields ---
      const makeInputField = (field, label) => {
        if (field) {
          field.style.backgroundColor = '#fff';
          field.style.border = '2px solid #007bff';
          field.setAttribute('title', `Enter the ${label} here`);
        }
      };
      
      makeInputField(fields.costUoP, 'Cost by UoP (main input)');
      
      // --- Debug: Show All Available Fields ---
      console.log('🔍 Available number fields in form:');
      form.querySelectorAll('input[type="number"]').forEach(input => {
        console.log(`- ${input.id}: ${input.previousElementSibling?.textContent || 'No label'}`);
      });
      
      // --- Event Listeners for Real-Time Updates ---
      const inputFields = [fields.costUoP, fields.uopToUoSFormula, fields.uosToUoUFormula].filter(f => f);
      
      inputFields.forEach(field => {
        field.addEventListener('input', () => {
          console.log('📝 Input detected, recalculating...');
          setTimeout(calculateCosts, 50); // Small delay to ensure value is updated
        });
        field.addEventListener('change', calculateCosts);
        field.addEventListener('blur', calculateCosts);
      });
      
      // --- Initial Calculation ---
      calculateCosts();
      
      // --- Fallback: Continuous Updates (in case other scripts interfere) ---
      let intervalId = setInterval(() => {
        // Only recalculate if the main input has a value
        if (fields.costUoP && fields.costUoP.value) {
          calculateCosts();
        }
      }, 500);
      
      // --- Cleanup on form destruction ---
      form.addEventListener('beforeunload', () => {
        if (intervalId) {
          clearInterval(intervalId);
        }
      });
      
      console.log('✅ Cost Calculator Ready - Enter Cost UoP to see automatic calculations');
    }
  };
})(Drupal);
