<?php

namespace Drupal\pdf_serialization;

use Drupal\Core\File\FileSystem;
use Drupal\Core\Render\Renderer;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Provides PDF manager service to generate content.
 */
class PdfManager implements PdfManagerInterface {

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  private $renderer;

  /**
   * Provides helpers to operate on files and stream wrappers.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * PdfManager constructor.
   *
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer.
   * @param \Drupal\Core\File\FileSystem $file_system
   *   Provides helpers to operate on files and stream wrappers.
   */
  public function __construct(Renderer $renderer, FileSystem $file_system) {
    $this->renderer = $renderer;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public function getPdf(array $content, array $options = [], string $destination = Destination::STRING_RETURN): string {
    $mpdf = new Mpdf([
      'mode' => 'utf-8',
      'format' => $options['pdf_settings']['format'] ?? 'A4',
      'tempDir' => $this->fileSystem->getTempDirectory(),
    ]);

    if (
      !empty($options['pdf_settings']['show_page_number'] &&
             (empty($options['export_method']) ||
              $options['export_method'] === 'standard'))
    ) {
      $mpdf->defaultfooterline = 0;
      $mpdf->setFooter('{PAGENO}');
    }

    if (!empty($options['pdf_settings']['show_header'])) {
      $header = [
        '#theme' => 'pdf_serialization_pdf_header',
        '#header_content' => $options['pdf_settings']['header_content'],
        '#view' => $content["#view"],
      ];
      $header_rendered = $this->renderer->render($header);
      $mpdf->SetHTMLHeader($header_rendered, 'O');
    }

    if (!empty($options['pdf_settings']['show_footer'])) {
      $footer = [
        '#theme' => 'pdf_serialization_pdf_footer',
        '#footer_content' => $options['pdf_settings']['footer_content'],
        '#view' => $content["#view"],
      ];
      $footer_rendered = $this->renderer->render($footer);
      $mpdf->SetHTMLFooter($footer_rendered);
    }

    $content_rendered = $this->renderer->render($content);
    $mpdf->WriteHTML($content_rendered);

    return $mpdf->Output('', $destination);
  }

}
