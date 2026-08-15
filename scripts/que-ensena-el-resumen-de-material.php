<?php

/**
 * @file
 * Ensena, celda por celda, lo que dibuja cada pantalla del resumen de material.
 *
 *   php vendor/bin/drush scr scripts/que-ensena-el-resumen-de-material.php
 *   php vendor/bin/drush scr scripts/que-ensena-el-resumen-de-material.php -- tec_order_material_calculation_summary/default
 *
 * Sin argumentos mira las cuatro pantallas de las dos vistas de calculo de
 * material: la maestra y el bloque del resumen, y la maestra y el bloque de los
 * terminos, que es la que se dibuja al lado y sirve de contraste.
 *
 * Por que hace falta esto y no basta con la prueba de humo: una pantalla
 * devuelve 200 con una columna llena de basura. La prueba de humo mira el codigo
 * HTTP, `dibujar-los-displays-tocados.php` mira la primera fila, y aqui se ven
 * TODAS las filas con el nombre de la columna al lado, mas la ficha tecnica de
 * cada columna: el formateador, la funcion de agregacion del nucleo
 * (`group_type`) y la de views_aggregator (`has_aggr` y `aggr`). Cuando una
 * columna ensena un identificador en lugar de una etiqueta, la culpa esta casi
 * siempre en una de esas tres casillas, y sin verlas juntas no se sabe en cual.
 *
 * Y hace una cosa mas, que es la que cierra el diagnostico: cada numero que sale
 * en una celda lo busca entre las propiedades crudas de la fila de resultados y
 * entre los terminos de taxonomia. Asi se distingue un identificador de termino
 * disfrazado de numero de un numero de verdad, sin suponer nada.
 *
 * No escribe nada. Sirve de foto del antes y del despues.
 */

use Drupal\views\Views;

$pedidas = $extra ?? [];
if (!$pedidas) {
  $pedidas = [
    'tec_order_material_calculation_summary/default',
    'tec_order_material_calculation_summary/block_1',
    'tec_order_material_calculation_terms/default',
    'tec_order_material_calculation_terms/block_1',
  ];
}

$cambiador = \Drupal::service('account_switcher');
$cambiador->switchTo(\Drupal::entityTypeManager()->getStorage('user')->load(1));

$pedido = pedidoDeVenta();
echo "\n";
echo "Pedido de venta con el que se prueba: " . ($pedido ?? 'NO HAY') . "\n";

foreach ($pedidas as $pedidaTexto) {
  [$nombreVista, $nombreDisplay] = array_pad(explode('/', $pedidaTexto, 2), 2, 'default');

  echo "\n";
  echo str_repeat('=', 100) . "\n";
  echo " $nombreVista / $nombreDisplay\n";
  echo str_repeat('=', 100) . "\n";

  $config = \Drupal::config("views.view.$nombreVista");
  if ($config->isNew()) {
    echo " No existe esa vista en la configuracion activa.\n";
    continue;
  }
  $displays = $config->get('display') ?? [];
  if (!isset($displays[$nombreDisplay])) {
    echo " La vista no tiene la pantalla $nombreDisplay.\n";
    continue;
  }

  fichaTecnica($displays, $nombreDisplay);
  dibujaYEnsenaLasCeldas($nombreVista, $nombreDisplay, $pedido);
}

echo "\n";

/**
 * Lo que dice la configuracion de la pantalla y de cada una de sus columnas.
 */
