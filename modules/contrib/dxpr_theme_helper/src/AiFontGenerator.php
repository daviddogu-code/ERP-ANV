<?php

namespace Drupal\dxpr_theme_helper;

/**
 * Service for generating font selections using AI.
 */
class AiFontGenerator extends AiThemeGeneratorBase {

  /**
   * Required font setting keys.
   */
  protected const REQUIRED_KEYS = [
    'body_font_face',
    'headings_font_face',
    'nav_font_face',
    'sitename_font_face',
    'blockquote_font_face',
  ];

  /**
   * Available Google Fonts loaded from JSON.
   *
   * @var array|null
   */
  protected ?array $availableFonts = NULL;

  /**
   * {@inheritdoc}
   */
  protected string $aiTag = 'dxpr-fonts';

  /**
   * {@inheritdoc}
   */
  protected function getRequiredKeys(): array {
    return self::REQUIRED_KEYS;
  }

  /**
   * {@inheritdoc}
   */
  protected function getResponseKey(): string {
    return 'fonts';
  }

  /**
   * {@inheritdoc}
   */
  protected function validateValue(string $key, mixed $value): ?string {
    if (!is_string($value)) {
      return $this->t('Invalid font value for @key: expected string.', [
        '@key' => $key,
      ]);
    }

    // Try to validate or fix the font key.
    $availableFonts = $this->loadAvailableFonts();
    if (!isset($availableFonts[$value])) {
      $fixedKey = $this->findClosestFont($value, $availableFonts);
      if ($fixedKey === NULL) {
        return $this->t('Invalid font selection for @key: @font. Font not found.', [
          '@key' => $key,
          '@font' => $value,
        ]);
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function generate(string $prompt): array {
    $result = parent::generate($prompt);

    // Post-process to fix any font keys that need adjustment.
    if (isset($result['fonts'])) {
      $availableFonts = $this->loadAvailableFonts();
      foreach ($result['fonts'] as $key => $fontKey) {
        if (!isset($availableFonts[$fontKey])) {
          $fixedKey = $this->findClosestFont($fontKey, $availableFonts);
          if ($fixedKey) {
            $result['fonts'][$key] = $fixedKey;
          }
        }
      }
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildSystemPrompt(): string {
    $fonts = $this->getCuratedFontList();

    // Build a condensed font list for the prompt.
    $fontListText = "";
    foreach ($fonts as $category => $families) {
      $fontListText .= "\n{$category}:\n";
      foreach ($families as $family => $variants) {
        $fontListText .= "  - {$family} (" . implode(', ', $variants) . ")\n";
      }
    }

    return <<<PROMPT
You are an art director and UX strategist selecting fonts for a website based on the user's request.

Return ONLY valid JSON (no markdown, no explanation):
{
  "body_font_face": "0FontFamily:variant",
  "headings_font_face": "0FontFamily:variant",
  "nav_font_face": "0FontFamily:variant",
  "sitename_font_face": "0FontFamily:variant",
  "blockquote_font_face": "0FontFamily:variant"
}

Keys: body_font_face (body text), headings_font_face (h1-h6), nav_font_face (navigation), sitename_font_face (site name), blockquote_font_face (blockquotes)

Format: "0" + FontFamily (spaces as +) + ":" + variant
Examples: "0Roboto:", "0Roboto:700", "0Open+Sans:300italic"

AVAILABLE FONTS (choose from these):
{$fontListText}
PROMPT;
  }

  /**
   * Load available Google Fonts from the theme's JSON file.
   *
   * @return array
   *   Array of available fonts keyed by font key (e.g., "0Roboto:400").
   */
  protected function loadAvailableFonts(): array {
    if ($this->availableFonts !== NULL) {
      return $this->availableFonts;
    }

    $this->availableFonts = [];

    // Try to find the google-webfonts.json file.
    $themePath = $this->getDxprThemePath();
    if (empty($themePath)) {
      return $this->availableFonts;
    }
    $jsonPath = $themePath . '/features/sooper-fonts/google-webfonts.json';

    if (!file_exists($jsonPath)) {
      return $this->availableFonts;
    }

    $jsonContent = file_get_contents($jsonPath);
    $webfonts = json_decode($jsonContent, TRUE);

    if (!is_array($webfonts)) {
      return $this->availableFonts;
    }

    foreach ($webfonts as $fontFamily) {
      $familyName = $fontFamily['family'] ?? '';
      $variants = $fontFamily['variants'] ?? [];
      $category = $fontFamily['category'] ?? 'sans-serif';

      foreach ($variants as $variant) {
        // Convert "regular" to empty string for key format.
        $variantKey = ($variant === 'regular') ? '' : $variant;
        $fontNameSafe = str_replace(' ', '+', $familyName) . ':' . $variantKey;
        $fontKey = '0' . $fontNameSafe;

        $this->availableFonts[$fontKey] = [
          'family' => $familyName,
          'variant' => $variant,
          'category' => $category,
        ];
      }
    }

    return $this->availableFonts;
  }

  /**
   * Get a curated list of popular fonts for the AI prompt.
   *
   * @return array
   *   Array of font families grouped by category.
   */
  protected function getCuratedFontList(): array {
    $fonts = $this->loadAvailableFonts();

    // Group fonts by category, keeping only common weights.
    $categorized = [];
    $commonWeights = [
      '',
      'regular',
      '300',
      '400',
      '500',
      '600',
      '700',
      'italic',
      '400italic',
      '700italic',
    ];

    foreach ($fonts as $fontData) {
      $category = $fontData['category'];
      $family = $fontData['family'];
      $variant = $fontData['variant'];

      // Only include common weights to keep the list manageable.
      if (!in_array($variant, $commonWeights)) {
        continue;
      }

      if (!isset($categorized[$category])) {
        $categorized[$category] = [];
      }

      if (!isset($categorized[$category][$family])) {
        $categorized[$category][$family] = [];
      }

      $categorized[$category][$family][] = $variant ?: 'regular';
    }

    return $categorized;
  }

  /**
   * Try to find the closest matching font key.
   *
   * @param string $fontKey
   *   The font key to match.
   * @param array $availableFonts
   *   Available fonts array.
   *
   * @return string|null
   *   The closest matching font key, or NULL if not found.
   */
  protected function findClosestFont(string $fontKey, array $availableFonts): ?string {
    // Extract family name from key.
    $family = preg_replace('/^0/', '', $fontKey);
    $family = preg_replace('/:.*$/', '', $family);
    $family = str_replace('+', ' ', $family);

    // Try to find any variant of this family.
    foreach ($availableFonts as $key => $data) {
      if (strcasecmp($data['family'], $family) === 0) {
        return $key;
      }
    }

    return NULL;
  }

}
