<?php

/**
 * Saca el sistema de unidades opcionales de las cinco vistas que lo usaban.
 *
 * Las columnas que hoy se dibujan salen de una condicional: si la casilla
 * escondida esta encendida ensena una cosa y si no otra. Como ahora todos los
 * materiales convierten siempre, la casilla no decide nada y hay que dejar la
 * rama buena escrita a pelo. Ojo, que la rama buena no siempre es la de la
 * casilla encendida: en las piezas del listado de materiales la rama encendida
 * ensena el plural del empaquetado, y lo que se quiere es el de la unidad de
 * compra. Copiarla tal cual dejaria la vista funcionando y dando otro dato, que
 * es peor que si petara.
 *
 * Va con red: despues de aplicar el plan repasa todos los manejadores que
 * quedan y, si alguno nombra algo que se acaba de borrar, no guarda nada.
 *
 * Uso: drush php:script scripts/sacar-a-oscar-de-las-vistas
 *      drush php:script scripts/sacar-a-oscar-de-las-vistas -- de-verdad
 *
 * Sin la palabra de-verdad solo cuenta lo que haria. Nada de guiones delante:
 * Drush entiende lo que empieza por guion como una opcion suya y no lo pasa.
 */

$deVerdad = in_array('de-verdad', (array) ($extra ?? []), TRUE);

// Los campos del sistema viejo. Si al acabar alguno sigue nombrado en alguna
// vista, es que el plan se ha dejado algo.
$condenados = [
  'field_tec_split_inventory',
  'field_tec_package_units',
  'field_tec_moq_package',
  'field_tec_stock_unit_check',
  'field_tec_packaging',
  'field_tec_split_unit',
  'field_tec_uop_pu_uou',
  'field_tec_uop_uou',
  'field_tec_uos_pu_uop',
  'field_tec_uos_pu_uou',
  'field_tec_uos_uop',
  'field_tec_uos_uou',
  'field_tec_uou_control',
  'field_tec_uou_split_qty',
  'field_tec_uou_uop',
  'field_tec_uou_uop_pu',
  'field_tec_uou_uos',
  'field_uop_to_uos',
  'field_1_uou_uos_pu',
  'field_tec_price_by_package',
  'field_tec_price_uou',
  'field_tec_moq_unit',
  'field_tec_purchasing_moq',
];

