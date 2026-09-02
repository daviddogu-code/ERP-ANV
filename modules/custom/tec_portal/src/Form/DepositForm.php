<?php

namespace Drupal\tec_portal\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\tec_portal\CustomerCompany;
use Drupal\tec_portal\InvoiceList;
use Drupal\tec_portal\PortalOrder;
use Drupal\tec_portal\TaxInvoice;
use Drupal\tec_production\Company;
use Drupal\tec_production\Vat;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Record a deposit on /o/order/{id}/deposit. Issue invoice is a separate act.
 *
 * Amount is what arrived in the bank, VAT included. Thailand splits 7% out of
 * that figure. Export stores the money and does not spend a 036x.
 */
class DepositForm extends FormBase {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected CustomerCompany $companies,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('tec_portal.company'),
    );
  }

  public function getFormId() {
    return 'tec_portal_deposit_form';
  }

  public static function title($tec_order = NULL): string {
    if (is_numeric($tec_order)) {
      $tec_order = \Drupal::entityTypeManager()->getStorage('tec_order')->load((int) $tec_order);
    }
    if ($tec_order) {
      $label = trim((string) $tec_order->label());
      if ($label !== '') {
        return 'Deposit ' . $label;
      }
    }
    return 'Record deposit';
  }

  /**
   * Factory staff, confirmed sales order. Not a portal customer.
   */
  public static function access(AccountInterface $account, $tec_order = NULL, $tec_invoice = NULL) {
    $companies = \Drupal::service('tec_portal.company');
    $result = AccessResult::allowedIf($companies->isFactory($account) && TaxInvoice::typesExist())
      ->addCacheContexts(['user.roles']);
    if (is_numeric($tec_order)) {
      $tec_order = \Drupal::entityTypeManager()->getStorage('tec_order')->load((int) $tec_order);
    }
    if (!$tec_order instanceof EntityInterface
      || $tec_order->getEntityTypeId() !== 'tec_order'
      || $tec_order->bundle() !== 'tec_sales_order'
      || !PortalOrder::hasProforma($tec_order)) {
      return AccessResult::forbidden()->addCacheContexts(['user.roles']);
    }
    $result->addCacheableDependency($tec_order);
    if ($tec_invoice) {
      if (is_numeric($tec_invoice) && TaxInvoice::typesExist()) {
        $tec_invoice = \Drupal::entityTypeManager()->getStorage(TaxInvoice::TYPE)->load((int) $tec_invoice);
      }
      if (!TaxInvoice::isInvoice($tec_invoice)
        || !TaxInvoice::isDeposit($tec_invoice)
        || (int) ($tec_invoice->hasField(TaxInvoice::ORDER_FIELD) ? $tec_invoice->get(TaxInvoice::ORDER_FIELD)->target_id : 0) !== (int) $tec_order->id()) {
        return AccessResult::forbidden()->addCacheContexts(['user.roles']);
      }
      $result->addCacheableDependency($tec_invoice);
    }
    return $result->andIf($tec_order->access('view', $account, TRUE));
  }

  public function buildForm(array $form, FormStateInterface $form_state, $tec_order = NULL, $tec_invoice = NULL) {
    $id = (int) ($form_state->get('order_id') ?: ($tec_order ? $tec_order->id() : 0));
    if ($id) {
      $loaded = $this->entityTypeManager->getStorage('tec_order')->load($id);
      if ($loaded) {
        $tec_order = $loaded;
      }
    }
    if (!$tec_order || $tec_order->getEntityTypeId() !== 'tec_order') {
      throw new NotFoundHttpException();
    }
    $invoice_id = (int) ($form_state->get('invoice_id') ?: ($tec_invoice ? $tec_invoice->id() : 0));
    if ($invoice_id && TaxInvoice::typesExist()) {
      $tec_invoice = $this->entityTypeManager->getStorage(TaxInvoice::TYPE)->load($invoice_id);
    }
    if ($tec_invoice && !TaxInvoice::isInvoice($tec_invoice)) {
      $tec_invoice = NULL;
    }

    $form_state->set('order_id', (int) $tec_order->id());
    $form_state->set('invoice_id', $tec_invoice ? (int) $tec_invoice->id() : 0);

    $issued = $tec_invoice && TaxInvoice::isIssued($tec_invoice);
    $rate = TaxInvoice::orderVatRate($tec_order);
    $needs_tax = $rate > 0;

    $form['#tree'] = TRUE;
    $form['#attributes']['class'][] = 'tec-portal';
    $form['#attached']['library'][] = 'tec_portal/shipment_form';

    $form['back'] = [
      '#type' => 'link',
      '#title' => $this->t('Back to @order', ['@order' => $tec_order->label()]),
      '#url' => Url::fromRoute('tec_portal.factory_order', ['tec_order' => $tec_order->id()]),
      '#attributes' => ['class' => ['tec-portal__back']],
    ];

    if (Company::taxId() === '') {
      $form['tax_warn'] = [
        '#markup' => '<div class="tec-portal__warning">'
          . $this->t('Company tax ID is empty. Fill it under Configuration → TEC → Company settings before issuing a tax invoice.')
          . '</div>',
      ];
    }

    if ($needs_tax) {
      $help = $this->t('Amount is what arrived in the bank, VAT included. @rate% is taken out of that figure. Save records the money. Issue Tax invoice / receipt spends the next tax-invoice number and freezes the paper. Print does not issue a number.', [
        '@rate' => Vat::formatRate($rate),
      ]);
    }
    else {
      $help = $this->t('Amount is what arrived in the bank. This order is export (0% VAT), so recording the deposit does not issue a tax invoice. The 036x series is used when this dispatch is invoiced.');
    }
    $form['help'] = [
      '#markup' => '<div class="tec-portal__help">' . $help . '</div>',
    ];

    $form['date'] = [
      '#type' => 'date',
      '#title' => $this->t('Date'),
      '#default_value' => $tec_invoice ? (TaxInvoice::dateValue($tec_invoice) ?: date('Y-m-d')) : date('Y-m-d'),
      '#required' => TRUE,
      '#disabled' => $issued,
    ];
    $form['amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Amount received'),
      '#default_value' => $tec_invoice ? TaxInvoice::grossOf($tec_invoice) : NULL,
      '#required' => TRUE,
      '#min' => 0.01,
      '#step' => 0.01,
      '#disabled' => $issued,
      '#description' => $needs_tax
        ? $this->t('Bank total. Example: 54570 becomes 51000 + 3570 VAT.')
        : $this->t('Bank total.'),
    ];
    $form['note'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Note'),
      '#default_value' => $tec_invoice ? TaxInvoice::noteOf($tec_invoice) : '',
      '#maxlength' => 255,
      '#disabled' => $issued,
      '#description' => $this->t('Bank reference, optional.'),
    ];

    if ($tec_invoice) {
      $form['split'] = [
        '#markup' => '<p class="tec-portal__help">'
          . $this->t('Base @net. VAT @vat. Total @gross.', [
            '@net' => TaxInvoice::money(TaxInvoice::netOf($tec_invoice)),
            '@vat' => TaxInvoice::money(TaxInvoice::vatOf($tec_invoice)),
            '@gross' => TaxInvoice::money(TaxInvoice::grossOf($tec_invoice)),
          ])
          . '</p>',
      ];
    }

    $form['actions'] = ['#type' => 'actions'];
    if (!$issued) {
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
        '#button_type' => 'primary',
      ];
      if ($needs_tax) {
        $form['actions']['issue'] = [
          '#type' => 'submit',
          '#value' => $this->t('Issue Tax invoice / receipt'),
          '#submit' => ['::submitIssue'],
          '#attributes' => [
            'onclick' => 'return confirm("Issue this Tax invoice / receipt? The number cannot be reused.");',
          ],
        ];
      }
    }
    else {
      $path = TaxInvoice::printPath($tec_invoice);
      if ($path !== '') {
        $form['actions']['print'] = [
          '#type' => 'link',
          '#title' => $this->t('Print'),
          '#url' => Url::fromUri('internal:' . $path),
          '#attributes' => [
            'class' => ['button', 'button--primary'],
            'target' => '_blank',
          ],
        ];
      }
    }

    $form['existing'] = (new InvoiceList())->orderPage($tec_order, FALSE);
    $form['existing']['#weight'] = 20;

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->get('invoice_id')) {
      $invoice = $this->entityTypeManager->getStorage(TaxInvoice::TYPE)->load((int) $form_state->get('invoice_id'));
      if (TaxInvoice::isIssued($invoice)) {
        return;
      }
    }
    $amount = (float) $form_state->getValue('amount');
    if ($amount <= 0) {
      $form_state->setErrorByName('amount', $this->t('Amount must be greater than zero.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $invoice = $this->persist($form_state);
    if (!$invoice) {
      return;
    }
    $this->messenger()->addStatus($this->t('Deposit recorded. Issue Tax invoice / receipt when the paper should take the next number.'));
    $this->goToDeposit($form_state, $invoice);
  }

  /**
   * Save, then spend the next 036x (Thailand only).
   */
  public function submitIssue(array &$form, FormStateInterface $form_state) {
    $invoice = $this->persist($form_state);
    if (!$invoice) {
      return;
    }
    try {
      $issued = TaxInvoice::issueDeposit($invoice);
      $this->messenger()->addStatus($this->t('Tax invoice @number issued.', [
        '@number' => TaxInvoice::formatNumber(TaxInvoice::number($issued)),
      ]));
      $this->goToDeposit($form_state, $issued);
    }
    catch (\InvalidArgumentException $e) {
      $this->messenger()->addError($e->getMessage());
      $this->goToDeposit($form_state, $invoice);
    }
    catch (\RuntimeException $e) {
      $this->messenger()->addError($e->getMessage());
      $this->goToDeposit($form_state, $invoice);
    }
  }

  /**
   * Write the recorded deposit without spending a number.
   */
  private function persist(FormStateInterface $form_state): ?EntityInterface {
    $order = $this->entityTypeManager->getStorage('tec_order')->load((int) $form_state->get('order_id'));
    if (!$order || $order->bundle() !== 'tec_sales_order') {
      $this->messenger()->addError($this->t('This order is gone.'));
      return NULL;
    }
    $existing = NULL;
    $invoice_id = (int) $form_state->get('invoice_id');
    if ($invoice_id) {
      $existing = $this->entityTypeManager->getStorage(TaxInvoice::TYPE)->load($invoice_id);
      if (TaxInvoice::isIssued($existing)) {
        return $existing;
      }
    }
    try {
      return TaxInvoice::recordDeposit(
        $order,
        (string) $form_state->getValue('date'),
        (float) $form_state->getValue('amount'),
        trim((string) $form_state->getValue('note')),
        $existing
      );
    }
    catch (\InvalidArgumentException $e) {
      $this->messenger()->addError($e->getMessage());
      return NULL;
    }
  }

  private function goToDeposit(FormStateInterface $form_state, EntityInterface $invoice): void {
    $order = TaxInvoice::orderOf($invoice);
    $form_state->setIgnoreDestination();
    if ($order) {
      $form_state->setRedirect('tec_portal.deposit_edit', [
        'tec_order' => $order->id(),
        'tec_invoice' => $invoice->id(),
      ]);
      return;
    }
    $form_state->setRedirect('tec_portal.invoice_list');
  }

}
