/**
 * @file
 * Switch between All Products / per-product docs, and clean print output.
 */
(function (Drupal, once) {
  'use strict';

  function showDoc(root, targetId) {
    var buttons = root.querySelectorAll('[data-doc-target]');
    var docs = root.querySelectorAll('[data-doc]');

    docs.forEach(function (doc) {
      var active = doc.getAttribute('data-doc') === String(targetId);
      doc.classList.toggle('is-active', active);
      if (active) {
        doc.removeAttribute('hidden');
      }
      else {
        doc.setAttribute('hidden', 'hidden');
      }
    });

    buttons.forEach(function (btn) {
      btn.classList.toggle(
        'is-active',
        btn.getAttribute('data-doc-target') === String(targetId)
      );
    });
  }

  function preparePrint(root) {
    cleanupPrint();
    document.documentElement.classList.add('tec-printing-production-docs');

    var active = root.querySelector('.tec-production-docs__doc.is-active');
    if (!active) {
      return;
    }

    var holder = document.createElement('div');
    holder.className = 'tec-print-root';
    var clone = active.cloneNode(true);
    clone.classList.add('is-active');
    clone.removeAttribute('hidden');
    holder.appendChild(clone);
    document.body.appendChild(holder);
  }

  function cleanupPrint() {
    document.documentElement.classList.remove('tec-printing-production-docs');
    document.querySelectorAll('body > .tec-print-root').forEach(function (el) {
      el.remove();
    });
  }

  Drupal.behaviors.tecProductionDocuments = {
    attach: function (context) {
      once('tec-production-docs', '[data-tec-production-docs]', context).forEach(function (root) {
        root.querySelectorAll('[data-doc-target]').forEach(function (btn) {
          btn.addEventListener('click', function (e) {
            e.preventDefault();
            showDoc(root, btn.getAttribute('data-doc-target'));
          });
        });

        var printBtn = root.querySelector('[data-tec-print]');
        if (printBtn) {
          printBtn.addEventListener('click', function (e) {
            e.preventDefault();
            preparePrint(root);
            window.print();
            // Fallback if afterprint does not fire (some browsers).
            window.setTimeout(cleanupPrint, 500);
          });
        }

        window.addEventListener('beforeprint', function () {
          if (!document.body.contains(root)) {
            return;
          }
          // If already prepared by button, skip duplicate.
          if (!document.querySelector('body > .tec-print-root')) {
            preparePrint(root);
          }
        });
        window.addEventListener('afterprint', cleanupPrint);
      });
    }
  };
})(Drupal, once);