// El plan, vista por vista y display por display.
//
//   quita:  identificadores de columnas que se van.
//   cambia: columnas que se quedan pero con otra formula, otra etiqueta, otro
//           texto o dejando de estar ocultas.
//   anade:  columnas nuevas, copiadas de otra que ya existe para heredar todos
//           los ajustes y no inventarse ninguno.
//   mueve:  columna => detras de que otra tiene que ir. Hace falta porque una
//           columna solo puede leer el valor de las que van antes que ella. Las
//           condicionales viejas estaban al final de la lista y por eso veian
//           todo; al escribir la rama buena en una columna que estaba antes, el
//           parentesis salia vacio sin dar ningun error.
//   relaciones: relaciones que se van.
$plan = [

  // Listado de materiales. La columna de unidad de inventario se dibuja y hay
  // que rehacerla; la de unidad de compra ya la hacia otra columna al lado con
  // el parentesis siempre puesto, asi que la condicional sobra.
  'tec_inventory' => [
    'block_1' => [
      'cambia' => [
        'field_tec_uos' => [
          'exclude' => FALSE,
          'label' => 'Inventory UoM',
          'texto' => '<span>{{ field_tec_uos }}</span><span class="subtle small"> ({{ field_tec_split_into }} {{ field_tec_unit_symbol_1 }})</span>',
        ],
      ],
      'quita' => [
        'views_conditional_field',
        'views_conditional_field_2',
        'field_tec_package_units',
        'field_tec_packaging',
        'field_tec_split_inventory',
      ],
      // Donde estaba la condicional, que era detras del factor y del simbolo.
      'mueve' => ['field_tec_uos' => 'field_tec_unit_symbol_1'],
    ],
    // La exportacion a hoja de calculo. Cuatro columnas del sistema viejo se
    // van; la de pedido minimo se cambia por el campo bueno para no perder la
    // columna.
    'data_export_1' => [
      'anade' => [
        'field_tec_moq_quantity' => [
          'copiar' => 'field_tec_purchasing_moq',
          'en_lugar_de' => 'field_tec_purchasing_moq',
          'label' => 'Moq',
        ],
      ],
      'quita' => [
        'field_tec_purchasing_moq',
        'field_tec_package_units',
        'field_tec_packaging',
        'field_tec_split_inventory',
      ],
    ],
  ],

  // Las cuatro piezas que se dibujan dentro del listado de materiales: unidad
  // de compra, de inventario, de consumo con su coste, y pedido minimo.
  'tec_inventory_elements' => [
    'default' => [
      'relaciones' => ['field_tec_packaging', 'field_tec_split_unit'],
    ],
    'block_1' => [
      'cambia' => [
        // El simbolo acompanya al factor 1 compra = x inventario, asi que tiene
        // que ser el simbolo de la unidad de inventario, no el de compra.
        'field_tec_unit_symbol' => ['relationship' => 'field_tec_uos'],
        'field_tec_unit_plural_1' => [
          'exclude' => FALSE,
          'texto' => "<div>\r\n<div><h5>{{ field_tec_unit_plural_1 }}</h5></div>\r\n<div class =\"subtle small\">({{ field_tec_units }} {{ field_tec_unit_symbol }})</div>\r\n</div>",
        ],
      ],
      'quita' => [
        'views_conditional_field',
        'field_tec_package_units',
        // Estas dos cuelgan de la relacion con el empaquetado.
        'name',
        'field_tec_unit_plural',
      ],
      'mueve' => ['field_tec_unit_plural_1' => 'field_tec_unit_symbol'],
    ],
    'block_2' => [
      'cambia' => [
        'field_tec_unit_plural' => [
          'exclude' => FALSE,
          'texto' => "<div>\r\n<div><h5>{{ field_tec_unit_plural }}</h5></div>\r\n<div class =\"subtle small\">({{ field_tec_split_into }} {{ field_tec_unit_symbol }})</div>\r\n</div>",
        ],
      ],
      'quita' => [
        'views_conditional_field',
        'field_tec_split_inventory',
        'field_tec_split_unit',
        'field_tec_unit_plural_2',
      ],
      'mueve' => ['field_tec_unit_plural' => 'field_tec_unit_symbol'],
    ],
    'block_3' => [
      // El coste por unidad de consumo lo calculaba una formula con dos campos
      // del sistema viejo. Ya existe un campo que guarda ese numero.
      'anade' => [
        'field_tec_price' => ['copiar_de' => 'block_4'],
      ],
      'cambia' => [
        'field_tec_unit_use' => [
          'exclude' => FALSE,
          'texto' => "<div>\r\n<div><h5>{{ field_tec_unit_use }}</h5></div>\r\n<div class =\"subtle small\">฿ {{ field_tec_price }}/{{ field_tec_unit_symbol }}</div>\r\n</div>",
        ],
      ],
      'quita' => [
        'views_conditional_field',
        'field_views_simple_math_field',
        'field_tec_price_by_package',
        'field_tec_split_inventory',
        'field_tec_split_unit',
        'field_tec_unit_plural_2',
      ],
      'mueve' => ['field_tec_unit_use' => 'field_tec_price'],
    ],
    'block_4' => [
      'cambia' => [
        'field_tec_moq_quantity' => [
          'exclude' => FALSE,
          'texto' => "<div>\r\n<div><h5>{{ field_tec_moq_quantity }} {{ field_tec_unit_purchase }}</h5></div>\r\n</div>",
        ],
      ],
      'quita' => [
        'views_conditional_field',
        'field_tec_moq_package',
        'field_tec_moq_unit',
        'field_tec_purchasing_moq',
        'field_tec_packaging',
      ],
      'mueve' => ['field_tec_moq_quantity' => 'field_tec_unit_purchase'],
    ],
  ],

  // Lineas de los pedidos. La columna de unidad se queda con la unidad de
  // compra, que es la que se le pide al proveedor.
  'tec_order_sales_order_line_items' => [
    'block_2' => [
      'cambia' => [
        'field_tec_unit_purchase' => ['exclude' => FALSE, 'label' => 'Unit'],
      ],
      'quita' => [
        'views_conditional_field',
        'field_tec_package_units',
        'field_tec_packaging',
      ],
    ],
    // Aqui las dos condicionales estaban ya muertas: quien las nombraba tenia
    // la reescritura apagada.
    'page_1' => [
      'quita' => [
        'views_conditional_field',
        'views_conditional_field_1',
        'field_views_simple_math_field',
        'field_tec_price_by_package',
        'field_tec_package_units',
        'field_tec_packaging',
      ],
    ],
    'page_3' => [
      'cambia' => [
        'field_tec_unit_purchase' => ['exclude' => FALSE, 'label' => 'Unit'],
      ],
      'quita' => [
        'views_conditional_field',
        'field_tec_package_units',
        'field_tec_packaging',
      ],
    ],
  ],

  // Materiales que hace falta comprar para un pedido, una fila por material.
  // El display maestro calculaba con la matriz de pares y no daba un numero;
  // se rehace igual que el bloque, que es el que se dibuja y ya estaba bien.
  'tec_order_material_calculation_terms' => [
    'default' => [
      'anade' => [
        'field_tec_split_into' => ['copiar_de' => 'block_1'],
        'field_tec_units' => ['copiar_de' => 'block_1'],
        'field_tec_price' => ['copiar' => 'field_tec_price_uou'],
      ],
      'cambia' => [
        'field_views_simple_math_field' => [
          'exclude' => FALSE,
          'label' => 'Total required UoS',
          'formula' => '@field_tec_quantity / @field_tec_split_into',
        ],
        'field_views_simple_math_field_2' => [
          'exclude' => FALSE,
          'label' => 'Total required UoP',
          'formula' => '@field_tec_quantity / @field_tec_split_into / @field_tec_units',
        ],
        'field_views_simple_math_field_5' => [
          'formula' => '@field_tec_quantity * @field_tec_price',
        ],
        'field_tec_uos' => ['exclude' => FALSE, 'label' => 'UoS'],
        'field_tec_unit_purchase' => ['exclude' => FALSE, 'label' => 'UoP'],
      ],
      'quita' => [
        'views_conditional_field',
        'views_conditional_field_1',
        'views_conditional_field_2',
        'views_conditional_field_3',
        'field_views_simple_math_field_3',
        'field_views_simple_math_field_4',
        'field_tec_uou_uos',
        'field_tec_uos_pu_uou',
        'field_tec_uop_pu_uou',
        'field_tec_uou_uop',
        'field_tec_uou_uop_pu',
        'field_tec_split_inventory',
        'field_tec_split_unit',
        'field_tec_package_units',
        'field_tec_packaging',
        'field_tec_price_uou',
      ],
    ],
    // El bloque ya calculaba bien. Aqui solo se tira lo que sobra.
    'block_1' => [
      'quita' => [
        'views_conditional_field',
        'views_conditional_field_1',
        'views_conditional_field_2',
        'views_conditional_field_3',
        'views_conditional_field_4',
        'field_views_simple_math_field_2',
        'field_tec_split_inventory',
        'field_tec_split_inventory_1',
        'field_tec_package_units',
        'field_tec_packaging',
        'field_tec_packaging_1',
        'field_uop_to_uos',
        'field_tec_uou_split_qty',
        'field_tec_unit_symbol',
      ],
    ],
  ],

  // El resumen del mismo calculo, agrupado por material.
  'tec_order_material_calculation_summary' => [
    'default' => [
      'anade' => [
        'field_tec_split_into' => ['copiar_de' => 'block_1'],
        'field_tec_units' => ['copiar_de' => 'block_1'],
        'field_tec_price' => ['copiar' => 'field_tec_price_uou'],
      ],
      'cambia' => [
        'field_views_simple_math_field' => [
          'exclude' => FALSE,
          'label' => 'Total required UoS',
          'formula' => '@field_tec_quantity / @field_tec_split_into',
        ],
        'field_views_simple_math_field_2' => [
          'exclude' => FALSE,
          'label' => 'Total required UoP',
          'formula' => '@field_tec_quantity / @field_tec_split_into / @field_tec_units',
        ],
        'field_views_simple_math_field_5' => [
          'formula' => '@field_tec_quantity * @field_tec_price',
        ],
        'field_tec_uos' => ['exclude' => FALSE, 'label' => 'UoS'],
        'field_tec_unit_purchase' => ['exclude' => FALSE, 'label' => 'UoP'],
      ],
      'quita' => [
        'views_conditional_field',
        'views_conditional_field_1',
        'views_conditional_field_2',
        'views_conditional_field_3',
        'field_views_simple_math_field_3',
        'field_views_simple_math_field_4',
        'field_tec_uou_uos',
        'field_tec_uos_pu_uou',
        'field_tec_uop_pu_uou',
        'field_tec_uou_uop',
        'field_tec_uou_uop_pu',
        'field_tec_split_inventory',
        'field_tec_split_unit',
        'field_tec_package_units',
        'field_tec_packaging',
        'field_tec_price_uou',
      ],
    ],
    'block_1' => [
      'quita' => [
        'views_conditional_field',
        'views_conditional_field_1',
        'views_conditional_field_2',
        'views_conditional_field_3',
        'views_conditional_field_4',
        'field_views_simple_math_field_2',
        'field_tec_split_inventory',
        'field_tec_split_inventory_1',
        'field_tec_package_units',
        'field_tec_packaging',
        'field_uop_to_uos',
        'field_tec_uou_split_qty',
        'field_tec_unit_symbol',
      ],
      // Esto no es de Oscar, es un fallo de siempre que sale al pasar la misma
      // comprobacion: la columna del proveedor pone un enlace a la ficha del
      // contacto, pero la direccion la trae una columna que va detras, asi que
      // el enlace sale vacio. Se adelanta la columna que trae la direccion, que
      // esta oculta, y asi el orden de lo que se ve no cambia.
      'mueve' => ['view_tec_crm' => 'name'],
    ],
  ],
];