function fichaTecnica(array $displays, string $nombreDisplay): void {
  $display = $displays[$nombreDisplay];
  $maestro = $displays['default'] ?? [];
  $suyo = $display['display_options'] ?? [];
  $delMaestro = $maestro['display_options'] ?? [];

  // Una pantalla hija solo tiene lo que ha sobrescrito; el resto lo hereda del
  // maestro. La lista `defaults` dice cual es cual, y equivocarse aqui es
  // justamente lo que hace que un arreglo "solo del maestro" se cuele en las
  // hijas.
  //
  // Y vive DENTRO de `display_options`, no al lado. Leerla del sitio equivocado
  // no da error: da una lista vacia, y una lista vacia se lee como "esta
  // pantalla no sobrescribe nada", que es exactamente la conclusion contraria a
  // la verdadera. Aqui costo un rato.
  $heredado = $suyo['defaults'] ?? [];
  $propio = function (string $clave) use ($nombreDisplay, $heredado): bool {
    if ($nombreDisplay === 'default') {
      return TRUE;
    }
    return ($heredado[$clave] ?? TRUE) === FALSE;
  };

  $tomaDe = function (string $clave) use ($propio, $suyo, $delMaestro) {
    return $propio($clave) ? ($suyo[$clave] ?? NULL) : ($delMaestro[$clave] ?? NULL);
  };

  $campos = $tomaDe('fields') ?? [];
  $estilo = $tomaDe('style') ?? [];
  $agregacionNucleo = (bool) $tomaDe('group_by');

  printf("\n Plantilla: %s\n", $display['display_plugin'] ?? '?');
  printf(" Ruta: %s\n", $suyo['path'] ?? '(no tiene, no es una pagina)');
  printf(" Estilo: %s%s\n", $estilo['type'] ?? '?', $propio('style') ? '' : '   (heredado del maestro)');
  printf(" Agregacion del nucleo (group_by): %s%s\n", $agregacionNucleo ? 'SI' : 'no', $propio('group_by') ? '' : '   (heredada del maestro)');
  printf(" Lista de campos: %s\n", $propio('fields') ? 'propia de esta pantalla' : 'HEREDADA del maestro');
  if ($nombreDisplay !== 'default') {
    printf(" Lo que dice `defaults` (false = sobrescrito aqui): %s\n", json_encode($heredado));
  }

  $info = $estilo['options']['info'] ?? [];
  $columnas = $estilo['options']['columns'] ?? [];

  echo "\n Columnas que se dibujan (las ocultas van al final, entre corchetes):\n\n";
  printf("   %-34s %-26s %-11s %-26s %s\n", 'campo', 'formateador', 'group_type', 'views_aggregator', 'etiqueta');
  printf("   %s\n", str_repeat('-', 118));

  $visibles = [];
  $ocultas = [];
  foreach ($campos as $id => $campo) {
    $linea = sprintf(
      "   %-34s %-26s %-11s %-26s %s",
      $id,
      $campo['type'] ?? ($campo['plugin_id'] ?? '?'),
      $campo['group_type'] ?? '-',
      resumeElAggregator($info[$id] ?? []),
      trim((string) ($campo['label'] ?? '')) === '' ? '(sin etiqueta)' : $campo['label']
    );
    if (!empty($campo['exclude']) || !isset($columnas[$id])) {
      $ocultas[] = '   [' . trim($linea) . ']';
    }
    else {
      $visibles[] = $linea;
    }
  }
  foreach ($visibles as $linea) {
    echo $linea . "\n";
  }
  foreach ($ocultas as $linea) {
    echo $linea . "\n";
  }
}

/**
 * En una linea, que le ha pedido views_aggregator a esa columna.
 */
function resumeElAggregator(array $info): string {
  if (!$info) {
    return 'sin ficha';
  }
  $partes = [];
  if (!empty($info['has_aggr'])) {
    $partes[] = 'grupo: ' . implode('+', array_map('quitaElPrefijo', array_values($info['aggr'] ?? [])));
  }
  if (!empty($info['has_aggr_column'])) {
    $partes[] = 'columna: ' . quitaElPrefijo((string) ($info['aggr_column'] ?? ''));
  }
  return $partes ? implode(', ', $partes) : 'nada';
}

/**
 * `views_aggregator_group_and_compress` no cabe en la tabla; el sufijo si.
 */
function quitaElPrefijo(string $funcion): string {
  return str_starts_with($funcion, 'views_aggregator_')
    ? substr($funcion, strlen('views_aggregator_'))
    : $funcion;
}

/**
 * Ejecuta la pantalla de verdad y escribe cada celda con su columna al lado.
 */
