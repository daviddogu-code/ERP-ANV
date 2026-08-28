<?php

/**
 * @file
 * Se va el "excel lover": la bandera, las etiquetas, las vistas y el modo.
 *
 *   php vendor\bin\drush.php scr scripts/se-va-el-excel-lover.php
 *
 * Oscar llamo "Excel lover" al pedido de un clic, y el nombre se quedo pegado a
 * treinta y tantos objetos de configuracion. En pantalla ya no se veia -- el
 * boton dice "+ Order" y la bandera se llama "TEC Order: Draft Sales order" --
 * pero asomaba en la direccion a la que lleva el boton:
 *
 *   /flag/flag/tec_draft_order_excel_lover/121?destination=/customer/121
 *
 * Esa direccion se ve al pasar el raton, se copia en un correo y se pega en un
 * mensaje, y ahi el nombre deja de ser una broma interna.
 *
 * El nombre nuevo estaba ya escrito en el hermano: la bandera de compras es
 * `tec_order_draft_po`, asi que la de ventas es **`tec_order_draft_sales`**. Lo
 * demas sigue la misma regla, decir lo que la cosa hace:
 *
 * | Que | Antes | Ahora |
 * |---|---|---|
 * | bandera de ventas | `tec_draft_order_excel_lover` | `tec_order_draft_sales` |
 * | bandera de cobro | `tec_order_checkout_excel` | `tec_order_checkout` |
 * | modo de la ficha | `excel_lover_button` | `sales_order_button` |
 * | hoja de las dos pantallas | `tec_excel_lover_orders` | `tec_order_draft_screens` |
 * | consulta de ECA, ventas | `tec_order_eca_create_draft_excel_lover` | `tec_order_eca_create_draft_sales` |
 * | consulta de ECA, compras | `tec_order_eca_create_draft_po_excel_lover` | `tec_order_eca_create_draft_purchase` |
 * | pantalla apagada y repetida | `tec_excel_lover_form` | `sales_order_draft_form_unused` |
 *
 * ## Por que no se renombra, se sustituye
 *
 * En Drupal el nombre de una entidad de configuracion **no se cambia**: se crea
 * otra igual con el nombre nuevo y se borra la vieja. Y entre esas dos cosas hay
 * que mover todo lo que la citaba, que aqui son 110 sitios: los permisos de
 * cuatro papeles, las dos acciones de la bandera, el campo de la bandera en seis
 * fichas de contacto, la pieza de Layout Builder de una septima, dos vistas de
 * pedidos, tres procesos de ECA, ocho ajustes de campo y una regla de CSS.
 *
 * El orden importa y es este: primero se crea la copia con el nombre nuevo,
 * luego se mueven las citas, y solo al final se borra la vieja. Al reves, entre
 * el borrado y el arreglo hay un instante en que los permisos de cuatro papeles
 * apuntan a una bandera que no existe.
 *
 * ## La mina de ECA, otra vez
 *
 * Un proceso de ECA vive en dos ficheros: `eca.eca.X` es lo que se ejecuta y
 * `eca.model.X` es el dibujo que ve el editor, y **el editor regenera el primero
 * desde el segundo cada vez que alguien pulsa guardar**. Cambiar solo el
 * ejecutable seria dejar el nombre viejo escondido en el dibujo, esperando a que
 * alguien abra el editor para volver a ponerlo.
 *
 * Aqui el nombre entra en los dos ficheros por el mismo sitio que en todo lo
 * demas, porque el dibujo es texto. Y luego se hace la pregunta que importa: se
 * le entrega el dibujo al modeller de ECA, que regenera el ejecutable, y se
 * comprueba que **no cambia nada**. Con red: si cambiara algo -- porque el
 * dibujo estuviera atrasado respecto a lo que se ejecuta -- el guion devuelve el
 * ejecutable a como estaba y lo cuenta, en vez de dejarlo regenerado a medias.
 *
 * Esa comprobacion va al final y no al principio, por algo que se aprendio a la
 * primera: **el modeller valida cada valor contra lo que existe**, y una bandera
 * o una vista que todavia no se ha creado no es un valor permitido. Preguntarle
 * antes de crear las cosas nuevas es que diga no a todo.
 *
 * ## Lo que no se toca, y por que
 *
 * - **El historial del diario.** `docs/backlog.md` cuenta lo que paso el dia que
 *   paso, con los nombres de ese dia. Reescribirlo seria mentir sobre el pasado.
 * - **Los guiones de limpieza de una vez** (`mirar-antes-de-borrar.php` y
 *   companyia), que son listas de la compra de una limpieza ya hecha.
 * - **Las direcciones `/o/draft/N` y `/po/draft/N`**, que nunca llevaron el
 *   nombre y son las que la gente tiene en el historial del navegador.
 *
 * Se puede lanzar dos veces: la segunda no encuentra nada que cambiar.
 */

