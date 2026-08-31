<?php

namespace Drupal\tec_production\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\tec_production\Purchasing;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Sends the filed supplier invoice as a download.
 *
 * The employees click Download and expect the scan to land in Downloads.
 * target=_blank opened the JPEG in a new tab, which is viewing, not saving.
 * This route is the same access as the dialog and answers with
 * Content-Disposition: attachment so the browser saves the file.
 */
class InvoiceDownloadController extends ControllerBase {

  /**
   * The scan on this purchase order, as an attachment.
   */
  public function download(EntityInterface $tec_order): BinaryFileResponse {
    $file = Purchasing::invoice($tec_order);
    $uri = $file ? $file->getFileUri() : '';
    if (!$file || $uri === '' || !is_file($uri)) {
      throw new NotFoundHttpException();
    }

    $name = $file->getFilename() ?: 'invoice.bin';
    $response = new BinaryFileResponse($uri, 200, [
      'Content-Type' => $file->getMimeType() ?: 'application/octet-stream',
    ], FALSE);
    $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $name);
    return $response;
  }

}
