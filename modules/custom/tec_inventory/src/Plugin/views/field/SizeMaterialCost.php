<?php

namespace Drupal\tec_inventory\Plugin\views\field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\tec_inventory\MaterialCost;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * What a size's materials cost, as a column.
 *
 * There is no field to read: the figure is the BoM added up, and the BoM lives
 * in another entity, one row at a time. So this asks MaterialCost, which is the
 * same thing the foot of the BoM table asks. One arithmetic, two screens.
 *
 * The reason it is not a Views aggregation is that the sum is over a chain --
 * size to BoM line to material -- and the two numbers multiplied sit at
 * different ends of it. A SQL sum could be written, and then the day somebody
 * changes how a quantity is parsed there would be two rules for the same money.
 *
 * The cost of asking per row is a load per size, which on a catalogue of a
 * hundred sizes is a hundred queries. That is what preRender() is for: it warms
 * every BoM line and every material of the whole page in two loads, and the
 * per-row question then answers itself from memory.
 */
#[ViewsField("tec_size_material_cost")]
class SizeMaterialCost extends FieldPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');

    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * Nothing to add to the query: the figure is not in a column.
   */
  public function query() {
  }

  /**
   * {@inheritdoc}
   *
   * Brings the whole page's BoM into memory in two loads, so that the hundred
   * rows below do not each go to the database for the same kind of thing.
   */
  public function preRender(&$values) {
    $lineas = [];
    foreach ($values as $fila) {
      $talla = $this->sizeOf($fila);
      if (!$talla) {
        continue;
      }
      foreach ($talla->get(MaterialCost::BOM_FIELD) as $referencia) {
        $lineas[$referencia->target_id] = $referencia->target_id;
      }
    }
    if (!$lineas) {
      return;
    }

    $materiales = [];
    foreach ($this->entityTypeManager->getStorage('tec_inventory')->loadMultiple($lineas) as $linea) {
      if (!$linea->hasField(MaterialCost::MATERIAL_FIELD)) {
        continue;
      }
      foreach ($linea->get(MaterialCost::MATERIAL_FIELD) as $referencia) {
        $materiales[$referencia->target_id] = $referencia->target_id;
      }
    }
    if ($materiales) {
      $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($materiales);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $talla = $this->sizeOf($values);
    if (!$talla) {
      return '';
    }

    $suma = MaterialCost::ofSize($talla);
    // A size with no BoM has no cost yet. Zero would read as free.
    if (!$suma || $suma['lines'] === 0) {
      return '';
    }

    $cuanto = "\u{0E3F} " . number_format($suma['total'], 2);

    if (!$suma['unpriced']) {
      return [
        '#markup' => $cuanto,
        '#cache' => ['tags' => $suma['tags']],
      ];
    }

    // The asterisk is not decoration. A total with a material missing reads as a
    // product that is cheaper to make than it is, and on a page of a hundred
    // rows nobody is going to open each one to find out.
    return [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $cuanto . ' *',
      '#attributes' => [
        'class' => ['tec-material-cost__incomplete'],
        'title' => $this->t('Not counted, no cost yet: @which', [
          '@which' => implode(', ', $suma['unpriced']),
        ]),
      ],
      '#attached' => ['library' => ['tec_inventory/material_cost']],
      '#cache' => ['tags' => $suma['tags']],
    ];
  }

  /**
   * The size on this row, or NULL if the row is not one.
   *
   * The column is offered on products, and only a size variation has a BoM. A
   * row that is a product or a colour gets an empty cell rather than a zero.
   */
  private function sizeOf(ResultRow $values) {
    $entidad = $this->getEntity($values);

    return $entidad && $entidad->getEntityTypeId() === 'tec_product' && $entidad->bundle() === 'tec_size_variation'
      ? $entidad
      : NULL;
  }

}