/**
 * Nombres maquina. `strtr()` prueba primero las claves mas largas, asi que
 * `..._po_excel_lover` no se lo come `..._excel_lover`.
 */
const MAQUINA = [
  'tec_order_eca_create_draft_po_excel_lover' => 'tec_order_eca_create_draft_purchase',
  'tec_order_eca_create_draft_excel_lover' => 'tec_order_eca_create_draft_sales',
  'tec_draft_order_excel_lover' => 'tec_order_draft_sales',
  'tec_excel_lover_orders' => 'tec_order_draft_screens',
  'tec_excel_lover_form' => 'sales_order_draft_form_unused',
  'tec_order_checkout_excel' => 'tec_order_checkout',
  'excel_lover_button' => 'sales_order_button',
  // La clase de CSS sale del nombre de la bandera con guiones.
  'flag-tec-draft-order-excel-lover' => 'flag-tec-order-draft-sales',
];

/**
 * Lo que lee una persona. De mas largo a mas corto por el mismo motivo.
 */
const ETIQUETAS = [
  'ECA Order: Create draft purchase order - Excel lover style ECA Model' => 'ECA Order: Create draft purchase order',
  'ECA Order: Create draft order - Excel lover style ECA Model' => 'ECA Order: Create draft sales order',
  'TEC Order: Create Purchase order - Excel lover' => 'TEC Order: Create Purchase order',
  'TEC Order: Create Sales order - Excel lover' => 'TEC Order: Create Sales order',
  'Excel lover purchase order buttons attachment' => 'Purchase order draft buttons attachment',
  'TEC Sales order: Excel lover editable Line items' => 'TEC Sales order: Editable line items',
  'Excel lover sales order buttons attachment' => 'Sales order draft buttons attachment',
  'TEC Sales order: Excel lover dynamic total' => 'TEC Sales order: Draft dynamic total',
  'Excel Lover Purchase order form page' => 'Purchase order draft page',
  'Excel Lover Sales order form page' => 'Sales order draft page',
  'TEC Order: Excel lover checkout' => 'TEC Order: Checkout',
  'Excel lover totals block' => 'Sales order draft totals block',
  'TEC Order: Checkout Excel' => 'TEC Order: Checkout',
  'TEC Excel lover orders' => 'TEC Order draft screens',
  'Excel totals attachment' => 'Sales order totals attachment',
  'Excel lover editable views' => 'Order draft editable views',
  'Excel lover button' => 'Sales order button',
  'Excel lover orders' => 'Order draft screens',
];

/**
 * Las entidades que hay que volver a crear con otro nombre, en orden de
 * creacion: un modo antes de la pantalla que lo usa.
 */
const SUSTITUIR = [
  ['entity_view_mode', 'tec_crm.excel_lover_button', 'tec_crm.sales_order_button', []],
  [
    'entity_view_display',
    'tec_crm.tec_contact_organization.excel_lover_button',
    'tec_crm.tec_contact_organization.sales_order_button',
    ['mode' => 'sales_order_button'],
  ],
  ['flag', 'tec_draft_order_excel_lover', 'tec_order_draft_sales', []],
  ['flag', 'tec_order_checkout_excel', 'tec_order_checkout', []],
  ['asset_injector_css', 'tec_excel_lover_orders', 'tec_order_draft_screens', []],
  ['view', 'tec_order_eca_create_draft_excel_lover', 'tec_order_eca_create_draft_sales', []],
  ['view', 'tec_order_eca_create_draft_po_excel_lover', 'tec_order_eca_create_draft_purchase', []],
];

const PROCESOS = ['process_sclj26d', 'process_kryibry', 'process_v0gguvo'];

// La vista que no existe y que ocho ajustes de campo siguen citando.
const VISTA_FANTASMA = 'tec_order_draft_order_line_items_excel_lover';

$gestor = \Drupal::entityTypeManager();
$fabrica = \Drupal::configFactory();
$mapa = MAQUINA + ETIQUETAS;

$problemas = 0;
$aviso = function (string $texto) use (&$problemas): void {
  print '  MAL   ' . $texto . "\n";
  $problemas++;
};

print "\n";
print "=====================================================================\n";
print "  Se va el excel lover\n";
print "=====================================================================\n\n";

