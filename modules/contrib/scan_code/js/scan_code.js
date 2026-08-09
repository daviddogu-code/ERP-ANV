/**
 * @file
 * Javascript file for scan code reading.
 */

(function (Drupal, drupalSettings, Quagga) {
  'use strict';

  var delay = 0;
  var throbber = 0;
  var open = false;
  var textBarcodeReading = drupalSettings.scan_code.textBarcodeReading;
  var patterns = drupalSettings.scan_code.patterns;
  if (drupalSettings.scan_code.statusOnLoad === 'open') {
    open = true;
  }

  Drupal.behaviors.BarcodeScanSearch = {
    attach: function (context, settings) {
      let scannerToggles = document.querySelectorAll('.scan-code-toggle');

      // If no media (camera, audio) - removing Barcode button.
      if (typeof navigator.mediaDevices === 'undefined') {
        console.log('Media devices does not exists or not allowed.');
      }

      scannerToggles.forEach(scannerToggle => {
        // If media devices does not allowed - removing scanner elements.
        if (typeof navigator.mediaDevices === 'undefined') {
          scannerToggle.remove();
        }
        else {
          scannerToggle.onclick = eventListener;
        }
      });

      /**
       * Scanner event listener.
       *
       * @param event
       */
      function eventListener(event) {
        event.preventDefault();
        let scannerContainer = document.querySelector('#scanner-container');
        if (scannerContainer === undefined || scannerContainer === null) {
          // Add scanner container
          scannerContainer = document.createElement('div');
          scannerContainer.id = 'scanner-container';
          scannerContainer.innerHTML = '<div id="interactive" class="viewport"></div>';
          this.parentElement.querySelector('input').parentElement.appendChild(scannerContainer);
          scannerContainer.style.display = 'block';

          open = true;
           Quagga.init({
            inputStream: {
              name: 'Live',
              type: 'LiveStream'
            },
            decoder: {
              readers: patterns,
            },
          }, function (err) {
            if (err) {
              console.log(err);
              return;
            }
            if (open) {
              Quagga.start();
            }
          });
        }
        else {
          open = false;
          Quagga.stop();
          scannerContainer.remove();
        }
      }

    }
  };

  // Processed - capturing process.
  Quagga.onProcessed(function (result) {
    if (delay < Date.now() - drupalSettings.scan_code.delay) {
      var drawingCtx = Quagga.canvas.ctx.overlay,
        drawingCanvas = Quagga.canvas.dom.overlay;

      var height = parseInt(drawingCanvas.getAttribute('height'));
      var width = parseInt(drawingCanvas.getAttribute('width'));

      drawingCtx.clearRect(0, 0, width, height);
      drawingCtx.fillStyle = 'rgba(0, 0, 0, 0.5)';
      drawingCtx.fillRect(0, height - 45, width, height);

      if (result && result.codeResult && result.codeResult.code) {
        Quagga.ImageDebug.drawPath(result.box, {x: 0, y: 1}, drawingCtx, {color: 'orange', lineWidth: 6});
        Quagga.ImageDebug.drawPath(result.line, {x: 'x', y: 'y'}, drawingCtx, {color: 'red', lineWidth: 3});
      }
      else {
        let dots = '';
        if (throbber < 30) {
          dots = '.';
        }
        else if (throbber < 60) {
          dots = '..';
        }
        else {
          dots = '...';
        }

        throbber++;
        if (throbber > 90) {
          throbber = 0;
        }

        let text = textBarcodeReading + dots;
        drawingCtx.font = '20px sans-serif';
        drawingCtx.fillStyle = 'white';
        drawingCtx.fillText(text, 10, height - 15);
      }
    }
  });

  // Processed - after scan code detection.
  Quagga.onDetected(function (result) {
    if (delay < Date.now() - drupalSettings.scan_code.delay) {
      delay = Date.now();

      // Draw needed image over scan (color rectangle).

      let drawingCtx = Quagga.canvas.ctx.overlay,
        drawingCanvas = Quagga.canvas.dom.overlay;
      let height = parseInt(drawingCanvas.getAttribute('height'));
      let width = parseInt(drawingCanvas.getAttribute('width'));

      drawingCtx.clearRect(0, height - 45, width, height);
      drawingCtx.fillStyle = 'rgba(0, 0, 0, 0.5)';
      drawingCtx.fillRect(0, height - 45, width, height);
      drawingCtx.fillStyle = 'white';
      drawingCtx.fillText(result.codeResult.code, 10, height - 15);

      let scannerContainer = document.querySelector('#scanner-container');
      let resultDetected = scannerContainer.parentElement.querySelector('input');
      resultDetected.select();
      resultDetected.value = result.codeResult.code;

      if (drupalSettings.scan_code.closeAfterScanning === 'closed') {
        Quagga.stop();
        scannerContainer.remove();
      }
    }
  });

})(Drupal, drupalSettings, Quagga);
