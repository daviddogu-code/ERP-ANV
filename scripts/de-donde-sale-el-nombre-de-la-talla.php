<?php

/**
 * @file
 * Que es exactamente una talla y de donde sale el nombre que se ve en pantalla.
 *
 * En el formulario de producto, la columna Sizes ensena "PRUEBA producto - Black -
 * XXS", y ese texto no aparece en el vocabulario de tallas. O sea que lo que se
 * ensena ahi no es la talla: es otra cosa que lleva la talla dentro. Esto lo
 * aclara, y de paso ensena donde estan los accesos rapidos que ya existen, para
 * saber donde encaja el de tallas.
 *
 * Uso: drush php:script scripts/de-donde-sale-el-nombre-de-la-talla
 */

$gestor = \Drupal::entityTypeManager();

echo "\n";
echo str_repeat('=', 82) . "\n";
echo " Los tres tipos de ficha de producto\n";
echo str_repeat('=', 82) . "\n\n";

foreach (\Drupal::service('entity_type.bundle.info')->getBundleInfo('tec_product') as $id => $info) {
  $cuantas = $gestor->getStorage('tec_product')->getQuery()
    ->accessCheck(FALSE)->condition('type', $id)->count()->execute();
  printf("  %-22s %-24s %s fichas\n", $id, $info['label'], $cuantas);
}

echo "\n";
echo str_repeat('=', 82) . "\n";
echo " El producto 887, de arriba abajo\n";
echo str_repeat('=', 82) . "\n\n";

$producto = $gestor->getStorage('tec_product')->load(887);
printf("  producto %s: \"%s\" (tipo %s)\n", $producto->id(), $producto->label(), $producto->bundle());

/**
 * Los campos de referencia que cuelgan de una ficha, con lo que apuntan.
 */
$hijos = static function ($entidad): array {
  // El vocabulario de un termino tambien es una referencia, pero es configuracion
  // y no tiene campos: bajar por ahi revienta y no lleva a ninguna parte.
  if (!$entidad instanceof \Drupal\Core\Entity\FieldableEntityInterface) {
    return [];
  }
  $salida = [];
  foreach ($entidad->getFields() as $nombre => $campo) {
    $definicion = $campo->getFieldDefinition();
    if ($definicion->getType() !== 'entity_reference' || $campo->isEmpty()) {
      continue;
    }
    if (in_array($nombre, ['type', 'uid', 'revision_uid', 'vid', 'parent'], TRUE)) {
      continue;
    }
    foreach ($campo as $trozo) {
      if ($trozo->entity) {
        $salida[] = [$nombre, $definicion->getLabel(), $trozo->entity];
      }
    }
  }
  return $salida;
};

foreach ($hijos($producto) as [$campo, $etiqueta, $hijo]) {
  printf(
    "      %-26s %-22s -> %s \"%s\"\n",
    $campo,
    $etiqueta,
    $hijo->getEntityTypeId() . ':' . $hijo->bundle(),
    $hijo->label()
  );
  // Un nivel mas, que es donde vive la talla.
  foreach ($hijos($hijo) as [$campoNieto, $etiquetaNieto, $nieto]) {
    printf(
      "          %-22s %-22s -> %s \"%s\"\n",
      $campoNieto,
      $etiquetaNieto,
      $nieto->getEntityTypeId() . ':' . $nieto->bundle(),
      $nieto->label()
    );
    foreach ($hijos($nieto) as [$campoBisnieto, $etiquetaBisnieto, $bisnieto]) {
      printf(
        "              %-18s %-22s -> %s \"%s\"\n",
        $campoBisnieto,
        $etiquetaBisnieto,
        $bisnieto->getEntityTypeId() . ':' . $bisnieto->bundle(),
        $bisnieto->label()
      );
    }
  }
}

echo "\n";
echo str_repeat('=', 82) . "\n";
echo " Que hay de verdad en el vocabulario de tallas\n";
echo str_repeat('=', 82) . "\n\n";

$terminos = $gestor->getStorage('taxonomy_term');
$tallas = $terminos->loadByProperties(['vid' => 'tec_sizes']);
printf("  %d terminos:\n      ", count($tallas));
echo implode(', ', array_map(static fn($t) => $t->label(), $tallas)) . "\n";

echo "\n";
echo str_repeat('=', 82) . "\n";
echo " Los accesos rapidos que ya existen\n";
echo str_repeat('=', 82) . "\n\n";

echo "  Enlaces de menu:\n";
foreach ($gestor->getStorage('menu_link_content')->loadMultiple() as $enlace) {
  printf(
    "      %-26s %-34s menu %s, peso %s%s\n",
    mb_strimwidth($enlace->label(), 0, 26, '...'),
    $enlace->get('link')->uri,
    $enlace->getMenuName(),
    $enlace->getWeight(),
    $enlace->isEnabled() ? '' : ' (apagado)'
  );
}

if ($gestor->hasDefinition('shortcut')) {
  echo "\n  Atajos:\n";
  $atajos = $gestor->getStorage('shortcut')->loadMultiple();
  if (!$atajos) {
    echo "      ninguno\n";
  }
  foreach ($atajos as $atajo) {
    printf(
      "      %-26s %-34s conjunto %s\n",
      mb_strimwidth($atajo->label(), 0, 26, '...'),
      $atajo->get('link')->uri,
      $atajo->bundle()
    );
  }
}

echo "\n";