// ---------------------------------------------------------------------------
// 1. Las copias con el nombre nuevo.
// ---------------------------------------------------------------------------
print "\n";
print "Las entidades que se sustituyen\n";
print str_repeat('-', 70) . "\n";

$porBorrar = [];

foreach (SUSTITUIR as [$tipo, $viejo, $nuevo, $extra]) {
  $almacen = $gestor->getStorage($tipo);
  $original = $almacen->load($viejo);
  $copia = $almacen->load($nuevo);

  if (!$original && $copia) {
    printf("  %-22s %-42s ya estaba hecho\n", $tipo, $viejo);
    continue;
  }
  if (!$original) {
    $aviso($tipo . ' ' . $viejo . ': no existe ni el viejo ni el nuevo');
    continue;
  }
  if (!$copia) {
    $nueva = $original->createDuplicate();
    $nueva->set('id', $nuevo);
    foreach ($extra as $clave => $valor) {
      $nueva->set($clave, $valor);
    }
    $nueva->save();
    printf("  %-22s %-42s -> %s\n", $tipo, $viejo, $nuevo);
  }
  else {
    printf("  %-22s %-42s la copia ya existia\n", $tipo, $viejo);
  }
  $porBorrar[] = [$tipo, $viejo];
}

// ---------------------------------------------------------------------------
// 2. Las citas: 110 sitios, uno por uno, por clave y por valor.
// ---------------------------------------------------------------------------
print "\n";
print "Las citas\n";
print str_repeat('-', 70) . "\n";

// Los objetos que estan a punto de borrarse no se tocan: se van enteros.
$intocables = [];
foreach ($porBorrar as [$tipo, $viejo]) {
  $definicion = $gestor->getDefinition($tipo);
  $intocables[] = $definicion->getConfigPrefix() . '.' . $viejo;
}

$renombrar = function ($valor) use (&$renombrar, $mapa) {
  if (!is_array($valor)) {
    return is_string($valor) ? strtr($valor, $mapa) : $valor;
  }
  $salida = [];
  foreach ($valor as $clave => $hijo) {
    $nueva = is_string($clave) ? strtr($clave, $mapa) : $clave;
    $salida[$nueva] = $renombrar($hijo);
  }
  return $salida;
};

$tocados = [];
foreach ($fabrica->listAll() as $nombre) {
  if (in_array($nombre, $intocables, TRUE)) {
    continue;
  }
  $datos = $fabrica->get($nombre)->getRawData();
  $nuevos = $renombrar($datos);

  // La vista que no existe: se le quita la cita a los ajustes de campo en vez
  // de renombrarla, porque no hay nada al otro lado.
  if (isset($nuevos['settings']['allowed_views'][VISTA_FANTASMA])) {
    unset($nuevos['settings']['allowed_views'][VISTA_FANTASMA]);
  }

  // Los permisos de un papel van en orden alfabetico, y el nombre nuevo empieza
  // por otra letra. Lo ordena Drupal al guardar por la via normal, y aqui se
  // guarda a pelo, asi que se ordena a mano o el fichero exportado se
  // reordenaria solo el dia que alguien toque el papel desde la pantalla.
  if (str_starts_with($nombre, 'user.role.') && isset($nuevos['permissions'])) {
    sort($nuevos['permissions']);
  }

  if ($nuevos === $datos) {
    continue;
  }
  $fabrica->getEditable($nombre)->setData($nuevos)->save();
  $tocados[] = $nombre;
}

printf("  %d objetos de configuracion movidos\n", count($tocados));
foreach ($tocados as $nombre) {
  print "    " . $nombre . "\n";
}

// ---------------------------------------------------------------------------
// 3. Los dos titulos que quedan repetidos, que no son un renombrado.
// ---------------------------------------------------------------------------
print "\n";
print "Las dos pantallas que no usa nadie\n";
print str_repeat('-', 70) . "\n";

$lineas = $gestor->getStorage('view')->load('tec_order_sales_order_line_items');
if ($lineas) {
  $pantallas = $lineas->get('display');
  $cambio = FALSE;

  // `page_2` y la copia apagada se llamaban igual, y despues del renombrado
  // seguirian llamandose igual. Se dice cual es la que no se usa.
  foreach ([
    'sales_order_draft_form_unused' => 'Sales order draft page (unused)',
    'attachment_2' => 'Sales order totals attachment (unused)',
  ] as $pantalla => $titulo) {
    if (isset($pantallas[$pantalla]) && ($pantallas[$pantalla]['display_title'] ?? '') !== $titulo) {
      printf("  %-32s \"%s\" -> \"%s\"\n", $pantalla, $pantallas[$pantalla]['display_title'] ?? '', $titulo);
      $pantallas[$pantalla]['display_title'] = $titulo;
      $cambio = TRUE;
    }
  }

  if ($cambio) {
    $lineas->set('display', $pantallas)->save();
  }
  else {
    print "  ya lo dicen\n";
  }
}