// Se trabaja con la entidad de la vista, no con el fichero de configuracion a
// pelo. Guardar la configuracion cruda se salta la entidad, y entonces Drupal no
// recalcula ni la lista de dependencias ni las etiquetas de cache, con lo que la
// vista se queda diciendo que necesita campos que ya no usa. Al borrar despues
// esos campos, Drupal ve una vista que depende de ellos y va a por ella.
$almacen = \Drupal::entityTypeManager()->getStorage('view');
$problemas = [];
$resumen = [];
$comoEstaba = [];

foreach ($plan as $vista => $displays) {
  $configuracion = $almacen->load($vista);
  if ($configuracion === NULL) {
    $problemas[] = "No existe la vista $vista.";
    continue;
  }
  $todos = $configuracion->get('display');
  // Copia de como estaba, para poder distinguir despues lo que rompe este
  // guion de lo que ya venia roto de antes.
  $comoEstaba[$vista] = $todos;

  echo "\n";
  echo str_repeat('=', 78) . "\n";
  echo " $vista\n";
  echo str_repeat('=', 78) . "\n";

  foreach ($displays as $display => $pasos) {
    if (!isset($todos[$display])) {
      $problemas[] = "$vista no tiene el display $display.";
      continue;
    }
    echo "\n  $display\n";

    $campos = $todos[$display]['display_options']['fields'] ?? [];

    // 1. Columnas nuevas, copiadas de una que ya existe.
    foreach ($pasos['anade'] ?? [] as $nuevo => $como) {
      if (isset($campos[$nuevo])) {
        echo "    ya estaba          $nuevo\n";
        continue;
      }
      $molde = NULL;
      if (isset($como['copiar_de'])) {
        $molde = $todos[$como['copiar_de']]['display_options']['fields'][$nuevo] ?? NULL;
        if ($molde === NULL) {
          $problemas[] = "$vista/$display: no hay $nuevo en " . $como['copiar_de'] . ' para copiarlo.';
          continue;
        }
      }
      else {
        $molde = $campos[$como['copiar']] ?? NULL;
        if ($molde === NULL) {
          $problemas[] = "$vista/$display: no hay " . $como['copiar'] . " para copiar y hacer $nuevo.";
          continue;
        }
        // Se copia otro campo, asi que hay que apuntarle al bueno.
        $molde['table'] = 'taxonomy_term__' . $nuevo;
        $molde['field'] = $nuevo;
        $molde['alter']['alter_text'] = FALSE;
        $molde['alter']['text'] = '';
      }
      $molde['id'] = $nuevo;
      $molde['exclude'] = TRUE;
      if (isset($como['label'])) {
        $molde['label'] = $como['label'];
        $molde['exclude'] = FALSE;
      }

      // Se mete en el sitio de otra para no descolocar las columnas.
      if (isset($como['en_lugar_de']) && isset($campos[$como['en_lugar_de']])) {
        $rehecho = [];
        foreach ($campos as $id => $campo) {
          if ($id === $como['en_lugar_de']) {
            $rehecho[$nuevo] = $molde;
          }
          $rehecho[$id] = $campo;
        }
        $campos = $rehecho;
      }
      else {
        $campos[$nuevo] = $molde;
      }
      echo "    anadida            $nuevo" . (empty($molde['exclude']) ? '  << se dibuja' : '') . "\n";
    }

    // 2. Columnas que cambian.
    foreach ($pasos['cambia'] ?? [] as $id => $cambios) {
      if (!isset($campos[$id])) {
        $problemas[] = "$vista/$display: no esta $id, que habia que cambiar.";
        continue;
      }
      $antes = empty($campos[$id]['exclude']) ? 'se dibujaba' : 'oculta';
      foreach ($cambios as $que => $valor) {
        switch ($que) {
          case 'formula':
            $campos[$id]['fieldset_one']['formula'] = $valor;
            break;

          case 'texto':
            $campos[$id]['alter']['alter_text'] = TRUE;
            $campos[$id]['alter']['text'] = $valor;
            break;

          case 'exclude':
            $campos[$id]['exclude'] = $valor;
            break;

          default:
            $campos[$id][$que] = $valor;
        }
      }
      $ahora = empty($campos[$id]['exclude']) ? 'se dibuja' : 'oculta';
      echo "    cambiada           $id  ($antes -> $ahora)\n";
      foreach ($cambios as $que => $valor) {
        if ($que !== 'exclude') {
          printf("        %-14s %s\n", $que, recortar((string) $valor));
        }
      }
    }

    // 3. Columnas que se van.
    foreach ($pasos['quita'] ?? [] as $id) {
      if (!isset($campos[$id])) {
        echo "    ya no estaba       $id\n";
        continue;
      }
      $seDibujaba = empty($campos[$id]['exclude']);
      $etiqueta = trim((string) ($campos[$id]['label'] ?? ''));
      unset($campos[$id]);
      printf(
        "    quitada            %s%s\n",
        $id,
        $seDibujaba ? '  << SE DIBUJABA, columna "' . $etiqueta . '" fuera' : ''
      );
    }

    // 4. Columnas que cambian de sitio, para que puedan leer lo que necesitan.
    foreach ($pasos['mueve'] ?? [] as $id => $detrasDe) {
      if (!isset($campos[$id])) {
        $problemas[] = "$vista/$display: no esta $id, que habia que mover.";
        continue;
      }
      if (!isset($campos[$detrasDe])) {
        $problemas[] = "$vista/$display: no esta $detrasDe, detras de la cual iba $id.";
        continue;
      }
      $movida = $campos[$id];
      unset($campos[$id]);
      $rehecho = [];
      foreach ($campos as $otro => $campo) {
        $rehecho[$otro] = $campo;
        if ($otro === $detrasDe) {
          $rehecho[$id] = $movida;
        }
      }
      $campos = $rehecho;
      echo "    movida             $id detras de $detrasDe\n";
    }

    $todos[$display]['display_options']['fields'] = $campos;

    // 5. Relaciones que se van.
    foreach ($pasos['relaciones'] ?? [] as $id) {
      if (isset($todos[$display]['display_options']['relationships'][$id])) {
        unset($todos[$display]['display_options']['relationships'][$id]);
        echo "    quitada relacion   $id\n";
      }
    }

    // 6. El estilo guarda una entrada por columna. Las que ya no existen se
    // limpian para que no queden nombres colgando.
    //
    // Se comprueba antes que el display tenga estilo propio. Muchos no lo
    // tienen, lo heredan del maestro, y con solo pedir la clave por referencia
    // PHP la crea vacia: quedaria un estilo sin tipo, que Drupal no espera y
    // que le hace saltar un aviso cada vez que carga la vista.
    if (isset($todos[$display]['display_options']['style']['options'])
      && is_array($todos[$display]['display_options']['style']['options'])) {
      $opciones = &$todos[$display]['display_options']['style']['options'];
      foreach (['columns', 'info'] as $lista) {
        if (!isset($opciones[$lista]) || !is_array($opciones[$lista])) {
          continue;
        }
        foreach (array_keys($opciones[$lista]) as $columna) {
          if (!isset($campos[$columna])) {
            unset($opciones[$lista][$columna]);
          }
        }
      }
      // Y una columna no puede agruparse dentro de otra que ya no esta.
      if (isset($opciones['columns']) && is_array($opciones['columns'])) {
        foreach ($opciones['columns'] as $columna => $donde) {
          if (!isset($campos[$donde])) {
            $opciones['columns'][$columna] = $columna;
          }
        }
      }
      unset($opciones);
    }
  }

  // Las columnas calculadas guardan aparte una lista con todas las columnas del
  // display y una marca en las que entran en el calculo. El modulo lee el valor
  // de cada columna marcada, aunque no salga en la formula, asi que una marca en
  // una columna borrada tumba la pagina entera. Se repasan todos los displays,
  // no solo los del plan, porque la lista puede venir sucia de antes.
  foreach ($todos as $display => $opciones) {
    $campos = $opciones['display_options']['fields'] ?? [];
    foreach ($campos as $id => $campo) {
      if (($campo['plugin_id'] ?? '') !== 'field_views_simple_math_field') {
        continue;
      }
      $lista = $campo['fieldset_one']['data_field'] ?? [];
      if (!is_array($lista)) {
        continue;
      }
      $sobran = [];
      foreach ($lista as $columna => $marcada) {
        if (!isset($campos[$columna])) {
          $sobran[$columna] = (bool) $marcada;
          unset($lista[$columna]);
        }
      }

      // Y al reves: lo que la formula nombra tiene que estar marcado. El modulo
      // solo sustituye los tokens de las columnas marcadas, asi que si falta
      // una, la arroba se queda escrita en la formula y la libreria de calculo
      // se queja de caracter ilegal y tumba la pagina.
      $faltan = [];
      $formula = (string) ($campo['fieldset_one']['formula'] ?? '');
      if (preg_match_all('/@([a-z0-9_]+)/i', $formula, $encontrados)) {
        foreach (array_unique($encontrados[1]) as $token) {
          if (isset($campos[$token]) && empty($lista[$token])) {
            $lista[$token] = $token;
            $faltan[] = $token;
          }
        }
      }

      if (!$sobran && !$faltan) {
        continue;
      }
      $todos[$display]['display_options']['fields'][$id]['fieldset_one']['data_field'] = $lista;
      foreach ($sobran as $columna => $estabaMarcada) {
        printf(
          "    %s: %s ya no cuenta con %s%s\n",
          $display,
          $id,
          $columna,
          $estabaMarcada ? '  << ESTABA MARCADA, esto rompia la pagina' : ''
        );
      }
      foreach ($faltan as $columna) {
        printf("    %s: %s ahora cuenta con %s  << lo pide su formula\n", $display, $id, $columna);
      }
    }
  }

  $configuracion->set('display', $todos);
  $resumen[$vista] = $configuracion;
}

