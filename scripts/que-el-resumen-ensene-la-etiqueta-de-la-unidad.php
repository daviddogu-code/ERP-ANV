<?php

/**
 * @file
 * Deja que la pantalla maestra del resumen de material ensene la etiqueta de la
 * unidad de consumo en lugar del identificador del termino.
 *
 *   php vendor/bin/drush scr scripts/que-el-resumen-ensene-la-etiqueta-de-la-unidad.php
 *   php vendor/bin/drush scr scripts/que-el-resumen-ensene-la-etiqueta-de-la-unidad.php -- --de-verdad
 *
 * Sin --de-verdad no cambia nada: dice como esta, que haria y como quedaria.
 * Con --de-verdad guarda la vista, y antes deja una copia del YAML de como
 * estaba en backups/.
 *
 * QUE ESTA MAL. La pantalla `default` de `tec_order_material_calculation_summary`
 * pone `2, 706` en la columna UoU, donde deberia poner `Centimeter (cm)`. No son
 * dos numeros: es uno solo, el 2706, que es el identificador del termino
 * `Centimeter (cm)`, escrito con separador de miles. El separador es el que la
 * columna tiene puesto para separar valores multiples, `', '`, reaprovechado
 * como separador de miles.
 *
 * POR QUE PASA. La columna tiene la agregacion del nucleo puesta en `sum` en vez
 * de `group`, y esa pantalla es la unica de la vista con la agregacion
 * encendida. Cuando la agregacion de una columna no es "agrupar", Views cambia
 * el manejador de la columna por el numerico, y el manejador numerico no sabe
 * nada de formateadores: el `entity_reference_label` que la columna tiene
 * configurado no se usa nunca. La consulta pide
 * `SUM(field_tec_unit_use_target_id)`, cada material aparece una sola vez, y la
 * suma de un solo identificador es el identificador. De ahi el numero.
 *
 * QUE SE HACE. Poner `group_type: group` en esa columna, y nada mas. Con
 * `group`, Views deja el manejador de campo normal, usa el formateador y ensena
 * la etiqueta.
 *
 * POR QUE ES SEGURO, que en esta vista es la pregunta importante: de ella
 * cuelgan las pantallas de pedido de venta, y ya se cayeron una vez. La pantalla
 * de bloque tiene su propia lista de campos (`defaults: fields: false`), asi que
 * un cambio en la lista de campos del maestro no la toca. Lo que la pantalla de
 * bloque SI hereda es el estilo, y el estilo no se toca aqui. Ademas el campo es
 * de un solo valor, asi que meterlo en el GROUP BY no puede partir una fila en
 * dos. Las tres cosas se comprueban antes de guardar, y si alguna no se cumple
 * el guion no guarda.
 *
 * Y despues de guardar se vuelven a dibujar las dos pantallas y se comparan
 * celda por celda con como estaban antes. Si la pantalla de bloque cambia una
 * sola celda, se avisa a gritos: significaria que se ha tocado algo heredado.
 */

use Drupal\views\Entity\View;
use Drupal\views\Views;

const LA_VISTA = 'tec_order_material_calculation_summary';
const LA_PANTALLA = 'default';
const LAS_HIJAS = ['block_1'];
const LA_COLUMNA = 'field_tec_unit_use';
const LA_AGREGACION_BUENA = 'group';

$deVerdad = FALSE;
foreach ((array) ($extra ?? []) as $argumento) {
  if (in_array($argumento, ['--de-verdad', 'de-verdad'], TRUE)) {
    $deVerdad = TRUE;
  }
}

$cambiador = \Drupal::service('account_switcher');
$cambiador->switchTo(\Drupal::entityTypeManager()->getStorage('user')->load(1));

echo "\n";
echo str_repeat('=', 86) . "\n";
echo $deVerdad
  ? " DE VERDAD: se guarda la vista\n"
  : " ENSAYO: solo se dice como quedaria. Anade -- --de-verdad para guardarlo\n";
echo str_repeat('=', 86) . "\n";

// ---------------------------------------------------------------------------
// Guardias.
// ---------------------------------------------------------------------------
echo "\n  --- guardias ---\n\n";

$abortar = [];

$vista = View::load(LA_VISTA);
if (!$vista) {
  printf("    no existe la vista %s\n\n", LA_VISTA);
  return;
}
// La vista del inventario la esta cambiando otra tarea; ni por descuido.
if (LA_VISTA === 'tec_inventory') {
  echo "    NO: tec_inventory la esta tocando otra tarea\n\n";
  return;
}
printf("    la vista %s existe\n", LA_VISTA);

