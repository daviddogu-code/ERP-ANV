/**
 * @file
 * Size-card row: window-bottom scrollbar, and matching row heights across
 * the cards in a group (product BoM and pattern recipe).
 */
(function (Drupal, once) {
  'use strict';

  const WRAP = '.tec-size-cards, .tec-size-cards-field .field__items, .field--name-field-tec-size-variations .field__items';

  function wraps() {
    const seen = new Set();
    const list = [];
    document.querySelectorAll(WRAP).forEach((wrap) => {
      if (seen.has(wrap)) {
        return;
      }
      seen.add(wrap);
      list.push(wrap);
    });
    return list;
  }

  function cardsIn(group) {
    return [...group.children].filter((el) => (
      el.classList.contains('tec-size-card')
      || el.classList.contains('field__item')
    ));
  }

  function bomTable(card) {
    const named = card.querySelector('table.tec-size-card__bom');
    if (named) {
      return named;
    }
    const middle = card.querySelector('.layout__region--middle table:not(.tec-material-cost)');
    if (middle) {
      return middle;
    }
    return [...card.querySelectorAll('table')].find((table) => !table.classList.contains('tec-material-cost')) || null;
  }

  function padBomBodies(cards) {
    const bodies = cards.map((card) => {
      const table = bomTable(card);
      return table && table.tBodies[0] ? table.tBodies[0] : null;
    });
    bodies.forEach((body) => {
      if (!body) {
        return;
      }
      [...body.querySelectorAll('tr.tec-size-card__bom-row--pad')].forEach((row) => row.remove());
    });
    const max = Math.max(0, ...bodies.map((body) => (body ? body.rows.length : 0)));
    bodies.forEach((body) => {
      if (!body || body.rows.length >= max) {
        return;
      }
      const cols = body.rows[0] ? body.rows[0].cells.length : 4;
      if (cols < 1) {
        return;
      }
      while (body.rows.length < max) {
        const tr = document.createElement('tr');
        tr.className = 'tec-size-card__bom-row--empty tec-size-card__bom-row--pad';
        for (let i = 0; i < cols; i++) {
          const td = document.createElement('td');
          td.innerHTML = '&nbsp;';
          tr.appendChild(td);
        }
        body.appendChild(tr);
      }
    });
  }

  function rowSets(group) {
    const cards = cardsIn(group);
    if (cards.length < 2) {
      return [];
    }
    const sets = [];
    const one = (selector) => cards.map((card) => {
      const el = card.querySelector(selector);
      return el ? [el] : [];
    });

    const tops = one('.layout__region--top');
    if (tops.some((rows) => rows.length)) {
      sets.push(tops);
    }
    const titles = one('.tec-size-card__title, .field--name-field-tec-size');
    if (titles.some((rows) => rows.length)) {
      sets.push(titles);
    }
    const prices = one('.field--name-field-tec-price');
    if (prices.some((rows) => rows.length)) {
      sets.push(prices);
    }
    const labels = one('.field--name-field-tec-view-bill-of-materials > .field__label, .tec-size-card > .field__label');
    if (labels.some((rows) => rows.length)) {
      sets.push(labels);
    }

    const boms = cards.map((card) => {
      const table = bomTable(card);
      return table ? [...table.querySelectorAll('tr')] : [];
    });
    if (boms.some((rows) => rows.length)) {
      sets.push(boms);
    }

    const forms = cards.map((card) => [...card.querySelectorAll('.tec-size-card__form-head, .tec-size-card__form-row')]);
    if (forms.some((rows) => rows.length)) {
      sets.push(forms);
    }

    const costs = cards.map((card) => {
      const table = card.querySelector('table.tec-material-cost');
      return table ? [...table.querySelectorAll('tr')] : [];
    });
    if (costs.some((rows) => rows.length)) {
      sets.push(costs);
    }
    return sets;
  }

  function clearRowHeight(row) {
    if (!row) {
      return;
    }
    row.style.height = '';
    row.querySelectorAll('th, td').forEach((cell) => {
      cell.style.height = '';
    });
  }

  function setRowHeight(row, px) {
    row.style.height = Math.ceil(px) + 'px';
  }

  function alignRows(group, reset) {
    const cards = cardsIn(group);
    if (cards.length < 2) {
      return;
    }
    if (reset) {
      padBomBodies(cards);
    }
    rowSets(group).forEach((rowsByCard) => {
      const n = Math.max(0, ...rowsByCard.map((rows) => rows.length));
      if (reset) {
        for (let i = 0; i < n; i++) {
          rowsByCard.forEach((rows) => clearRowHeight(rows[i]));
        }
      }
      for (let i = 0; i < n; i++) {
        let max = 0;
        rowsByCard.forEach((rows) => {
          if (rows[i]) {
            max = Math.max(max, rows[i].getBoundingClientRect().height);
          }
        });
        if (max <= 0) {
          continue;
        }
        rowsByCard.forEach((rows) => {
          if (!rows[i]) {
            return;
          }
          const now = rows[i].getBoundingClientRect().height;
          if (Math.abs(now - max) >= 0.5) {
            setRowHeight(rows[i], max);
          }
        });
      }
    });
  }

  function activeWrap(list) {
    let visible = null;
    list.forEach((wrap) => {
      const rect = wrap.getBoundingClientRect();
      const onScreen = rect.top < window.innerHeight && rect.bottom > 0;
      const overflows = wrap.scrollWidth > wrap.clientWidth + 1;
      if (onScreen && overflows && !visible) {
        visible = wrap;
      }
    });
    return visible;
  }

  function ensureBar() {
    let bar = document.querySelector('.tec-size-cards__hscroll');
    if (bar) {
      return bar;
    }
    bar = document.createElement('div');
    bar.className = 'tec-size-cards__hscroll';
    const spacer = document.createElement('div');
    spacer.className = 'tec-size-cards__hscroll-inner';
    bar.appendChild(spacer);
    document.body.appendChild(bar);

    bar.addEventListener('scroll', () => {
      const left = bar.scrollLeft;
      wraps().forEach((wrap) => {
        if (wrap.scrollLeft !== left) {
          wrap.scrollLeft = left;
        }
      });
    });

    return bar;
  }

  function layout() {
    const bar = document.querySelector('.tec-size-cards__hscroll');
    if (!bar) {
      return;
    }
    const spacer = bar.querySelector('.tec-size-cards__hscroll-inner');
    const list = wraps();
    const wrap = activeWrap(list);
    if (!wrap) {
      bar.style.display = 'none';
      return;
    }
    const rect = wrap.getBoundingClientRect();
    spacer.style.width = wrap.scrollWidth + 'px';
    bar.style.left = rect.left + 'px';
    bar.style.width = rect.width + 'px';
    bar.style.display = 'block';
    if (bar.scrollLeft !== wrap.scrollLeft) {
      bar.scrollLeft = wrap.scrollLeft;
    }
  }

  Drupal.behaviors.tecSizeCardsHscroll = {
    attach(context) {
      once('tec-size-cards-bar', 'html', context).forEach(ensureBar);
      once('tec-size-cards-wrap', WRAP, context).forEach((wrap) => {
        wrap.addEventListener('scroll', () => {
          const bar = document.querySelector('.tec-size-cards__hscroll');
          if (bar && bar.scrollLeft !== wrap.scrollLeft) {
            bar.scrollLeft = wrap.scrollLeft;
          }
          wraps().forEach((other) => {
            if (other !== wrap && other.scrollLeft !== wrap.scrollLeft) {
              other.scrollLeft = wrap.scrollLeft;
            }
          });
        });
      });
      layout();
      once('tec-size-cards-window', 'html', context).forEach(() => {
        window.addEventListener('scroll', layout, { passive: true });
        window.addEventListener('resize', () => {
          wraps().forEach((group) => alignRows(group, true));
          layout();
        });
      });
    },
  };

  Drupal.behaviors.tecSizeCardsAlignRows = {
    attach(context) {
      once('tec-size-cards-align', WRAP, context).forEach((group) => {
        const cards = cardsIn(group);
        if (cards.length < 2) {
          return;
        }
        const run = (reset) => {
          alignRows(group, reset);
          layout();
        };
        run(true);
        if (typeof ResizeObserver === 'function') {
          let ticking = false;
          const ro = new ResizeObserver(() => {
            if (group.dataset.tecAligning === '1' || ticking) {
              return;
            }
            ticking = true;
            requestAnimationFrame(() => {
              ticking = false;
              group.dataset.tecAligning = '1';
              run(false);
              requestAnimationFrame(() => {
                delete group.dataset.tecAligning;
              });
            });
          });
          cards.forEach((card) => ro.observe(card));
        }
      });
    },
  };
})(Drupal, once);
