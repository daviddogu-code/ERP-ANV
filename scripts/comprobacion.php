<?php

/**
 * @file
 * Comprobacion del ERP. Se ejecuta con: drush scr scripts/comprobacion.php
 *
 * Sirve para responder una sola pregunta despues de cada cambio grande:
 * el ERP, ¿sigue haciendo lo mismo que antes?
 *
 * Hace tres cosas. Comprueba que la estructura sigue en pie, crea entidades de
 * verdad para ver si los automatismos de ECA reaccionan, y revisa que no haya
 * quedado nada roto. Todo lo que crea lo borra al terminar, y lo que modifica
 * lo deja como estaba.
 *
 * El 14 de agosto de 2026 cambio de fondo. Hasta esa noche vigilaba recuentos:
 * 4.773 elementos de escandallo, 869 materiales, 18 pedidos. Esa noche se borro
 * todo el contenido de pruebas, y una comprobacion que solo sepa contar
 * contenido no sirve para nada contra una base vacia.
 *
 * Asi que ahora vigila otra cosa. Que los dieciseis tipos de contenido sigan
 * declarados, que los vocabularios de referencia tengan lo que tenian, y que
 * los automatismos sigan reaccionando. Cuanto contenido hay se sigue
 * imprimiendo, marcado como dato, pero desde el 15 de agosto de 2026 ya no da
 * veredicto: ese fue el dia que el ERP empezo a usarse de verdad, y una cifra
 * que sube porque alguien esta trabajando no es una averia. Esto ultimo era lo unico que de verdad
 * probaba algo, y era tambien lo unico que dependia de los datos de prueba: se
 * apoyaba en un material cualquiera de los 869 y en una linea de pedido con
 * precio. Ahora se fabrica sus propios datos, los usa, y los borra. Es mas
 * trabajo, pero deja de depender de que alguien no haya borrado el material del
 * que colgaba la prueba.
 */

// -----------------------------------------------------------------------------
// Cifras de referencia. Del 14 de agosto de 2026, despues del borrado.
// -----------------------------------------------------------------------------
// Los tipos de contenido de cada entidad. Aqui no se cuenta contenido, se
// comprueba que la estructura sigue declarada: si un dia falta un tipo de esta
// lista, alguien ha borrado configuracion sin querer, y eso es mucho mas grave
// que un recuento que baila.
const REFERENCIA_TIPOS = [
  'tec_inventory' => ['tec_bom_item', 'tec_inventory_item', 'tec_inventory_transaction', 'tec_line_item_bom_item'],
  'tec_product' => ['tec_color_variation', 'tec_product', 'tec_size_variation'],
  'tec_line_item' => ['tec_po_line_item', 'tec_sales_order_line_item'],
  'tec_order' => ['tec_draft_order', 'tec_purchase_order', 'tec_sales_order'],
  'tec_crm' => ['tec_contact_organization', 'tec_contact_person'],
  'tec_production_entry' => ['tec_production_entry'],
];

// Las seis entidades de contenido quedaron a cero el 14 de agosto de 2026, y
// esa misma noche dejaron de estarlo: hubo que fabricar un juego de pruebas
// -proveedor, material, producto, color, talla, escandallo, pedido de venta y
// pedido de compra- para poder comprobar que las vistas de calculo daban bien
// los numeros despues de sacarles el sistema de unidades de Oscar. Sin datos no
// hay manera de comprobar una formula.
//
// Estas seis cifras dieron veredicto hasta el 15 de agosto de 2026, y ese dia
// dejaron de darlo. El motivo es que el ERP empezo a usarse: el dueno creo su
// primer material de verdad y su primer pedido de compra desde la pantalla, y
// los recuentos se pusieron en rojo sin que nada estuviera roto, solo porque
// habia mas contenido que el dia que se escribieron. Un guardian que da la
// alarma cada vez que alguien hace su trabajo es un guardian que se acaba
// mirando de reojo, y entonces no avisa el dia que hay que avisar.
//
// Asi que ahora solo informan: se imprimen al lado de lo que trae el juego de
// pruebas, para poder mirarlas de un vistazo, y el aprobado lo dan las
// comprobaciones que no dependen de cuanto contenido haya -la estructura, los
// automatismos y los vocabularios de referencia-, que son las que de verdad
// dicen si el ERP sigue haciendo lo mismo que antes.
const CONTENIDO_CON_JUEGO = [
  'tec_inventory' => 4,
  'tec_product' => 3,
  'tec_line_item' => 2,
  'tec_order' => 2,
  'tec_crm' => 2,
  'tec_production_entry' => 0,
];

// Los materiales son taxonomia, pero son contenido: crece cada vez que alguien
// da de alta uno. Los demas vocabularios son datos de referencia -colores,
// tipos de material, tallas, unidades- que cambian a proposito y pocas veces,
// asi que esos si siguen dando veredicto. Y bien que hacen: fue el recuento de
// tipos de material el que delato el termino "vcvcvcx" que se colo al teclear
// en el formulario del material.
const VOCABULARIOS_DE_CONTENIDO = ['tec_inventory'];

// Los vocabularios. Los tres primeros se vaciaron a proposito; `tec_brands` y
// `tec_patterns` siguen existiendo vacios porque retirarlos es un cambio de
// configuracion aparte: los dos procesos de duplicar producto copian sus
// campos, y hay que desmontarlos antes. Cuando se retiren, estas dos lineas se
// van con ellos.
//
// Los dos terminos de material y la marca son del juego de pruebas de la noche del
// 14 de agosto, igual que las cifras de contenido de mas arriba, y se van con el.
// Por eso esas dos lineas tampoco se tocan a mano: van en TAXONOMIA_DEL_JUEGO y se
// suman o no segun este el juego puesto.
const TAXONOMIA_DEL_JUEGO = [
  'tec_inventory' => 2,
  'tec_brands' => 1,
];

const REFERENCIA_TAXONOMIA = [
  'tec_inventory' => 0,
  'tec_brands' => 0,
  'tec_patterns' => 0,
  'tags' => 0,
  'tec_colors' => 32,
  'tec_crm_contact_type' => 3,
  'tec_materials' => 22,
  'tec_product_types' => 23,
  // Eran catorce hasta la madrugada del 15 de agosto de 2026, cuando el dueno
  // anadio "20 oz" por el formulario del vocabulario mientras se arreglaba la
  // portada de marcas. Se comprobo antes de tocar esta cifra, porque una talla de
  // mas puede ser basura de una prueba y entonces lo que hay que borrar es la
  // talla y no la referencia: el registro dice "Created new term 20 oz" desde
  // /admin/structure/taxonomy/manage/tec_sizes/add con el usuario 1, y el nombre
  // continua la serie de onzas que ya estaba -6, 8, 10, 12, 14, 16 y 18-. O sea
  // que es un dato de verdad y la que estaba vieja era la cuenta.
  'tec_sizes' => 15,
  'tec_units' => 13,
];

