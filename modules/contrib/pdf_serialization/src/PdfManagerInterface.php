<?php

namespace Drupal\pdf_serialization;

use Mpdf\Output\Destination;

/**
 * Interface that all PDF managers must implement.
 */
interface PdfManagerInterface {

  /**
   * Get generated PDF content.
   *
   * @param array $content
   *   PDF content.
   * @param array $options
   *   (Optional) Pdf export settings. Defaults to [].
   * @param string $destination
   *   (Optional) The file destination. Default to Destination::STRING_RETURN.
   *
   * @return string
   *   The PDF output.
   *
   * @throws \Mpdf\MpdfException
   * @throws \Exception
   */
  public function getPdf(array $content, array $options = [], string $destination = Destination::STRING_RETURN): string;

}
