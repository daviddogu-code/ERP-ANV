<?php

namespace Drupal\commerce_pricelist\Form;

use Drupal\commerce_pricelist\Entity\PriceListInterface;
use Drupal\commerce_pricelist\Entity\PriceListItemInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PriceListItemExportForm extends FormBase {

  /**
   * The number of price list items to process in each batch.
   *
   * @var int
   */
  const BATCH_SIZE = 25;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs a new PriceListItemExportForm object.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager, FileSystemInterface $file_system) {
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_pricelist_item_export_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, PriceListInterface $commerce_pricelist = NULL) {
    $form_state->set('price_list_id', $commerce_pricelist->id());
    $form_state->set('price_list_bundle', $commerce_pricelist->bundle());
    $purchasable_entity_type_id = $commerce_pricelist->bundle();
    $purchasable_entity_type = $this->entityTypeManager->getDefinition($purchasable_entity_type_id);
    $default_purchasable_entity_column = $purchasable_entity_type->id();
    if (strpos($default_purchasable_entity_column, 'commerce_') === 0) {
      $default_purchasable_entity_column = str_replace('commerce_', '', $default_purchasable_entity_column);
    }
    $form['#tree'] = TRUE;
    $form['mapping'] = [
      '#type' => 'details',
      '#title' => $this->t('CSV mapping options'),
      '#collapsible' => TRUE,
      '#open' => TRUE,
    ];
    $form['mapping']['purchasable_entity_column'] = [
      '#type' => 'textfield',
      '#title' => $this->t('@purchasable_entity_type column', [
        '@purchasable_entity_type' => $purchasable_entity_type->getLabel(),
      ]),
      '#default_value' => $default_purchasable_entity_column,
      '#required' => TRUE,
    ];
    $form['mapping']['purchasable_entity_label_column'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label column'),
      '#description' => $this->t('Leave blank to exclude the label from the export.'),
      '#default_value' => 'title',
    ];
    $form['mapping']['quantity_column'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Quantity column'),
      '#default_value' => 'quantity',
      '#required' => TRUE,
    ];
    $form['mapping']['list_price_column'] = [
      '#type' => 'textfield',
      '#title' => $this->t('List price column'),
      '#default_value' => 'list_price',
      '#required' => TRUE,
    ];
    $form['mapping']['price_column'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Price column'),
      '#default_value' => 'price',
      '#required' => TRUE,
    ];
    $form['mapping']['currency_column'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Currency column'),
      '#default_value' => 'currency_code',
      '#required' => TRUE,
    ];
    $form['options'] = [
      '#type' => 'details',
      '#title' => $this->t('CSV file options'),
      '#collapsible' => TRUE,
      '#open' => FALSE,
    ];
    $form['options']['delimiter'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Delimiter'),
      '#size' => 5,
      '#maxlength' => 1,
      '#default_value' => ',',
      '#required' => TRUE,
    ];
    $form['options']['enclosure'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Enclosure'),
      '#size' => 5,
      '#maxlength' => 1,
      '#default_value' => '"',
      '#required' => TRUE,
    ];
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export prices'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $batch = [
      'title' => $this->t('Exporting prices'),
      'progress_message' => '',
      'operations' => [],
      'finished' => [$this, 'finishBatch'],
    ];
    $price_list_id = $form_state->get('price_list_id');

    $batch['operations'][] = [
      [get_class($this), 'batchProcess'],
      [
        $this->fileSystem->createFilename("pricelist-$price_list_id-prices.csv", 'temporary://'),
        $values['mapping'],
        $values['options'],
        $price_list_id,
      ],
    ];

    batch_set($batch);
    $form_state->setRedirect('entity.commerce_pricelist_item.collection', [
      'commerce_pricelist' => $price_list_id,
    ]);
  }

  /**
   * Batch process to export price list items to CSV.
   *
   * @param string $file_uri
   *   The CSV file URI.
   * @param array $mapping
   *   The mapping options.
   * @param array $csv_options
   *   The CSV options.
   * @param string $price_list_id
   *   The price list ID.
   * @param array $context
   *   The batch context.
   */
  public static function batchProcess($file_uri, array $mapping, array $csv_options, $price_list_id, array &$context) {
    $entity_type_manager = \Drupal::entityTypeManager();
    $price_list_storage = $entity_type_manager->getStorage('commerce_pricelist');
    $price_list_item_storage = $entity_type_manager->getStorage('commerce_pricelist_item');
    /** @var \Drupal\commerce_pricelist\Entity\PriceList $price_list */
    $price_list = $price_list_storage->load($price_list_id);
    /** @var \Drupal\Core\StreamWrapper\StreamWrapperInterface $stream_wrapper */
    $stream_wrapper = \Drupal::service('stream_wrapper.temporary');
    $stream_wrapper->setUri($file_uri);
    try {
      $csv = new \SplFileObject($stream_wrapper->realpath(), 'a+');
      $csv->setCsvControl($csv_options['delimiter'], $csv_options['enclosure']);
    }
    catch (\Exception $e) {
      $context['results']['error_message'] = t('Cannot open the CSV file @filename for writing, aborting.', ['@filename' => $stream_wrapper->realpath()]);
      $context['sandbox']['finished'] = 1;
      return;
    }

    if (empty($context['sandbox'])) {
      $price_list_item_count = $price_list_item_storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', $price_list->bundle())
        ->condition('price_list_id', $price_list->id())
        ->count()
        ->execute();

      $context['sandbox']['header_mapping'] = static::buildHeader($mapping);
      // Append the configured headers to the CSV file.
      $csv->fputcsv($context['sandbox']['header_mapping']);
      $context['sandbox']['export_total'] = (int) $price_list_item_count;
      $context['results']['external_url'] = $stream_wrapper->getExternalUrl();
      $context['results']['export_count'] = 0;
    }

    $export_total = $context['sandbox']['export_total'];
    $export_count = &$context['results']['export_count'];
    $remaining = $export_total - $export_count;
    $limit = ($remaining < self::BATCH_SIZE) ? $remaining : self::BATCH_SIZE;
    $price_list_item_ids = $price_list_item_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $price_list->bundle())
      ->condition('price_list_id', $price_list->id())
      ->range($export_count, $limit)
      ->execute();

    if (!$price_list_item_ids) {
      $context['finished'] = 1;
      return;
    }
    $price_list_items = $price_list_item_storage->loadMultiple($price_list_item_ids);
    /** @var \Drupal\commerce_pricelist\Entity\PriceListItemInterface $price_list_item */
    foreach ($price_list_items as $price_list_item) {
      $row = static::buildRow($price_list_item, $context['sandbox']['header_mapping']);
      $csv->fputcsv($row);
      $export_count++;
    }
    $context['message'] = t('Exporting @exported of @export_total price list items', [
      '@exported' => $export_count,
      '@export_total' => $export_total,
    ]);
    $context['finished'] = $export_count / $export_total;
  }

  /**
   * Batch finished callback: display batch statistics.
   *
   * @param bool $success
   *   Indicates whether the batch has completed successfully.
   * @param mixed[] $results
   *   The array of results gathered by the batch processing.
   * @param string[] $operations
   *   If $success is FALSE, contains the operations that remained unprocessed.
   */
  public static function finishBatch($success, array $results, array $operations) {
    $messenger = \Drupal::messenger();
    if (!$success) {
      $error_operation = reset($operations);
      $messenger->addError(t('An error occurred while processing @operation with arguments: @args', [
        '@operation' => $error_operation[0],
        '@args' => (string) print_r($error_operation[0], TRUE),
      ]));
      return;
    }

    if (isset($results['error_message'])) {
      $messenger->addError($results['error_message']);
      return;
    }
    $translation = \Drupal::translation();
    if (!empty($results['export_count'])) {
      $messenger->addStatus($translation->formatPlural(
        $results['export_count'],
        'Exported 1 price.',
        'Exported @count prices.'
      ));
    }
    if (isset($results['external_url'])) {
      $messenger->addStatus(t('The generated file can be downloaded at the following url: <a href="@url">@url</a>.', ['@url' => $results['external_url']]));
    }
  }

  /**
   * Builds the CSV header row.
   *
   * @param array $mapping
   *   The column mapping array.
   *
   * @return array
   *   The CSV header row.
   */
  protected static function buildHeader(array $mapping) {
    // Nothing to do, but could potentially be overridden by a child class.
    return array_filter($mapping);
  }

  /**
   * Builds the CSV row for the given price list item.
   *
   * @param \Drupal\commerce_pricelist\Entity\PriceListItemInterface $price_list_item
   *   The price list item.
   * @param array $header_mapping
   *   The CSV header mapping.
   *
   * @return array
   *   The CSV row for the given price list item.
   */
  protected static function buildRow(PriceListItemInterface $price_list_item, array $header_mapping) {
    $purchased_entity = $price_list_item->getPurchasableEntity();
    $id = $price_list_item->bundle() === 'commerce_product_variation' ? $purchased_entity->getSku() : $purchased_entity->id();
    $list_price = '';

    if ($price_list_item->getListPrice()) {
      $list_price = $price_list_item->getListPrice()->getNumber();
    }
    $row = [$id];

    // Include the title if it wasn't excluded from the export.
    if (!empty($header_mapping['purchasable_entity_label_column'])) {
      $row[] = $purchased_entity->label();
    }

    return array_merge($row, [
      $price_list_item->getQuantity(),
      $list_price,
      $price_list_item->getPrice()->getNumber(),
      $price_list_item->getPrice()->getCurrencyCode(),
    ]);
  }

}
