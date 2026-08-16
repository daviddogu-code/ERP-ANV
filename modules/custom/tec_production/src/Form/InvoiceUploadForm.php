<?php

namespace Drupal\tec_production\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\tec_production\Purchasing;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * File the supplier's invoice against a purchase order.
 *
 * A small dialog opened from the Purchase Control screen, because filing an
 * invoice is a ten second job done thirty times on the last day of the month
 * and it should not cost a page load each time.
 *
 * It replaces the last manual step of the Google Sheet: scan the invoice,
 * upload it to a Drive folder, copy the link into the row, delete the row. Here
 * the file lives on the order it belongs to, and the row leaves the screen by
 * itself.
 *
 * Two details that are on purpose:
 *
 * - The file goes to the private filesystem. An invoice carries a supplier's
 *   prices and tax number, and in the public one anybody who guesses the
 *   address downloads it without logging in.
 * - The upload accepts photos as well as PDFs, and does not force the camera.
 *   On a phone the picker then offers both, so the person standing at the
 *   delivery door can photograph the paper and whoever is at a desk can attach
 *   the scan, without two different screens.
 */
class InvoiceUploadForm extends FormBase {

  /**
   * What a scanner or a phone produces, and nothing else.
   */
  const EXTENSIONS = 'pdf jpg jpeg png heic webp';

  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tec_production_invoice_upload_form';
  }

  /**
   * Only for purchase orders, and only for whoever runs purchasing.
   *
   * The type declaration is load-bearing, the same as on ReceiveForm::access().
   * Without it Drupal hands this method the raw '923' from the path instead of
   * the loaded order, every check fails, and the dialog is forbidden to
   * everybody: its arguments resolver only reaches for the upcast object when a
   * parameter says which class it wants.
   */
  public static function access(AccountInterface $account, ?EntityInterface $tec_order = NULL) {
    $allowed = $tec_order
      && $tec_order->bundle() === 'tec_purchase_order'
      && $account->hasPermission('access tec purchase queue');

    return AccessResult::allowedIf($allowed)
      ->addCacheableDependency($tec_order)
      ->cachePerPermissions();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $tec_order = NULL) {
    if (!$tec_order || $tec_order->bundle() !== 'tec_purchase_order') {
      throw new NotFoundHttpException();
    }
    $form_state->set('order_id', (int) $tec_order->id());

    $existing = $tec_order->get('field_tec_supplier_invoice')->entity;
    $supplier = $tec_order->get('field_tec_vendor')->entity;

    $form['head'] = [
      '#markup' => '<div class="tec-invoice__head">'
        . '<div class="tec-invoice__order">' . Html::escape($tec_order->label()) . '</div>'
        . ($supplier ? '<div class="tec-invoice__supplier">' . Html::escape($supplier->label()) . '</div>' : '')
        . '</div>',
    ];

    if ($existing) {
      $form['existing'] = [
        '#markup' => '<div class="tec-invoice__existing">'
          . $this->t('Already filed: %name. Uploading another one replaces it.', ['%name' => $existing->getFilename()])
          . '</div>',
      ];
    }

    $form['invoice'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Invoice'),
      '#required' => TRUE,
      '#upload_location' => 'private://supplier-invoices/' . date('Y'),
      '#upload_validators' => [
        'FileExtension' => ['extensions' => self::EXTENSIONS],
      ],
      '#description' => $this->t('A scan or a photo. PDF, JPG, PNG or HEIC.'),
      // Documents and pictures, and deliberately not "capture": with capture a
      // phone opens straight into the camera and there is no way to attach the
      // PDF that is already on it. Offering both is what lets the same screen
      // serve the desk and the delivery door.
      '#accept' => '.pdf,image/*',
    ];

    // Saying it here beats letting the order disappear and be missed. The
    // screen keeps orders that still owe material even once they are invoiced.
    if (Purchasing::receiptState($tec_order) !== 'full') {
      $form['still_coming'] = [
        '#markup' => '<div class="tec-invoice__warning">'
          . $this->t('Part of this order has not arrived yet, so it stays on the screen until it does.')
          . '</div>',
      ];
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('File invoice'),
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('tec_production.purchase_queue'),
      '#attributes' => ['class' => ['button', 'dialog-cancel']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $order = $this->entityTypeManager->getStorage('tec_order')->load($form_state->get('order_id'));
    $fids = $form_state->getValue('invoice') ?: [];
    $file = $fids ? $this->entityTypeManager->getStorage('file')->load(reset($fids)) : NULL;
    if (!$order || !$file) {
      $this->messenger()->addError($this->t('The invoice was not saved. Try again.'));
      return;
    }

    // A managed file is temporary until something claims it, and the cron that
    // sweeps temporary files would delete this one within hours.
    $file->setPermanent();
    $file->save();

    $order->set('field_tec_supplier_invoice', ['target_id' => $file->id()]);
    $order->save();

    $this->messenger()->addStatus($this->t('Invoice filed for @order.', ['@order' => $order->label()]));
    $form_state->setRedirect('tec_production.purchase_queue');
  }

}
