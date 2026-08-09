/* Purchase Order calculator — wired to your Views DOM & field names.
   Reads:
     - Qty:  td.views-field-form-field-field-tec-quantity-1  (input)
     - Cost: td.views-field-field-tec-cost                   (e.g., "฿ 170.00")
   Writes:
     - Visible subtotals (both display columns if present)
     - Hidden widget (form_field_field_tec_line_item_total_number[..]) IF it exists
   Notes:
     - Safe if the widget field is hidden/removed from the View: we only write when found.
     - Does NOT touch any "order total" field.
*/
(function ($, Drupal) {
  "use strict";

  // simple once guard
  function bindOnce($els, ns) {
    var KEY = "tecOnce-" + ns;
    return $els.filter(function () {
      var already = $(this).data(KEY);
      if (already) return false;
      $(this).data(KEY, true);
      return true;
    });
  }

  // parse money like "฿ 1,234.50" (strips currency, spaces incl. nbsp, and commas)
  function parseMoney(text) {
    if (!text) return 0;
    var t = String(text)
      .replace(/฿/g, "")
      .replace(/\u00A0/g, "")  // nbsp
      .replace(/\s/g, "")
      .replace(/,/g, "");
    var n = parseFloat(t);
    return isNaN(n) ? 0 : n;
  }

  function formatMoney(n) {
    n = Number(n) || 0;
    return "฿ " + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  }

  // Extract entity id from qty input name:
  // form_field_field_tec_quantity_1[4048][field_tec_quantity][0][value] -> 4048
  function getEntityIdFromQtyName(name) {
    if (!name) return null;
    var m = name.match(/form_field_field_tec_quantity_\d*\[(\d+)\]\[field_tec_quantity]/);
    return m ? m[1] : null;
  }

  // Name of the hidden total widget
  function liTotalInputName(entityId) {
    return (
      'form_field_field_tec_line_item_total_number[' +
      entityId +
      '][field_tec_line_item_total_number][0][value]'
    );
  }

  // Calculate one row and (optionally) persist to hidden widget
  function calcRowAndPersist($row) {
    var $qty = $row.find('td.views-field-form-field-field-tec-quantity-1 input[name*="[field_tec_quantity]"]');
    if (!$qty.length) return 0;

    var cost = parseMoney($row.find("td.views-field-field-tec-cost").text());

    var raw = ($qty.val() || "").toString().trim();
    var qty = raw === "" ? NaN : parseFloat(raw.replace(",", "."));
    var total = isNaN(qty) ? 0 : qty * cost;

    // Update visible totals (both display columns if present)
    $row.find(".views-field-field-tec-line-item-total-number strong").text(formatMoney(total));
    $row.find(".views-field-field-tec-line-item-total-number-1 strong").text(formatMoney(total));

    // Write to hidden widget ONLY if it exists
    var entityId = getEntityIdFromQtyName($qty.attr("name"));
    if (entityId) {
      var targetName = liTotalInputName(entityId);
      var $li =
        $row.find('td.views-field-form-field-field-tec-line-item-total-number input[name="' + targetName + '"]');
      if (!$li.length) {
        $li = $row.closest("form").find('input[name="' + targetName + '"]');
      }
      if ($li.length) {
        // if qty is empty, keep widget empty (don’t force "0.00")
        if (raw === "") {
          $li.val("").trigger("input").trigger("change");
        } else {
          $li.val(total.toFixed(2)).trigger("input").trigger("change");
        }
      }
    }

    return total;
  }

  function recalcAll($context) {
    var grand = 0;
    $context.find("table.views-table tbody tr").each(function () {
      var $tr = $(this);
      if ($tr.hasClass("grand-total-row")) return;
      grand += calcRowAndPersist($tr);
    });
    $context.find(".grand-total-value").text(formatMoney(grand));
  }

  // small debounce to keep things snappy
  function debounce(fn, ms) {
    var t;
    return function () {
      var ctx = this, args = arguments;
      clearTimeout(t);
      t = setTimeout(function () { fn.apply(ctx, args); }, ms);
    };
  }

  Drupal.behaviors.tecPurchaseTotals = {
    attach: function (context) {
      var $ctx = $(context);

      // Bind qty inputs once
      bindOnce(
        $ctx.find(
          'td.views-field-form-field-field-tec-quantity-1 input[name^="form_field_field_tec_quantity_"], ' +
          'td.views-field-form-field-field-tec-quantity-1 input[name*="[field_tec_quantity]"]'
        ),
        "qty"
      ).on("input change blur", debounce(function () {
        var $row = $(this).closest("tr");
        calcRowAndPersist($row);
        // recompute grand total from fresh calculations (not from text)
        var $table = $row.closest("table");
        var g = 0;
        $table.find("tbody tr").each(function () {
          var $tr = $(this);
          if ($tr.hasClass("grand-total-row")) return;
          // recompute quickly without re-triggering widget writes:
          var $qty = $tr.find('td.views-field-form-field-field-tec-quantity-1 input[name*="[field_tec_quantity]"]');
          var cost = parseMoney($tr.find("td.views-field-field-tec-cost").text());
          var raw = ($qty.val() || "").toString().trim();
          var q = raw === "" ? NaN : parseFloat(raw.replace(",", "."));
          g += isNaN(q) ? 0 : q * cost;
        });
        $table.find(".grand-total-value").text(formatMoney(g));
      }, 50));

      // Ensure rows are calculated before submit
      bindOnce($ctx.find('form[id*="views-form"]'), "submit").on("submit", function () {
        recalcAll($ctx);
        return true;
      });

      // Initial pass (first render & after AJAX)
      setTimeout(function () {
        recalcAll($ctx);
      }, 150);
    }
  };
})(jQuery, Drupal);