function dibujaYEnsenaLasCeldas(string $nombreVista, string $nombreDisplay, $pedido): void {
  $vista = Views::getView($nombreVista);
  if (!$vista) {
    echo "\n No se puede cargar la vista.\n";
    return;
  }
  $vista->setDisplay($nombreDisplay);

  // Las dos vistas de calculo llevan el pedido en el argumento; sin el no
  // devuelven ni una fila y la foto no valdria para nada.
  $cuantos = count($vista->display_handler->getHandlers('argument'));
  if ($cuantos > 0) {
    $vista->setArguments(array_fill(0, $cuantos, $pedido));
  }

  try {
    $vista->preExecute();
    $vista->execute();
    $salida = $vista->render();
    $html = (string) \Drupal::service('renderer')->renderInIsolation($salida);
  }
  catch (\Throwable $error) {
    echo "\n PETA al dibujar: " . get_class($error) . ': ' . $error->getMessage() . "\n";
    echo " en " . basename($error->getFile()) . ':' . $error->getLine() . "\n";
    return;
  }

  printf("\n Filas que trae la consulta: %d\n", count($vista->result));

  $tabla = leeLaTabla($html);
  if (!$tabla['filas']) {
    $texto = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html))));
    echo "\n No dibuja una tabla. Texto que suelta: " . ($texto === '' ? '(nada)' : $texto) . "\n";
    $vista->destroy();
    return;
  }

  foreach ($tabla['filas'] as $numero => $celdas) {
    printf("\n Fila %d:\n\n", $numero + 1);
    foreach ($celdas as $columna => $valor) {
      $cabecera = $tabla['cabeceras'][$columna] ?? ('columna ' . ($columna + 1));
      printf("   %-30s %s\n", substr($cabecera, 0, 30), $valor === '' ? '(vacia)' : $valor);
      foreach (deDondeSale($valor, $vista->result) as $pista) {
        printf("   %-30s    ^ %s\n", '', $pista);
      }
    }
  }

  $vista->destroy();
}

/**
 * Rastrea los numeros de una celda: termino de taxonomia o numero de verdad.
 *
 * Se mira en dos sitios y se dicen los dos, porque cada uno contesta media
 * pregunta. Que exista un termino con ese identificador no prueba que la celda
 * sea un identificador: `2` existe como termino en casi cualquier sitio. Lo que
 * lo prueba es encontrar el mismo numero en una propiedad de la fila de
 * resultados cuyo nombre acabe en `target_id`, que es la columna donde Views
 * guarda el extremo de una referencia.
 */
function deDondeSale(string $celda, array $resultados): array {
  $texto = trim($celda);
  if ($texto === '' || !preg_match('/^[0-9,. ]+$/', $texto)) {
    return [];
  }
  $pistas = [];
  $almacen = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

  // Cada trozo de digitos por separado y, ademas, todos los digitos pegados. Lo
  // segundo no es un capricho: `2, 706` parecen dos numeros y es uno solo, el
  // 2706, escrito con separador de miles. Mirando solo los trozos no se
  // encuentra nada y se concluye que el numero no sale de la fila, que es falso.
  $candidatos = preg_split('/[^0-9]+/', $texto, -1, PREG_SPLIT_NO_EMPTY);
  $pegados = preg_replace('/[^0-9]/', '', $texto);
  if (count($candidatos) > 1 && $pegados !== '') {
    $candidatos[] = $pegados;
  }

  foreach (array_unique($candidatos) as $numero) {
    $donde = [];
    foreach ($resultados as $fila) {
      foreach (get_object_vars($fila) as $propiedad => $valor) {
        if (is_scalar($valor) && (string) $valor === (string) $numero) {
          $donde[$propiedad] = TRUE;
        }
      }
    }
    $donde = array_keys($donde);

    $termino = $almacen->load($numero);
    $pistas[] = sprintf(
      '%s: %s%s',
      $numero,
      $termino
        ? sprintf('hay un termino con ese id, "%s" del vocabulario %s', $termino->label(), $termino->bundle())
        : 'no hay ningun termino con ese id',
      $donde ? '; en la fila esta en ' . implode(', ', array_slice($donde, 0, 4)) : '; no aparece crudo en la fila'
    );
  }
  return $pistas;
}

/**
 * Saca las cabeceras y las celdas de la primera tabla del HTML.
 *
 * Con DOMDocument y no con expresiones regulares: las celdas llevan enlaces,
 * imagenes y hasta tablas de ayuda dentro, y una expresion regular corta por
 * donde no debe y ensena media celda, que en una foto del antes y el despues
 * es peor que no ensenar nada.
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
    $celdas = [];
    foreach ($fila->childNodes as $hija) {
      if ($hija->nodeName === 'td') {
        $celdas[] = aplana($hija->textContent);
      }
    }
    if ($celdas) {
      $filas[] = $celdas;
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
 * El pedido de venta mas reciente, que es el que llena estas dos vistas.
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
