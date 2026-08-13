<?php

declare(strict_types=1);

namespace Drupal\dxpr_theme_helper\Commands;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Attributes as CLI;

/**
 * Drush commands for per-node DXPR Theme page layout fields.
 */
class DxprThemePageCommands extends DxprThemeCommandsBase {

  /**
   * DXPR Theme Helper field names.
   */
  protected const DTH_FIELDS = [
    'field_dth_page_layout',
    'field_dth_hide_regions',
    'field_dth_main_content_width',
    'field_dth_body_background',
    'field_dth_page_title_backgrou',
  ];

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EntityFieldManagerInterface $entityFieldManager,
  ) {
    parent::__construct();
  }

  /**
   * Gets DXPR Theme field values for a node.
   */
  #[CLI\Command(name: 'dxt:page:get', aliases: ['dxt-pg'])]
  #[CLI\Help(description: '[YAML] Gets per-node DXPR Theme layout field values (page layout, hidden regions, content width, backgrounds).')]
  #[CLI\Argument(name: 'nid', description: 'Node ID')]
  #[CLI\Usage(name: 'drush dxt:page:get 42', description: 'Get theme fields for node 42')]
  public function pageGet(string $nid): string {
    $this->switchToAdmin();

    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    if (!$node) {
      return $this->error(sprintf('Node %s not found.', $nid));
    }

    $fields = $this->getAvailableDthFields($node->bundle());
    if (empty($fields)) {
      return $this->error(sprintf('No DXPR Theme fields found on content type "%s".', $node->bundle()));
    }

    $values = [];
    foreach ($fields as $fieldName) {
      if (!$node->hasField($fieldName)) {
        continue;
      }
      $field = $node->get($fieldName);
      $cardinality = $field->getFieldDefinition()->getFieldStorageDefinition()->getCardinality();

      if ($cardinality === 1) {
        $values[$fieldName] = $field->isEmpty() ? NULL : $field->first()->getString();
      }
      else {
        $items = [];
        foreach ($field as $item) {
          $items[] = $item->getString();
        }
        $values[$fieldName] = $items;
      }
    }

    return $this->yaml([
      'success' => TRUE,
      'nid' => (int) $node->id(),
      'title' => $node->label(),
      'type' => $node->bundle(),
      'fields' => $values,
    ]);
  }

  /**
   * Sets DXPR Theme field values on a node.
   */
  #[CLI\Command(name: 'dxt:page:set', aliases: ['dxt-ps'])]
  #[CLI\Help(description: '[YAML] Sets per-node DXPR Theme layout overrides. Creates a new revision.')]
  #[CLI\Argument(name: 'nid', description: 'Node ID')]
  #[CLI\Option(name: 'layout', description: 'Page layout: fullwidth or boxed')]
  #[CLI\Option(name: 'hide-regions', description: 'Comma-separated regions to hide (e.g. navigation,footer)')]
  #[CLI\Option(name: 'content-width', description: 'Content width class: full, 1-3, 1-2, 2-3, 5-6')]
  #[CLI\Option(name: 'dry-run', description: 'Show what would change without saving')]
  #[CLI\Usage(name: 'drush dxt:page:set 42 --layout=fullwidth', description: 'Set full width layout')]
  #[CLI\Usage(name: 'drush dxt-ps 42 --hide-regions=navigation,footer --layout=boxed', description: 'Set layout and hide regions')]
  public function pageSet(
    string $nid,
    array $options = [
      'layout' => NULL,
      'hide-regions' => NULL,
      'content-width' => NULL,
      'dry-run' => FALSE,
    ],
  ): string {
    $this->switchToAdmin();

    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    if (!$node) {
      return $this->error(sprintf('Node %s not found.', $nid));
    }

    $fields = $this->getAvailableDthFields($node->bundle());
    if (empty($fields)) {
      return $this->error(sprintf('No DXPR Theme fields found on content type "%s".', $node->bundle()));
    }

    $changes = [];

    // Layout.
    if ($options['layout'] !== NULL) {
      if (!in_array($options['layout'], ['fullwidth', 'boxed'])) {
        return $this->error('Invalid --layout value.', ['Valid options: fullwidth, boxed.']);
      }
      if (in_array('field_dth_page_layout', $fields)) {
        $changes['field_dth_page_layout'] = $options['layout'];
      }
      else {
        return $this->error('field_dth_page_layout not available on this content type.');
      }
    }

    // Hide regions.
    if ($options['hide-regions'] !== NULL) {
      if (in_array('field_dth_hide_regions', $fields)) {
        $regions = array_map('trim', explode(',', $options['hide-regions']));
        $changes['field_dth_hide_regions'] = $regions;
      }
      else {
        return $this->error('field_dth_hide_regions not available on this content type.');
      }
    }

    // Content width.
    if ($options['content-width'] !== NULL) {
      $widthMap = [
        'full' => 'dxpr-theme-util-full-width-content',
        '1-3' => 'dxpr-theme-util-content-center-4-col',
        '1-2' => 'dxpr-theme-util-content-center-6-col',
        '2-3' => 'dxpr-theme-util-content-center-8-col',
        '5-6' => 'dxpr-theme-util-content-center-10-col',
      ];
      if (!isset($widthMap[$options['content-width']])) {
        return $this->error('Invalid --content-width value.', ['Valid options: full, 1-3, 1-2, 2-3, 5-6.']);
      }
      if (in_array('field_dth_main_content_width', $fields)) {
        $changes['field_dth_main_content_width'] = $widthMap[$options['content-width']];
      }
      else {
        return $this->error('field_dth_main_content_width not available on this content type.');
      }
    }

    if (empty($changes)) {
      return $this->error('No changes specified. Use --layout, --hide-regions, or --content-width.');
    }

    if ($options['dry-run']) {
      return $this->yaml([
        'success' => TRUE,
        'message' => 'Dry run: node would be updated.',
        'dry_run' => TRUE,
        'nid' => (int) $node->id(),
        'changes' => $changes,
      ]);
    }

    foreach ($changes as $fieldName => $value) {
      $node->set($fieldName, $value);
    }

    if ($node->getEntityType()->isRevisionable()) {
      $node->setNewRevision(TRUE);
      $node->setRevisionLogMessage('Updated via dxt:page:set Drush command.');
    }
    $node->save();

    return $this->success(sprintf('Node %s updated.', $nid), [
      'data' => [
        'nid' => (int) $node->id(),
        'title' => $node->label(),
        'changes' => $changes,
      ],
    ]);
  }

  /**
   * Gets DTH fields available on a given bundle.
   *
   * @param string $bundle
   *   The node bundle.
   *
   * @return array
   *   List of available DTH field names.
   */
  protected function getAvailableDthFields(string $bundle): array {
    $definitions = $this->entityFieldManager->getFieldDefinitions('node', $bundle);
    $available = [];
    foreach (self::DTH_FIELDS as $fieldName) {
      if (isset($definitions[$fieldName])) {
        $available[] = $fieldName;
      }
    }
    return $available;
  }

}