// La red: se repasan las cinco vistas buscando referencias a lo que se acaba de
// borrar. Se repasa dos veces, antes y despues, porque estas vistas ya traian
// cabos sueltos de fabrica y no hay que confundirlos con los recien hechos.
echo "\n";
echo str_repeat('=', 78) . "\n";
echo " Repaso\n";
echo str_repeat('=', 78) . "\n";

$deAntes = [];
$deAhora = [];
foreach ($resumen as $vista => $configuracion) {
  $deAntes = array_merge($deAntes, repasar($vista, $comoEstaba[$vista], $condenados));
  $deAhora = array_merge($deAhora, repasar($vista, $configuracion->get('display'), $condenados));
}

$nuevos = array_values(array_diff(array_unique($deAhora), array_unique($deAntes)));
$arreglados = count(array_unique($deAntes)) - count(array_intersect(array_unique($deAntes), array_unique($deAhora)));
$yaEstaban = array_values(array_intersect(array_unique($deAntes), array_unique($deAhora)));

printf("\n Cabos sueltos que habia antes: %d. Arreglados de paso: %d.\n", count(array_unique($deAntes)), $arreglados);

if ($yaEstaban) {
  echo "\n Siguen ahi, pero ya venian roto de antes y no son de este trabajo:\n\n";
  foreach ($yaEstaban as $problema) {
    echo "   - $problema\n";
  }
}

