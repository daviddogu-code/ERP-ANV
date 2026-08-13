<?php

namespace Drupal\dxpr_theme_helper\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dxpr_theme_helper\AiFontGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for AI font generation endpoint.
 */
final class AiFontController extends ControllerBase {

  /**
   * The AI font generator service.
   *
   * @var \Drupal\dxpr_theme_helper\AiFontGenerator
   */
  protected AiFontGenerator $fontGenerator;

  /**
   * Constructs an AiFontController object.
   *
   * @param \Drupal\dxpr_theme_helper\AiFontGenerator $font_generator
   *   The AI font generator service.
   */
  public function __construct(AiFontGenerator $font_generator) {
    $this->fontGenerator = $font_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dxpr_theme_helper.ai_font_generator')
    );
  }

  /**
   * Generate font selections using AI.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with fonts or error.
   */
  public function generate(Request $request): JsonResponse {
    $prompt = $request->request->get('prompt', '');
    $result = $this->fontGenerator->generate($prompt);

    if (isset($result['error'])) {
      $statusCode = $this->fontGenerator->isAvailable() ? 400 : 503;
      return new JsonResponse($result, $statusCode);
    }

    return new JsonResponse($result);
  }

}
