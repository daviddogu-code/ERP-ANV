/**
 * @file
 * Purchase Control (/supplier-orders/queue).
 *
 * Two jobs.
 *
 * The status column is a hand-built dropdown instead of a <select>. That is not
 * decoration: the whole column is read by colour, and a native dropdown cannot
 * be relied on to paint the options inside it -- Safari ignores the colours
 * outright, and the browsers that do honour them disagree about how. A list of
 * elements we own paints the same everywhere, at the cost of the keyboard
 * handling below that a <select> would have given us for nothing.
 *
 * And nothing here has a Save button. Every change POSTs to
 * /supplier-orders/queue/save as it is made, the same arrangement as the
 * checkboxes on /stock. On failure the cell goes back to what it was, so the
 * screen never shows a value that is not stored.
 */
(function (Drupal, once) {
  'use strict';

  var PREFIX = 'tec-po-status--';

  /**
   * The token is fetched once and reused: one round trip, not one per cell.
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

  function flash(cell, ok) {
    if (!cell) {
      return;
    }
    var cls = ok ? 'is-saved' : 'is-save-error';
    cell.classList.add(cls);
    window.setTimeout(function () {
      cell.classList.remove(cls);
    }, 700);
  }

  function complain(message) {
    if (Drupal.Message) {
      new Drupal.Message().add(message, { type: 'error' });
    }
    else {
      window.alert(message);
    }
  }

  /**
   * Swap the class that carries the pill colour.
   */
  function paint(element, value) {
    Array.prototype.slice.call(element.classList).forEach(function (name) {
      if (name.indexOf(PREFIX) === 0) {
        element.classList.remove(name);
      }
    });
    element.classList.add(PREFIX + value);
  }

  function orderOf(element) {
    var row = element.closest('tr[data-order]');
    return row ? row.getAttribute('data-order') : null;
  }

  /**
   * POST one cell. Resolves with the server's reading of the order, which is
   * not always what was sent: an order received while the page sat open comes
   * back Delivered whatever the dropdown said.
   */
  function save(orderId, field, value) {
    return getCsrfToken().then(function (token) {
      return fetch(Drupal.url('supplier-orders/queue/save'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': token
        },
        body: JSON.stringify({
          order: parseInt(orderId, 10),
          field: field,
          value: value
        })
      });
    }).then(function (resp) {
      if (!resp || !resp.ok) {
        throw new Error('HTTP ' + (resp ? resp.status : '?'));
      }
      return resp.json();
    });
  }

  // ---------------------------------------------------------------- dropdown

  function optionsOf(menu) {
    return Array.prototype.slice.call(menu.querySelectorAll('.tec-pq__opt'));
  }

  function isOpen(menu) {
    return menu.classList.contains('is-open');
  }

  function close(menu, refocus) {
    var trigger = menu.querySelector('.tec-pq__trigger');
    var list = menu.querySelector('.tec-pq__list');
    menu.classList.remove('is-open');
    list.hidden = true;
    trigger.setAttribute('aria-expanded', 'false');
    if (refocus) {
      trigger.focus();
    }
  }

  function closeAll(except) {
    Array.prototype.slice.call(document.querySelectorAll('.tec-pq__menu.is-open')).forEach(function (menu) {
      if (menu !== except) {
        close(menu, false);
      }
    });
  }

  function open(menu) {
    var trigger = menu.querySelector('.tec-pq__trigger');
    var list = menu.querySelector('.tec-pq__list');
    closeAll(menu);
    menu.classList.add('is-open');
    list.hidden = false;
    trigger.setAttribute('aria-expanded', 'true');

    var options = optionsOf(menu);
    var current = options.filter(function (option) {
      return option.getAttribute('data-value') === menu.getAttribute('data-value');
    })[0];
    (current || options[0]).focus();
  }

  /**
   * Replace a menu with a plain pill, for when the answer stops being a choice.
   *
   * Only reachable when the server disagrees with what was just picked, which
   * means somebody received the goods while this page was open. Leaving the
   * dropdown there would invite a second attempt at a question that has been
   * settled by the receipt.
   */
  function freeze(menu, progress, label) {
    var pill = document.createElement('span');
    pill.className = 'tec-po-status ' + PREFIX + progress;
    pill.textContent = label;
    menu.parentNode.replaceChild(pill, menu);
  }

  function choose(menu, value) {
    var trigger = menu.querySelector('.tec-pq__trigger');
    var cell = menu.closest('td');
    var previous = menu.getAttribute('data-value');
    var previousLabel = trigger.textContent;
    var chosen = optionsOf(menu).filter(function (option) {
      return option.getAttribute('data-value') === value;
    })[0];

    close(menu, true);
    if (value === previous) {
      return;
    }

    // Painted before the request answers, because a colour that waits for the
    // network reads as a click that did not register, and people click again.
    menu.setAttribute('data-value', value);
    paint(trigger, value);
    trigger.textContent = chosen ? chosen.textContent : value;
    optionsOf(menu).forEach(function (option) {
      option.setAttribute('aria-selected', option === chosen ? 'true' : 'false');
    });

    save(orderOf(menu), 'status', value).then(function (data) {
      flash(cell, true);
      if (data.progress !== value) {
        freeze(menu, data.progress, data.label);
      }
    }).catch(function () {
      menu.setAttribute('data-value', previous);
      paint(trigger, previous);
      trigger.textContent = previousLabel;
      optionsOf(menu).forEach(function (option) {
        option.setAttribute('aria-selected', option.getAttribute('data-value') === previous ? 'true' : 'false');
      });
      flash(cell, false);
      complain(Drupal.t('Could not save the status — it was put back. Check the connection and try again.'));
    });
  }

  /**
   * Arrow keys walk the list, Enter and space pick, Escape gives up. This is
   * the part a native dropdown would have done for us.
   */
  function moveFocus(menu, from, step) {
    var options = optionsOf(menu);
    var index = options.indexOf(from);
    var next = index + step;
    if (next < 0) {
      next = options.length - 1;
    }
    if (next >= options.length) {
      next = 0;
    }
    options[next].focus();
  }

  function wireMenu(menu) {
    var trigger = menu.querySelector('.tec-pq__trigger');
    var list = menu.querySelector('.tec-pq__list');

    trigger.addEventListener('click', function (event) {
      event.preventDefault();
      if (isOpen(menu)) {
        close(menu, true);
      }
      else {
        open(menu);
      }
    });

    trigger.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        open(menu);
      }
    });

    list.addEventListener('click', function (event) {
      var option = event.target.closest('.tec-pq__opt');
      if (option) {
        choose(menu, option.getAttribute('data-value'));
      }
    });

    list.addEventListener('keydown', function (event) {
      var option = event.target.closest('.tec-pq__opt');
      if (event.key === 'Escape') {
        event.preventDefault();
        close(menu, true);
      }
      else if (event.key === 'ArrowDown' && option) {
        event.preventDefault();
        moveFocus(menu, option, 1);
      }
      else if (event.key === 'ArrowUp' && option) {
        event.preventDefault();
        moveFocus(menu, option, -1);
      }
      else if ((event.key === 'Enter' || event.key === ' ') && option) {
        event.preventDefault();
        choose(menu, option.getAttribute('data-value'));
      }
      else if (event.key === 'Tab') {
        close(menu, false);
      }
    });
  }

  // -------------------------------------------------------------------- dates

  function wireDate(input) {
    var was = input.value;
    input.addEventListener('change', function () {
      var value = input.value;
      var cell = input.closest('td');
      save(orderOf(input), 'expected', value).then(function () {
        was = value;
        flash(cell, true);
      }).catch(function () {
        input.value = was;
        flash(cell, false);
        complain(Drupal.t('Could not save the expected day — it was put back. Check the connection and try again.'));
      });
    });
  }

  Drupal.behaviors.tecPurchaseQueue = {
    attach: function (context) {
      once('tec-pq-menu', '.tec-pq__menu', context).forEach(wireMenu);
      once('tec-pq-date', '.tec-pq__expected input', context).forEach(wireDate);

      once('tec-pq-outside', 'body', context).forEach(function (body) {
        body.addEventListener('click', function (event) {
          if (!event.target.closest('.tec-pq__menu')) {
            closeAll(null);
          }
        });
      });
    }
  };
})(Drupal, once);