const REFERENCIA_VARIOS = [
  // Eran siete hasta el 14 de agosto de 2026. Se borro `devT`, que tenia rol de
  // administrador -o sea permisos totales- con un correo en un dominio que parece
  // una errata del del dueno. Estaba bloqueada desde 2024 y sin nada colgado, pero
  // una llave maestra que no es de nadie conocido no se deja bloqueada, se quita.
  //
  // Y son cinco desde el 15 de agosto de 2026, cuando se repasaron las cuentas a
  // mano. Se fueron cuatro que no eran de nadie de la empresa: `rat`,
  // `david-antiguo`, `executive` y el `manager` viejo, dos de ellas con correo en
  // drusphere.com y otra en actafight.com, dominios ajenos. Y entraron las tres de
  // los empleados: `lukpla`, `coo` y `manager`. Neto, una menos. Las cinco que
  // cuenta son el anonimo, `david` y esas tres.
  'usuarios' => 5,
  // Eran 315 hasta el 14 de agosto de 2026, y se vaciaron en dos pasadas esa
  // noche. Primero las 142 fotos que se quedaron sin dueno al vaciar los datos de
  // prueba, con sus 304 versiones recortadas. Despues los 104 CSV y Excel de las
  // importaciones de ensayo, mas 10 archivos sueltos que ya no tenian ficha. Los
  // 69 que quedaron son 39 fuentes tipograficas, 28 imagenes -entre ellas los
  // iconos de la portada y los de la aplicacion movil- y 2 hojas de estilo. El 70
  // es el icono de marcas, refichado el 15 de agosto: la imagen nunca se fue del
  // disco, pero su ficha murio con la portada. El 71 es el icono de la lista de
  // compra, del mismo dia.
  'ficheros' => 71,
  // Eran doce hasta el 14 de agosto de 2026. Se retiro la pagina /bom, que era
  // Super BOM, porque su funcion la hace ya el tablero de stock. Y volvieron a ser
  // doce el 15, al rehacer la portada de marcas, que llevaba borrada desde antes de
  // las limpiezas: la marca es obligatoria en producto, asi que sin esa pantalla no
  // se podia crear ni una marca ni, por tanto, un producto. Son trece desde ese
  // mismo dia, al anadir el icono de la lista de compra: /purchase existia pero
  // no habia manera de llegar sin escribir la direccion a mano.
  'nodos' => 13,
  // Fueron seis unas horas: se pusieron cuatro para que los iconos de la cola, el
  // registro, el informe y el stock llevaran a su pantalla. Vuelven a ser dos
  // porque el arreglo bueno llego el mismo dia: el enlace sale ahora del campo
  // field_tec_target de cada portada y va derecho, sin rebote.
  'redirecciones' => 2,
  // Eran tres hasta el 15 de agosto de 2026. Se borraron las fichas 5 "Primo
  // products" y 6 "Trust products", huerfanas desde que desaparecio su tipo de
  // importador, tec_products_csv_importer. Con el tipo borrado, la lista de
  // /admin/content/feed se caia entera al pedir su etiqueta, o sea que el ERP se
  // quedaba sin la pantalla de importaciones por dos fichas muertas. No se
  // perdio nada: la tabla tec_gui__feeds_item ya estaba vacia en los tres
  // volcados que hay, y no ha existido nunca una tec_product__feeds_item, asi
  // que ningun importador ha escrito jamas un producto del sistema nuevo.
  // Queda la ficha 4, "Inventory Excel-CSV file", que es la de los materiales.
  'importaciones' => 1,
  // Eran ocho. El nucleo borro el de Super BOM al borrar su nodo.
  'enlaces_menu' => 7,
  // Eran 36, de los que 29 estaban encendidos, hasta la noche del 14 de agosto
  // de 2026. Se borraron los cuatro del sistema de unidades opcionales de
  // Oscar: `process_rxuimsq`, que estaba encendido y aplastaba las unidades de
  // stock y de uso con la de compra en cada guardado, y sus tres clones
  // apagados `uix9n5i`, `pehrnr8` e `idtd6ah`.
  'procesos_eca' => 32,
  'procesos_eca_encendidos' => 28,
  // Eran 146 hasta el 13 de agosto de 2026. Se quedaron en 145 cuando dxpr_theme
  // 8 trajo los colores del tema a sus propios ajustes y desinstalo `color`, que
  // ya no estaba en el nucleo. Los veintiun ajustes de color siguen ahi y no
  // quedo ni un resto del modulo viejo.
  //
  // Vuelven a ser 146 el 14 de agosto: se desinstalo `upgrade_status`, que ya
  // habia cumplido, y `update` se queda para siempre y por eso ahora si cuenta.
  // Es el modulo del nucleo que avisa de las alertas de seguridad, o sea justo lo
  // que le faltaba a este sitio cuando acumulo ochenta y dos sin que nadie se
  // enterara. Si algun dia se decide apagarlo, esta cifra baja a 145.
  'modulos' => 146,
  // De los trece trabajos de cron, ocho encendidos. Los cinco apagados estan
  // apagados a proposito: node_cron y los dos de feeds no hacen falta, y los dos
  // de CRON_QUE_SIGUE_APAGADO son los que se desarmaron el 14 de agosto.
  'trabajos_cron_encendidos' => 8,
  // Y trece en total. Se cuenta el total, y no solo los encendidos, para que el
  // dia que un modulo nuevo traiga su propio hook_cron se entere alguien antes de
  // que lleve un mes corriendo por su cuenta.
  'trabajos_cron' => 13,
];

// Estos dos no se vuelven a encender sin pensarlo. El de las copias volcaba la
// base entera a la carpeta privada del proyecto cada noche y guardaba treinta:
// es lo que dejo 178 volcados y 111 MB dentro del arbol de ficheros, que hubo que
// sacar a mano. El de ECA se despertaba 96 veces al dia sin que ningun proceso
// escuchara. Durante meses el cron no corrio aqui y nadie lo habia notado; el 15
// de agosto de 2026 se ejecuto por primera vez, ya con los dos desarmados, y
// estas dos lineas son las que avisan si vuelven.
const CRON_QUE_SIGUE_APAGADO = ['backup_migrate_cron', 'eca_base_cron'];

