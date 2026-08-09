/**
 * jQuery Select2 Multi checkboxes
 * - allow to select multi values via normal dropdown control
 *
 * author      : wasikuss
 * repo        : https://github.com/wasikuss/select2-multi-checkboxes
 * inspired by : https://github.com/select2/select2/issues/411
 * License     : MIT
 */
(function ($) {
  var S2MultiCheckboxes = function (options, element) {

    var self = this;
    self.options = options;
    self.$element = $(element);
    var values = self.$element.val();
    self.$element.removeAttr('multiple');
    self.select2 = self.$element.select2({
      allowClear: true,
      minimumResultsForSearch: options.minimumResultsForSearch,
      maximumSelection: JSON.parse(element.dataset.select2Config).maximumSelectionLength, //changed
      allowMultiple: JSON.parse(element.dataset.select2Config).multiple, //changed
      placeholder: options.placeholder,
      closeOnSelect: false,
      templateSelection: function () {
        return self.options.templateSelection(self.$element.val() || [], $('option', self.$element).length);
      },
      templateResult: function (result) {
        if (result.loading !== undefined)
          return result.text;
        return $('<div>').text(result.text).addClass(self.options.wrapClass);
      },
      matcher: function (params, data) {
        var original_matcher = $.fn.select2.defaults.defaults.matcher;
        var result = original_matcher(params, data);
        if (result && self.options.searchMatchOptGroups && data.children && result.children && data.children.length != result.children.length) {
          result.children = data.children;
        }
        return result;
      }
    }).data('select2');
    self.select2.$results.off("mouseup").on("mouseup", ".select2-results__option[aria-selected]", (function (self) {
      return function (evt) {

        var allowMultiple = self.selection.options.options.allowMultiple //changed
        var maxSelectionLength = self.selection.options.options.maximumSelection//changed
        var currentSelection = self.$element[0].selectedOptions.length //changed
        var $this = $(this);
        const Utils = $.fn.select2.amd.require('select2/utils')
        var data = Utils.GetData(this, 'data');
        if ($this.attr('aria-selected') === 'true') { //changed
          self.trigger('unselect', {
            originalEvent: evt,
            data: data
          });
          return;
        }
        if (!allowMultiple) {
          currentSelection = self.$element[0].selectedOptions.length - 1 //changed
          if (currentSelection == maxSelectionLength) {
            self.trigger('select', {
              originalEvent: evt,
              data: data
            });
          }
        } else if(currentSelection !== maxSelectionLength || maxSelectionLength === 0) { //changed
          self.trigger('select', {
            originalEvent: evt,
            data: data
          });
        }

      }
    })(self.select2));
    if (element[0].getAttribute("value") !== "") {
      self.$element.attr('multiple', 'multiple').val(values).trigger('change.select2');
    }
  }

  $.fn.extend({
    select2MultiCheckboxes: function () {
      var options = $.extend({
        placeholder: 'Choose elements',
        templateSelection: function (selected, total) {
          return selected.length + ' > ' + total + ' total';
        },
        wrapClass: 'wrap',
        minimumResultsForSearch: -1,
        searchMatchOptGroups: true
      }, arguments[0]);

      this.each(function () {
        new S2MultiCheckboxes(options, this);
      });
    }
  });
})(jQuery);
