<?php

namespace Drupal\tec_production\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Register a +/- stock mutation for one material from the /stock board.
 *
 * Creates a tec_inventory_transaction entity; the existing ECA models
 * ("Stock manager") append it to the material and update
 * field_tec_stock_level, exactly like the "Add new stock resolutions"
 * widget on the material edit form.
 */
class StockAdjustForm extends FormBase {

  protected EntityTypeManagerInterface $entityTypeManager;

  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  public function getFormId() {
    return 'tec_production_stock_adjust_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?TermInterface $taxonomy_term = NULL) {
    if (!$taxonomy_term || $taxonomy_term->bundle() !== 'tec_inventory') {
      throw new NotFoundHttpException();
    }
    $form_state->set('material_tid', (int) $taxonomy_term->id());

    $stock = $taxonomy_term->hasField('field_tec_stock_level') && !$taxonomy_term->get('field_tec_stock_level')->isEmpty()
      ? (float) $taxonomy_term->get('field_tec_stock_level')->value
      : 0.0;
    $unit = '';
    if ($taxonomy_term->hasField('field_tec_uos') && !$taxonomy_term->get('field_tec_uos')->isEmpty()) {
      $unit_term = $taxonomy_term->get('field_tec_uos')->entity;
      $unit = $unit_term ? (string) $unit_term->label() : '';
    }

    $form['#attached']['library'][] = 'tec_production/stock_control';

    $stock_display = rtrim(rtrim(number_format($stock, 2, '.', ','), '0'), '.');
    $form['material'] = [
      '#markup' => '<div class="tec-stock-adjust__head">'
        . '<div class="tec-stock-adjust__name">' . Html::escape($taxonomy_term->label()) . '</div>'
        . '<div class="tec-stock-adjust__current">' . $this->t('Current stock')
        . ' <span class="tec-stock-adjust__num">' . $stock_display . '</span>'
        . ($unit !== '' ? ' <span class="tec-stock-adjust__unit">' . Html::escape($unit) . '</span>' : '')
        . '</div></div>',
    ];

    $form['delta'] = [
      '#type' => 'number',
      '#title' => $this->t('Adjustment (+/-)'),
      '#step' => 'any',
      '#required' => TRUE,
      '#description' => $this->t('Positive number adds to stock, negative subtracts. In @unit.', [
        '@unit' => $unit !== '' ? $unit : $this->t('the inventory unit (UoS)'),
      ]),
      '#attributes' => ['autofocus' => 'autofocus', 'class' => ['tec-stock-adjust__delta']],
    ];

    $form['note'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Note'),
      '#required' => FALSE,
      '#maxlength' => 255,
      '#description' => $this->t('Optional: reason for the adjustment.'),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Register mutation'),
      '#button_type' => 'primary',
    ];
    // Inside the modal, dialog-cancel just closes it; standalone it is a
    // normal link back to the board.
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('tec_production.stock'),
      '#attributes' => ['class' => ['button', 'dialog-cancel']],
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ((float) $form_state->getValue('delta') == 0.0) {
      $form_state->setErrorByName('delta', $this->t('The adjustment cannot be 0.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $tid = (int) $form_state->get('material_tid');
    $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($tid);
    if (!$term) {
      $this->messenger()->addError($this->t('Material not found.'));
      return;
    }
    $delta = (float) $form_state->getValue('delta');
    $note = trim((string) $form_state->getValue('note'));

    $transaction = $this->entityTypeManager->getStorage('tec_inventory')->create([
      'type' => 'tec_inventory_transaction',
      'title' => sprintf('%s %+g (stock board)', $term->label(), $delta),
      'uid' => $this->currentUser()->id(),
      'field_tec_inventory' => $tid,
      'field_tec_quantity' => $delta,
      'field_tec_mutation_note' => $note !== '' ? $note : NULL,
    ]);
    $transaction->save();

    $this->messenger()->addStatus($this->t('Mutation @delta registered for %material.', [
      '@delta' => ($delta > 0 ? '+' : '') . $delta,
      '%material' => $term->label(),
    ]));
    $form_state->setRedirect('tec_production.stock');
  }

}
