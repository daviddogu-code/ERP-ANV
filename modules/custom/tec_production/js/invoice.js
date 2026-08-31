/**
 * @file
 * Download the filed invoice; do not open it in a tab.
 *
 * Download used to be target=_blank onto /system/files, so Chrome showed the
 * JPEG. The employees expected a file in Downloads. The click is taken here
 * so the Drupal modal cannot steal it, and the href is the attachment route.
 */
(function (Drupal, once) {
  'use strict';

  function save(url, name) {
    var a = document.createElement('a');
    a.href = url;
    if (name) {
      a.setAttribute('download', name);
    }
    a.rel = 'noopener';
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    a.remove();
  }

  Drupal.behaviors.tecInvoiceDownload = {
    attach: function (context) {
      once('tec-invoice-download', 'a.tec-invoice__download', context).forEach(function (link) {
        link.addEventListener('mousedown', function (e) {
          e.stopPropagation();
        });
        link.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          save(link.href, link.getAttribute('download'));
        });
      });
      once('tec-invoice-open', 'a.tec-invoice__open', context).forEach(function (link) {
        link.addEventListener('mousedown', function (e) {
          e.stopPropagation();
        });
      });
    }
  };
})(Drupal, once);