// ---------------------------------------------------------------------------
// 4. Ahora si: borrar las viejas.
// ---------------------------------------------------------------------------
print "\n";
print "Las viejas\n";
print str_repeat('-', 70) . "\n";

foreach (array_reverse($porBorrar) as [$tipo, $viejo]) {
  $vieja = $gestor->getStorage($tipo)->load($viejo);
  if (!$vieja) {
    printf("  %-22s %-42s ya no estaba\n", $tipo, $viejo);
    continue;
  }
  $vieja->delete();
  printf("  %-22s %-42s borrada\n", $tipo, $viejo);
}

// Y las cuatro que se quedaron a medio camino. Las dos acciones de cada bandera
// las crea el modulo Flag, y al borrar la bandera las borra buscandolas por su
// identificador. Aqui no las encontro: el nombre nuevo ya habia entrado dentro
// de ellas con todo lo demas, asi que por dentro se llamaban de la bandera
// nueva mientras el nombre del objeto seguia siendo el viejo. Quedaron cuatro
// objetos que Drupal busca por el nombre y en los que encuentra un desconocido.
//
// Ese desacuerdo es justo la senal: un objeto cuyo nombre acaba en una cosa y
// cuyo `id` dice otra es un resto. Si por dentro dijera lo mismo que su nombre
// no seria un resto, seria algo que no se ha movido, y eso se cuenta en vez de
// borrarlo.
foreach ($fabrica->listAll() as $nombre) {
  $viejo = NULL;
  foreach (array_keys(MAQUINA) as $candidato) {
    if (str_contains($nombre, $candidato)) {
      $viejo = $candidato;
      break;
    }
  }
  if ($viejo === NULL) {
    continue;
  }

  $dentro = (string) $fabrica->get($nombre)->get('id');
  if ($dentro !== '' && !str_ends_with($nombre, $dentro)) {
    $fabrica->getEditable($nombre)->delete();
    printf("  %-22s %-42s resto, borrado (por dentro decia %s)\n", 'objeto', $nombre, $dentro);
    continue;
  }
  $aviso('sigue existiendo ' . $nombre . ' y por dentro se llama igual: no es un resto, es algo sin mover');
}

// ---------------------------------------------------------------------------
// 5. Los dibujos de ECA contra sus ejecutables.
//
// Aqui el nombre ya esta cambiado en los dos ficheros de cada proceso, porque el
// dibujo es texto y ha entrado por el mismo sitio que todo lo demas. Lo que
// falta es la pregunta que de verdad importa: si alguien abre el editor y pulsa
// guardar, ¿sale lo mismo? Se le entrega el dibujo tal como esta al modeller de
// ECA, que regenera el ejecutable, y se comprueba que **no cambia nada**.
//
// Y esto va al final, no al principio, por lo que se aprendio a la primera: el
// modeller valida cada valor contra lo que existe, y una bandera o una vista que
// todavia no se ha creado no es un valor permitido.
// ---------------------------------------------------------------------------
print "\n";
print "Los tres procesos de ECA: el dibujo y lo que se ejecuta\n";
print str_repeat('-', 70) . "\n";

$aplanar = function (array $datos, string $camino = '') use (&$aplanar): array {
  $plano = [];
  foreach ($datos as $clave => $valor) {
    $aqui = $camino === '' ? (string) $clave : $camino . '.' . $clave;
    if (is_array($valor)) {
      $plano += $aplanar($valor, $aqui);
      continue;
    }
    $plano[$aqui] = $valor;
  }
  return $plano;
};

$modeller = \Drupal::service('eca.service.modeller')->getModeller('bpmn_io');