// El unico boton de crear al que se le permite esconderse con la lista vacia.
// Patrones esta pendiente de decidir si se retira del ERP, asi que no se le
// arregla un boton a una pantalla que puede desaparecer. El dia que se decida,
// esta lista se queda vacia o se va entera con la vista.
const BOTONES_ESCONDIDOS_A_PROPOSITO = ['tec_patterns:block_1'];

// Los tipos de entidad a los que se les perdona seguir teniendo definicion
// instalada sin existir ya. Esta vacia, y lo suyo es que siga asi.
//
// El 15 de agosto de 2026 habia tres huerfanos y se fueron los tres, porque los
// tres eran la misma cosa: el tipo de subtipo que ECK deriva por cada tipo de
// entidad y que se queda atras cuando se borra el tipo. Ninguno tenia tablas, ni
// campos, ni configuracion, ni nada que apuntara a el.
//
// Dos se llamaban `commerce_store_type` y `commerce_product_variation_type`, y
// el nombre engana: no eran de Drupal Commerce, que aqui no esta instalado ni
// esta en el disco. Los traia `eck`, con la clase EckEntityBundle y las
// etiquetas "assd type" y "temp1 type", o sea dos pruebas de alguien que
// reutilizo dos nombres de maquina de Commerce. Lo que si dejo Commerce, y sigue
// ahi, son 55 tablas sueltas con 180 filas: eso es otra conversacion y no la
// vigila este guardian, que mira definiciones y no tablas.
//
// Se deja el mecanismo puesto para el dia que aparezca una que de verdad haya
// que tolerar. Lo que no se puede hacer ese dia es apagar el guardian.
const DEFINICIONES_INSTALADAS_QUE_SE_TOLERAN = [];

// Herramientas de diagnostico que se instalan durante una actualizacion y se
// quitan al terminar. No cuentan para el total. Vacia desde el 14 de agosto de
// 2026; se deja el mecanismo puesto porque en la proxima subida hara falta otra
// vez.
const MODULOS_TEMPORALES = [];

// -----------------------------------------------------------------------------
// Utilidades.
// -----------------------------------------------------------------------------
$resultados = [];

function comprobar(array &$resultados, string $nombre, bool $bien, string $detalle = ''): void {
  $resultados[] = ['nombre' => $nombre, 'bien' => $bien, 'detalle' => $detalle];
  printf("  [%s] %-50s %s\n", $bien ? 'BIEN' : 'FALLA', $nombre, $detalle);
}

/**
 * Una cifra que se ensena y no se juzga.
 *
 * No entra en el recuento de aprobados a proposito: son cifras que crecen
 * cuando alguien usa el ERP, asi que ponerlas en rojo seria confundir trabajo
 * hecho con algo roto. Se imprimen porque una cifra rara sigue mereciendo una
 * mirada, aunque no merezca una alarma.
 */
function informar(string $nombre, int $cuantos, string $detalle = ''): void {
  printf("  [dato ] %-50s %s\n", $nombre, $cuantos . ($detalle === '' ? '' : ' (' . $detalle . ')'));
}

function titulo(string $t): void {
  echo "\n" . $t . "\n" . str_repeat('-', strlen($t)) . "\n";
}

$etm = \Drupal::entityTypeManager();
$db = \Drupal::database();
$wid_inicial = (int) $db->query('SELECT MAX(wid) FROM {watchdog}')->fetchField();

echo "\n";
echo "===============================================================\n";
echo " COMPROBACION DEL ERP - " . date('d/m/Y H:i:s') . "\n";
echo "===============================================================\n";

// -----------------------------------------------------------------------------
// 1. La estructura sigue en pie.
// -----------------------------------------------------------------------------
titulo('1. La estructura sigue en pie');

$info = \Drupal::service('entity_type.bundle.info');
foreach (REFERENCIA_TIPOS as $entidad => $esperados) {
  $hay = array_keys($info->getBundleInfo($entidad));
  sort($hay);
  $faltan = array_diff($esperados, $hay);
  $sobran = array_diff($hay, $esperados);
  comprobar($resultados, "tipos de $entidad", !$faltan && !$sobran,
    count($hay) . ' de ' . count($esperados)
    . ($faltan ? ', FALTA ' . implode(' y ', $faltan) : '')
    . ($sobran ? ', sobra ' . implode(' y ', $sobran) : ''));
}

// Esta puesto el juego de pruebas? Se le pregunta a los datos, no a un interruptor:
// se cuentan las fichas cuyo nombre empieza por PRUEBA, que es el mismo criterio con
// el que las busca `borrar-el-juego-de-pruebas.php`. No sirve para saber cuantas son
// -las copias de escandallo que fabrica ECA salen con el nombre del material, sin
// PRUEBA delante-, pero para saber si esta o no esta, basta.
$conPrueba = 0;
foreach (['tec_inventory' => 'title', 'tec_product' => 'title', 'tec_crm' => 'title', 'taxonomy_term' => 'name'] as $tipo => $campo) {
  $conPrueba += (int) $etm->getStorage($tipo)->getQuery()
    ->accessCheck(FALSE)
    ->condition($campo, 'PRUEBA', 'STARTS_WITH')
    ->count()
    ->execute();
}
$juegoPuesto = $conPrueba > 0;
echo '  El juego de pruebas esta ' . ($juegoPuesto ? "PUESTO ($conPrueba fichas con PRUEBA delante)" : 'BORRADO')
  . ", que es con lo que se comparan las cifras de contenido de abajo.\n";

foreach (CONTENIDO_CON_JUEGO as $entidad => $delJuego) {
  $n = (int) $etm->getStorage($entidad)->getQuery()->accessCheck(FALSE)->count()->execute();
  informar("contenido de $entidad", $n, $juegoPuesto ? "el juego trae $delJuego" : 'sin juego puesto');
}

foreach (REFERENCIA_TAXONOMIA as $vid => $esperado) {
  $n = (int) $etm->getStorage('taxonomy_term')->getQuery()
    ->accessCheck(FALSE)
    ->condition('vid', $vid)
    ->count()
    ->execute();

  if (in_array($vid, VOCABULARIOS_DE_CONTENIDO, TRUE)) {
    informar("taxonomia $vid", $n, $juegoPuesto ? 'el juego trae ' . (TAXONOMIA_DEL_JUEGO[$vid] ?? 0) : 'sin juego puesto');
    continue;
  }

  if ($juegoPuesto) {
    $esperado += TAXONOMIA_DEL_JUEGO[$vid] ?? 0;
  }
  comprobar($resultados, "taxonomia $vid", $n === $esperado, "$n (se esperaban $esperado)");
}

