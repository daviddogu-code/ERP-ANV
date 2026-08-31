<?php

namespace Drupal\tec_production\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\tec_production\Purchasing;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Invoice icon on purchase order listings.
 *
 * The file lives on the order. Listings used to lose the row once the invoice
 * was filed, and the order card hid the field, so nobody who was not staring at
 * Purchase Control could open the scan again. The icon is empty when nothing is
 * filed, so it can sit next to Print on both open and closed rows.
 *
 * Click opens the same File invoice dialog Purchase Control uses: that is the
 * only place a wrong scan can be replaced after the row has left that screen.
 * Hover still shows the scan, from the file URL on the link.
 */
#[ViewsField("tec_purchase_invoice")]
class PurchaseInvoice extends FieldPluginBase {

  /**
   * Public so the lists and the order card share one icon.
   */
  public const ICON = 'public://000-invoice-16.png';

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Nothing to add. The file hangs off the order, which is already loaded.
  }

  /**
   * {@inheritdoc}
   */
  public function clickSortable() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['show_label'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['show_label'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show the word Invoice next to the icon'),
      '#default_value' => !empty($this->options['show_label']),
      '#description' => $this->t('On the order card. The supplier list only has room for the icon.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $order = $this->getEntity($values);
    if (!$order || $order->bundle() !== 'tec_purchase_order') {
      return '';
    }

    $file = Purchasing::invoice($order);
    $url = Purchasing::invoiceUrl($order);
    if (!$file || !$url) {
      return '';
    }

    $title = $this->t('Invoice: @name. Click to replace it.', ['@name' => $file->getFilename()]);
    $with_word = !empty($this->options['show_label']);
    $classes = Purchasing::invoiceDialogAttributes($with_word ? [] : ['tec-control']);
    $trigger = [
      '#type' => 'link',
      '#url' => Purchasing::invoiceDialogUrl($order),
      '#attributes' => $classes + [
        'title' => (string) $title,
      ],
      '#title' => [
        'icon' => [
          '#theme' => 'image',
          '#uri' => self::ICON,
          '#alt' => (string) $this->t('Invoice'),
        ],
        'word' => $with_word ? ['#markup' => ' ' . $this->t('Invoice')] : [],
      ],
    ];

    $preview = Purchasing::invoicePreview($file, $trigger, $url);
    if ($with_word) {
      $preview = [
        '#type' => 'container',
        '#attributes' => ['class' => ['btn-default']],
        'inner' => [
          '#type' => 'html_tag',
          '#tag' => 'strong',
          'preview' => $preview,
        ],
      ];
    }

    $preview['#cache']['tags'] = array_merge(
      $order->getCacheTags(),
      $file->getCacheTags(),
    );
    return $preview;
  }

}
