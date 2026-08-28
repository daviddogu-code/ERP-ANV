<?php

/**
 * @file
 * Las listas de productos dejan de preguntar al cliente y miran a la marca.
 *
 *   php vendor\bin\drush.php scr scripts/las-pantallas-siguen-el-orden-de-la-marca.php
 *
 * Viene detras de scripts/la-marca-ordena-sus-productos.php, que abrio el campo
 * donde cada producto guarda su sitio dentro de su marca y lo sembro por orden
 * alfabetico. Este cambia quien lo lee.
 *
 * Hasta hoy cuatro pantallas ordenaban los productos por el peso de
 * draggableviews, apuntando todas a la misma pantalla: `tec_products:page_2`,
 * el "Organize products" que colgaba de la ficha del cliente en
 * /tec_crm/N/reorder. Era el orden de un cliente, uno por cliente, y no valia
 * para lo unico que de verdad hacia falta: la proforma. La vista que crea el
 * borrador corre para un cliente y no puede leer el orden de otro, asi que las
 * lineas salian como las devolvia la base de datos.
 *
 * Asi que el orden se muda al producto y esa pantalla se retira. Con ella se van
 * sus filas y las de otras cuatro vistas que ya no existen, y draggableviews se
 * queda sin nadie que lo use: el modulo sigue instalado, pero desde hoy no
 * ordena ni una lista del ERP.
 *
 * ## Que ordena cada pantalla a partir de ahora
 *
 *   block_1  Portada de productos     tipo -> marca -> sitio en la marca
 *   block_2  Bloque de cliente        sitio en la marca
 *   block_4  Pestana Products         sitio en la marca (y el orden de marcas
 *                                     del cliente lo sigue poniendo
 *                                     tec_crm_ux_views_pre_render)
 *   block_5  Portada, apagada         tipo -> marca -> sitio en la marca
 *   catalogo Catalogo de la marca     sitio -> nombre -> color -> talla
 *
 * En la portada el sitio va detras del tipo y de la marca a proposito: es una
 * lista de todas las marcas a la vez, y el sitio empieza otra vez por el 1 en
 * cada una. Ordenar por el sitio primero mezclaria las marcas, que es peor de lo
 * que habia. En la pestana del cliente no hace falta: alli las marcas ya vienen
 * agrupadas y en el orden que el cliente eligio.
 *
 * Se puede lanzar dos veces: la segunda no encuentra nada que cambiar.
 */

use Drupal\tec_production\CataloguePosition;

const VISTA = 'tec_products';
const PANTALLA_VIEJA = 'page_2';

/**
 * El sitio del producto, como orden de una vista cuyas filas son productos.
 */
function ordenPorSitio(string $relacion = 'none'): array {
  return [
    'id' => CataloguePosition::COLUMN,
    'table' => CataloguePosition::TABLE,
    'field' => CataloguePosition::COLUMN,
    'relationship' => $relacion,
    'group_type' => 'group',
    'admin_label' => '',
    'plugin_id' => 'standard',
    'order' => 'ASC',
    'expose' => ['label' => '', 'field_identifier' => ''],
    'exposed' => FALSE,
  ];
}

/**
 * La marca, para que sus productos no se mezclen con los de otra.
 *
 * Por el numero del termino y no por el nombre porque el nombre pide una
 * relacion que estas dos pantallas no tienen, y lo que hace falta aqui es que
 * los productos de una marca salgan juntos y siempre en el mismo sitio.
 */
function ordenPorMarca(): array {
  return [
    'id' => CataloguePosition::BRAND_COLUMN,
    'table' => CataloguePosition::BRAND_TABLE,
    'field' => CataloguePosition::BRAND_COLUMN,
    'relationship' => 'none',
    'group_type' => 'group',
    'admin_label' => '',
    'plugin_id' => 'standard',
    'order' => 'ASC',
    'expose' => ['label' => '', 'field_identifier' => ''],
    'exposed' => FALSE,
  ];
}

$vista = \Drupal::entityTypeManager()->getStorage('view')->load(VISTA);
if (!$vista) {
  print "\n  No existe la vista " . VISTA . ".\n\n";
  return;
}

print "\n";
print "=====================================================================\n";
print "  Las pantallas siguen el orden de la marca\n";
print "=====================================================================\n\n";

