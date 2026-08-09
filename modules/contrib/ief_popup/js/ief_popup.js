(function ($, Drupal, drupalSettings) {

    //$("body").append("<div class='ief-popup-overlay'></div>");

    Drupal.behaviors.ief_popup = {
        attach: function (context, settings) {

            /* make sure the Drupal toolbar is placed behind the IEF Complex Widget Dialog */
            if ($(".ief-popup-wrapper", context).length > 0) {
                $(".toolbar").css("position", "relative");
                $(".toolbar").css("z-index",  1);
            }
            else {
                $(".toolbar").css('z-index',  '');
            }
          $(".toolbar").css("z-index",  "unset");
            /* fire click event on cancel button if .ief-popup-close is clicked */
            $(".ief-popup-close").click(function (e) {
                e.preventDefault();
                form_wrapper = $(this).closest(".form-wrapper");
                form_wrapper.find(".ief-popup-cancel").mousedown();
                e.stopImmediatePropagation();
            });

            /* move drupal messages from IEF to .ief-popup-wrapper */
            if ($(context).attr("data-drupal-messages") !== undefined &&
                $(".ief-popup-wrapper").length > 0) {
                //$(context).css("display", "none");
                $(context).css("margin-top", 0);
                $(context).css("margin-bottom", 0);
                $(".ief-popup-wrapper .ui-dialog-content").prepend(context);
            }

            // tweak some CSS with IEF dialog used in vertical tabs so that tabs
            // are positioned "under" the dialog
            if ($(".ief-popup-wrapper", context).length > 0) {
                $(".vertical-tabs__menu-item").addClass("ief-open");
                $(".vertical-tabs__menu-item a").css("z-index", 0);
                $(".vertical-tabs__items--processed").css("position", "unset");
            }
            else {
                $(".vertical-tabs__menu-item").removeClass("ief-open");
                $(".vertical-tabs__menu-item a").css("z-index", 3);
                $(".vertical-tabs__items--processed").css("position", "relative");
            }
        }
    }

})(jQuery, Drupal, drupalSettings);
