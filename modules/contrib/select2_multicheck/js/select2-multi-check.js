(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.select2MultiCheckboxes = {
    attach: function (context) {
      $(".select2-multiple").select2MultiCheckboxes({
        templateSelection: function (selected, total, context) {
          return Drupal.t("Selected @selected of @total", {
            "@selected": selected.length,
            "@total": total
          });
        },
      });
      $(".select2-single").select2MultiCheckboxes({
        templateSelection: function (selected, total, context) {
          if (typeof (selected) == "object") {
            return Drupal.t("Selected @selected of @total", {
              "@selected": selected.length,
              "@total": total - 1
            });
          } else {
            return Drupal.t("Selected @selected of @total", {
              "@selected": 1,
              "@total": total - 1
            });
          }
        },
      });
      // Get the widget width for elements with multiple selections.
      $(".select2-multiple + .select2-container").each(function(index){
        $(this).css("width",JSON.parse($(this)[0].previousSibling.dataset.select2Config).width)
      })
      // Get the widget width for elements with a single selection.
      $(".select2-single + .select2-container").each(function(index){
        $(this).css("width",JSON.parse($(this)[0].previousSibling.dataset.select2Config).width)
      })
    }
  };
})(jQuery, Drupal, drupalSettings);
