<?php

declare(strict_types=1);

namespace Drupal\dxpr_theme_helper\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drush\Attributes as CLI;
use Symfony\Component\Yaml\Yaml;

/**
 * Drush commands for DXPR Theme configuration management.
 */
class DxprThemeConfigCommands extends DxprThemeCommandsBase {

  /**
   * Maps palette color keys to Bootstrap CSS variables and classes.
   *
   * Used by dxt:palette:get to show how each palette color affects
   * Bootstrap utility classes in the rendered page.
   */
  protected const PALETTE_BOOTSTRAP_MAP = [
    'base' => ['variable' => '--bs-primary', 'classes' => 'btn-primary, bg-primary, text-primary, border-primary'],
    'basetext' => ['variable' => '--bs-primary (text)', 'classes' => 'text on btn-primary, text on bg-primary'],
    'accent1' => ['variable' => '--bs-secondary', 'classes' => 'btn-secondary, bg-secondary, text-secondary'],
    'accent1text' => ['variable' => '--bs-secondary (text)', 'classes' => 'text on btn-secondary, text on bg-secondary'],
    'accent2' => ['variable' => '(tertiary)', 'classes' => '(custom palette color)'],
    'accent2text' => ['variable' => '(tertiary text)', 'classes' => '(custom palette color)'],
    'text' => ['variable' => '--bs-body-color', 'classes' => 'inherited by all body text'],
    'headings' => ['variable' => '--bs-dark, --bs-heading-color', 'classes' => 'bg-dark, text-dark; inherited by h1-h6'],
    'link' => ['variable' => '--bs-link-color', 'classes' => 'inherited by all <a> tags'],
    'silver' => ['variable' => '--bs-light', 'classes' => 'bg-light'],
    'body' => ['variable' => '--bs-body-bg', 'classes' => 'page background'],
    'graylight' => ['variable' => '--bs-secondary-color', 'classes' => 'text-muted, muted text'],
    'graylighter' => ['variable' => '--bs-border-color', 'classes' => 'default border color'],
    'card' => ['variable' => '(region)', 'classes' => 'card background in theme regions'],
    'cardtext' => ['variable' => '(region)', 'classes' => 'card text in theme regions'],
    'header' => ['variable' => '(region)', 'classes' => 'main header/navbar background'],
    'headertext' => ['variable' => '(region)', 'classes' => 'main header/navbar text'],
    'headerside' => ['variable' => '(region)', 'classes' => 'sidebar header background'],
    'headersidetext' => ['variable' => '(region)', 'classes' => 'sidebar header text'],
    'secheader' => ['variable' => '(region)', 'classes' => 'secondary header (topbar) background'],
    'secheadertext' => ['variable' => '(region)', 'classes' => 'secondary header (topbar) text'],
    'footer' => ['variable' => '(region)', 'classes' => 'footer background'],
    'footertext' => ['variable' => '(region)', 'classes' => 'footer text'],
    'pagetitle' => ['variable' => '(region)', 'classes' => 'page title region background'],
    'pagetitletext' => ['variable' => '(region)', 'classes' => 'page title region text'],
  ];

  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly ThemeExtensionList $themeList,
    protected readonly ?ThemeSettingsProvider $themeSettingsProvider = NULL,
  ) {
    parent::__construct();
  }

  /**
   * Gets a theme setting, with fallback for Drupal < 11.3.
   *
   * Uses ThemeSettingsProvider when available (Drupal 11.3+),
   * falls back to theme_get_setting() on older versions.
   */
  protected function getThemeSetting(string $key, string $theme): mixed {
    if ($this->themeSettingsProvider !== NULL) {
      return $this->themeSettingsProvider->getSetting($key, $theme);
    }
    // @phpstan-ignore function.deprecated
    return theme_get_setting($key, $theme);
  }

  /**
   * Gets a single theme setting value.
   */
  #[CLI\Command(name: 'dxt:config:get', aliases: ['dxt-cg'])]
  #[CLI\Help(description: '[YAML] Gets a DXPR Theme setting value with schema metadata.')]
  #[CLI\Argument(name: 'key', description: 'The setting key')]
  #[CLI\Option(name: 'theme', description: 'Target theme (default: active DXPR theme)')]
  #[CLI\Usage(name: 'drush dxt:config:get header_top_layout', description: 'Get header layout')]
  #[CLI\Usage(name: 'drush dxt-cg body_font_size --theme=my_subtheme', description: 'Get body font size for subtheme')]
  public function configGet(
    string $key,
    array $options = [
      'theme' => NULL,
    ],
  ): string {
    $this->switchToAdmin();

    try {
      $theme = $this->resolveTheme($options['theme'], $this->configFactory, $this->themeList);
    }
    catch (\RuntimeException $e) {
      return $this->error($e->getMessage());
    }

    $schema = $this->loadSchema();
    if (!isset($schema[$key])) {
      return $this->error(
        sprintf('Unknown setting "%s".', $key),
        ['Use drush dxt:config:list to see available settings.']
      );
    }

    $value = $this->getThemeSetting($key, $theme);
    $def = $schema[$key];

    $item = [
      'key' => $key,
      'value' => $value,
      'title' => $def['title'] ?? $key,
      'type' => $def['type'],
      'section' => $def['section'],
      'description' => $def['description'] ?? '',
      'default' => $def['default'] ?? NULL,
    ];

    // Include type-specific metadata.
    if (isset($def['options']) && is_array($def['options'])) {
      $item['options'] = $def['options'];
    }
    if (isset($def['min'])) {
      $item['min'] = $def['min'];
      $item['max'] = $def['max'];
      $item['step'] = $def['step'];
    }
    if (isset($def['unit'])) {
      $item['unit'] = $def['unit'];
    }
    if (isset($def['ai_description'])) {
      $item['ai_description'] = $def['ai_description'];
    }

    return $this->yaml([
      'success' => TRUE,
      'theme' => $theme,
      'setting' => $item,
    ]);
  }

  /**
   * Sets a single theme setting value.
   */
  #[CLI\Command(name: 'dxt:config:set', aliases: ['dxt-cs'])]
  #[CLI\Help(description: '[YAML] Sets a DXPR Theme setting value. Validates against schema and rebuilds CSS cache.')]
  #[CLI\Argument(name: 'key', description: 'The setting key')]
  #[CLI\Argument(name: 'value', description: 'The new value')]
  #[CLI\Option(name: 'theme', description: 'Target theme (default: active DXPR theme)')]
  #[CLI\Option(name: 'dry-run', description: 'Validate without saving')]
  #[CLI\Option(name: 'append', description: 'Append value to existing value instead of replacing (useful for custom_css_site, custom_css_page)')]
  #[CLI\Usage(name: 'drush dxt:config:set header_top_layout centered', description: 'Set header to centered')]
  #[CLI\Usage(name: 'drush dxt:config:set body_font_size 16 --dry-run', description: 'Validate setting change')]
  #[CLI\Usage(name: 'drush dxt:config:set color_palette_base "#6C3CE1"', description: 'Set a single palette color')]
  #[CLI\Usage(name: 'drush dxt:config:set custom_css_site "h1 { color: red; }" --append', description: 'Append CSS without overwriting')]
  public function configSet(
    string $key,
    string $value,
    array $options = [
      'theme' => NULL,
      'dry-run' => FALSE,
      'append' => FALSE,
    ],
  ): string {
    $this->switchToAdmin();

    try {
      $theme = $this->resolveTheme($options['theme'], $this->configFactory, $this->themeList);
    }
    catch (\RuntimeException $e) {
      return $this->error($e->getMessage());
    }

    // Handle individual palette color keys (color_palette_<name>).
    if (str_starts_with($key, 'color_palette_')) {
      return $this->setPaletteColor($key, $value, $theme, $options['dry-run']);
    }

    // Append to existing value if --append is set.
    if ($options['append']) {
      $config = $this->configFactory->get($theme . '.settings');
      $existing = $config->get($key);
      if (is_string($existing) && $existing !== '') {
        $value = $existing . "\n" . $value;
      }
    }

    $errors = $this->validateValue($key, $value);
    if (!empty($errors)) {
      return $this->error(sprintf('Invalid value for "%s".', $key), $errors);
    }

    $normalized = $this->normalizeValue($key, $value);
    $config = $this->configFactory->getEditable($theme . '.settings');
    $oldValue = $config->get($key);

    if ($options['dry-run']) {
      return $this->yaml([
        'success' => TRUE,
        'message' => 'Dry run: setting would be updated.',
        'dry_run' => TRUE,
        'theme' => $theme,
        'key' => $key,
        'old_value' => $oldValue,
        'new_value' => $normalized,
      ]);
    }

    $config->set($key, $normalized)->save();
    $this->rebuildCssCache($theme);

    return $this->success(sprintf('Setting "%s" updated.', $key), [
      'data' => [
        'theme' => $theme,
        'key' => $key,
        'old_value' => $oldValue,
        'new_value' => $normalized,
      ],
    ]);
  }

  /**
   * Lists theme settings.
   */
  #[CLI\Command(name: 'dxt:config:list', aliases: ['dxt-cl'])]
  #[CLI\Help(description: '[YAML] Lists DXPR Theme settings. Use --detail for full schema introspection (type, valid values, ranges).')]
  #[CLI\Option(name: 'section', description: 'Filter by section: layout, header, page-title, colors, fonts, typography, block-design, custom-css')]
  #[CLI\Option(name: 'theme', description: 'Target theme (default: active DXPR theme)')]
  #[CLI\Option(name: 'detail', description: 'Include full schema metadata (type, options, range, default)')]
  #[CLI\Option(name: 'keys-only', description: 'Output only setting keys')]
  #[CLI\Option(name: 'sections-only', description: 'Output only section names')]
  #[CLI\Usage(name: 'drush dxt:config:list --section=header --detail', description: 'Introspect all header settings with valid values')]
  #[CLI\Usage(name: 'drush dxt:config:list --sections-only', description: 'List available sections')]
  #[CLI\Usage(name: 'drush dxt-cl --keys-only', description: 'List all setting keys')]
  public function configList(
    array $options = [
      'section' => NULL,
      'theme' => NULL,
      'detail' => FALSE,
      'keys-only' => FALSE,
      'sections-only' => FALSE,
    ],
  ): string {
    $this->switchToAdmin();

    if ($options['sections-only']) {
      return $this->yaml([
        'success' => TRUE,
        'sections' => $this->getSections(),
      ]);
    }

    try {
      $theme = $this->resolveTheme($options['theme'], $this->configFactory, $this->themeList);
    }
    catch (\RuntimeException $e) {
      return $this->error($e->getMessage());
    }

    $schema = $this->loadSchema();

    if ($options['section'] !== NULL && !in_array($options['section'], $this->getSections())) {
      return $this->error(
        sprintf('Unknown section "%s".', $options['section']),
        [sprintf('Valid sections: %s.', implode(', ', $this->getSections()))]
      );
    }

    $items = [];
    foreach ($schema as $key => $def) {
      if ($options['section'] !== NULL && $def['section'] !== $options['section']) {
        continue;
      }

      if ($options['keys-only']) {
        $items[] = $key;
        continue;
      }

      $item = [
        'key' => $key,
        'value' => $this->getThemeSetting($key, $theme),
        'title' => $def['title'] ?? $key,
        'section' => $def['section'],
      ];

      if ($options['detail']) {
        $item['type'] = $def['type'];
        $item['description'] = $def['description'] ?? '';
        $item['default'] = $def['default'] ?? NULL;
        if (isset($def['options']) && is_array($def['options'])) {
          $item['options'] = $def['options'];
        }
        if (isset($def['min'])) {
          $item['min'] = $def['min'];
          $item['max'] = $def['max'];
          $item['step'] = $def['step'];
        }
        if (isset($def['unit'])) {
          $item['unit'] = $def['unit'];
        }
        if (isset($def['ai_description'])) {
          $item['ai_description'] = $def['ai_description'];
        }
      }

      $items[] = $item;
    }

    if ($options['keys-only']) {
      return $this->yaml([
        'success' => TRUE,
        'theme' => $theme,
        'count' => count($items),
        'keys' => $items,
      ]);
    }

    return $this->yaml([
      'success' => TRUE,
      'theme' => $theme,
      'section' => $options['section'],
      'count' => count($items),
      'items' => $items,
    ], 6);
  }

  /**
   * Exports theme settings to YAML.
   */
  #[CLI\Command(name: 'dxt:config:export', aliases: ['dxt-ce'])]
  #[CLI\Help(description: '[YAML] Exports DXPR Theme settings. Output is import-compatible.')]
  #[CLI\Option(name: 'file', description: 'Write to file instead of stdout')]
  #[CLI\Option(name: 'section', description: 'Export only a specific section')]
  #[CLI\Option(name: 'theme', description: 'Target theme (default: active DXPR theme)')]
  #[CLI\Usage(name: 'drush dxt:config:export --file=/tmp/theme.yml', description: 'Export to file')]
  #[CLI\Usage(name: 'drush dxt-ce --section=colors', description: 'Export only color settings')]
  public function configExport(
    array $options = [
      'file' => NULL,
      'section' => NULL,
      'theme' => NULL,
    ],
  ): string {
    $this->switchToAdmin();

    try {
      $theme = $this->resolveTheme($options['theme'], $this->configFactory, $this->themeList);
    }
    catch (\RuntimeException $e) {
      return $this->error($e->getMessage());
    }

    $schema = $this->loadSchema();
    $settings = [];

    foreach ($schema as $key => $def) {
      if ($options['section'] !== NULL && $def['section'] !== $options['section']) {
        continue;
      }
      $value = $this->getThemeSetting($key, $theme);
      if ($value !== NULL) {
        $settings[$key] = $value;
      }
    }

    // color_palette is not in the schema (it's a serialized blob, not a
    // form-widget setting), so fetch it explicitly and decompose into
    // individual color_palette_<name> keys for human/AI readability and
    // clean round-trip with import. Only include when exporting all
    // sections or the colors section.
    if ($options['section'] === NULL || $options['section'] === 'colors') {
      $palette = $this->getThemeSetting('color_palette', $theme);
      if (is_string($palette) && $palette !== '') {
        $decoded = @unserialize($palette, ['allowed_classes' => FALSE]);
        if (is_array($decoded)) {
          foreach ($decoded as $colorKey => $colorValue) {
            $settings['color_palette_' . $colorKey] = $colorValue;
          }
        }
      }
    }

    $exportData = [
      'theme' => $theme,
      'exported' => date('c'),
      'settings' => $settings,
    ];

    if ($options['file'] !== NULL) {
      $dir = dirname($options['file']);
      if (!is_dir($dir)) {
        mkdir($dir, 0755, TRUE);
      }
      file_put_contents($options['file'], Yaml::dump($exportData, 4, 2));
      return $this->success(sprintf('Settings exported to %s.', $options['file']), [
        'data' => [
          'theme' => $theme,
          'file' => $options['file'],
          'count' => count($settings),
        ],
      ]);
    }

    return Yaml::dump($exportData, 4, 2);
  }

  /**
   * Imports theme settings from YAML.
   */
  #[CLI\Command(name: 'dxt:config:import', aliases: ['dxt-ci'])]
  #[CLI\Help(description: '[YAML] Imports DXPR Theme settings from a YAML file. Validates all values before applying.')]
  #[CLI\Argument(name: 'file', description: 'Path to YAML file')]
  #[CLI\Option(name: 'theme', description: 'Target theme (default: from file or active DXPR theme)')]
  #[CLI\Option(name: 'dry-run', description: 'Validate without applying')]
  #[CLI\Usage(name: 'drush dxt:config:import /tmp/theme.yml', description: 'Import settings')]
  #[CLI\Usage(name: 'drush dxt-ci /tmp/theme.yml --dry-run', description: 'Validate import file')]
  public function configImport(
    string $file,
    array $options = [
      'theme' => NULL,
      'dry-run' => FALSE,
    ],
  ): string {
    $this->switchToAdmin();

    if (!file_exists($file)) {
      return $this->error(sprintf('File not found: %s', $file));
    }

    $data = Yaml::parseFile($file);
    if (!is_array($data) || !isset($data['settings'])) {
      return $this->error('Invalid import file. Expected YAML with "settings" key.');
    }

    try {
      $theme = $this->resolveTheme($options['theme'] ?? ($data['theme'] ?? NULL), $this->configFactory, $this->themeList);
    }
    catch (\RuntimeException $e) {
      return $this->error($e->getMessage());
    }

    // Separate palette colors from regular settings.
    $paletteColors = [];
    $regularSettings = [];
    foreach ($data['settings'] as $key => $value) {
      if (str_starts_with($key, 'color_palette_')) {
        $colorKey = substr($key, strlen('color_palette_'));
        $paletteColors[$colorKey] = $value;
      }
      else {
        $regularSettings[$key] = $value;
      }
    }

    // Validate regular settings (atomic: all-or-nothing).
    // Skip validation for values that match what's already stored — this
    // handles schema/default mismatches during export→import round-trips.
    $config = $this->configFactory->get($theme . '.settings');
    $allErrors = [];
    foreach ($regularSettings as $key => $value) {
      $current = $config->get($key);
      if ($current !== NULL && $current == $value) {
        continue;
      }
      if (is_bool($value)) {
        $v = $value ? '1' : '0';
      }
      elseif (is_scalar($value)) {
        $v = (string) $value;
      }
      else {
        $v = $value;
      }
      $errors = $this->validateValue($key, $v);
      if (!empty($errors)) {
        $allErrors[$key] = $errors;
      }
    }

    // Validate palette colors are valid hex.
    foreach ($paletteColors as $colorKey => $value) {
      if (!preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', (string) $value)) {
        $allErrors['color_palette_' . $colorKey] = ['Value must be a hex color (e.g. #ff0000).'];
      }
    }

    if (!empty($allErrors)) {
      return $this->error('Validation failed. No settings were changed.', $allErrors);
    }

    $totalCount = count($regularSettings) + count($paletteColors);

    if ($options['dry-run']) {
      return $this->yaml([
        'success' => TRUE,
        'message' => 'Dry run: all values valid, import would succeed.',
        'dry_run' => TRUE,
        'theme' => $theme,
        'count' => $totalCount,
        'palette_colors' => count($paletteColors),
      ]);
    }

    // Apply regular settings.
    $config = $this->configFactory->getEditable($theme . '.settings');
    foreach ($regularSettings as $key => $value) {
      $config->set($key, $this->normalizeValue($key, $value));
    }

    // If the raw color_palette key (serialized string) was imported,
    // auto-set color_scheme to custom so the palette CSS applies.
    if (isset($regularSettings['color_palette'])) {
      $config->set('color_scheme', 'custom');
    }

    // Apply palette colors (merge into serialized palette).
    if (!empty($paletteColors)) {
      $serialized = $config->get('color_palette') ?? '';
      $palette = unserialize($serialized, ['allowed_classes' => FALSE]) ?: [];
      foreach ($paletteColors as $colorKey => $value) {
        $palette[$colorKey] = $value;
      }
      // Switch to custom scheme so the palette CSS applies.
      $config->set('color_scheme', 'custom');
      $config->set('color_palette', serialize($palette));
    }
    $config->save();
    $this->rebuildCssCache($theme);

    return $this->success(sprintf('Imported %d settings.', $totalCount), [
      'data' => [
        'theme' => $theme,
        'count' => $totalCount,
        'palette_colors' => count($paletteColors),
      ],
    ]);
  }

  /**
   * Resets theme settings to defaults.
   */
  #[CLI\Command(name: 'dxt:config:reset', aliases: ['dxt-cr'])]
  #[CLI\Help(description: '[YAML] Resets DXPR Theme settings to defaults from the schema.')]
  #[CLI\Option(name: 'section', description: 'Reset only a specific section')]
  #[CLI\Option(name: 'theme', description: 'Target theme (default: active DXPR theme)')]
  #[CLI\Option(name: 'dry-run', description: 'Show what would be reset without applying')]
  #[CLI\Usage(name: 'drush dxt:config:reset --section=typography', description: 'Reset typography settings')]
  #[CLI\Usage(name: 'drush dxt-cr --dry-run', description: 'Preview full reset')]
  public function configReset(
    array $options = [
      'section' => NULL,
      'theme' => NULL,
      'dry-run' => FALSE,
    ],
  ): string {
    $this->switchToAdmin();

    try {
      $theme = $this->resolveTheme($options['theme'], $this->configFactory, $this->themeList);
    }
    catch (\RuntimeException $e) {
      return $this->error($e->getMessage());
    }

    $schema = $this->loadSchema();
    $config = $this->configFactory->getEditable($theme . '.settings');
    $resetKeys = [];

    foreach ($schema as $key => $def) {
      if ($options['section'] !== NULL && $def['section'] !== $options['section']) {
        continue;
      }
      // Skip settings without a known default.
      if (!array_key_exists('default', $def) || $def['default'] === NULL) {
        continue;
      }
      // Skip dynamic defaults.
      if (is_string($def['default']) && str_starts_with($def['default'], '_dynamic')) {
        continue;
      }
      $resetKeys[$key] = $def['default'];
    }

    if ($options['dry-run']) {
      return $this->yaml([
        'success' => TRUE,
        'message' => 'Dry run: settings would be reset to defaults.',
        'dry_run' => TRUE,
        'theme' => $theme,
        'section' => $options['section'],
        'count' => count($resetKeys),
        'keys' => array_keys($resetKeys),
      ]);
    }

    foreach ($resetKeys as $key => $default) {
      $config->set($key, $this->normalizeValue($key, $default));
    }
    $config->save();
    $this->rebuildCssCache($theme);

    return $this->success(sprintf('Reset %d settings to defaults.', count($resetKeys)), [
      'data' => [
        'theme' => $theme,
        'section' => $options['section'],
        'count' => count($resetKeys),
      ],
    ]);
  }

  /**
   * Gets the current color palette with Bootstrap mapping.
   */
  #[CLI\Command(name: 'dxt:palette:get', aliases: ['dxt-pg-colors'])]
  #[CLI\Help(description: '[YAML] Gets the current color palette showing each color with its Bootstrap CSS variable and class mapping.')]
  #[CLI\Option(name: 'theme', description: 'Target theme (default: active DXPR theme)')]
  #[CLI\Usage(name: 'drush dxt:palette:get', description: 'Show palette with Bootstrap mapping')]
  public function paletteGet(
    array $options = [
      'theme' => NULL,
    ],
  ): string {
    $this->switchToAdmin();

    try {
      $theme = $this->resolveTheme($options['theme'], $this->configFactory, $this->themeList);
    }
    catch (\RuntimeException $e) {
      return $this->error($e->getMessage());
    }

    $config = $this->configFactory->get($theme . '.settings');
    $serialized = $config->get('color_palette') ?? '';
    $palette = unserialize($serialized, ['allowed_classes' => FALSE]) ?: [];

    $colors = [];
    foreach ($palette as $key => $hex) {
      $entry = [
        'color' => $hex,
      ];
      if (isset(self::PALETTE_BOOTSTRAP_MAP[$key])) {
        $entry['css_variable'] = self::PALETTE_BOOTSTRAP_MAP[$key]['variable'];
        $entry['bootstrap_classes'] = self::PALETTE_BOOTSTRAP_MAP[$key]['classes'];
      }
      $colors[$key] = $entry;
    }

    return $this->yaml([
      'success' => TRUE,
      'theme' => $theme,
      'count' => count($palette),
      'palette' => $colors,
    ], 5);
  }

  /**
   * Sets the entire color palette in one command.
   *
   * Palette keys map to Bootstrap classes: base → btn-primary/bg-primary,
   * accent1 → btn-secondary/bg-secondary, silver → bg-light, headings → bg-dark.
   * Use dxt:palette:get to see all mappings with current colors.
   */
  #[CLI\Command(name: 'dxt:palette:set', aliases: ['dxt-ps-colors'])]
  #[CLI\Help(description: '[YAML] Sets multiple palette colors at once. Keys map to Bootstrap: base=btn-primary, accent1=btn-secondary, silver=bg-light, headings=bg-dark. See dxt:palette:get for full mapping.')]
  #[CLI\Argument(name: 'colors', description: 'Color assignments as key=#hex pairs (e.g. base=#6C3CE1 accent1=#00D4FF)')]
  #[CLI\Option(name: 'theme', description: 'Target theme (default: active DXPR theme)')]
  #[CLI\Option(name: 'dry-run', description: 'Validate without saving')]
  #[CLI\Usage(name: 'drush dxt:palette:set base=#6C3CE1 basetext=#ffffff accent1=#00D4FF', description: 'Set multiple palette colors')]
  #[CLI\Usage(name: 'drush dxt:palette:set base=#333 link=#0066cc --dry-run', description: 'Preview palette changes')]
  public function paletteSet(
    array $colors,
    array $options = [
      'theme' => NULL,
      'dry-run' => FALSE,
    ],
  ): string {
    $this->switchToAdmin();

    try {
      $theme = $this->resolveTheme($options['theme'], $this->configFactory, $this->themeList);
    }
    catch (\RuntimeException $e) {
      return $this->error($e->getMessage());
    }

    if (empty($colors)) {
      return $this->error('No colors specified. Pass colors as key=#hex pairs (e.g. base=#6C3CE1 accent1=#00D4FF).');
    }

    // Parse key=value pairs.
    $parsed = [];
    $errors = [];
    foreach ($colors as $pair) {
      if (!str_contains($pair, '=')) {
        $errors[] = sprintf('Invalid format "%s". Use key=#hex (e.g. base=#6C3CE1).', $pair);
        continue;
      }
      [$colorKey, $hex] = explode('=', $pair, 2);
      if (!preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $hex)) {
        $errors[] = sprintf('Invalid color "%s" for key "%s". Must be hex (e.g. #ff0000).', $hex, $colorKey);
        continue;
      }
      $parsed[$colorKey] = $hex;
    }

    if (!empty($errors)) {
      return $this->error('Invalid color values.', $errors);
    }

    $config = $this->configFactory->getEditable($theme . '.settings');
    $serialized = $config->get('color_palette') ?? '';
    $palette = unserialize($serialized, ['allowed_classes' => FALSE]) ?: [];

    $changes = [];
    foreach ($parsed as $colorKey => $hex) {
      $changes[$colorKey] = [
        'old' => $palette[$colorKey] ?? NULL,
        'new' => $hex,
      ];
    }

    if ($options['dry-run']) {
      return $this->yaml([
        'success' => TRUE,
        'message' => 'Dry run: palette would be updated.',
        'dry_run' => TRUE,
        'theme' => $theme,
        'count' => count($parsed),
        'changes' => $changes,
      ]);
    }

    foreach ($parsed as $colorKey => $hex) {
      $palette[$colorKey] = $hex;
    }
    // Switch to custom scheme so the palette CSS applies.
    $config->set('color_scheme', 'custom');
    $config->set('color_palette', serialize($palette))->save();
    $this->rebuildCssCache($theme);

    return $this->success(sprintf('Updated %d palette colors.', count($parsed)), [
      'data' => [
        'theme' => $theme,
        'count' => count($parsed),
        'colors' => $parsed,
      ],
    ]);
  }

  /**
   * Sets a single color in the serialized palette.
   *
   * Handles keys like color_palette_base, color_palette_accent1, etc.
   * Deserializes the palette, updates the specific color, re-serializes.
   *
   * @param string $key
   *   The full key (e.g. color_palette_base).
   * @param string $value
   *   The hex color value.
   * @param string $theme
   *   The theme machine name.
   * @param bool $dryRun
   *   Whether this is a dry run.
   *
   * @return string
   *   YAML response.
   */
  protected function setPaletteColor(string $key, string $value, string $theme, bool $dryRun): string {
    $colorKey = substr($key, strlen('color_palette_'));

    // Validate hex color.
    if (!preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $value)) {
      return $this->error(
        sprintf('Invalid value for "%s".', $key),
        ['Value must be a hex color (e.g. #ff0000 or #f00).']
      );
    }

    $config = $this->configFactory->getEditable($theme . '.settings');
    $serialized = $config->get('color_palette') ?? '';
    $palette = unserialize($serialized, ['allowed_classes' => FALSE]) ?: [];

    $oldValue = $palette[$colorKey] ?? NULL;

    if ($dryRun) {
      return $this->yaml([
        'success' => TRUE,
        'message' => 'Dry run: palette color would be updated.',
        'dry_run' => TRUE,
        'theme' => $theme,
        'key' => $key,
        'color_key' => $colorKey,
        'old_value' => $oldValue,
        'new_value' => $value,
      ]);
    }

    $palette[$colorKey] = $value;
    // Switch to custom scheme so the palette CSS applies.
    $config->set('color_scheme', 'custom');
    $config->set('color_palette', serialize($palette))->save();
    $this->rebuildCssCache($theme);

    return $this->success(sprintf('Palette color "%s" updated.', $colorKey), [
      'data' => [
        'theme' => $theme,
        'key' => $key,
        'color_key' => $colorKey,
        'old_value' => $oldValue,
        'new_value' => $value,
      ],
    ]);
  }

}