$displays = $vista->get('display');

if (!isset($displays[LA_PANTALLA])) {
  $abortar[] = sprintf('la vista no tiene la pantalla %s', LA_PANTALLA);
}
$campo = $displays[LA_PANTALLA]['display_options']['fields'][LA_COLUMNA] ?? NULL;
if ($campo === NULL) {
  $abortar[] = sprintf('la pantalla %s no tiene la columna %s', LA_PANTALLA, LA_COLUMNA);
}
else {
  printf("    la columna %s esta en la pantalla %s\n", LA_COLUMNA, LA_PANTALLA);

  // Que este mal de la manera que se espera. Si ya esta en `group`, no hay nada
  // que hacer y decirlo es mejor que guardar otra vez lo mismo.
  $comoEsta = $campo['group_type'] ?? 'group';
  if ($comoEsta === LA_AGREGACION_BUENA) {
    printf("\n    la columna ya esta en '%s'. No hay nada que arreglar.\n\n", LA_AGREGACION_BUENA);
    return;
  }
  printf("    la columna tiene la agregacion en '%s', que es lo que se viene a arreglar\n", $comoEsta);

  // El formateador tiene que ser el de la etiqueta: es lo que va a ensenar el
  // nombre del termino en cuanto Views vuelva a usarlo.
  $formateador = $campo['type'] ?? '';
  if ($formateador !== 'entity_reference_label') {
    $abortar[] = sprintf('el formateador de la columna es %s y se esperaba entity_reference_label', $formateador);
  }
  else {
    echo "    el formateador ya es entity_reference_label; solo hay que dejar que se use\n";
  }
}

// La pantalla maestra tiene que ser la unica con la agregacion encendida, o el
// cambio significaria otra cosa en las demas.
$agregacionMaestra = !empty($displays[LA_PANTALLA]['display_options']['group_by']);
printf("    la pantalla %s tiene la agregacion del nucleo %s\n", LA_PANTALLA, $agregacionMaestra ? 'encendida' : 'apagada');

// Y las hijas tienen que tener su propia lista de campos. Es la comprobacion que
// impide que el arreglo se cuele en lo que usa la fabrica.
foreach (LAS_HIJAS as $hija) {
  $suyos = $displays[$hija]['display_options']['defaults']['fields'] ?? TRUE;
  if ($suyos !== FALSE) {
    $abortar[] = sprintf('la pantalla %s HEREDA la lista de campos del maestro: el cambio se le colaria', $hija);
  }
  else {
    printf("    la pantalla %s tiene su propia lista de campos: el cambio no la alcanza\n", $hija);
  }
}

// El campo, de un solo valor. Si admitiera varios, meterlo en el GROUP BY podria
// partir una fila en dos y cambiar la cuenta de materiales.
$almacenCampo = \Drupal::service('entity_field.manager')
  ->getFieldStorageDefinitions('taxonomy_term')[LA_COLUMNA] ?? NULL;
$cardinalidad = $almacenCampo ? $almacenCampo->getCardinality() : NULL;
if ($cardinalidad !== 1) {
  $abortar[] = sprintf('%s admite %s valores; con mas de uno el GROUP BY podria partir filas', LA_COLUMNA, $cardinalidad ?? '?');
}
else {
  printf("    %s es de un solo valor: el GROUP BY no puede partir filas\n", LA_COLUMNA);
}

if ($abortar) {
  echo "\n  NO SE SIGUE:\n";
  foreach ($abortar as $motivo) {
    echo '    - ' . $motivo . "\n";
  }
  echo "\n";
  return;
}

// ---------------------------------------------------------------------------
// 1. Como se dibuja ahora.
// ---------------------------------------------------------------------------
echo "\n  --- 1. como se dibuja ahora ---\n\n";

$pedido = pedidoDeVenta();
printf("    pedido de venta con el que se prueba: %s\n", $pedido ?? 'NO HAY');

$antes = [];
foreach (array_merge([LA_PANTALLA], LAS_HIJAS) as $pantalla) {
  $antes[$pantalla] = celdas($pantalla, $pedido);
  ensenaLasCeldas($pantalla, $antes[$pantalla]);
}