foreach ([
  'usuarios' => 'user',
  'ficheros' => 'file',
  'nodos' => 'node',
  'redirecciones' => 'redirect',
  'importaciones' => 'feeds_feed',
  'enlaces_menu' => 'menu_link_content',
] as $etiqueta => $entidad) {
  $n = (int) $etm->getStorage($entidad)->getQuery()->accessCheck(FALSE)->count()->execute();
  comprobar($resultados, $etiqueta, $n === REFERENCIA_VARIOS[$etiqueta],
    "$n (se esperaban " . REFERENCIA_VARIOS[$etiqueta] . ")");
}

// Cada portada tiene que decir que pantalla abre, y esa pantalla tiene que
// existir. Esto se comprueba porque el 14 de agosto se descubrio que cuatro de
// los catorce iconos del inicio no llevaban a ninguna parte, y llevaban asi meses
// sin que nada avisara: la prueba de humo pedia las paginas y respondian 200,
// porque responder 200 no es lo mismo que servir para algo.
$portadas = $etm->getStorage('node')->loadMultiple(
  $etm->getStorage('node')->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', 'tec_landing_page')
    ->execute()
);

$validador = \Drupal::service('path.validator');
$sinDestino = [];
$destinoRoto = [];

foreach ($portadas as $portada) {
  if (!$portada->hasField('field_tec_target') || $portada->get('field_tec_target')->isEmpty()) {
    $sinDestino[] = $portada->label();
    continue;
  }
  $ruta = $portada->get('field_tec_target')->first()->getUrl()->toString();
  if (!$validador->getUrlIfValidWithoutAccessCheck($ruta)) {
    $destinoRoto[] = $portada->label() . ' -> ' . $ruta;
  }
}

comprobar($resultados, 'las portadas dicen que pantalla abren', !$sinDestino,
  $sinDestino ? 'sin destino: ' . implode(', ', $sinDestino) : count($portadas) . ' de ' . count($portadas));
comprobar($resultados, 'y esa pantalla existe', !$destinoRoto,
  $destinoRoto ? implode(', ', $destinoRoto) : 'todas');

// Las imagenes de marca: los dos logotipos, los dos favicon y el fondo de la
// pantalla de entrar. Se comprueban porque no tienen ficha en la base de datos
// -el formulario de ajustes del tema las dejo en el disco sin registrarlas-, y
// eso las deja fuera de todo lo que Drupal sabe vigilar. La configuracion las
// nombra, asi que si alguien borra el archivo no salta ningun error: solo
// desaparecen de la pantalla. Se leen de la configuracion y no de una lista,
// para que esto siga valiendo el dia que se cambie el logotipo.
$sistemaFicheros = \Drupal::service('file_system');
$imagenesDeMarca = [];
foreach (\Drupal::configFactory()->listAll() as $nombre) {
  $plano = print_r(\Drupal::config($nombre)->getRawData(), TRUE);
  if (preg_match_all('#(public|private)://[^\'"\s\]]+#', $plano, $encontrados)) {
    foreach ($encontrados[0] as $uri) {
      if (preg_match('/\.(jpe?g|png|gif|webp|ico|svg)$/i', $uri)) {
        $imagenesDeMarca[$uri] = TRUE;
      }
    }
  }
}
$marcaQueFalta = [];
foreach (array_keys($imagenesDeMarca) as $uri) {
  $ruta = $sistemaFicheros->realpath($uri);
  if (!$ruta || !is_file($ruta)) {
    $marcaQueFalta[] = $uri;
  }
}
comprobar($resultados, 'las imagenes de marca siguen en el disco', !$marcaQueFalta,
  $marcaQueFalta ? 'FALTAN: ' . implode(', ', $marcaQueFalta) : count($imagenesDeMarca) . ' de ' . count($imagenesDeMarca));

// Ningun boton de crear puede esconderse cuando la lista esta vacia, que es justo
// cuando es la unica manera de empezar. Un area de cabecera con `empty: false` no
// se pinta sin filas: para un boton de exportar esta bien, para el de crear es una
// trampa. El 14 de agosto aparecieron diez asi recorriendo las portadas, y el 15
// otros cinco que ese barrido no podia ver porque sus displays no cuelgan de
// ninguna portada -el de marcas entre ellos, escondido detras de una portada que
// tampoco existia-. Por eso esto mira la configuracion y no las pantallas: la
// configuracion esta ahi aunque no haya puerta por donde llegar.
$botonesEscondidos = [];
foreach (\Drupal::configFactory()->listAll('views.view.') as $nombre) {
  $vista = \Drupal::config($nombre);
  foreach ($vista->get('display') ?? [] as $displayId => $display) {
    foreach (['header', 'footer'] as $sitio) {
      foreach ($display['display_options'][$sitio] ?? [] as $area) {
        if (($area['plugin_id'] ?? '') !== 'text_custom' || !empty($area['empty'])) {
          continue;
        }
        $texto = (string) ($area['content'] ?? '');
        // Que sea un enlace, y que sea de crear: '+ algo' o una ruta de anadir.
        if (!str_contains($texto, '<a ') || !preg_match('~(\+\s*\w|/add\b|/add/|/add\?)~i', $texto)) {
          continue;
        }
        $botonesEscondidos[] = substr($nombre, strlen('views.view.')) . ':' . $displayId;
      }
    }
  }
}
$botonesDeMas = array_diff($botonesEscondidos, BOTONES_ESCONDIDOS_A_PROPOSITO);
comprobar($resultados, 'ningun boton de crear se esconde con la lista vacia', !$botonesDeMas,
  $botonesDeMas
    ? 'ESCONDIDOS: ' . implode(', ', $botonesDeMas)
    : 'solo ' . implode(', ', BOTONES_ESCONDIDOS_A_PROPOSITO) . ', a proposito');

// Lo que la base tiene apuntado como instalado tiene que coincidir con lo que
// dice la configuracion. Cuando no coincide, el informe de estado se queda en
// rojo permanente, y un rojo que siempre esta ensena a no mirar los rojos: el
// dia que aparezca uno de verdad, nadie lo vera. Los dos que habia, herencia de
// una actualizacion de tipo de entidad que se quedo a medias, se fueron el 15 de
// agosto con tec_gui.
$descuadres = \Drupal::entityDefinitionUpdateManager()->getChangeSummary();
comprobar($resultados, 'ninguna definicion de entidad descuadrada', !$descuadres,
  $descuadres ? 'DESCUADRADO: ' . implode(', ', array_keys($descuadres)) : 'todas al dia');