$pantallas = $vista->get('display');
$cambios = 0;

print "1. Quien ordenaba por el cliente\n";
print "--------------------------------\n";

// Lo que va detras del sitio en cada pantalla. El orden de las claves es el
// orden de los criterios, asi que se reconstruye la lista entera.
$nuevos = [
  'block_1' => ['field_tec_product_type_target_id', 'marca', 'sitio'],
  'block_2' => ['sitio'],
  'block_4' => ['sitio'],
  'block_5' => ['field_tec_product_type_target_id', 'marca', 'sitio'],
];

foreach ($nuevos as $nombre => $receta) {
  if (!isset($pantallas[$nombre])) {
    printf("  %-9s no existe\n", $nombre);
    continue;
  }
  $viejos = $pantallas[$nombre]['display_options']['sorts'] ?? [];
  $arrastraba = FALSE;
  foreach ($viejos as $orden) {
    if (str_starts_with((string) ($orden['plugin_id'] ?? ''), 'draggable_views_sort')) {
      $arrastraba = TRUE;
    }
  }

  $lista = [];
  foreach ($receta as $que) {
    if ($que === 'sitio') {
      $lista[CataloguePosition::COLUMN] = ordenPorSitio();
    }
    elseif ($que === 'marca') {
      $lista[CataloguePosition::BRAND_COLUMN] = ordenPorMarca();
    }
    elseif (isset($viejos[$que])) {
      $lista[$que] = $viejos[$que];
    }
  }

  if ($lista === $viejos) {
    printf("  %-9s ya ordenaba por el sitio\n", $nombre);
    continue;
  }

  $pantallas[$nombre]['display_options']['sorts'] = $lista;
  printf(
    "  %-9s %s -> %s\n",
    $nombre,
    $arrastraba ? 'peso de draggableviews' : (implode(' + ', array_keys($viejos)) ?: 'sin orden'),
    implode(' + ', array_keys($lista))
  );
  $cambios++;
}

print "\n2. El catalogo de la marca lidera con el sitio\n";
print "----------------------------------------------\n";

// Las filas del catalogo son tallas, asi que el sitio se lee subiendo hasta el
// producto por la misma relacion que ya usaba el nombre.
if (isset($pantallas['brand_catalogue'])) {
  $viejos = $pantallas['brand_catalogue']['display_options']['sorts'] ?? [];
  $relacion = $viejos['field_product_name_value']['relationship'] ?? 'reverse__tec_product__field_tec_color_variations';

  $lista = [CataloguePosition::COLUMN => ordenPorSitio($relacion)];
  foreach ($viejos as $clave => $orden) {
    if ($clave !== CataloguePosition::COLUMN) {
      $lista[$clave] = $orden;
    }
  }

  if ($lista === $viejos) {
    print "  ya lideraba con el sitio\n";
  }
  else {
    $pantallas['brand_catalogue']['display_options']['sorts'] = $lista;
    printf("  %s -> %s\n", implode(' + ', array_keys($viejos)), implode(' + ', array_keys($lista)));
    $cambios++;
  }
}
else {
  print "  no existe el catalogo\n";
}

print "\n3. Los botones que llevaban a la pantalla vieja\n";
print "-----------------------------------------------\n";

// Dos cabeceras tenian un boton "Organize products" apuntando a
// /tec_crm/N/reorder. El cliente ya no ordena productos, asi que el boton se va.
foreach (['block_2', 'block_4'] as $nombre) {
  foreach (['header', 'footer', 'empty'] as $zona) {
    foreach ($pantallas[$nombre]['display_options'][$zona] ?? [] as $clave => $area) {
      // El area de texto a secas guarda la cadena en 'content'; la de texto con
      // formato, en 'content.value'. Aqui valen las dos.
      $texto = is_array($area['content'] ?? NULL)
        ? (string) ($area['content']['value'] ?? '')
        : (string) ($area['content'] ?? '');
      if (!str_contains($texto, '/reorder')) {
        continue;
      }
      unset($pantallas[$nombre]['display_options'][$zona][$clave]);
      printf("  %s: fuera el boton de %s (%s)\n", $nombre, $zona, $clave);
      $cambios++;
    }
  }
}