foreach (PROCESOS as $proceso) {
  $xml = (string) $fabrica->get('eca.model.' . $proceso)->get('modeldata');
  if ($xml === '') {
    printf("  %-16s no tiene dibujo\n", $proceso);
    continue;
  }
  if (!$modeller) {
    $aviso('no hay modeller bpmn_io: no se puede comprobar ' . $proceso);
    continue;
  }

  $antes = $fabrica->get('eca.eca.' . $proceso)->getRawData();
  $modeller->save($xml);
  if ($modeller->hasError()) {
    $aviso('ECA no acepta el dibujo de ' . $proceso);
    continue;
  }

  $fabrica->reset('eca.eca.' . $proceso);
  $despues = $fabrica->get('eca.eca.' . $proceso)->getRawData();

  $planoAntes = $aplanar($antes);
  $planoDespues = $aplanar($despues);
  $diferencias = [];
  foreach ($planoAntes as $clave => $valor) {
    if (!array_key_exists($clave, $planoDespues)) {
      $diferencias[] = 'se ha ido ' . $clave;
      continue;
    }
    if ($planoDespues[$clave] !== $valor) {
      $diferencias[] = $clave . ': "' . $valor . '" -> "' . $planoDespues[$clave] . '"';
    }
  }
  foreach (array_diff_key($planoDespues, $planoAntes) as $clave => $valor) {
    $diferencias[] = 'ha aparecido ' . $clave;
  }

  if ($diferencias) {
    // El dibujo no cuadra con lo que se ejecuta. Se devuelve el ejecutable y se
    // cuenta, que es mejor que dejarlo regenerado a medias.
    $fabrica->getEditable('eca.eca.' . $proceso)->setData($antes)->save();
    $aviso($proceso . ': regenerar el dibujo cambia el ejecutable, devuelto. '
      . implode(' | ', array_slice($diferencias, 0, 6)));
    continue;
  }

  printf("  %-16s el dibujo genera exactamente lo que ya se ejecuta\n", $proceso);
}

// ---------------------------------------------------------------------------
// 6. Que no quede nada.
// ---------------------------------------------------------------------------
print "\n";
print "Lo que queda en la configuracion\n";
print str_repeat('-', 70) . "\n";

$restos = [];
$buscar = function ($valor, array $camino, string $nombre) use (&$buscar, &$restos): void {
  if (is_array($valor)) {
    foreach ($valor as $clave => $hijo) {
      if (is_string($clave) && preg_match('/excel[ _-]?lover/i', $clave)) {
        $restos[] = $nombre . ' clave ' . implode('.', array_merge($camino, [$clave]));
      }
      $buscar($hijo, array_merge($camino, [(string) $clave]), $nombre);
    }
    return;
  }
  if (is_string($valor) && preg_match('/excel[ _-]?lover/i', $valor)) {
    $restos[] = $nombre . ' ' . implode('.', $camino);
  }
};

foreach ($fabrica->listAll() as $nombre) {
  // El nombre del objeto cuenta tanto como lo que lleva dentro, y no se ve
  // mirando dentro: las cuatro acciones de las banderas tenian el nombre viejo
  // y el contenido nuevo, y por eso pasaron la primera vez.
  foreach (array_keys(MAQUINA) as $candidato) {
    if (str_contains($nombre, $candidato)) {
      $restos[] = $nombre . ' (el nombre del objeto)';
      break;
    }
  }
  $buscar($fabrica->get($nombre)->getRawData(), [], $nombre);
}

if ($restos) {
  foreach ($restos as $resto) {
    print "  queda: " . $resto . "\n";
  }
  $aviso(count($restos) . ' apariciones siguen en la configuracion');
}
else {
  print "  ninguna\n";
}

// Las acciones de la bandera las crea y las borra el propio modulo Flag. Se
// comprueba que ha hecho las dos cosas, porque si no el boton no tiene accion.
print "\n";
print "Las acciones de las banderas\n";
print str_repeat('-', 70) . "\n";

foreach (['tec_order_draft_sales', 'tec_order_checkout'] as $bandera) {
  foreach (['flag', 'unflag'] as $que) {
    $id = 'flag_action.' . $bandera . '_' . $que;
    $existe = (bool) $gestor->getStorage('action')->load($id);
    printf("  %-56s %s\n", $id, $existe ? 'esta' : 'NO ESTA');
    if (!$existe) {
      $problemas++;
    }
  }
}
foreach (['tec_draft_order_excel_lover', 'tec_order_checkout_excel'] as $bandera) {
  foreach (['flag', 'unflag'] as $que) {
    $id = 'flag_action.' . $bandera . '_' . $que;
    if ($gestor->getStorage('action')->load($id)) {
      $aviso('sigue existiendo la accion vieja ' . $id);
    }
  }
}

print "\n";
print "=====================================================================\n";
if ($problemas === 0) {
  print "  Hecho. El nombre ya no esta en ninguna parte de la configuracion.\n";
}
else {
  printf("  %d cosas mal. Mirarlas antes de exportar.\n", $problemas);
}
print "=====================================================================\n\n";