// ---------------------------------------------------------------------------
// 2. La copia de la vista de antes.
// ---------------------------------------------------------------------------
echo "\n  --- 2. copia de la vista de antes ---\n\n";

$copia = sprintf(
  '%s/backups/views.view.%s.antes-de-las-etiquetas-%s.yml',
  \Drupal::root(),
  LA_VISTA,
  date('Y-m-d')
);
file_put_contents(
  $copia,
  \Symfony\Component\Yaml\Yaml::dump(\Drupal::config('views.view.' . LA_VISTA)->getRawData(), 8, 2)
);
printf(
  "    escrita: backups/%s (%s bytes)\n",
  basename($copia),
  number_format(filesize($copia))
);

// ---------------------------------------------------------------------------
// 3. Lo que se cambia.
// ---------------------------------------------------------------------------
echo "\n  --- 3. lo que se cambia ---\n\n";

printf(
  "    display.%s.display_options.fields.%s.group_type:  '%s'  ->  '%s'\n",
  LA_PANTALLA,
  LA_COLUMNA,
  $displays[LA_PANTALLA]['display_options']['fields'][LA_COLUMNA]['group_type'] ?? 'group',
  LA_AGREGACION_BUENA
);
echo "\n    y nada mas: ni el estilo, ni las demas columnas, ni las pantallas hijas\n";

if (!$deVerdad) {
  echo "\n" . str_repeat('=', 86) . "\n";
  echo " SE HARIA. Anade -- --de-verdad para guardarlo\n";
  echo str_repeat('=', 86) . "\n\n";
  return;
}

// ---------------------------------------------------------------------------
// 4. Guardar.
// ---------------------------------------------------------------------------
echo "\n  --- 4. guardar ---\n\n";

// Se copia el arbol entero, se cambia la hoja y se vuelve a poner. Sin
// referencias de PHP: en este proyecto ya se han creado con `&` entradas a medias
// en rutas que no existian -un `style` sin su clave `type`- y eso revienta la
// vista con "Undefined array key type" al actualizarla.
$displays = $vista->get('display');
if (!isset($displays[LA_PANTALLA]['display_options']['fields'][LA_COLUMNA])) {
  echo "    la ruta de la columna ya no esta donde se esperaba; no se guarda nada\n\n";
  return;
}
$displays[LA_PANTALLA]['display_options']['fields'][LA_COLUMNA]['group_type'] = LA_AGREGACION_BUENA;
$vista->set('display', $displays);
$vista->save();

echo "    guardado\n";

// ---------------------------------------------------------------------------
// 5. Como queda, leido de nuevo.
// ---------------------------------------------------------------------------
echo "\n  --- 5. como queda ---\n\n";

\Drupal::entityTypeManager()->getStorage('view')->resetCache([LA_VISTA]);
Views::viewsData()->clear();
drupal_static_reset();

$releido = View::load(LA_VISTA)->get('display');
printf(
  "    group_type de %s en %s: '%s'\n",
  LA_COLUMNA,
  LA_PANTALLA,
  $releido[LA_PANTALLA]['display_options']['fields'][LA_COLUMNA]['group_type'] ?? '(no esta)'
);

$despues = [];
foreach (array_merge([LA_PANTALLA], LAS_HIJAS) as $pantalla) {
  $despues[$pantalla] = celdas($pantalla, $pedido);
  ensenaLasCeldas($pantalla, $despues[$pantalla]);
}

// ---------------------------------------------------------------------------
// 6. Que ha cambiado de verdad.
// ---------------------------------------------------------------------------
echo "\n  --- 6. que ha cambiado, celda por celda ---\n\n";

$hijasTocadas = 0;
foreach (array_merge([LA_PANTALLA], LAS_HIJAS) as $pantalla) {
  $cambios = queHaCambiado($antes[$pantalla], $despues[$pantalla]);
  printf("    %s: %d celdas distintas\n", $pantalla, count($cambios));
  foreach ($cambios as $cambio) {
    printf("        %s\n", $cambio);
  }
  if ($pantalla !== LA_PANTALLA && $cambios) {
    $hijasTocadas++;
  }
}

echo "\n" . str_repeat('=', 86) . "\n";
if ($hijasTocadas > 0) {
  echo " CUIDADO: una pantalla hija ha cambiado. Se ha tocado algo heredado.\n";
  echo " Volver atras con la copia de backups/ antes de seguir.\n";
}
else {
  echo " HECHO. Solo ha cambiado la pantalla maestra.\n";
}
echo str_repeat('=', 86) . "\n\n";
echo "  Clave de configuracion tocada: views.view." . LA_VISTA . "\n\n";

