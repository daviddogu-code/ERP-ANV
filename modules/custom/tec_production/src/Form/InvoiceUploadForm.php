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
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\tec_production\Purchasing;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * File the supplier's invoice against a purchase order.
 *
 * A small dialog opened from Purchase Control and from Supplier Orders.
 * Filing is a ten second job done thirty times on the last day of the month
 * and it should not cost a page load each time. Closed orders leave Purchase
 * Control, so the same form has to hang off the list or there is nowhere to
 * replace a bad scan.
 *
 * It replaces the last manual step of the Google Sheet: scan the invoice,
 * upload it to a Drive folder, copy the link into the row, delete the row. Here
 * the file lives on the order it belongs to, and the row leaves Purchase
 * Control by itself.
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
 * - Replacing a file discards the previous one. A scanner error is not worth a
 *   version history; if audit ever asks for the old scan, that is the day to
 *   keep it.
 * - The scan is renamed INV_PO_{ddmmyyyy}_{order}.pdf on the way in, Bangkok
 *   date, so a ZIP for the accountant does not carry 1893728.jpg.
 */
class InvoiceUploadForm extends FormBase {

  /**
   * What a scanner or a phone produces, and nothing else.
   */
  const EXTENSIONS = 'pdf jpg jpeg png heic webp';

  protected EntityTypeManagerInterface $entityTypeManager;

  protected FileUsageInterface $fileUsage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->fileUsage = $container->get('file.usage');
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

    $form['#attached']['library'][] = 'tec_production/invoice';

    $form['head'] = [
      '#markup' => '<div class="tec-invoice__head">'
        . '<div class="tec-invoice__order">' . Html::escape($tec_order->label()) . '</div>'
        . ($supplier ? '<div class="tec-invoice__supplier">' . Html::escape($supplier->label()) . '</div>' : '')
        . '</div>',
    ];

    if ($existing) {
      $view = Purchasing::invoiceUrl($tec_order);
      $save = Purchasing::invoiceDownloadUrl($tec_order);
      $form['existing'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['tec-invoice__existing']],
        'label' => [
          '#markup' => $this->t('Already filed:'),
        ],
      ];
      if ($view) {
        $form['existing']['open'] = [
          '#type' => 'link',
          '#title' => $existing->getFilename(),
          '#url' => $view,
          '#attributes' => [
            'class' => ['tec-invoice__open'],
            'target' => '_blank',
            'rel' => 'noopener noreferrer',
          ],
        ];
      }
      else {
        $form['existing']['name'] = [
          '#markup' => Html::escape($existing->getFilename()),
        ];
      }
      if ($save) {
        $form['existing']['download'] = [
          '#type' => 'link',
          '#title' => $this->t('Download'),
          '#url' => $save,
          '#attributes' => [
            'class' => ['button', 'tec-invoice__download'],
            'download' => $existing->getFilename(),
          ],
        ];
      }
      $form['replace_notice'] = [
        '#markup' => '<p class="tec-invoice__replace">' . $this->t('This replaces the current file.') . '</p>',
      ];
    }

    $form['invoice'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Invoice'),
      '#required' => TRUE,
      '#upload_location' => 'private://supplier-invoices/' . Purchasing::invoiceClock()->format('Y'),
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
      '#value' => $existing ? $this->t('Replace invoice') : $this->t('File invoice'),
      '#button_type' => 'primary',
    ];
    if ($existing) {
      $form['actions']['remove'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove invoice'),
        '#submit' => ['::removeInvoice'],
        '#limit_validation_errors' => [],
        '#attributes' => [
          'class' => ['tec-invoice__remove'],
          'title' => (string) $this->t('Takes the paper off this order. If the goods are in, it goes back on Purchase Control.'),
        ],
      ];
    }
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $this->backUrl(),
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
    if (!$order || !$file instanceof FileInterface) {
      $this->messenger()->addError($this->t('The invoice was not saved. Try again.'));
      return;
    }

    // Drop the previous scan first when the new one wants the same path.
    // Moving onto a URI that still has a file entity would reuse that record
    // and then deleting "the old one" would erase the file just filed.
    $old = Purchasing::invoice($order);
    $dest = Purchasing::invoiceDestination($order, $file, $old);
    if ($old && $old->getFileUri() === $dest) {
      $this->putInvoice($order, NULL);
      $old = NULL;
    }

    $file = Purchasing::placeInvoice($file, $order, $old);
    $this->putInvoice($order, $file);
    $this->messenger()->addStatus($this->t('Invoice filed for @order.', ['@order' => $order->label()]));
    $form_state->setRedirectUrl($this->backUrl());
  }

  /**
   * Clear the filed scan so the order can go back on Purchase Control.
   */
  public function removeInvoice(array &$form, FormStateInterface $form_state) {
    $order = $this->entityTypeManager->getStorage('tec_order')->load($form_state->get('order_id'));
    if (!$order) {
      $this->messenger()->addError($this->t('The invoice was not removed. Try again.'));
      return;
    }

    $this->putInvoice($order, NULL);
    $this->messenger()->addStatus($this->t('Invoice removed from @order.', ['@order' => $order->label()]));
    $form_state->setRedirectUrl($this->backUrl());
  }

  /**
   * Point the order at a new scan, or at none, and drop the previous file.
   */
  protected function putInvoice(EntityInterface $order, ?FileInterface $file): void {
    $old = Purchasing::invoice($order);
    $order->set('field_tec_supplier_invoice', $file ? ['target_id' => $file->id()] : []);
    $order->save();

    if ($old && (!$file || (int) $old->id() !== (int) $file->id())) {
      $this->forgetFile($old, $order);
    }
  }

  /**
   * A scanner error is not kept. Unused files are deleted, not archived.
   */
  protected function forgetFile(FileInterface $file, EntityInterface $order): void {
    $this->fileUsage->delete($file, 'file', $order->getEntityTypeId(), $order->id());
    if (!$this->fileUsage->listUsage($file)) {
      $file->delete();
    }
  }

  /**
   * The screen that opened the dialog, or Purchase Control if it was typed in.
   */
  protected function backUrl(): Url {
    $destination = $this->getRequest()->query->get('destination');
    if (is_string($destination) && str_starts_with($destination, '/') && !str_starts_with($destination, '//')) {
      try {
        return Url::fromUserInput($destination);
      }
      catch (\InvalidArgumentException $e) {
        // A bad destination is treated as none, not as a way off the site.
      }
    }
    return Url::fromRoute('tec_production.purchase_queue');
  }

}
