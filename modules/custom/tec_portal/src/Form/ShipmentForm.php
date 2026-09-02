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
use Drupal\tec_portal\Shipment;
use Drupal\tec_portal\TaxInvoice;
use Drupal\tec_production\LineItemDisplay;
use Drupal\tec_production\SalesStatus;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Load this shipment: quantities against remaining (/o/ship/{id}).
 *
 * Remaining is ordered minus already shipped on other shipments. One shipment
 * lists every confirmed sales line of this customer, so two orders of the
 * same company can go on the same paper.
 */
class ShipmentForm extends FormBase {

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
    return 'tec_portal_shipment_form';
  }

  /**
   * Page title: the shipment number.
   */
  public static function title($tec_shipment = NULL): string {
    if (is_numeric($tec_shipment) && Shipment::typesExist()) {
      $tec_shipment = \Drupal::entityTypeManager()->getStorage(Shipment::TYPE)->load((int) $tec_shipment);
    }
    if (Shipment::isShipment($tec_shipment)) {
      $label = trim((string) $tec_shipment->label());
      if ($label !== '') {
        return $label;
      }
    }
    return 'Shipment';
  }

  /**
   * Factory staff who can see this shipment. Not a portal customer.
   */
  public static function access(AccountInterface $account, $tec_shipment = NULL) {
    $companies = \Drupal::service('tec_portal.company');
    $result = AccessResult::allowedIf($companies->isFactory($account) && Shipment::typesExist())
      ->addCacheContexts(['user.roles']);
    if (is_numeric($tec_shipment)) {
      $tec_shipment = \Drupal::entityTypeManager()->getStorage(Shipment::TYPE)->load((int) $tec_shipment);
    }
    if (!Shipment::isShipment($tec_shipment)) {
      return AccessResult::forbidden()->addCacheContexts(['user.roles']);
    }
    $result->addCacheableDependency($tec_shipment);
    return $result->andIf($tec_shipment->access('view', $account, TRUE));
  }

  public function buildForm(array $form, FormStateInterface $form_state, $tec_shipment = NULL) {
    $id = (int) ($form_state->get('shipment_id') ?: ($tec_shipment ? $tec_shipment->id() : 0));
    if ($id) {
      $loaded = $this->entityTypeManager->getStorage(Shipment::TYPE)->load($id);
      if ($loaded) {
        $tec_shipment = $loaded;
      }
    }
    if (!Shipment::isShipment($tec_shipment)) {
      throw new NotFoundHttpException();
    }

    $form_state->set('shipment_id', (int) $tec_shipment->id());
    $company = Shipment::customer($tec_shipment);
    $rows = Shipment::loadRows(Shipment::customerId($tec_shipment), (int) $tec_shipment->id());
    $issued = TaxInvoice::ofShipment($tec_shipment);
    $locked = $issued !== NULL;

    $form['#tree'] = TRUE;
    $form['#attributes']['class'][] = 'tec-portal';
    $form['#attached']['library'][] = 'tec_portal/shipment_form';

    if ($company) {
      $form['back'] = [
        '#type' => 'link',
        '#title' => $this->t('Back to @company', ['@company' => $company->label()]),
        '#url' => $company->toUrl(),
        '#attributes' => ['class' => ['tec-portal__back']],
      ];
    }

    $form['meta'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['tec-portal__meta']],
      'title' => [
        '#markup' => '<strong>' . htmlspecialchars((string) $tec_shipment->label(), ENT_QUOTES) . '</strong>',
      ],
      'packing' => [
        '#type' => 'link',
        '#title' => $this->t('Print packing list'),
        '#url' => Url::fromUri('internal:' . Shipment::packingPath($tec_shipment)),
        '#attributes' => [
          'class' => ['button', 'tec-portal__proforma'],
          'target' => '_blank',
        ],
      ],
    ];
    if ($issued) {
      $form['meta']['invoice'] = [
        '#type' => 'link',
        '#title' => $this->t('Print invoice @number', [
          '@number' => TaxInvoice::formatNumber(TaxInvoice::number($issued)),
        ]),
        '#url' => Url::fromUri('internal:' . TaxInvoice::printPath($issued)),
        '#attributes' => [
          'class' => ['button', 'tec-portal__proforma'],
          'target' => '_blank',
          'rel' => 'noopener noreferrer',
        ],
      ];
    }

    $sold = Shipment::soldTo($tec_shipment);
    $ship = Shipment::shipTo($tec_shipment);
    if ($locked) {
      $form['facts'] = [
        '#markup' => '<p class="tec-portal__nothing">'
          . $this->t('Tax invoice @number is issued. Quantities cannot be changed. Print opens that paper. A mistake is a credit note later, not a rewrite.', [
            '@number' => TaxInvoice::formatNumber(TaxInvoice::number($issued)),
          ])
          . '</p>',
        '#allowed_tags' => ['p'],
      ];
    }
    else {
      $form['facts'] = [
        '#markup' => '<p class="tec-portal__help">'
          . $this->t('Date @date. Incoterms @incoterms. Sold to @sold. Ship to @ship. Type how many of each line go on this pickup. Remaining is the order minus what is already on other shipments. Save quantities as often as you need. Issue Tax invoice / receipt when this load is right: that spends the next tax-invoice number and freezes the paper.', [
            '@date' => Shipment::dateValue($tec_shipment) ?: '—',
            '@incoterms' => Shipment::incoterms($tec_shipment),
            '@sold' => $sold ? $sold->label() : '—',
            '@ship' => $ship ? $ship->label() : '—',
          ])
          . '</p>',
      ];
    }

    $form['shown'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['tec-ship__status-key'],
      ],
      'yes' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['tec-ship__status-row'],
        ],
        'label' => [
          '#markup' => '<span class="tec-ship__status-label">' . $this->t('Status shown on this screen:') . '</span>',
        ],
        'pills' => SalesStatus::pills([
          SalesStatus::PENDING_PAYMENT,
          SalesStatus::PROCESSING,
          SalesStatus::READY_FOR_PRODUCTION,
          SalesStatus::ON_PRODUCTION,
          SalesStatus::COMPLETED,
          SalesStatus::READY_FOR_COLLECTION,
          SalesStatus::SHIPPED,
        ], TRUE),
      ],
      'no' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['tec-ship__status-row'],
        ],
        'label' => [
          '#markup' => '<span class="tec-ship__status-label">' . $this->t('Status NOT shown on this screen:') . '</span>',
        ],
        'pills' => SalesStatus::pills([
          SalesStatus::OPEN,
          SalesStatus::CLOSED,
          SalesStatus::CANCELLED,
        ]),
      ],
    ];

    $rates = [];
    foreach ($rows as $row) {
      if ($row['order']->hasField('field_tec_vat_rate') && !$row['order']->get('field_tec_vat_rate')->isEmpty()) {
        $rates[(string) $row['order']->get('field_tec_vat_rate')->value] = TRUE;
      }
    }
    if (count($rates) > 1) {
      $form['vat_warn'] = [
        '#markup' => '<div class="tec-portal__warning">'
          . $this->t('These orders do not share the same VAT rate. One shipment should be one VAT logic. The invoice will still print each order’s stamped rate on the goods of that order.')
          . '</div>',
      ];
    }

    $form['lines'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Order'),
        $this->t('Product'),
        $this->t('Colour'),
        $this->t('Size'),
        ['data' => $this->t('Ordered'), 'class' => ['tec-portal__num']],
        ['data' => $this->t('Shipped'), 'class' => ['tec-portal__num']],
        ['data' => $this->t('Remaining'), 'class' => ['tec-portal__num']],
        ['data' => $this->t('This shipment'), 'class' => ['tec-portal__num']],
      ],
      '#empty' => $this->t('This customer has no confirmed sales lines with a quantity.'),
      '#attributes' => ['class' => ['tec-portal__table']],
    ];

    $allowed = [];
    foreach ($rows as $row) {
      $line = $row['line'];
      $lid = (int) $line->id();
      $allowed[$lid] = [
        'remaining' => $row['remaining'],
        'order_id' => (int) $row['order']->id(),
      ];
      $here = Shipment::qtyOnShipment($tec_shipment, $lid);
      $product = $line->hasField('field_tec_product') ? $line->get('field_tec_product')->entity : NULL;
      $size = $line->hasField('field_tec_size_variation') ? $line->get('field_tec_size_variation')->entity : NULL;
      $colour = LineItemDisplay::colorVariation($line);
      $form['lines'][$lid] = [
        'order' => ['#plain_text' => (string) $row['order']->label()],
        'product' => ['#plain_text' => $product ? (string) $product->label() : (string) $line->label()],
        'colour' => ['#plain_text' => LineItemDisplay::colorLabel($colour)],
        'size' => ['#plain_text' => LineItemDisplay::sizeLabel($size)],
        'ordered' => [
          '#plain_text' => number_format($row['ordered'], 0),
          '#wrapper_attributes' => ['class' => ['tec-portal__num']],
        ],
        'shipped' => [
          '#plain_text' => number_format($row['shipped'], 0),
          '#wrapper_attributes' => ['class' => ['tec-portal__num']],
        ],
        'remaining' => [
          '#plain_text' => number_format($row['remaining'], 0),
          '#wrapper_attributes' => ['class' => ['tec-portal__num']],
        ],
        'qty' => $locked
          ? [
            '#plain_text' => number_format($here, 0),
            '#wrapper_attributes' => ['class' => ['tec-portal__num']],
          ]
          : [
            '#type' => 'number',
            '#title' => $this->t('Quantity this shipment'),
            '#title_display' => 'invisible',
            '#min' => 0,
            '#max' => $row['remaining'],
            '#step' => 1,
            '#default_value' => $here,
            '#attributes' => ['class' => ['tec-portal__qty-box']],
            '#wrapper_attributes' => ['class' => ['tec-portal__qty', 'tec-portal__num']],
          ],
      ];
    }
    $form_state->set('allowed_lines', $allowed);
    $form_state->set('shipment_locked', $locked);

    if ($rows && !$locked) {
      $form['actions'] = ['#type' => 'actions'];
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Save quantities'),
        '#button_type' => 'primary',
      ];
      $form['actions']['issue'] = [
        '#type' => 'submit',
        '#value' => $this->t('Issue Tax invoice / receipt'),
        '#submit' => ['::submitIssue'],
        '#attributes' => [
          'formtarget' => '_blank',
          'onclick' => 'if (!confirm("Issue the Tax invoice / receipt for this dispatch? The number cannot be reused and quantities cannot be changed afterwards.")) { return false; } window.setTimeout(function () { window.location.reload(); }, 2000);',
        ],
      ];
    }
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->get('shipment_locked')) {
      return;
    }
    $allowed = $form_state->get('allowed_lines') ?: [];
    $values = $form_state->getValue('lines') ?: [];
    foreach ($allowed as $lid => $info) {
      $qty = (int) ($values[$lid]['qty'] ?? 0);
      if ($qty < 0) {
        $form_state->setError($form['lines'][$lid]['qty'], $this->t('Quantity cannot be negative.'));
      }
      if ($qty > (int) $info['remaining']) {
        $form_state->setError($form['lines'][$lid]['qty'], $this->t('This shipment cannot exceed remaining (@remaining).', [
          '@remaining' => $info['remaining'],
        ]));
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $shipment = $this->persistQuantities($form_state);
    if (!$shipment) {
      return;
    }
    $this->messenger()->addStatus($this->t('Quantities saved. Packing list uses these lines. Issue Tax invoice / receipt when this load is right.'));
    $this->stayOnShipment($form_state, (int) $shipment->id());
  }

  /**
   * Save quantities, then spend the next 036x for this dispatch.
   */
  public function submitIssue(array &$form, FormStateInterface $form_state) {
    $shipment = $this->persistQuantities($form_state);
    if (!$shipment) {
      return;
    }
    try {
      $invoice = TaxInvoice::issueForShipment($shipment);
      $path = TaxInvoice::printPath($invoice);
      if ($path !== '') {
        $form_state->setIgnoreDestination();
        $form_state->setRedirectUrl(Url::fromUri('internal:' . $path));
        return;
      }
    }
    catch (\InvalidArgumentException $e) {
      $this->messenger()->addError($e->getMessage());
    }
    catch (\RuntimeException $e) {
      $this->messenger()->addError($e->getMessage());
    }
    $this->stayOnShipment($form_state, (int) $shipment->id());
  }

  /**
   * Write ship-item quantities. Does not issue a tax invoice.
   */
  private function persistQuantities(FormStateInterface $form_state): ?EntityInterface {
    $id = (int) $form_state->get('shipment_id');
    $shipment = $this->entityTypeManager->getStorage(Shipment::TYPE)->load($id);
    if (!Shipment::isShipment($shipment)) {
      return NULL;
    }
    if (TaxInvoice::shipmentIsLocked($shipment)) {
      $this->messenger()->addWarning($this->t('This dispatch already has a tax invoice. Quantities cannot be changed.'));
      return $shipment;
    }
    $allowed = $form_state->get('allowed_lines') ?: [];
    $values = $form_state->getValue('lines') ?: [];
    $existing = [];
    foreach (Shipment::itemEntities($shipment) as $item) {
      $lid = 0;
      if ($item->hasField(Shipment::SOURCE_LINE_FIELD) && !$item->get(Shipment::SOURCE_LINE_FIELD)->isEmpty()) {
        $lid = (int) $item->get(Shipment::SOURCE_LINE_FIELD)->target_id;
      }
      if ($lid) {
        $existing[$lid] = $item;
      }
    }
    $storage = $this->entityTypeManager->getStorage(Shipment::ITEM_TYPE);
    foreach ($allowed as $lid => $info) {
      $qty = (int) ($values[$lid]['qty'] ?? 0);
      $item = $existing[$lid] ?? NULL;
      if ($qty < 1) {
        if ($item) {
          $item->delete();
        }
        continue;
      }
      if ($item) {
        $item->set(Shipment::QTY_FIELD, $qty);
        $item->save();
        continue;
      }
      $created = $storage->create([
        'type' => Shipment::ITEM_BUNDLE,
        'title' => 'SHIP-' . $shipment->id() . '-' . $lid,
        Shipment::SHIPMENT_FIELD => (int) $shipment->id(),
        Shipment::ORDER_FIELD => (int) $info['order_id'],
        Shipment::SOURCE_LINE_FIELD => $lid,
        Shipment::QTY_FIELD => $qty,
      ]);
      $created->save();
    }
    $shipment = $this->entityTypeManager->getStorage(Shipment::TYPE)->load($id);
    if (Shipment::isShipment($shipment)) {
      Shipment::refreshTitle($shipment);
    }
    return $shipment;
  }

  private function stayOnShipment(FormStateInterface $form_state, int $id): void {
    $form_state->setIgnoreDestination();
    $form_state->setRedirect('tec_portal.shipment', ['tec_shipment' => $id]);
  }

}