// De tec_gui no puede quedar nada. Era el producto original del programador
// anterior, sustituido por tec_product, y lo que lo hacia caro es que dejo un
// campo OBLIGATORIO colgado de las lineas de pedido de venta. Esto se mira
// porque volver a entrar es facil: basta con importar configuracion vieja para
// que el tipo de entidad reaparezca y con el su campo obligatorio.
// Ojo con la vista tec_gui_bom_item_viewfields, que lleva "gui" en el nombre y
// NO es de esto: la usan dos campos vivos de los despieces. Por eso aqui no se
// busca el texto "tec_gui" a lo bruto, sino las cuatro cosas que importan.
$restosDeGui = [];
if (\Drupal::config('eck.eck_entity_type.tec_gui')->get('id')) {
  $restosDeGui[] = 'el tipo de entidad ha vuelto';
}
foreach ($db->query("SHOW TABLES LIKE 'tec_gui%'")->fetchCol() as $tabla) {
  $restosDeGui[] = 'tabla ' . $tabla;
}
foreach (\Drupal::configFactory()->listAll('field.storage.') as $nombre) {
  if (\Drupal::config($nombre)->get('settings.target_type') === 'tec_gui') {
    $restosDeGui[] = 'campo ' . \Drupal::config($nombre)->get('id');
  }
}
foreach (\Drupal\user\Entity\Role::loadMultiple() as $rol) {
  foreach ($rol->getPermissions() as $permiso) {
    if (str_contains($permiso, 'tec_gui') && !str_contains($permiso, 'bom_item_viewfields')) {
      $restosDeGui[] = $rol->id() . ': ' . $permiso;
    }
  }
}
comprobar($resultados, 'no queda nada de tec_gui', !$restosDeGui,
  $restosDeGui ? 'HA VUELTO: ' . implode(', ', $restosDeGui) : 'ni tablas ni campos ni permisos');

// Ninguna definicion de entidad instalada puede quedar huerfana: o sea, ningun
// tipo guardado en `entity.definitions.installed` que el sitio ya no conozca.
// Drupal apunta ahi una copia de cada tipo tal y como estaba el dia que se
// instalo, y cuando se retira un tipo y la limpieza se queda a medias, esa copia
// se queda dentro para siempre.
//
// Este guardian nacio de un fallo del de aqui arriba. El de tec_gui daba el
// visto bueno mirando tablas, campos y permisos, que es donde se esconde lo
// caro, pero no miraba el almacen de claves: el 15 de agosto de 2026 los 55
// guardianes estaban en verde con tres huerfanos dentro, y uno era de tec_gui.
//
// Y no lo cazaba nadie mas, porque el descuadre de tres lineas mas arriba
// tampoco puede: getChangeList() recorre los tipos que el sitio conoce HOY para
// compararlos con su copia instalada, asi que a un tipo que ya no existe no lo
// compara con nada y no lo pinta de rojo nunca. Por eso estos restos aguantan
// meses sin que nadie se entere. El caso contrario -un tipo que el sitio conoce
// y que no tiene copia instalada- ese si lo caza el descuadre, y por eso aqui
// solo se mira en un sentido.
//
// Va sin ninguna lista de nombres a proposito. La gracia no es acordarse de los
// tres de aquel dia, es que el dia que se retire otro tipo de entidad y se
// olvide la cola, esto lo cante solo. Se miran las dos entradas que guarda cada
// tipo, la del tipo y la de sus campos, porque una retirada hecha a mano por la
// tabla puede llevarse una y dejar la otra.
$instaladas = [];
foreach (array_keys(\Drupal::keyValue('entity.definitions.installed')->getAll()) as $clave) {
  foreach (['.entity_type', '.field_storage_definitions'] as $cola) {
    if (str_ends_with($clave, $cola)) {
      $instaladas[substr($clave, 0, -strlen($cola))] = TRUE;
    }
  }
}
$huerfanas = array_diff(array_keys($instaladas), array_keys($etm->getDefinitions()),
  DEFINICIONES_INSTALADAS_QUE_SE_TOLERAN);
comprobar($resultados, 'ninguna definicion de entidad instalada huerfana', !$huerfanas,
  $huerfanas
    ? 'HUERFANAS: ' . implode(', ', $huerfanas)
    : count($instaladas) . ' instaladas, todas de tipos que el sitio conoce'
      . (DEFINICIONES_INSTALADAS_QUE_SE_TOLERAN
        ? ', menos ' . implode(' y ', DEFINICIONES_INSTALADAS_QUE_SE_TOLERAN) . ', a proposito'
        : ''));

// -----------------------------------------------------------------------------
// 2. Estructura: modulos y automatismos.
// -----------------------------------------------------------------------------
titulo('2. Los modulos y los automatismos siguen ahi');

$instalados = array_keys(\Drupal::service('extension.list.module')->getAllInstalledInfo());
$temporales = array_intersect($instalados, MODULOS_TEMPORALES);
$modulos = count($instalados) - count($temporales);
comprobar($resultados, 'modulos instalados', $modulos === REFERENCIA_VARIOS['modulos'],
  "$modulos (se esperaban " . REFERENCIA_VARIOS['modulos'] . ")"
  . ($temporales ? ', mas ' . implode(' y ', $temporales) . ' de diagnostico' : ''));

$ecas = $etm->getStorage('eca')->loadMultiple();
$encendidos = count(array_filter($ecas, fn($e) => $e->status()));
comprobar($resultados, 'procesos de ECA', count($ecas) === REFERENCIA_VARIOS['procesos_eca'],
  count($ecas) . " (se esperaban " . REFERENCIA_VARIOS['procesos_eca'] . ")");
comprobar($resultados, 'procesos de ECA encendidos', $encendidos === REFERENCIA_VARIOS['procesos_eca_encendidos'],
  "$encendidos (se esperaban " . REFERENCIA_VARIOS['procesos_eca_encendidos'] . ")");

// El parche de inline_entity_form se aplica a mano y Composer lo borra sin
// avisar cada vez que toca el modulo. Esta linea es la que hay que vigilar.
$ief = DRUPAL_ROOT . '/modules/contrib/inline_entity_form/inline_entity_form.module';
$parche_puesto = file_exists($ief)
  && str_contains(file_get_contents($ief), "'entities']) ?? []");
comprobar($resultados, 'parche de inline_entity_form', $parche_puesto,
  $parche_puesto ? 'sigue aplicado' : 'HA DESAPARECIDO, lo ha borrado Composer');