$problemas = array_merge($problemas, $nuevos);

if ($problemas) {
  echo "\n No se guarda nada. Este guion ha roto " . count($problemas) . " cosas:\n\n";
  foreach (array_unique($problemas) as $problema) {
    echo "   - $problema\n";
  }
  echo "\n";
  return;
}

echo "\n Ni un cabo suelto nuevo: ningun manejador nombra nada borrado.\n";

if (!$deVerdad) {
  echo "\n Esto era un ensayo. Para guardarlo:\n";
  echo "   drush php:script scripts/sacar-a-oscar-de-las-vistas -- de-verdad\n\n";
  return;
}

foreach ($resumen as $vista => $configuracion) {
  $configuracion->save();
  echo " guardada $vista\n";
}

echo "\n Guardadas " . count($resumen) . " vistas.\n\n";

/**
 * Recorta un texto para que quepa en una linea del informe.
 */
function recortar(string $texto, int $largo = 58): string {
  $texto = trim(preg_replace('/\s+/', ' ', $texto));
  return mb_strlen($texto) <= $largo ? $texto : mb_substr($texto, 0, $largo - 1) . '.';
}

/**
 * Busca cabos sueltos en todos los displays de una vista.
 *
 * Cuatro clases de cabo: un manejador que apunta a un campo del sistema viejo,
 * una condicional que decide con uno, un manejador que cuelga de una relacion
 * que no existe, y un texto o una formula que nombra una columna que no esta.
 */