// Y la cabecera de cada grupo de marca de la pestana Products gana el enlace a
// donde se ordena esa marca ahora, que es el sitio natural: al lado de los
// productos de esa marca y de su boton de anadir uno.
$grupo = &$pantallas['block_4']['display_options']['fields']['por_marca']['alter']['text'];
if (isset($grupo) && !str_contains((string) $grupo, '/organize')) {
  $grupo = rtrim((string) $grupo)
    . ' <span class="btn-default"><strong><a href="/taxonomy/term/{{ field_tec_brand_1 }}/organize?destination=/tec_crm/{{ raw_arguments.id }}">Organize</a></strong></span>';
  print "  block_4: la cabecera de cada marca gana el enlace Organize\n";
  $cambios++;
}
elseif (isset($grupo)) {
  print "  block_4: la cabecera de cada marca ya tenia el enlace\n";
}
unset($grupo);

// Restos del arrastre en el mapa de columnas de la pestana, que apuntan a un
// campo que ya no esta en ninguna parte.
foreach (['block_4'] as $nombre) {
  $estilo = &$pantallas[$nombre]['display_options']['style']['options'];
  foreach (['columns', 'info'] as $donde) {
    if (isset($estilo[$donde]['draggableviews'])) {
      unset($estilo[$donde]['draggableviews']);
      printf("  %s: fuera la columna draggableviews del estilo (%s)\n", $nombre, $donde);
      $cambios++;
    }
  }
  unset($estilo);
}

print "\n4. La pantalla de ordenar del cliente se retira\n";
print "-----------------------------------------------\n";

if (isset($pantallas[PANTALLA_VIEJA])) {
  $ruta = $pantallas[PANTALLA_VIEJA]['display_options']['path'] ?? '?';
  unset($pantallas[PANTALLA_VIEJA]);
  printf("  retirada %s, que era /%s\n", PANTALLA_VIEJA, $ruta);
  $cambios++;
}
else {
  printf("  %s ya no estaba\n", PANTALLA_VIEJA);
}

if ($cambios) {
  $vista->set('display', $pantallas);
  $vista->save();
  printf("\n  Guardada la vista con %d cambios.\n", $cambios);
}
else {
  print "\n  Nada que cambiar en la vista.\n";
}

// Que no quede ni un rastro del arrastre en la vista.
$restos = [];
foreach ($vista->get('display') as $nombre => $pantalla) {
  $texto = json_encode($pantalla['display_options'] ?? []);
  if (str_contains((string) $texto, 'draggable')) {
    $restos[] = $nombre;
  }
}
printf("  Pantallas que aun mencionan draggableviews: %s\n", $restos ? implode(', ', $restos) : 'ninguna');
printf("  Modulos de los que depende la vista: %s\n", implode(', ', $vista->getDependencies()['module'] ?? []));

print "\n5. Las filas que guardaban aquellos ordenes\n";
print "-------------------------------------------\n";

$db = \Drupal::database();
if (!$db->schema()->tableExists('draggableviews_structure')) {
  print "  no hay tabla draggableviews_structure\n";
}
else {
  $vistas = \Drupal::configFactory()->listAll('views.view.');
  $existe = [];
  foreach ($vistas as $nombre) {
    $existe[substr($nombre, strlen('views.view.'))] = TRUE;
  }

  $consulta = $db->select('draggableviews_structure', 'd');
  $consulta->addField('d', 'view_name');
  $consulta->addField('d', 'view_display');
  $consulta->addExpression('COUNT(*)', 'n');
  $consulta->groupBy('d.view_name');
  $consulta->groupBy('d.view_display');
  $grupos = $consulta->execute()->fetchAll();

  if (!$grupos) {
    print "  la tabla ya estaba vacia\n";
  }
  foreach ($grupos as $fila) {
    $vive = isset($existe[$fila->view_name]);
    printf(
      "  %-46s %4d filas   %s\n",
      $fila->view_name . ':' . $fila->view_display,
      $fila->n,
      $vive ? 'la vista existe, pero ya no arrastra' : 'la vista ya no existe'
    );
  }

  $borradas = $db->delete('draggableviews_structure')->execute();
  printf("  Borradas %d filas. Desde hoy nadie escribe ahi.\n", $borradas);
}

print "\n";
print "Ahora hay que exportar la configuracion para que esto quede en el disco.\n";
print "\n";
