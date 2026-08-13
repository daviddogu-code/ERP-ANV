<?php

declare(strict_types=1);

namespace Drupal\dxpr_theme_helper\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\dxpr_theme_helper\AiFontGenerator;
use Drupal\dxpr_theme_helper\AiPaletteGenerator;
use Drush\Attributes as CLI;

/**
 * Drush commands for AI-powered theme generation.
 */
class DxprThemeAiCommands extends DxprThemeCommandsBase {

  public function __construct(
    protected readonly AiPaletteGenerator $paletteGenerator,
    protected readonly AiFontGenerator $fontGenerator,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly ThemeExtensionList $themeList,
  ) {
    parent::__construct();
  }

  /**
   * Generates a color palette using AI.
   */
  #[CLI\Command(name: 'dxt:generate:palette', aliases: ['dxt-gp'])]
  #[CLI\Help(description: '[YAML] Generates a color palette from a natural language prompt using the Drupal AI module.')]
  #[CLI\Argument(name: 'prompt', description: 'Describe the color palette (e.g. "Modern tech startup with blue accents")')]
  #[CLI\Option(name: 'apply', description: 'Write generated palette to theme settings')]
  #[CLI\Option(name: 'theme', description: 'Target theme (default: active DXPR theme)')]
  #[CLI\Option(name: 'dry-run', description: 'Validate availability without generating')]
  #[CLI\Usage(name: 'drush dxt:generate:palette "Warm bakery tones"', description: 'Generate palette')]
  #[CLI\Usage(name: 'drush dxt-gp "Dark mode gaming" --apply', description: 'Generate and apply')]
  public function generatePalette(
    string $prompt,
    array $options = [
      'apply' => FALSE,
      'theme' => NULL,
      'dry-run' => FALSE,
    ],
  ): string {
    $this->switchToAdmin();

    if (!$this->paletteGenerator->isAvailable()) {
      return $this->error('AI module is not installed. Install drupal/ai to use this command.', [
        'Alternative: use drush dxt:palette:set to set palette colors manually (e.g. drush dxt:palette:set base=#6C3CE1 accent1=#00D4FF).',
      ]);
    }

    if (!$this->paletteGenerator->hasConfiguredProvider()) {
      return $this->error('No AI chat provider configured. Configure one at /admin/config/ai/settings.', [
        'Alternative: use drush dxt:palette:set to set palette colors manually (e.g. drush dxt:palette:set base=#6C3CE1 accent1=#00D4FF).',
      ]);
    }

    if ($options['dry-run']) {
      return $this->yaml([
        'success' => TRUE,
        'message' => 'Dry run: AI palette generation is available.',
        'dry_run' => TRUE,
        'prompt' => $prompt,
      ]);
    }

    $result = $this->paletteGenerator->generate($prompt);

    if (isset($result['error'])) {
      return $this->error($result['error']);
    }

    $colors = $result['colors'] ?? [];

    if ($options['apply']) {
      try {
        $theme = $this->resolveTheme($options['theme'], $this->configFactory, $this->themeList);
      }
      catch (\RuntimeException $e) {
        return $this->error($e->getMessage());
      }

      $config = $this->configFactory->getEditable($theme . '.settings');
      $config->set('color_scheme', 'custom');
      $config->set('color_palette', serialize($colors));
      $config->save();
      $this->rebuildCssCache($theme);

      return $this->success('Palette generated and applied.', [
        'data' => [
          'theme' => $theme,
          'prompt' => $prompt,
          'colors' => $colors,
        ],
      ]);
    }

    return $this->yaml([
      'success' => TRUE,
      'message' => 'Palette generated. Use --apply to save to theme settings.',
      'prompt' => $prompt,
      'colors' => $colors,
    ]);
  }

  /**
   * Generates font selections using AI.
   */
  #[CLI\Command(name: 'dxt:generate:fonts', aliases: ['dxt-gf'])]
  #[CLI\Help(description: '[YAML] Generates font selections from a natural language prompt using the Drupal AI module.')]
  #[CLI\Argument(name: 'prompt', description: 'Describe the typography style (e.g. "Clean and modern tech aesthetic")')]
  #[CLI\Option(name: 'apply', description: 'Write generated fonts to theme settings')]
  #[CLI\Option(name: 'theme', description: 'Target theme (default: active DXPR theme)')]
  #[CLI\Option(name: 'dry-run', description: 'Validate availability without generating')]
  #[CLI\Usage(name: 'drush dxt:generate:fonts "Elegant editorial style"', description: 'Generate fonts')]
  #[CLI\Usage(name: 'drush dxt-gf "Bold tech branding" --apply', description: 'Generate and apply')]
  public function generateFonts(
    string $prompt,
    array $options = [
      'apply' => FALSE,
      'theme' => NULL,
      'dry-run' => FALSE,
    ],
  ): string {
    $this->switchToAdmin();

    if (!$this->fontGenerator->isAvailable()) {
      return $this->error('AI module is not installed. Install drupal/ai to use this command.');
    }

    if (!$this->fontGenerator->hasConfiguredProvider()) {
      return $this->error('No AI chat provider configured. Configure one at /admin/config/ai/settings.');
    }

    if ($options['dry-run']) {
      return $this->yaml([
        'success' => TRUE,
        'message' => 'Dry run: AI font generation is available.',
        'dry_run' => TRUE,
        'prompt' => $prompt,
      ]);
    }

    $result = $this->fontGenerator->generate($prompt);

    if (isset($result['error'])) {
      return $this->error($result['error']);
    }

    $fonts = $result['fonts'] ?? [];

    if ($options['apply']) {
      try {
        $theme = $this->resolveTheme($options['theme'], $this->configFactory, $this->themeList);
      }
      catch (\RuntimeException $e) {
        return $this->error($e->getMessage());
      }

      $config = $this->configFactory->getEditable($theme . '.settings');
      $fontKeys = [
        'body_font_face',
        'headings_font_face',
        'nav_font_face',
        'sitename_font_face',
        'blockquote_font_face',
      ];
      foreach ($fontKeys as $fontKey) {
        if (isset($fonts[$fontKey])) {
          $config->set($fontKey, $fonts[$fontKey]);
        }
      }
      $config->save();
      $this->rebuildCssCache($theme);

      return $this->success('Fonts generated and applied.', [
        'data' => [
          'theme' => $theme,
          'prompt' => $prompt,
          'fonts' => $fonts,
        ],
      ]);
    }

    return $this->yaml([
      'success' => TRUE,
      'message' => 'Fonts generated. Use --apply to save to theme settings.',
      'prompt' => $prompt,
      'fonts' => $fonts,
    ]);
  }

}