function repasar(string $vista, array $displays, array $condenados): array {
  $cabos = [];
  $relacionesMaestro = $displays['default']['display_options']['relationships'] ?? [];

  foreach ($displays as $display => $opciones) {
    $campos = $opciones['display_options']['fields'] ?? [];
    $relaciones = $opciones['display_options']['relationships'] ?? $relacionesMaestro;

    foreach (['fields', 'filters', 'sorts', 'arguments'] as $clase) {
      foreach ($opciones['display_options'][$clase] ?? [] as $id => $manejador) {
        if (in_array($manejador['field'] ?? '', $condenados, TRUE)) {
          $cabos[] = "$vista/$display: $clase $id sigue apuntando a " . $manejador['field'] . '.';
        }
        if (in_array($manejador['if'] ?? '', $condenados, TRUE)) {
          $cabos[] = "$vista/$display: la condicional $id sigue decidiendo con " . $manejador['if'] . '.';
        }

        $relacion = $manejador['relationship'] ?? 'none';
        if ($relacion !== 'none' && $relacion !== '' && !isset($relaciones[$relacion])) {
          $cabos[] = "$vista/$display: $clase $id cuelga de la relacion $relacion, que no existe.";
        }

        // Solo cuenta el texto que se dibuja de verdad. Estas vistas estan
        // llenas de reescrituras apagadas que nombran campos de hace anos.
        $textos = [];
        if (!empty($manejador['alter']['alter_text'])) {
          $textos[] = (string) ($manejador['alter']['text'] ?? '');
        }
        if (($manejador['plugin_id'] ?? '') === 'views_conditional_field') {
          $textos[] = (string) ($manejador['then'] ?? '');
          $textos[] = (string) ($manejador['or'] ?? '');
        }
        $formula = (string) ($manejador['fieldset_one']['formula'] ?? '');
        $juntos = implode(' ', $textos) . ' ' . $formula;

        foreach ($condenados as $campo) {
          if (preg_match('/[@{ ]' . preg_quote($campo, '/') . '(?![a-z0-9_])/', $juntos)) {
            $cabos[] = "$vista/$display: $clase $id nombra $campo en su texto.";
          }
        }
        if (preg_match_all('/\{\{ *([a-z0-9_]+) *\}\}/i', implode(' ', $textos), $encontrados)) {
          $orden = array_keys($campos);
          $donde = array_search($id, $orden, TRUE);
          foreach ($encontrados[1] as $token) {
            if (!isset($campos[$token])) {
              $cabos[] = "$vista/$display: $clase $id nombra {{ $token }}, que no es una columna.";
              continue;
            }
            // Una columna solo ve el valor de las que se dibujan antes que
            // ella. Si nombra una posterior, el hueco sale vacio y no salta
            // ningun error: la pagina carga y el dato no esta.
            if ($donde !== FALSE && $token !== $id) {
              $dondeEsta = array_search($token, $orden, TRUE);
              if ($dondeEsta !== FALSE && $dondeEsta > $donde) {
                $cabos[] = "$vista/$display: $id nombra {{ $token }}, que va despues, y saldra vacio.";
              }
            }
          }
        }
        if (preg_match_all('/@([a-z0-9_]+)/i', $formula, $encontrados)) {
          foreach ($encontrados[1] as $token) {
            if (!isset($campos[$token])) {
              $cabos[] = "$vista/$display: la formula de $id usa @$token, que no es una columna.";
            }
          }
        }

        // Una columna calculada lee el valor de cada columna que tenga marcada
        // en su lista, aunque no salga en la formula. Si la columna marcada no
        // existe, la pagina peta.
        foreach ($manejador['fieldset_one']['data_field'] ?? [] as $columna => $marcada) {
          if ($marcada && !isset($campos[$columna])) {
            $cabos[] = "$vista/$display: $id tiene marcada la columna $columna, que no existe.";
          }
        }
      }
    }
  }

  return $cabos;
}
