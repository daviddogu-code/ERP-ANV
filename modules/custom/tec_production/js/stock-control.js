/**
 * @file
 * Stock Control board: master column checkboxes + live Required / balances.
 *
 * Server-side values are authoritative; this only previews the numbers as
 * checkboxes are toggled, before Save persists them.
 */
(function (Drupal, once) {
  'use strict';

  function fmt(value) {
    var rounded = Math.round(value * 100) / 100;
    return rounded.toLocaleString('en-US', { maximumFractionDigits: 2 });
  }

  function setNumber(row, selector, value) {
    var el = row.querySelector(selector);
    if (!el) {
      return;
    }
    el.textContent = fmt(value);
    el.classList.toggle('is-neg', value < -0.000001);
  }

  function recalcRow(row) {
    var stock = parseFloat(row.dataset.stock || '0') || 0;
    var split = parseFloat(row.dataset.split || '1') || 1;
    var units = parseFloat(row.dataset.units || '1') || 1;
    var onOrder = parseFloat(row.dataset.onorder || '0') || 0;

    var required = 0;
    row.querySelectorAll('.tec-stock__qty').forEach(function (span) {
      var qty = parseFloat(span.dataset.qty || '0') || 0;
      var checkbox = row.querySelector('.tec-stock__chk[data-order="' + span.dataset.order + '"]');
      var checked = checkbox && checkbox.checked;
      span.closest('td').classList.toggle('is-checked', !!checked);
      if (qty > 0 && !checked) {
        required += qty;
      }
    });

    var afterQueue = stock - required / split;
    var afterQueueUop = afterQueue / units;
    var projUop = afterQueueUop + onOrder;

    setNumber(row, '.tec-stock__req', required);
    setNumber(row, '.tec-stock__after-queue', afterQueue);
    setNumber(row, '.tec-stock__after-queue-uop', afterQueueUop);
    setNumber(row, '.tec-stock__proj-uop', projUop);
  }

  function refreshMaster(table, orderId) {
    var master = table.querySelector('.tec-stock__master[data-order="' + orderId + '"]');
    if (!master) {
      return;
    }
    var boxes = table.querySelectorAll('tbody .tec-stock__chk[data-order="' + orderId + '"]');
    var all = boxes.length > 0;
    boxes.forEach(function (box) {
      if (!box.checked) {
        all = false;
      }
    });
    master.checked = all;
  }

  /**
   * Auto-save: every toggle POSTs immediately to /stock/check (there is no
   * Save button). On failure the checkboxes revert and an error is shown.
   */
  var csrfTokenPromise = null;
  function getCsrfToken() {
    if (!csrfTokenPromise) {
      csrfTokenPromise = fetch(Drupal.url('session/token')).then(function (r) {
        return r.text();
      });
    }
    return csrfTokenPromise;
  }

  function cellOf(table, orderId, tid) {
    var row = table.querySelector('tbody tr[data-tid="' + tid + '"]');
    return row ? row.querySelector('.tec-stock__chk[data-order="' + orderId + '"]') : null;
  }

  function flashCells(table, orderId, tids, ok) {
    tids.forEach(function (tid) {
      var box = cellOf(table, orderId, tid);
      var td = box && box.closest('td');
      if (!td) {
        return;
      }
      var cls = ok ? 'is-saved' : 'is-save-error';
      td.classList.add(cls);
      setTimeout(function () {
        td.classList.remove(cls);
      }, 700);
    });
  }

  function saveChecks(table, orderId, tids, checked) {
    getCsrfToken().then(function (token) {
      return fetch(Drupal.url('stock/check'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': token
        },
        body: JSON.stringify({
          order_id: parseInt(orderId, 10),
          materials: tids.map(Number),
          checked: !!checked
        })
      });
    }).then(function (resp) {
      if (!resp || !resp.ok) {
        throw new Error('HTTP ' + (resp ? resp.status : '?'));
      }
      flashCells(table, orderId, tids, true);
    }).catch(function () {
      // Revert the UI so the screen never lies about what is stored.
      tids.forEach(function (tid) {
        var box = cellOf(table, orderId, tid);
        if (box) {
          box.checked = !checked;
          recalcRow(box.closest('tr'));
        }
      });
      refreshMaster(table, orderId);
      flashCells(table, orderId, tids, false);
      var msg = Drupal.t('Could not save — the checkbox was reverted. Check the connection and try again.');
      if (Drupal.Message) {
        new Drupal.Message().add(msg, { type: 'error' });
      }
      else {
        window.alert(msg);
      }
    });
  }

  /**
   * Google Sheets style UX, same pattern as /o/queue: one fixed horizontal
   * scrollbar at the bottom of the window (synced with the table) and the
   * header row frozen while the page scrolls vertically.
   */
  function setupSheetScrolling(table) {
    var wrap = table.closest('.tec-stock__scroll');
    if (!wrap) {
      return;
    }

    var bar = document.createElement('div');
    bar.className = 'tec-stock__hscroll';
    var spacer = document.createElement('div');
    spacer.className = 'tec-stock__hscroll-inner';
    bar.appendChild(spacer);
    document.body.appendChild(bar);

    // Opaque strip that hides rows sliding between the admin toolbar and the
    // frozen header (covers any measurement mismatch with the theme).
    var mask = document.createElement('div');
    mask.className = 'tec-stock__topmask';
    document.body.appendChild(mask);

    // Two-way sync (assigning an unchanged scrollLeft fires no event).
    bar.addEventListener('scroll', function () {
      wrap.scrollLeft = bar.scrollLeft;
    });
    wrap.addEventListener('scroll', function () {
      bar.scrollLeft = wrap.scrollLeft;
    });

    var thead = table.querySelector('thead');

    // Real bottom edge of the fixed top chrome (Gin/toolbar). The value
    // reported by Drupal.displace can differ a few px from what is painted.
    function computeStickyTop() {
      var measured = 0;
      var found = false;
      document.querySelectorAll('#toolbar-administration, .gin-secondary-toolbar, [data-offset-top]').forEach(function (el) {
        var pos = getComputedStyle(el).position;
        if (pos === 'fixed' || pos === 'sticky') {
          var r = el.getBoundingClientRect();
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
      var rect = wrap.getBoundingClientRect();

      // Bottom bar: only while the table is on screen and overflows.
      spacer.style.width = table.scrollWidth + 'px';
      bar.style.left = rect.left + 'px';
      bar.style.width = rect.width + 'px';
      var overflows = table.scrollWidth > wrap.clientWidth + 1;
      var onScreen = rect.top < window.innerHeight && rect.bottom > 0;
      bar.style.display = overflows && onScreen ? 'block' : 'none';

      // Frozen header: push it down by how far the table top has scrolled
      // past the admin toolbar.
      var stickyTop = computeStickyTop();
      var frozenHeight = thead ? thead.offsetHeight : 0;
      var y = stickyTop - rect.top;
      var maxY = wrap.offsetHeight - frozenHeight - 60;
      y = Math.max(0, Math.min(y, Math.max(0, maxY)));
      var transform = y > 0 ? 'translateY(' + y + 'px)' : '';
      if (thead) {
        thead.style.transform = transform;
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

  Drupal.behaviors.tecStockControl = {
    attach: function (context) {
      once('tec-stock', 'table.tec-stock__table', context).forEach(function (table) {
        setupSheetScrolling(table);
        table.querySelectorAll('tbody tr').forEach(recalcRow);
        table.querySelectorAll('thead .tec-stock__master').forEach(function (master) {
          refreshMaster(table, master.dataset.order);
        });

        table.addEventListener('change', function (event) {
          var target = event.target;
          if (target.classList.contains('tec-stock__chk')) {
            recalcRow(target.closest('tr'));
            refreshMaster(table, target.dataset.order);
            var tid = target.closest('tr').dataset.tid;
            saveChecks(table, target.dataset.order, [tid], target.checked);
          }
          else if (target.classList.contains('tec-stock__master')) {
            var orderId = target.dataset.order;
            var changedTids = [];
            table.querySelectorAll('tbody .tec-stock__chk[data-order="' + orderId + '"]').forEach(function (box) {
              if (box.checked !== target.checked) {
                box.checked = target.checked;
                changedTids.push(box.closest('tr').dataset.tid);
              }
            });
            table.querySelectorAll('tbody tr').forEach(recalcRow);
            if (changedTids.length) {
              saveChecks(table, orderId, changedTids, target.checked);
            }
          }
        });
      });
    }
  };
})(Drupal, once);
