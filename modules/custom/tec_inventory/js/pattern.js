(function (Drupal, once, $) {
  Drupal.behaviors.tecPatternKind = {
    attach(context) {
      once('tec-pattern-kind', 'select.tec-pattern-recipe__kind', context).forEach((el) => {
        const paint = () => {
          const row = el.closest('.tec-size-card__form-row');
          const isType = el.value === 'type';
          if (row) {
            row.classList.toggle('tec-size-card__form-row--material', !isType);
            row.classList.toggle('tec-size-card__form-row--type', isType);
          }
        };
        el.addEventListener('change', paint);
        paint();
      });
    },
  };

  Drupal.behaviors.tecPatternSaveUnlock = {
    attach(context) {
      once('tec-pattern-save-unlock', 'html', context).forEach(() => {
        const unlock = () => {
          document.querySelectorAll('.tec-pattern-save').forEach((btn) => {
            btn.disabled = false;
          });
          document.querySelectorAll('.form-actions .ajax-progress, .gin-sticky-form-actions .ajax-progress').forEach((el) => {
            el.remove();
          });
        };
        $(document).on('ajaxError.tecPatternSave ajaxStop.tecPatternSave', unlock);
        $(document).on('ajaxComplete.tecPatternSave', () => {
          const err = document.querySelector('#tec-pattern-shell .messages--error');
          if (err) {
            err.scrollIntoView({ block: 'start' });
          }
        });
      });
    },
  };
})(Drupal, once, jQuery);
