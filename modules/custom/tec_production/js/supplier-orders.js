/**
 * @file
 * Invoice hover preview on the supplier lists.
 *
 * The icon opens the File invoice dialog. Views would strip an iframe out of
 * the cell, so the preview box lives on the page and is filled from
 * data-preview-url (the scan) on hover. PDFs are fetched only then, not for
 * every row on load. The box is position:fixed so the table's sideways scroll
 * cannot clip it.
 */
(function (Drupal, once) {
  'use strict';

  var hideTimer = null;

  function box() {
    var el = document.getElementById('tec-po-invoice-preview-box');
    if (!el) {
      el = document.createElement('div');
      el.id = 'tec-po-invoice-preview-box';
      el.className = 'tec-po-invoice-preview__box';
      el.setAttribute('hidden', 'hidden');
      document.body.appendChild(el);
    }
    return el;
  }

  function previewHref(link) {
    return link.getAttribute('data-preview-url') || link.getAttribute('href') || '';
  }

  function isPdf(link) {
    var kind = link.getAttribute('data-preview') || '';
    if (kind === 'pdf') {
      return true;
    }
    if (kind === 'image') {
      return false;
    }
    return /\.pdf(\b|$)/i.test(previewHref(link));
  }

  function fill(el, link) {
    var href = previewHref(link);
    if (!href) {
      return;
    }
    el.innerHTML = '';
    if (isPdf(link)) {
      var frame = document.createElement('iframe');
      frame.className = 'tec-po-invoice-preview__pdf';
      frame.setAttribute('title', link.getAttribute('title') || '');
      frame.setAttribute('tabindex', '-1');
      frame.src = href;
      el.appendChild(frame);
    }
    else {
      var img = document.createElement('img');
      img.className = 'tec-po-invoice-preview__img';
      img.alt = link.getAttribute('title') || '';
      img.src = href;
      el.appendChild(img);
    }
  }

  function place(el, link) {
    var r = link.getBoundingClientRect();
    el.removeAttribute('hidden');
    var w = el.offsetWidth || 320;
    var h = el.offsetHeight || 400;
    var left = r.left - w - 12;
    if (left < 8) {
      left = Math.min(r.right + 12, window.innerWidth - w - 8);
    }
    var top = r.top;
    if (top + h > window.innerHeight - 8) {
      top = Math.max(8, window.innerHeight - h - 8);
    }
    el.style.left = left + 'px';
    el.style.top = top + 'px';
  }

  function hide() {
    var el = document.getElementById('tec-po-invoice-preview-box');
    if (!el) {
      return;
    }
    el.setAttribute('hidden', 'hidden');
    el.innerHTML = '';
  }

  Drupal.behaviors.tecInvoicePreview = {
    attach: function (context) {
      once('tec-invoice-preview', 'a.tec-po-invoice-preview', context).forEach(function (link) {
        link.addEventListener('mouseenter', function () {
          window.clearTimeout(hideTimer);
          var el = box();
          fill(el, link);
          place(el, link);
        });
        link.addEventListener('mouseleave', function () {
          hideTimer = window.setTimeout(hide, 80);
        });
        link.addEventListener('click', hide);
      });
    }
  };
})(Drupal, once);
