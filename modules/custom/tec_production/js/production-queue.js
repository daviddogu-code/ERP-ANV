/**
 * @file
 * Orders on Queue behaviours:
 * - Live deadline / NO renumbering while dragging rows. Produced (X) is
 *   read-only here: it comes from the Daily Production Log.
 * - Google Sheets style UX: one fixed horizontal scrollbar at the bottom of
 *   the window (synced with the table) and header + TOTAL row frozen while
 *   the page scrolls vertically.
 *
 * Server-side values (saved on submit) remain the source of truth.
 */
(function (Drupal, once) {
  'use strict';

  function recalc(table) {
    const piecesDay = parseFloat(table.dataset.piecesDay || '0');
    const includeOpen = table.dataset.includeOpen === '1';
    if (!(piecesDay > 0)) {
      return;
    }
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    let cumulative = 0;

    // Bottom row is produced first, so accumulate from bottom to top.
    const rows = Array.from(table.querySelectorAll('tbody tr.tec-queue__row')).reverse();
    rows.forEach(function (row, index) {
      // Renumber the NO column: 1 = bottom = next to produce.
      const noCell = row.querySelector('.tec-queue__no');
      if (noCell) {
        noCell.textContent = String(index + 1);
      }
      const remaining = parseFloat(row.dataset.remaining || '0');

      const isOpen = row.dataset.open === '1';
      const isPostMfg = row.dataset.postMfg === '1';
      // Completed / Ready for collection: visible on queue, no mfg capacity.
      const counts = !isPostMfg && (!isOpen || includeOpen);
      const cell = row.querySelector('.tec-queue__deadline');
      if (!cell) {
        return;
      }
      if (counts) {
        cumulative += remaining;
        const deadline = new Date(today.getTime() + (cumulative / piecesDay) * 86400000);
        cell.textContent = deadline.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
      }
      else {
        cell.textContent = '—';
      }
    });
  }

  /**
   * Fixed bottom scrollbar synced with the table + frozen header rows.
   */
  function setupSheetScrolling(table) {
    const wrap = table.closest('.tec-queue__table-wrap');
    if (!wrap) {
      return;
    }

    const bar = document.createElement('div');
    bar.className = 'tec-queue__hscroll';
    const spacer = document.createElement('div');
    spacer.className = 'tec-queue__hscroll-inner';
    bar.appendChild(spacer);
    document.body.appendChild(bar);

    // Opaque strip that hides rows sliding between the admin toolbar and the
    // frozen header (covers any measurement mismatch with the theme).
    const mask = document.createElement('div');
    mask.className = 'tec-queue__topmask';
    document.body.appendChild(mask);

    // Two-way sync (assigning an unchanged scrollLeft fires no event).
    bar.addEventListener('scroll', function () {
      wrap.scrollLeft = bar.scrollLeft;
    });
    wrap.addEventListener('scroll', function () {
      bar.scrollLeft = wrap.scrollLeft;
    });

    const thead = table.querySelector('thead');
    const totals = table.querySelector('tr.tec-queue__totals');

    // Real bottom edge of the fixed top chrome (Gin/toolbar). The value
    // reported by Drupal.displace can differ a few px from what is painted,
    // which left a strip where rows showed through.
    function computeStickyTop() {
      let measured = 0;
      let found = false;
      document.querySelectorAll('#toolbar-administration, .gin-secondary-toolbar, [data-offset-top]').forEach(function (el) {
        const pos = getComputedStyle(el).position;
        if (pos === 'fixed' || pos === 'sticky') {
          const r = el.getBoundingClientRect();
          if (r.top <= 1 && r.bottom < window.innerHeight / 2) {
            measured = Math.max(measured, r.bottom);
            found = true;
          }
        }
      });
      if (found) {
        return measured;
      }
      return (Drupal.displace && Drupal.displace.offsets && Drupal.displace.offsets.top) || 0;
    }

    function layout() {
      const rect = wrap.getBoundingClientRect();

      // Bottom bar: only while the table is on screen and overflows.
      spacer.style.width = table.scrollWidth + 'px';
      bar.style.left = rect.left + 'px';
      bar.style.width = rect.width + 'px';
      const overflows = table.scrollWidth > wrap.clientWidth + 1;
      const onScreen = rect.top < window.innerHeight && rect.bottom > 0;
      bar.style.display = overflows && onScreen ? 'block' : 'none';

      // Frozen header + TOTAL row: push them down by how far the table top
      // has scrolled past the admin toolbar.
      const stickyTop = computeStickyTop();
      const frozenHeight = (thead ? thead.offsetHeight : 0) + (totals ? totals.offsetHeight : 0);
      let y = stickyTop - rect.top;
      const maxY = wrap.offsetHeight - frozenHeight - 60;
      y = Math.max(0, Math.min(y, Math.max(0, maxY)));
      const transform = y > 0 ? 'translateY(' + y + 'px)' : '';
      if (thead) {
        thead.style.transform = transform;
      }
      if (totals) {
        totals.style.transform = transform;
      }

      // White strip from the viewport top to the frozen header, so no row
      // ever shows through the remaining gap.
      if (y > 0 && thead) {
        mask.style.height = Math.max(0, thead.getBoundingClientRect().top + 1) + 'px';
        mask.style.display = 'block';
      }
      else {
        mask.style.display = 'none';
      }
    }

    layout();
    window.addEventListener('scroll', layout, { passive: true });
    window.addEventListener('resize', layout);
  }

  Drupal.behaviors.tecProductionQueue = {
    attach(context) {
      once('tec-queue', '#tec-production-queue', context).forEach(function (table) {
        setupSheetScrolling(table);

        // Recalculate whenever tabledrag reorders rows.
        const tbody = table.querySelector('tbody');
        if (tbody) {
          new MutationObserver(function () {
            recalc(table);
          }).observe(tbody, { childList: true });
        }
      });
    },
  };
})(Drupal, once);