// El cron. Se comprueba porque `tec_crm` y `tec_brands` siguen en el disco con 49
// copias viejas de configuracion dentro, y un `features:import` despistado puede
// devolver a la vida cosas que se apagaron a mano.
$trabajos = $etm->getStorage('ultimate_cron_job')->loadMultiple();
$cronEncendidos = count(array_filter($trabajos, fn($t) => $t->status()));
comprobar($resultados, 'trabajos de cron encendidos',
  $cronEncendidos === REFERENCIA_VARIOS['trabajos_cron_encendidos'],
  "$cronEncendidos de " . count($trabajos) . " (se esperaban " . REFERENCIA_VARIOS['trabajos_cron_encendidos'] . ")");

comprobar($resultados, 'trabajos de cron en total',
  count($trabajos) === REFERENCIA_VARIOS['trabajos_cron'],
  count($trabajos) . " (se esperaban " . REFERENCIA_VARIOS['trabajos_cron'] . ")");

// Quien dispara el cron tiene que ser el sistema, no las visitas. Con
// automated_cron puesto, el cron lo lanza la primera persona que entra por la
// manana y le toca esperar a ella. Se comprueba tambien que no asome por
// core.extension, que es por donde volveria a colarse en un config:import.
$cronDeVisitas = \Drupal::moduleHandler()->moduleExists('automated_cron');
comprobar($resultados, 'el cron no lo disparan las visitas', !$cronDeVisitas,
  $cronDeVisitas ? 'automated_cron ESTA INSTALADO' : 'automated_cron fuera');

$revividos = array_filter(CRON_QUE_SIGUE_APAGADO,
  fn($id) => isset($trabajos[$id]) && $trabajos[$id]->status());
comprobar($resultados, 'los dos que se desarmaron siguen apagados', !$revividos,
  $revividos ? 'HAN VUELTO: ' . implode(' y ', $revividos) : implode(' y ', CRON_QUE_SIGUE_APAGADO));

// La bandera que lee Schedule::run() es `enabled`, no el `status` de la ficha.
$horarios = $etm->getStorage('backup_migrate_schedule')->loadMultiple();
$horariosVivos = array_filter($horarios, fn($h) => $h->get('enabled'));
comprobar($resultados, 'ningun horario de copias encendido', !$horariosVivos,
  $horariosVivos ? 'ENCENDIDO: ' . implode(', ', array_keys($horariosVivos)) : count($horarios) . ' apagado');

// Ninguna importacion periodica, ni programada ni encolada. Importa desde que el
// cron corre de verdad: una pasada bastaria para lanzar el importador de materiales
// por su cuenta, y con 857 filas de catalogo eso es mucho ruido si se cuela algo mal
// mapeado. Se miran las dos puertas, porque cada una se abre por su lado: el tipo de
// importador decide cada cuanto toca, y la ficha guarda cuando le toca a ella. En la
// ficha, `next` a -1 es nunca, y `queued` a cero es que no esta esperando en la cola.
$periodicos = [];
foreach ($etm->getStorage('feeds_feed_type')->loadMultiple() as $tipo) {
  if ((int) $tipo->getImportPeriod() !== -1) {
    $periodicos[] = 'el tipo ' . $tipo->id() . ' importa cada ' . $tipo->getImportPeriod() . 's';
  }
}
foreach ($etm->getStorage('feeds_feed')->loadMultiple() as $ficha) {
  if ((int) ($ficha->get('next')->value ?? -1) !== -1) {
    $periodicos[] = 'la ficha ' . $ficha->id() . ' tiene hora puesta';
  }
  if ((int) ($ficha->get('queued')->value ?? 0) !== 0) {
    $periodicos[] = 'la ficha ' . $ficha->id() . ' espera en la cola';
  }
}
comprobar($resultados, 'ninguna importacion periodica', !$periodicos,
  $periodicos ? 'ENCENDIDA: ' . implode(', ', $periodicos) : 'todas a nunca');

// Que el cron corre de verdad. Es el guardian que caza el fallo silencioso del
// despliegue: que la linea del crontab no se pegara nunca, o que se rompiera y
// llevemos meses con la queja escrita en un registro que nadie mira. Solo se exige
// donde hay cron del sistema; aqui, en Windows, no lo hay, asi que se informa de la
// edad y no se suspende a nadie por ello.
$ultimoCron = (int) \Drupal::state()->get('system.cron_last', 0);
$horas = $ultimoCron ? (time() - $ultimoCron) / 3600 : NULL;
$hayCronDelSistema = PHP_OS_FAMILY !== 'Windows';
comprobar($resultados, 'el cron ha corrido en las ultimas 24 horas',
  !$hayCronDelSistema || ($horas !== NULL && $horas < 24),
  $horas === NULL
    ? 'NUNCA ha corrido'
    : sprintf('hace %.1f horas%s', $horas, $hayCronDelSistema ? '' : ', y aqui no hay cron del sistema'));

// Sin esta cifra, dblog_cron no recorta nada y el registro crece sin freno: el
// objeto de configuracion no existia, y lo que no existe no vale cero, vale nada.
$filas = \Drupal::config('dblog.settings')->get('row_limit');
comprobar($resultados, 'limite del registro puesto', $filas === 100000,
  $filas === NULL ? 'SIN DEFINIR, el registro crecera sin freno' : "$filas filas");

// -----------------------------------------------------------------------------
// 3. Los automatismos reaccionan.
// -----------------------------------------------------------------------------
// Esta parte se fabrica todo lo que necesita. El orden de creacion importa: el
// material va primero porque todo lo demas cuelga de el, y por eso al borrar se
// recorre la lista al reves. Si se borrara el material teniendo cosas colgadas,
// se tropezaria con el fallo conocido de la bandera que se queda puesta.
titulo('3. Los automatismos reaccionan');

$creadas = [];
$restaurar = [];
$marca = 'COMPROBACION ' . date('His');

$unidad = reset($etm->getStorage('taxonomy_term')->getQuery()
  ->accessCheck(FALSE)->condition('vid', 'tec_units')->range(0, 1)->execute());

$material = $etm->getStorage('taxonomy_term')->create([
  'vid' => 'tec_inventory',
  'name' => $marca . ' material',
  'field_tec_units' => $unidad ? ['target_id' => $unidad] : [],
  'field_tec_stock_level' => 0,
]);
$material->save();
$creadas[] = ['taxonomy_term', $material->id()];