/**
 * Dibuja una pantalla y devuelve sus celdas, con la cabecera de cada una.
 */
function celdas(string $pantalla, $pedido): array {
  $vista = Views::getView(LA_VISTA);
  if (!$vista) {
    return [];
  }
  $vista->setDisplay($pantalla);
  $cuantos = count($vista->display_handler->getHandlers('argument'));
  if ($cuantos > 0) {
    $vista->setArguments(array_fill(0, $cuantos, $pedido));
  }
  try {
    $vista->preExecute();
    $vista->execute();
    $html = (string) \Drupal::service('renderer')->renderInIsolation($vista->render());
  }
  catch (\Throwable $error) {
    return ['PETA' => get_class($error) . ': ' . $error->getMessage()];
  }
  $filas = count($vista->result);
  $vista->destroy();

  $tabla = leeLaTabla($html);
  $celdas = ['filas de la consulta' => (string) $filas];
  foreach ($tabla['filas'] as $numero => $unaFila) {
    foreach ($unaFila as $columna => $valor) {
      $cabecera = $tabla['cabeceras'][$columna] ?? ('columna ' . ($columna + 1));
      $celdas[sprintf('fila %d / %s', $numero + 1, $cabecera)] = $valor;
    }
  }
  return $celdas;
}

/**
 * Escribe las celdas de una pantalla.
 */
function ensenaLasCeldas(string $pantalla, array $celdas): void {
  printf("\n    %s:\n", $pantalla);
  foreach ($celdas as $donde => $valor) {
    printf("      %-42s %s\n", substr($donde, 0, 42), $valor === '' ? '(vacia)' : $valor);
  }
}

/**
 * Las diferencias entre dos fotos de celdas.
 */
function queHaCambiado(array $antes, array $despues): array {
  $cambios = [];
  foreach ($antes + $despues as $donde => $daIgual) {
    $uno = $antes[$donde] ?? '(no estaba)';
    $otro = $despues[$donde] ?? '(ya no esta)';
    if ($uno !== $otro) {
      $cambios[] = sprintf('%s:  "%s"  ->  "%s"', $donde, $uno, $otro);
    }
  }
  return $cambios;
}

/**
 * Saca cabeceras y celdas de la primera tabla del HTML.
 */
function leeLaTabla(string $html): array {
  $vacia = ['cabeceras' => [], 'filas' => []];
  if (trim($html) === '' || !str_contains($html, '<t')) {
    return $vacia;
  }
  $documento = new \DOMDocument();
  $anterior = libxml_use_internal_errors(TRUE);
  $documento->loadHTML('<?xml encoding="UTF-8">' . $html);
  libxml_clear_errors();
  libxml_use_internal_errors($anterior);

  $tablas = $documento->getElementsByTagName('table');
  if ($tablas->length === 0) {
    return $vacia;
  }
  $tabla = $tablas->item(0);

  $cabeceras = [];
  foreach ($tabla->getElementsByTagName('th') as $celda) {
    $cabeceras[] = aplana($celda->textContent);
  }
  $filas = [];
  foreach ($tabla->getElementsByTagName('tr') as $fila) {
    $unaFila = [];
    foreach ($fila->childNodes as $hija) {
      if ($hija->nodeName === 'td') {
        $unaFila[] = aplana($hija->textContent);
      }
    }
    if ($unaFila) {
      $filas[] = $unaFila;
    }
  }
  return ['cabeceras' => $cabeceras, 'filas' => $filas];
}

/**
 * Deja un trozo de texto en una linea y sin espacios de sobra.
 */
function aplana(string $texto): string {
  return trim(preg_replace('/\s+/', ' ', html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
}

/**
 * El pedido de venta mas reciente, que es el que llena esta vista.
 */
function pedidoDeVenta() {
  $almacen = \Drupal::entityTypeManager()->getStorage('tec_order');
  foreach (['tec_sales_order', 'tec_purchase_order'] as $clase) {
    $ids = $almacen->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $clase)
      ->sort('id', 'DESC')
      ->range(0, 1)
      ->execute();
    if ($ids) {
      return reset($ids);
    }
  }
  return NULL;
}
