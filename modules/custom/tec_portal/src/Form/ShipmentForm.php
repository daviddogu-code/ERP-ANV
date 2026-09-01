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
      'invoice' => [
        '#type' => 'link',
        '#title' => $this->t('Print invoice'),
        '#url' => Url::fromUri('internal:' . Shipment::invoicePath($tec_shipment)),
        '#attributes' => [
          'class' => ['button', 'tec-portal__proforma'],
          'target' => '_blank',
        ],
      ],
    ];

    $sold = Shipment::soldTo($tec_shipment);
    $ship = Shipment::shipTo($tec_shipment);
    $form['facts'] = [
      '#markup' => '<p class="tec-portal__help">'
        . $this->t('Date @date. Incoterms @incoterms. Sold to @sold. Ship to @ship. Type how many of each line go on this pickup. Remaining is the order minus what is already on other shipments.', [
          '@date' => Shipment::dateValue($tec_shipment) ?: '—',
          '@incoterms' => Shipment::incoterms($tec_shipment),
          '@sold' => $sold ? $sold->label() : '—',
          '@ship' => $ship ? $ship->label() : '—',
        ])
        . '</p>',
    ];

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
        'qty' => [
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

    if ($rows) {
      $form['actions'] = ['#type' => 'actions'];
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Save quantities'),
        '#button_type' => 'primary',
      ];
    }
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
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
    $id = (int) $form_state->get('shipment_id');
    $shipment = $this->entityTypeManager->getStorage(Shipment::TYPE)->load($id);
    if (!Shipment::isShipment($shipment)) {
      return;
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
    $this->messenger()->addStatus($this->t('Quantities saved. Packing list and invoice use the same lines.'));
    $form_state->setIgnoreDestination();
    $form_state->setRedirect('tec_portal.shipment', ['tec_shipment' => $shipment->id()]);
  }

}