// 3.1 y 3.2 - process_p2q045n.
$bom = $etm->getStorage('tec_inventory')->create([
  'type' => 'tec_bom_item',
  'title' => '',
  'field_tec_inventory' => ['target_id' => $material->id()],
  'field_tec_quantity' => 2.5,
]);
$bom->save();
$creadas[] = ['tec_inventory', $bom->id()];
$bom = $etm->getStorage('tec_inventory')->loadUnchanged($bom->id());
comprobar($resultados, 'escandallo: titulo automatico', $bom->label() === $material->label(),
  "'" . $bom->label() . "'");

$bom->set('field_tec_quantity', 3.75);
$bom->save();
$bom = $etm->getStorage('tec_inventory')->loadUnchanged($bom->id());
$copia = $bom->get('field_tec_quantity_input')->value;
comprobar($resultados, 'escandallo: copia de cantidad', $copia !== NULL && (float) $copia > 0,
  'entrada = ' . var_export($copia, TRUE));

// 3.3 - process_uhiwdqa.
$talla = $etm->getStorage('tec_product')->create([
  'type' => 'tec_size_variation',
  'title' => $marca . ' talla',
]);
$talla->save();
$creadas[] = ['tec_product', $talla->id()];

$bom2 = $etm->getStorage('tec_inventory')->create([
  'type' => 'tec_bom_item',
  'title' => '',
  'field_tec_inventory' => ['target_id' => $material->id()],
  'field_tec_quantity' => 1.25,
  'field_tec_size_variation' => ['target_id' => $talla->id()],
]);
$bom2->save();
$creadas[] = ['tec_inventory', $bom2->id()];
$talla = $etm->getStorage('tec_product')->loadUnchanged($talla->id());
$enganchados = array_column($talla->get('field_tec_bom')->getValue(), 'target_id');
comprobar($resultados, 'escandallo: se engancha a la talla', in_array($bom2->id(), $enganchados),
  count($enganchados) . ' en la talla');

// 3.4 - process_oy5yfqx.
$mov = $etm->getStorage('tec_inventory')->create([
  'type' => 'tec_inventory_transaction',
  'title' => $marca . ' movimiento',
  'field_tec_inventory' => ['target_id' => $material->id()],
  'field_tec_quantity' => 7,
]);
$mov->save();
$creadas[] = ['tec_inventory', $mov->id()];
$material = $etm->getStorage('taxonomy_term')->loadUnchanged($material->id());
$movimientos = array_column($material->get('field_tec_stock_mutations')->getValue(), 'target_id');
comprobar($resultados, 'inventario: el movimiento se engancha', in_array($mov->id(), $movimientos),
  count($movimientos) . ' en el material');

// 3.5 - process_hkreor6.
$cid = reset($etm->getStorage('taxonomy_term')->getQuery()
  ->accessCheck(FALSE)->condition('vid', 'tec_colors')->range(0, 1)->execute());
$cv = $etm->getStorage('tec_product')->create([
  'type' => 'tec_color_variation',
  'title' => '',
  'field_tec_colors' => ['target_id' => $cid],
]);
$cv->save();
$creadas[] = ['tec_product', $cv->id()];
$cv = $etm->getStorage('tec_product')->loadUnchanged($cv->id());
comprobar($resultados, 'color: titulo automatico', $cv->label() !== '' && $cv->label() !== NULL,
  "'" . $cv->label() . "'");

// 3.6 - process_lvy385w. Dos cosas que se averiguaron a base de sonda la noche
// del 14 de agosto, y que conviene no volver a averiguar:
//
// La linea tiene que colgar de una talla. El proceso solo escucha el evento de
// actualizar, y todo lo que calcula gira alrededor del escandallo de la talla:
// una linea con precio y cantidad y nada mas no es un caso real, y el proceso
// la deja en paz, con el total vacio. Por eso se reutiliza la talla del 3.3,
// que ya tiene un escandallo colgado.
//
// Y al guardar, el proceso crea por su cuenta un elemento de escandallo de
// linea y lo engancha. Nadie lo pide y no aparece en ningun sitio, asi que hay
// que ir a buscarlo para borrarlo: si se queda, la siguiente comprobacion
// encuentra contenido donde deberia haber cero y falla sin motivo aparente.
$linea = $etm->getStorage('tec_line_item')->create([
  'type' => 'tec_sales_order_line_item',
  'title' => $marca . ' linea',
  'field_tec_price' => 12.5,
  'field_tec_quantity' => 4,
  'field_tec_size_variation' => ['target_id' => $talla->id()],
]);
$linea->save();
$linea = $etm->getStorage('tec_line_item')->loadUnchanged($linea->id());

$linea->set('field_tec_quantity', 6);
$linea->save();
$linea = $etm->getStorage('tec_line_item')->loadUnchanged($linea->id());
$total = (float) $linea->get('field_tec_line_item_total_number')->value;
comprobar($resultados, 'linea de pedido: recalcula el total', abs($total - 75.0) < 0.01,
  "12,5 x 6 = " . number_format($total, 2, ',', ''));

$de_propina = array_column($linea->get('field_tec_line_item_bom')->getValue(), 'target_id');
foreach ($etm->getStorage('flagging')->loadByProperties(['entity_id' => $linea->id()]) as $bandera) {
  $bandera->delete();
}
$linea->delete();
foreach ($etm->getStorage('tec_inventory')->loadMultiple($de_propina) as $sobra) {
  $sobra->delete();
}

// -----------------------------------------------------------------------------
// Limpieza de lo creado. Al reves del orden de creacion, y quitando antes las
// banderas que ECA pone al vuelo, que si no se quedan colgadas sin dueno.
// -----------------------------------------------------------------------------
foreach (array_reverse($creadas) as [$tipo, $id]) {
  foreach ($etm->getStorage('flagging')->loadByProperties(['entity_id' => $id]) as $bandera) {
    $bandera->delete();
  }
  if ($e = $etm->getStorage($tipo)->load($id)) {
    $e->delete();
  }
}
foreach ($restaurar as [$tipo, $id, $campo, $valor]) {
  if ($e = $etm->getStorage($tipo)->loadUnchanged($id)) {
    $e->set($campo, $valor);
    $e->save();
  }
}

// -----------------------------------------------------------------------------
// 4. Nada roto.
// -----------------------------------------------------------------------------
titulo('4. Nada se ha quedado roto');

// En reposo no debe quedar ninguna bandera puesta en todo el sitio. Cada una es
// un bloqueo que se quedo colgado, y un bloqueo colgado significa que ese
// material o esa linea ya no recalcula y nadie avisa. Hasta el 15 de agosto esto
// hacia una excepcion con tec_eca_gui_lock, que resulto ser una marca huerfana
// sobre una ficha GUI que no existia; se fue con el resto de tec_gui.
$banderas = $db->query('SELECT flag_id, COUNT(*) AS n FROM {flagging} GROUP BY flag_id')->fetchAllKeyed();
comprobar($resultados, 'sin banderas colgadas', !$banderas,
  $banderas ? 'sobran: ' . json_encode($banderas) : 'ninguna puesta');

