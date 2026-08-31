/**
 * Open the print dialog after letterhead and line thumbnails have loaded.
 *
 * The old injector called print() in the document head, so Chrome drew the
 * paper before the 40x40 photos arrived.
 */
(function () {
  'use strict';

  var printed = false;

  function printNow() {
    if (printed) {
      return;
    }
    printed = true;
    window.print();
  }

  function whenImagesReady(done) {
    var nodes = document.querySelectorAll('.tec-portal--proforma img.tec-portal__img');
    var pending = 0;

    function one() {
      pending -= 1;
      if (pending <= 0) {
        done();
      }
    }

    for (var i = 0; i < nodes.length; i++) {
      var img = nodes[i];
      if (img.complete && img.naturalWidth !== undefined) {
        continue;
      }
      pending += 1;
      img.addEventListener('load', one);
      img.addEventListener('error', one);
    }

    if (pending === 0) {
      done();
    }
  }

  function go() {
    whenImagesReady(printNow);
    window.setTimeout(printNow, 4000);
  }

  if (document.readyState === 'complete') {
    go();
  }
  else {
    window.addEventListener('load', go);
  }
})();
