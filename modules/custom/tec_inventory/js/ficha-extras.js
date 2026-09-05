/**
 * @file
 * Extra rows live inside each size BoM table on the product ficha.
 */
(function (Drupal, once, $) {
  'use strict';

  const metaCache = {};

  function colorIdOf(table) {
    return table.getAttribute('data-tec-color-id') || '';
  }

  function scopeOf(table) {
    let node = table.parentElement;
    while (node && node !== document.body) {
      const sizes = node.querySelector('.field--name-field-tec-size-variations');
      if (sizes && sizes.contains(table)) {
        return node;
      }
      node = node.parentElement;
    }
    return table.closest('.field--name-field-tec-size-variations') || table.parentElement;
  }

  function tablesOf(scope, colorId) {
    return [...scope.querySelectorAll('table.tec-size-card__bom[data-tec-color-id="' + colorId + '"]')];
  }

  function extractTid(value) {
    const match = /\((\d+)\)\s*$/.exec(value || '');
    return match ? parseInt(match[1], 10) : 0;
  }

  function parseQty(raw) {
    const text = String(raw || '').trim();
    if (/^[+-]?\d+(\.\d+)?$/.test(text)) {
      return parseFloat(text);
    }
    const fraction = text.match(/^([+-]?\d+(?:\.\d+)?)\s*\/\s*(\d+(?:\.\d+)?)$/);
    if (fraction) {
      const denominator = parseFloat(fraction[2]);
      if (!denominator) {
        return null;
      }
      return parseFloat(fraction[1]) / denominator;
    }
    return null;
  }

  function formatCost(amount) {
    return '\u0E3F ' + amount.toLocaleString('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  }

  function nextLine(tables) {
    let max = -1;
    tables.forEach((table) => {
      table.querySelectorAll('tr.tec-ficha-extra').forEach((tr) => {
        const n = parseInt(tr.getAttribute('data-tec-extra-line') || '-1', 10);
        if (!Number.isNaN(n)) {
          max = Math.max(max, n);
        }
      });
    });
    return max + 1;
  }

  function skuLabel(value) {
    return String(value || '').replace(/\s*\(\d+\)\s*$/, '').trim();
  }

  function skuWrap(node) {
    return node ? node.closest('.tec-ficha-extra-sku') : null;
  }

  function showPicked(wrap) {
    if (!wrap) {
      return;
    }
    const input = wrap.querySelector('.tec-ficha-extra-sku-input');
    const name = wrap.querySelector('.tec-ficha-extra-sku-name');
    wrap.classList.add('is-picked');
    wrap.classList.remove('is-editing');
    if (input) {
      input.tabIndex = -1;
    }
    if (name) {
      name.textContent = skuLabel(input ? input.value : '');
    }
  }

  function showEditing(wrap) {
    if (!wrap) {
      return;
    }
    const input = wrap.querySelector('.tec-ficha-extra-sku-input');
    wrap.classList.add('is-editing');
    wrap.classList.remove('is-picked');
    if (input) {
      input.tabIndex = 0;
      input.focus();
      input.select();
    }
  }

  function extraRow(line, term, path, sizeId) {
    const tr = document.createElement('tr');
    tr.className = 'tec-recipe--blue tec-recipe--extra tec-ficha-extra';
    tr.setAttribute('data-tec-extra-line', String(line));
    tr.innerHTML = '<td class="views-align-left tec-ficha-extra-sku-cell"></td>'
      + '<td class="views-align-right text-end"></td>'
      + '<td class="views-align-left tec-ficha-extra-uou">&nbsp;</td>'
      + '<td class="views-align-right text-end tec-ficha-extra-cost">&nbsp;</td>';
    const wrap = document.createElement('div');
    wrap.className = 'tec-ficha-extra-sku is-editing';
    const name = document.createElement('button');
    name.type = 'button';
    name.className = 'tec-ficha-extra-sku-name';
    name.setAttribute('aria-label', 'Change SKU');
    const sku = document.createElement('input');
    sku.type = 'text';
    sku.id = 'tec-ficha-extra-sku-' + sizeId + '-' + line;
    sku.size = 16;
    sku.autocomplete = 'off';
    sku.className = 'form-text form-autocomplete tec-ficha-extra-sku-input';
    sku.setAttribute('aria-label', 'SKU');
    sku.setAttribute('data-tec-extra-line', String(line));
    sku.setAttribute('data-tec-size-term', term);
    sku.setAttribute('data-autocomplete-path', path);
    wrap.appendChild(name);
    wrap.appendChild(sku);
    const qty = document.createElement('input');
    qty.type = 'text';
    qty.size = 6;
    qty.autocomplete = 'off';
    qty.className = 'form-text tec-ficha-extra-qty-input';
    qty.setAttribute('aria-label', 'Requires');
    qty.setAttribute('data-tec-extra-line', String(line));
    qty.setAttribute('data-tec-size-term', term);
    tr.cells[0].appendChild(wrap);
    tr.cells[1].appendChild(qty);
    return tr;
  }

  function paintRow(tr, meta) {
    if (meta) {
      const uou = meta.uou || '';
      if (uou) {
        tr.setAttribute('data-tec-uou', uou);
      }
      else {
        tr.removeAttribute('data-tec-uou');
      }
      if (meta.price === null || meta.price === undefined || meta.price === '') {
        tr.removeAttribute('data-tec-unit-price');
      }
      else {
        tr.setAttribute('data-tec-unit-price', String(meta.price));
      }
    }
    const uouCell = tr.querySelector('.tec-ficha-extra-uou') || tr.cells[2];
    const costCell = tr.querySelector('.tec-ficha-extra-cost') || tr.cells[3];
    const qty = tr.querySelector('.tec-ficha-extra-qty-input');
    const parsed = parseQty(qty ? qty.value : '');
    const unit = parseFloat(tr.getAttribute('data-tec-unit-price'));
    const uou = tr.getAttribute('data-tec-uou') || '';
    uouCell.textContent = uou || '\u00a0';
    if (parsed !== null && !Number.isNaN(unit)) {
      const line = parsed * unit;
      tr.setAttribute('data-tec-line-cost', String(Math.round(line * 100) / 100));
      costCell.textContent = formatCost(line);
    }
    else {
      tr.removeAttribute('data-tec-line-cost');
      costCell.textContent = '\u00a0';
    }
  }

  function retotal(table) {
    let sum = 0;
    table.querySelectorAll('tr[data-tec-line-cost]').forEach((tr) => {
      const n = parseFloat(tr.getAttribute('data-tec-line-cost'));
      if (!Number.isNaN(n)) {
        sum += n;
      }
    });
    const wrap = table.closest('.tec-size-card__recipe') || table.parentElement;
    const amount = wrap ? wrap.querySelector('.tec-material-cost__amount') : null;
    if (amount) {
      amount.textContent = formatCost(sum);
    }
  }

  function collect(tables) {
    const lines = {};
    tables.forEach((table) => {
      const term = table.getAttribute('data-tec-size-term');
      table.querySelectorAll('tr.tec-ficha-extra').forEach((tr) => {
        const line = tr.getAttribute('data-tec-extra-line');
        if (line === null) {
          return;
        }
        if (!lines[line]) {
          lines[line] = { cells: {} };
        }
        const sku = tr.querySelector('.tec-ficha-extra-sku-input');
        const qty = tr.querySelector('.tec-ficha-extra-qty-input');
        lines[line].cells[term] = {
          target_id: extractTid(sku ? sku.value : ''),
          qty: qty ? qty.value.trim() : '',
        };
      });
    });
    return Object.keys(lines).sort((a, b) => Number(a) - Number(b)).map((key) => lines[key]);
  }

  function statusNode(tables) {
    for (let i = 0; i < tables.length; i++) {
      const node = tables[i].querySelector('.tec-ficha-extra-status');
      if (node) {
        return node;
      }
    }
    return null;
  }

  function setStatus(tables, kind) {
    const node = statusNode(tables);
    if (!node) {
      return;
    }
    window.clearTimeout(node.tecFichaExtraStatusTimer);
    node.classList.remove('is-ok', 'is-error');
    let text = '';
    if (kind === 'saved') {
      node.classList.add('is-ok');
      text = Drupal.t('Saved');
      node.tecFichaExtraStatusTimer = window.setTimeout(() => {
        if (node.classList.contains('is-ok')) {
          node.textContent = '';
          node.classList.remove('is-ok');
        }
      }, 2500);
    }
    else {
      node.classList.add('is-error');
      text = Drupal.t('Could not save');
    }
    node.textContent = text;
    if (Drupal.announce) {
      Drupal.announce(text);
    }
  }

  function save(table) {
    const url = table.getAttribute('data-tec-extras-save');
    const colorId = colorIdOf(table);
    if (!url || !colorId) {
      return;
    }
    const token = (typeof drupalSettings !== 'undefined' && drupalSettings.tecInventory)
      ? (drupalSettings.tecInventory.csrfToken || '')
      : '';
    const scope = scopeOf(table);
    const tables = tablesOf(scope, colorId);
    const stamp = (tables[0].tecFichaExtraSaveGen || 0) + 1;
    tables.forEach((one) => {
      one.tecFichaExtraSaveGen = stamp;
    });
    fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': token,
      },
      body: JSON.stringify({ lines: collect(tables) }),
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error();
        }
        return response.json();
      })
      .then((data) => {
        if (tables[0].tecFichaExtraSaveGen !== stamp) {
          return;
        }
        if (!data || data.ok !== true) {
          throw new Error();
        }
        setStatus(tables, 'saved');
      })
      .catch(() => {
        if (tables[0].tecFichaExtraSaveGen !== stamp) {
          return;
        }
        setStatus(tables, 'error');
      });
  }

  function skuMetaPath() {
    return (typeof drupalSettings !== 'undefined' && drupalSettings.tecInventory)
      ? (drupalSettings.tecInventory.skuMetaPath || '')
      : '';
  }

  function fetchMeta(tid) {
    if (metaCache[tid]) {
      return Promise.resolve(metaCache[tid]);
    }
    const path = skuMetaPath();
    if (!path || !tid) {
      return Promise.resolve({ uou: '', price: null });
    }
    return fetch(path + '?id=' + tid, { credentials: 'same-origin' })
      .then((response) => response.json())
      .then((meta) => {
        metaCache[tid] = meta;
        return meta;
      });
  }

  function bindPath(table) {
    const path = table.getAttribute('data-autocomplete-path') || '';
    table.querySelectorAll('.tec-ficha-extra-sku-input').forEach((input) => {
      if (path && !input.getAttribute('data-autocomplete-path')) {
        input.setAttribute('data-autocomplete-path', path);
      }
    });
  }

  function bindSku(input) {
    $(input).on('autocompleteselect.tecFichaExtras', (event, ui) => {
      const tr = input.closest('tr');
      const table = input.closest('table');
      if (!tr || !table) {
        return;
      }
      const item = ui.item || {};
      metaCache[extractTid(item.value)] = { uou: item.uou || '', price: item.price };
      showPicked(skuWrap(input));
      paintRow(tr, { uou: item.uou || '', price: item.price });
      retotal(table);
      save(table);
      window.dispatchEvent(new Event('resize'));
    });
    input.addEventListener('blur', () => {
      window.setTimeout(() => {
        const wrap = skuWrap(input);
        if (!wrap || wrap.classList.contains('is-picked')) {
          return;
        }
        if (extractTid(input.value)) {
          showPicked(wrap);
          window.dispatchEvent(new Event('resize'));
        }
      }, 200);
    });
  }

  function bindName(name) {
    name.addEventListener('click', (event) => {
      event.preventDefault();
      showEditing(skuWrap(name));
    });
  }

  function scheduleSave(table, tr) {
    const sku = tr.querySelector('.tec-ficha-extra-sku-input');
    const tid = extractTid(sku ? sku.value : '');
    const apply = (meta) => {
      paintRow(tr, meta);
      retotal(table);
      window.clearTimeout(table.tecFichaExtraTimer);
      table.tecFichaExtraTimer = window.setTimeout(() => save(table), 400);
    };
    if (tid && !tr.getAttribute('data-tec-unit-price')) {
      fetchMeta(tid).then(apply);
      return;
    }
    apply();
  }

  Drupal.behaviors.tecFichaExtras = {
    attach(context) {
      once('tec-ficha-extras-path', 'table.tec-size-card__bom[data-tec-color-id]', context).forEach(bindPath);
      once('tec-ficha-extra-sku', '.tec-ficha-extra-sku-input', context).forEach(bindSku);
      once('tec-ficha-extra-sku-name', '.tec-ficha-extra-sku-name', context).forEach(bindName);

      once('tec-ficha-extras-add', 'button.tec-ficha-extra-add', context).forEach((btn) => {
        btn.addEventListener('click', (event) => {
          event.preventDefault();
          const table = btn.closest('table');
          if (!table) {
            return;
          }
          const colorId = colorIdOf(table);
          const path = table.getAttribute('data-autocomplete-path') || '';
          const scope = scopeOf(table);
          const tables = tablesOf(scope, colorId);
          const line = nextLine(tables);
          tables.forEach((one) => {
            const term = one.getAttribute('data-tec-size-term') || '';
            const sizeId = one.getAttribute('data-tec-size-id') || '0';
            const tr = extraRow(line, term, path, sizeId);
            const addRow = one.querySelector('tr.tec-ficha-extra-add-row');
            if (addRow) {
              addRow.parentNode.insertBefore(tr, addRow);
            }
            else if (one.tBodies[0]) {
              one.tBodies[0].appendChild(tr);
            }
            Drupal.attachBehaviors(tr);
          });
          window.dispatchEvent(new Event('resize'));
        });
      });

      once('tec-ficha-extras-save', 'table.tec-size-card__bom[data-tec-color-id]', context).forEach((table) => {
        table.addEventListener('change', (event) => {
          const tr = event.target.closest('tr.tec-ficha-extra');
          if (!tr) {
            return;
          }
          if (event.target.classList.contains('tec-ficha-extra-sku-input')) {
            const tid = extractTid(event.target.value);
            if (tid) {
              fetchMeta(tid).then((meta) => {
                paintRow(tr, meta);
                showPicked(skuWrap(event.target));
                retotal(table);
                window.clearTimeout(table.tecFichaExtraTimer);
                table.tecFichaExtraTimer = window.setTimeout(() => save(table), 400);
                window.dispatchEvent(new Event('resize'));
              });
              return;
            }
            paintRow(tr, { uou: '', price: null });
          }
          scheduleSave(table, tr);
        });
      });
    },
  };
})(Drupal, once, jQuery);
