/**
 * @file
 * Keeps the NO column honest while products are dragged around.
 *
 * Core tabledrag renumbers its own hidden weight selects and nothing else, so
 * without this the numbers on screen would only agree with the list again after
 * Save. Same MutationObserver trick as the production queue.
 */
((Drupal, once) => {
  function renumber(table) {
    const rows = table.querySelectorAll('tbody tr');
    let place = 0;
    rows.forEach((row) => {
      const cell = row.querySelector('.tec-brand-order__no');
      if (!cell) {
        return;
      }
      place += 1;
      cell.textContent = place;
    });
  }

  Drupal.behaviors.tecBrandProducts = {
    attach(context) {
      once('tec-brand-products', '#tec-brand-products', context).forEach((table) => {
        const tbody = table.querySelector('tbody');
        if (!tbody) {
          return;
        }
        new MutationObserver(() => {
          renumber(table);
        }).observe(tbody, { childList: true });
      });
    },
  };
})(Drupal, once);
