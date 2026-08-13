<?php

declare(strict_types=1);

namespace Drupal\dxpr_theme_helper\Commands;

use Drupal\Core\Extension\ModuleExtensionList;
use Drush\Attributes as CLI;

/**
 * Drush command for AI coding assistant setup.
 */
class DxprThemeSetupCommands extends DxprThemeCommandsBase {

  public function __construct(
    protected readonly ModuleExtensionList $moduleExtensionList,
  ) {
    parent::__construct();
  }

  /**
   * Installs AI skill files to the project root.
   */
  #[CLI\Command(name: 'dxt:setup-ai', aliases: ['dxt-sa'])]
  #[CLI\Help(description: '[YAML] Installs DXPR Theme AI skill files so coding assistants can discover dxt:* commands and theme setting rules.')]
  #[CLI\Option(name: 'host', description: 'Target: claude, agents, or all (default: all)')]
  #[CLI\Option(name: 'check', description: 'Check if installed files are up to date (no changes made)')]
  #[CLI\Usage(name: 'drush dxt:setup-ai', description: 'Install for all AI tools')]
  #[CLI\Usage(name: 'drush dxt:setup-ai --check', description: 'Check if skill files are up to date')]
  #[CLI\Usage(name: 'drush dxt-sa --host=claude', description: 'Install for Claude Code only')]
  #[CLI\Usage(name: 'drush dxt-sa --host=agents', description: 'Install for Codex/Gemini/Copilot/Cursor')]
  public function setupAi(
    array $options = [
      'host' => 'all',
      'check' => FALSE,
    ],
  ): string {
    $modulePath = $this->getModulePath();
    $projectRoot = $this->getProjectRoot();
    $host = $options['host'] ?? 'all';

    if ($modulePath === NULL) {
      return $this->error('Could not determine dxpr_theme_helper module path.');
    }
    if ($projectRoot === NULL) {
      return $this->error('Could not determine project root (no composer.json found).');
    }
    if (!in_array($host, ['claude', 'agents', 'all'])) {
      return $this->error('Invalid --host value. Use: claude, agents, or all.');
    }

    // Check mode: compare installed vs source files.
    if ($options['check']) {
      return $this->checkSkillFiles($modulePath, $projectRoot, $host);
    }

    $results = [];
    $installClaude = in_array($host, ['claude', 'all']);
    $installAgents = in_array($host, ['agents', 'all']);

    if ($installClaude) {
      $results = array_merge($results, $this->installFile(
        $modulePath,
        $projectRoot,
        '.claude/skills/dxt/SKILL.md',
      ));
    }

    if ($installAgents) {
      $results = array_merge($results, $this->installFile(
        $modulePath,
        $projectRoot,
        '.agents/skills/dxt/SKILL.md',
      ));
      $results = array_merge($results, $this->installFile(
        $modulePath,
        $projectRoot,
        '.agents/skills/dxt/agents/openai.yaml',
      ));
    }

    $supportedTools = [];
    if ($installClaude) {
      $supportedTools[] = 'Claude Code: .claude/skills/dxt/SKILL.md';
    }
    if ($installAgents) {
      $supportedTools[] = 'Codex / Gemini / Copilot / Cursor: .agents/skills/dxt/SKILL.md';
    }

    return $this->yaml([
      'success' => TRUE,
      'message' => 'DXPR Theme AI skill files installed.',
      'actions' => $results,
      'supported_tools' => $supportedTools,
    ]);
  }

  /**
   * Checks if installed skill files match the module source.
   */
  protected function checkSkillFiles(string $modulePath, string $projectRoot, string $host): string {
    $files = [];
    if (in_array($host, ['claude', 'all'])) {
      $files[] = '.claude/skills/dxt/SKILL.md';
    }
    if (in_array($host, ['agents', 'all'])) {
      $files[] = '.agents/skills/dxt/SKILL.md';
      $files[] = '.agents/skills/dxt/agents/openai.yaml';
    }

    $results = [];
    $outdated = FALSE;
    foreach ($files as $relativePath) {
      $source = $modulePath . '/' . $relativePath;
      $dest = $projectRoot . '/' . $relativePath;

      if (!file_exists($dest)) {
        $results[] = sprintf('%s — NOT INSTALLED', $relativePath);
        $outdated = TRUE;
      }
      elseif (!file_exists($source)) {
        $results[] = sprintf('%s — source missing', $relativePath);
      }
      elseif (md5_file($source) !== md5_file($dest)) {
        $results[] = sprintf('%s — OUTDATED', $relativePath);
        $outdated = TRUE;
      }
      else {
        $results[] = sprintf('%s — up to date', $relativePath);
      }
    }

    if ($outdated) {
      return $this->yaml([
        'success' => FALSE,
        'message' => 'Skill files are outdated. Run drush dxt:setup-ai to update.',
        'files' => $results,
      ]);
    }

    return $this->yaml([
      'success' => TRUE,
      'message' => 'All skill files are up to date.',
      'files' => $results,
    ]);
  }

  /**
   * Copies a single file from module to project root.
   */
  protected function installFile(string $modulePath, string $projectRoot, string $relativePath): array {
    $results = [];
    $source = $modulePath . '/' . $relativePath;
    $dest = $projectRoot . '/' . $relativePath;

    if (!file_exists($source)) {
      return [sprintf('Source not found: %s', $relativePath)];
    }

    $destDir = dirname($dest);
    if (!is_dir($destDir)) {
      mkdir($destDir, 0755, TRUE);
    }

    $action = file_exists($dest) ? 'updated' : 'installed';
    copy($source, $dest);
    $results[] = sprintf('%s %s at %s', basename($relativePath), $action, $relativePath);

    return $results;
  }

  /**
   * Gets the dxpr_theme_helper module path.
   */
  protected function getModulePath(): ?string {
    try {
      $path = $this->moduleExtensionList->getPath('dxpr_theme_helper');
      return $path ? DRUPAL_ROOT . '/' . $path : NULL;
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * Gets the Composer project root.
   */
  protected function getProjectRoot(): ?string {
    $dir = defined('DRUPAL_ROOT') ? DRUPAL_ROOT : getcwd();
    for ($i = 0; $i < 5; $i++) {
      if (file_exists($dir . '/composer.json')) {
        return $dir;
      }
      $parent = dirname($dir);
      if ($parent === $dir) {
        break;
      }
      $dir = $parent;
    }
    return NULL;
  }

}