// La bandera colgada es el sintoma; esto es la causa. Un campo que apunta a un
// termino que ya no existe es lo que hace que ECA se pare en seco: la accion que
// carga el material no encuentra nada, devuelve acceso denegado, y la cadena se
// corta antes de quitar el bloqueo. Desde el 15 de agosto el formulario impide
// borrar un material en uso, pero por codigo se sigue pudiendo, y el importador
// todavia esta por venir, asi que conviene contarlas.
$colgando = [];
foreach ($etm->getStorage('field_storage_config')->loadMultiple() as $almacenCampo) {
  if (!in_array($almacenCampo->getType(), ['entity_reference', 'entity_reference_revisions'], TRUE)
    || $almacenCampo->getSetting('target_type') !== 'taxonomy_term') {
    continue;
  }
  $campo = $almacenCampo->getName();
  try {
    $tabla = $etm->getStorage($almacenCampo->getTargetEntityTypeId())->getTableMapping()->getFieldTableName($campo);
  }
  catch (\Throwable $e) {
    continue;
  }
  if (!$db->schema()->tableExists($tabla)) {
    continue;
  }
  $rotas = (int) $db->query(
    "SELECT COUNT(*) FROM {" . $tabla . "} f
     LEFT JOIN {taxonomy_term_field_data} t ON t.tid = f." . $campo . "_target_id
     WHERE t.tid IS NULL"
  )->fetchField();
  if ($rotas) {
    $colgando[$almacenCampo->getTargetEntityTypeId() . '.' . $campo] = $rotas;
  }
}
comprobar($resultados, 'ninguna referencia a un termino borrado', !$colgando,
  $colgando ? 'ROTAS: ' . json_encode($colgando) : 'todas resuelven');

$errores = $db->query('SELECT COUNT(*) FROM {watchdog} WHERE wid > :w AND severity <= 3 AND type <> :t',
  [':w' => $wid_inicial, ':t' => 'eca'])->fetchField();
comprobar($resultados, 'sin errores nuevos en el registro', (int) $errores === 0, "$errores errores");

// -----------------------------------------------------------------------------
// 5. El circulo de la compra se cierra.
// -----------------------------------------------------------------------------
titulo('5. El circulo de la compra se cierra');

// Desde el 16 de agosto de 2026 un pedido de compra puede dejar de estar en
// camino. Antes no: el estado admitia un unico valor y ninguna linea guardaba lo
// recibido, asi que lo pedido contaba como pendiente para siempre y el tablero
// mandaba comprar cosas que ya estaban en la estanteria.
foreach (
  [
    'tec_line_item' => ['tec_po_line_item' => ['field_tec_quantity_received', 'field_tec_no_more_expected', 'field_tec_close_reason']],
    'tec_inventory' => ['tec_inventory_transaction' => ['field_tec_po_line']],
  ] as $entidad => $tipos
) {
  foreach ($tipos as $tipo => $campos) {
    $definiciones = \Drupal::service('entity_field.manager')->getFieldDefinitions($entidad, $tipo);
    $faltan = array_values(array_diff($campos, array_keys($definiciones)));
    comprobar($resultados, "los datos de la recepcion en $tipo", !$faltan,
      $faltan ? 'FALTA ' . implode(' y ', $faltan) : count($campos) . ' campos');
  }
}

$estados = \Drupal::service('entity_field.manager')
  ->getFieldStorageDefinitions('tec_order')['field_tec_po_status']
  ->getSetting('allowed_values');
comprobar($resultados, 'el pedido de compra se puede cerrar',
  isset($estados['open']) && isset($estados['closed']),
  implode(' y ', array_keys($estados)));

try {
  \Drupal::service('router.route_provider')->getRouteByName('tec_production.po_receive');
  comprobar($resultados, 'la puerta de recibir existe', TRUE, '/tec_order/{id}/receive');
}
catch (\Throwable $e) {
  comprobar($resultados, 'la puerta de recibir existe', FALSE, 'no hay ruta');
}

// Y la comprobacion que de verdad importa: ningun pedido abierto sin nada
// pendiente. Uno asi es justo el fallo que se arreglo -sigue contando como que
// viene de camino aunque ya no venga nada- y solo puede aparecer si el cierre
// automatico deja de funcionar o si alguien reabre un pedido a mano.
$abiertos = $etm->getStorage('tec_order')->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'tec_purchase_order')
  ->condition('field_tec_po_status', 'open')
  ->execute();
$vacios = [];
$por_estado = ['none' => 0, 'partial' => 0, 'full' => 0, 'cancelled' => 0];
foreach ($etm->getStorage('tec_order')->loadMultiple($abiertos) as $pedidoCompra) {
  $pendiente = 0.0;
  foreach ($pedidoCompra->get('field_tec_line_items')->referencedEntities() as $lineaCompra) {
    $pendiente += \Drupal\tec_production\Purchasing::outstanding($lineaCompra);
  }
  if ($pendiente <= 0.0) {
    $vacios[] = (string) $pedidoCompra->label();
  }
  $por_estado[\Drupal\tec_production\Purchasing::receiptState($pedidoCompra)]++;
}
comprobar($resultados, 'ningun pedido abierto sin nada pendiente', !$vacios,
  $vacios ? 'DEBERIAN ESTAR CERRADOS: ' . implode(', ', $vacios) : count($abiertos) . ' abiertos, todos esperando algo');
informar('pedidos abiertos por estado de entrega', count($abiertos),
  'sin recibir ' . $por_estado['none'] . ', a medias ' . $por_estado['partial']);

// -----------------------------------------------------------------------------
// Resumen.
// -----------------------------------------------------------------------------
$mal = array_filter($resultados, fn($r) => !$r['bien']);
echo "\n===============================================================\n";
printf(" RESULTADO: %d bien, %d mal, de %d comprobaciones\n",
  count($resultados) - count($mal), count($mal), count($resultados));
echo "===============================================================\n";

if ($mal) {
  echo "\nLo que falla:\n";
  foreach ($mal as $r) {
    echo '  - ' . $r['nombre'] . ': ' . $r['detalle'] . "\n";
  }
  echo "\nNO SEGUIR hasta entender por que.\n";
}
else {
  echo "\nTodo correcto. Se puede seguir.\n";
}
echo "\n";
