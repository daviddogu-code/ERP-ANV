<?php

namespace Drupal\dxpr_theme_helper\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dxpr_theme_helper\AiPaletteGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for AI palette generation endpoint.
 */
final class AiPaletteController extends ControllerBase {

  /**
   * The AI palette generator service.
   *
   * @var \Drupal\dxpr_theme_helper\AiPaletteGenerator
   */
  protected AiPaletteGenerator $paletteGenerator;

  /**
   * Constructs an AiPaletteController object.
   *
   * @param \Drupal\dxpr_theme_helper\AiPaletteGenerator $palette_generator
   *   The AI palette generator service.
   */
  public function __construct(AiPaletteGenerator $palette_generator) {
    $this->paletteGenerator = $palette_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dxpr_theme_helper.ai_palette_generator')
    );
  }

  /**
   * Generate a color palette using AI.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with colors or error.
   */
  public function generate(Request $request): JsonResponse {
    $prompt = $request->request->get('prompt', '');
    $result = $this->paletteGenerator->generate($prompt);

    if (isset($result['error'])) {
      $statusCode = $this->paletteGenerator->isAvailable() ? 400 : 503;
      return new JsonResponse($result, $statusCode);
    }

    return new JsonResponse($result);
  }

}
