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

// Y que las cifras de verdad se puedan escribir. Drupal valida cada casilla
// decimal contra un paso que saca de los decimales de la columna, y esa
// comprobacion suya esta rota: Number::validStep() mide en coma flotante el
// resto de dividir el valor por el paso, y lo compara contra un margen fijo de
// paso/2^24. El resto crece con el tamano del numero y el margen no, asi que a
// partir de 10^(9 - decimales) no pasa nada, por redondo que sea. Con seis
// decimales el techo son mil y rechazaba un coste de 799,70; con cinco son diez
// mil y rechazaba un factor de 10000.
//
// El rodeo pone el paso en 'any' y comprueba la regla de verdad contando los
// digitos detras del punto. Si algun dia Drupal lo arregla, esto lo dice.
$pasoSigueRoto = !\Drupal\Component\Utility\Number::validStep('10000', pow(0.1, 5));
comprobar($resultados, 'el paso de Drupal sigue roto, el rodeo hace falta',
  $pasoSigueRoto,
  $pasoSigueRoto ? 'techo en 10^(9-decimales)' : 'Drupal lo ha arreglado, se puede quitar el rodeo');

// Lo que importa no es que el rodeo este escrito, sino que llegue a la pantalla.
// Se construye la ficha de material de verdad y se mira casilla por casilla, asi
// que un campo decimal nuevo queda cubierto sin tocar el guardian.
$fichaMaterial = \Drupal::service('entity.form_builder')->getForm(
  $etm->getStorage('taxonomy_term')->create(['vid' => 'tec_inventory'])
);
$sinRodeo = [];
$sinComprobacion = [];
$derivados = ['field_tec_price_uos', 'field_tec_price'];
foreach (\Drupal\Core\Render\Element::children($fichaMaterial) as $nombreCampo) {
  $casilla = $fichaMaterial[$nombreCampo]['widget'][0]['value'] ?? NULL;
  if (!is_array($casilla) || ($casilla['#type'] ?? NULL) !== 'number') {
    continue;
  }
  $almacenCampo = \Drupal\field\Entity\FieldStorageConfig::loadByName('taxonomy_term', $nombreCampo);
  if (!$almacenCampo || $almacenCampo->getType() !== 'decimal') {
    continue;
  }
  if (($casilla['#step'] ?? NULL) !== 'any') {
    $sinRodeo[] = $nombreCampo;
  }
  // Los dos costes derivados no se comprueban a proposito: el servidor los
  // recalcula al guardar, asi que juzgar lo que deje el navegador solo podria
  // frenar un guardado por una cifra que iba a ser sustituida.
  $valida = in_array('_admin_form_styles_check_decimals', $casilla['#element_validate'] ?? [], TRUE);
  if (!$valida && !in_array($nombreCampo, $derivados, TRUE)) {
    $sinComprobacion[] = $nombreCampo;
  }
}
comprobar($resultados, 'toda casilla decimal del material se libra del paso',
  $sinRodeo === [], implode(', ', $sinRodeo) ?: 'ninguna se queda con el paso roto');
comprobar($resultados, 'y las que se teclean cuentan sus decimales',
  $sinComprobacion === [], implode(', ', $sinComprobacion) ?: 'todas comprobadas');

// Y que ninguna ficha guardada haya quedado encerrada: el valor ya estaba en la
// base de datos y el formulario lo revalida en cada guardado, asi que subir los
// decimales de una columna puede bloquear fichas que nadie ha tocado.
$encerradas = [];
foreach (\Drupal::configFactory()->listAll('field.storage.') as $nombreAlmacen) {
  $almacenCampo = \Drupal::config($nombreAlmacen);
  if ($almacenCampo->get('type') !== 'decimal') {
    continue;
  }
  $tipoEntidad = $almacenCampo->get('entity_type');
  $nombreCampo = $almacenCampo->get('field_name');
  $paso = pow(0.1, (int) $almacenCampo->get('settings.scale'));
  if (!$etm->hasDefinition($tipoEntidad)) {
    continue;
  }
  $tabla = $tipoEntidad . '__' . $nombreCampo;
  if (!$db->schema()->tableExists($tabla)) {
    continue;
  }
  foreach ($db->select($tabla, 't')->fields('t', ['entity_id', $nombreCampo . '_value'])->execute() as $fila) {
    $valor = $fila->{$nombreCampo . '_value'};
    if (is_numeric($valor) && !\Drupal\Component\Utility\Number::validStep($valor, $paso)) {
      $encerradas[] = $tipoEntidad . ' ' . $fila->entity_id . ': ' . $nombreCampo;
    }
  }
}
informar('fichas que Drupal solo dejaria guardar con el rodeo', count($encerradas),
  $encerradas ? implode(', ', array_slice($encerradas, 0, 4)) : 'ninguna');

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

// Que la puerta exista no sirve de nada si no hay por donde entrar. El boton vive
// en attachment_6 de la vista de lineas, al lado de Edit order y Print/PDF, y no
// en la pestana del tema: DXPR la pinta flotando sobre la cabecera, ilegible, y
// su bloque solo lo ve el rol administrador. Cualquiera que retoque esa vista por
// la pantalla de administracion puede llevarse el boton por delante sin notarlo.
$condicional = \Drupal::config('views.view.tec_order_sales_order_line_items')
  ->get('display.attachment_6.display_options.fields.views_conditional_field') ?? [];
comprobar($resultados, 'la ficha del pedido abierto ofrece el boton de recibir',
  str_contains($condicional['then'] ?? '', '/receive'),
  isset($condicional['then']) ? 'en attachment_6' : 'no hay campo condicional');
comprobar($resultados, 'y el pedido cerrado no lo ofrece',
  !str_contains($condicional['or'] ?? '', '/receive') && str_contains($condicional['or'] ?? '', '/print'),
  'cerrado solo imprime');

// La lista de pedidos a proveedores tiene que preguntar por el estado de compra.
// Llevaba desde el dia que se hizo preguntando por field_tec_order_status, que es
// el de los pedidos de VENTA: la pantalla esta filtrada a pedidos de compra, asi
// que la columna del estado salia vacia el cien por cien de las veces y el mismo
// campo inexistente decidia los botones, de modo que el lapiz de editar tampoco
// aparecia nunca. Como es un copiar y pegar facil de repetir, se vigila.
$lista = \Drupal::config('views.view.tec_supplier_orders')->get('display.default.display_options.fields') ?? [];
comprobar($resultados, 'la lista de proveedores lee el estado de compra',
  isset($lista['field_tec_po_status']) && !isset($lista['field_tec_order_status']),
  isset($lista['field_tec_order_status']) ? 'PREGUNTA POR EL DE VENTAS' : 'field_tec_po_status');
comprobar($resultados, 'y sus botones tambien',
  ($lista['views_conditional_field']['if'] ?? '') === 'field_tec_po_status_1'
  && str_contains($lista['nothing']['alter']['text'] ?? '', '/po/draft/'),
  (string) ($lista['views_conditional_field']['if'] ?? 'no hay condicional'));

// La columna de la entrega, que es la que distingue "no ha llegado nada" de "ha
// llegado la mitad". No hay campo detras: sale de un manejador propio que llama a
// Purchasing::receiptState(), asi que se comprueban las dos cosas, que el
// manejador exista y que las dos listas lo usen.
$manejadores = \Drupal::service('plugin.manager.views.field')->getDefinitions();
comprobar($resultados, 'el manejador de la entrega existe',
  isset($manejadores['tec_purchase_receipt_state']), 'tec_purchase_receipt_state');
foreach ([
  'views.view.tec_supplier_orders' => 'display.default.display_options.fields',
  'views.view.tec_orders_orders' => 'display.block_3.display_options.fields',
] as $vista => $donde) {
  $campos = \Drupal::config($vista)->get($donde) ?? [];
  comprobar($resultados, 'la columna de la entrega en ' . substr($vista, strlen('views.view.')),
    ($campos['tec_receipt_state']['plugin_id'] ?? '') === 'tec_purchase_receipt_state',
    isset($campos['tec_receipt_state']) ? 'puesta' : 'FALTA');
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
$sinRellenar = [];
$por_estado = ['none' => 0, 'partial' => 0, 'full' => 0, 'cancelled' => 0];
foreach ($etm->getStorage('tec_order')->loadMultiple($abiertos) as $pedidoCompra) {
  $pendiente = 0.0;
  $pedido = 0.0;
  foreach ($pedidoCompra->get('field_tec_line_items')->referencedEntities() as $lineaCompra) {
    $pendiente += \Drupal\tec_production\Purchasing::outstanding($lineaCompra);
    if ($lineaCompra->hasField('field_tec_quantity') && !$lineaCompra->get('field_tec_quantity')->isEmpty()) {
      $pedido += (float) $lineaCompra->get('field_tec_quantity')->value;
    }
  }
  if ($pendiente <= 0.0) {
    // Sin cantidades no es un pedido que haya que cerrar, es uno que nadie ha
    // rellenado. La bandera de la ficha del proveedor crea justo eso: un pedido
    // con una linea por material y las cantidades en blanco para que alguien las
    // escriba. Meterlos en el mismo saco que un pedido ya servido dejaria el
    // guardian en rojo para siempre por algo que no esta mal.
    if ($pedido > 0.0) {
      $vacios[] = (string) $pedidoCompra->label();
    }
    else {
      $sinRellenar[] = (string) $pedidoCompra->label();
    }
  }
  $por_estado[\Drupal\tec_production\Purchasing::receiptState($pedidoCompra)]++;
}
comprobar($resultados, 'ningun pedido servido sigue abierto', !$vacios,
  $vacios ? 'DEBERIAN ESTAR CERRADOS: ' . implode(', ', $vacios) : count($abiertos) . ' abiertos, ninguno servido del todo');
informar('pedidos abiertos por estado de entrega', count($abiertos),
  'sin recibir ' . $por_estado['none'] . ', a medias ' . $por_estado['partial']);
if ($sinRellenar) {
  informar('pedidos abiertos sin cantidades, a medio escribir', count($sinRellenar),
    implode(', ', $sinRellenar));
}

// -----------------------------------------------------------------------------
// 6. Cada pedido tiene su numero, y solo uno.
// -----------------------------------------------------------------------------
titulo('6. Cada pedido tiene su numero, y solo uno');

// Desde el 16 de agosto de 2026 el nombre de un pedido se decide en un unico
// sitio, OrderNumber, llamado desde un gancho de guardado. Antes lo decidian
// tres: las dos ECA que crean pedidos y la lista de la compra. Los tres contaban
// distinto y dos de ellos solo veian pedidos publicados, cuando todos nacen sin
// publicar, asi que el segundo pedido de un cliente recibia el numero que ya
// tenia el primero. Dos pedidos con el mismo numero no son una numeracion.
$formato = '/^(?:.{1,6} )?\d{2}-\d{3}$/';
$malFormato = [];
$repetidos = [];
$sinCodigo = [];
$vistos = [];
$cuantosPedidos = 0;

foreach ($etm->getStorage('tec_order')->loadMultiple() as $pedidoNum) {
  $tipo = $pedidoNum->bundle();
  if (!\Drupal\tec_production\OrderNumber::handles($tipo)) {
    continue;
  }
  $cuantosPedidos++;
  $nombre = (string) $pedidoNum->label();

  if (!preg_match($formato, $nombre)) {
    $malFormato[] = $pedidoNum->id() . ': "' . $nombre . '"';
  }

  $clave = $tipo . '|' . $nombre;
  if (isset($vistos[$clave])) {
    $repetidos[] = '"' . $nombre . '" (' . $vistos[$clave] . ' y ' . $pedidoNum->id() . ')';
  }
  $vistos[$clave] = $pedidoNum->id();

  // Un pedido cuyo contacto tiene codigo corto pero cuyo nombre no lo lleva solo
  // puede salir de una cosa: que el contacto se pegase al pedido despues del
  // primer guardado, que es cuando se decide el nombre. Es el fallo que tenian
  // las dos ECA y por el que se les cambio el orden de los pasos, asi que si
  // vuelve a aparecer es que alguien ha vuelto a moverlos.
  $campo = \Drupal\tec_production\OrderNumber::contactField($tipo);
  $contactoNum = $pedidoNum->hasField($campo) && !$pedidoNum->get($campo)->isEmpty()
    ? $pedidoNum->get($campo)->entity
    : NULL;
  $codigo = \Drupal\tec_production\OrderNumber::shortCode($contactoNum);
  if ($codigo !== '' && !str_starts_with($nombre, $codigo . ' ')) {
    $sinCodigo[] = $pedidoNum->id() . ': "' . $nombre . '" deberia empezar por "' . $codigo . '"';
  }
}

comprobar($resultados, 'todos los pedidos con el formato CODIGO AA-NNN', !$malFormato,
  $malFormato ? implode(', ', $malFormato) : $cuantosPedidos . ' pedidos');
comprobar($resultados, 'ningun numero repartido dos veces', !$repetidos,
  $repetidos ? 'REPETIDOS: ' . implode(', ', $repetidos) : 'todos distintos');
comprobar($resultados, 'el codigo del contacto esta en el nombre', !$sinCodigo,
  $sinCodigo ? implode('; ', $sinCodigo) : 'ninguno se quedo sin codigo');

// El campo del codigo corto, uno solo y en las dos clases de ficha. Faltaba en
// las de persona, y una persona puede ser cliente: el selector de cliente de un
// pedido de venta filtra por tipo de contacto, no por clase de ficha.
foreach (['tec_contact_organization', 'tec_contact_person'] as $clase) {
  $campoCodigo = \Drupal::service('entity_field.manager')->getFieldDefinitions('tec_crm', $clase);
  comprobar($resultados, 'el codigo corto en las fichas de ' . str_replace('tec_contact_', '', $clase),
    isset($campoCodigo['field_tec_customer_code']) && !isset($campoCodigo['field_tec_supplier_code']),
    isset($campoCodigo['field_tec_supplier_code']) ? 'HA VUELTO EL DE PROVEEDOR' : 'uno solo');
}

// Que la numeracion siga en un solo sitio. Se comprueba en los dos ficheros de
// cada proceso, no en uno: una ECA vive en eca.eca.X, que es lo que se ejecuta, y
// en eca.model.X, que es el dibujo que edita la pantalla. El editor regenera el
// primero desde el segundo cada vez que alguien guarda, asi que un parche puesto
// solo en el ejecutable dura hasta el siguiente clic. Asi es justo como estaba
// esto antes: el dibujo no sabia nada del codigo corto ni de los ceros.
foreach (['process_sclj26d' => 'ventas', 'process_kryibry' => 'compras'] as $proceso => $que) {
  // Solo el titulo del PEDIDO. Estos procesos tambien ponen el titulo de cada
  // linea, que es otra cosa y tiene que seguir ahi.
  $acciones = \Drupal::config('eca.eca.' . $proceso)->get('actions') ?? [];
  $ponenTitulo = [];
  foreach ($acciones as $id => $accion) {
    if (($accion['configuration']['field_name'] ?? '') === 'title'
      && ($accion['configuration']['object'] ?? '') === 'order') {
      $ponenTitulo[] = $id;
    }
  }
  comprobar($resultados, "la ECA de $que ya no pone el nombre", !$ponenTitulo,
    $ponenTitulo ? 'LO PONE EN ' . implode(', ', $ponenTitulo) : 'lo pone el gancho');

  // Lo mismo en el dibujo, y hay que leerlo como XML por la misma razon: un paso
  // que pone el titulo de una linea es legitimo, uno que pone el del pedido no.
  $dibujo = (string) \Drupal::config('eca.model.' . $proceso)->get('modeldata');
  $enElDibujo = [];
  $xmlDibujo = new DOMDocument();
  if ($dibujo !== '' && @$xmlDibujo->loadXML($dibujo)) {
    $buscador = new DOMXPath($xmlDibujo);
    foreach ($buscador->query('//*[local-name()="task"]') as $paso) {
      $valores = [];
      foreach ($buscador->query('*[local-name()="extensionElements"]/*[local-name()="field"]', $paso) as $campo) {
        $valores[$campo->getAttribute('name')] = trim($campo->textContent);
      }
      if (($valores['field_name'] ?? '') === 'title' && ($valores['object'] ?? '') === 'order') {
        $enElDibujo[] = $paso->getAttribute('id');
      }
    }
  }
  comprobar($resultados, "y el dibujo de $que dice lo mismo", !$enElDibujo,
    $enElDibujo ? 'EL DIBUJO AUN LO PONE EN ' . implode(', ', $enElDibujo) : 'de acuerdo con el ejecutable');

  // Y el orden de los pasos: el contacto tiene que quedar pegado al pedido antes
  // del guardado que decide el nombre, no despues.
  $guardar = $acciones['Activity_0eek2xm']['successors'][0]['id'] ?? '';
  $contactoPaso = $acciones['Activity_01wk5lh']['successors'][0]['id'] ?? '';
  comprobar($resultados, "en $que el contacto se pega antes de guardar",
    $contactoPaso === 'Activity_0eek2xm' && $guardar !== 'Activity_01wk5lh',
    $contactoPaso === 'Activity_0eek2xm' ? 'crear, contacto, guardar' : 'GUARDA ANTES DE PEGAR EL CONTACTO');
}

// Y la comprobacion general de la misma trampa, para los treinta y dos procesos.
// Un proceso de ECA vive en dos ficheros y el editor regenera uno desde el otro,
// asi que un parche puesto solo en el que se ejecuta dura hasta el siguiente clic
// de cualquiera. No hace falta comparar los ficheros enteros: basta con que los
// dos conozcan los mismos pasos. Si el dibujo tiene un paso de menos, ese paso se
// evapora en cuanto alguien guarde.
$descuadrados = [];
foreach (\Drupal::configFactory()->listAll('eca.eca.') as $nombreEca) {
  $proceso = substr($nombreEca, strlen('eca.eca.'));
  $enEjecutable = array_keys(\Drupal::config($nombreEca)->get('actions') ?? []);
  $dibujoProceso = (string) \Drupal::config('eca.model.' . $proceso)->get('modeldata');
  if ($dibujoProceso === '') {
    continue;
  }
  $xmlProceso = new DOMDocument();
  if (!@$xmlProceso->loadXML($dibujoProceso)) {
    $descuadrados[] = $proceso . ' (el dibujo no es XML valido)';
    continue;
  }
  $buscadorProceso = new DOMXPath($xmlProceso);
  $enDibujo = [];
  foreach ($buscadorProceso->query('//*[@*[local-name()="modelerTemplate"]]') as $paso) {
    foreach ($paso->attributes as $atributo) {
      if ($atributo->localName === 'modelerTemplate' && str_starts_with($atributo->value, 'org.drupal.action.')) {
        $enDibujo[] = $paso->getAttribute('id');
      }
    }
  }
  $soloEjecutable = array_diff($enEjecutable, $enDibujo);
  $soloDibujo = array_diff($enDibujo, $enEjecutable);
  if ($soloEjecutable || $soloDibujo) {
    $descuadrados[] = $proceso . ': ' . ($soloEjecutable ? 'solo se ejecuta ' . implode(',', $soloEjecutable) : '')
      . ($soloEjecutable && $soloDibujo ? '; ' : '')
      . ($soloDibujo ? 'solo dibujado ' . implode(',', $soloDibujo) : '');
  }
}
comprobar($resultados, 'ninguna ECA con el dibujo desfasado', !$descuadrados,
  $descuadrados ? implode(' | ', $descuadrados) : count(\Drupal::configFactory()->listAll('eca.eca.')) . ' procesos, dibujo y ejecutable de acuerdo');

// Y que nadie se haya hecho su propio contador otra vez.
$listaCompra = file_get_contents(DRUPAL_ROOT . '/modules/custom/tec_production/src/Form/PurchaseListForm.php');
comprobar($resultados, 'la lista de la compra no se hace su numero',
  !str_contains($listaCompra, 'function nextOrderNumber'),
  str_contains($listaCompra, 'function nextOrderNumber') ? 'SE LO VUELVE A HACER' : 'lo pide al gancho');

// -----------------------------------------------------------------------------
// 7. El IVA sale de la ficha del proveedor y se queda en el pedido.
// -----------------------------------------------------------------------------
titulo('7. El IVA sale de la ficha del proveedor y se queda en el pedido');

// Tres situaciones distintas son una sola pregunta: si ese proveedor cobra IVA
// tailandes. El de fuera no, el tailandes pequeno sin registrar tampoco, el
// registrado si. Asi que no hay una regla por paises con excepciones pegadas,
// hay un dato por proveedor. Y el porcentaje vive en un solo ajuste, no en cada
// ficha, para que el dia que el pais lo cambie se toque una vez.
comprobar($resultados, 'el porcentaje esta en un solo ajuste',
  \Drupal::config('tec_production.settings')->get('vat_rate') !== NULL,
  'vat_rate = ' . var_export(\Drupal::config('tec_production.settings')->get('vat_rate'), TRUE));

foreach (['tec_contact_organization', 'tec_contact_person'] as $bundleIva) {
  comprobar($resultados, 'las fichas de ' . $bundleIva . ' dicen como tributan',
    (bool) \Drupal\field\Entity\FieldConfig::loadByName('tec_crm', $bundleIva, \Drupal\tec_production\Vat::TREATMENT_FIELD));
}

$tratamientos = array_keys(\Drupal\tec_production\Vat::treatments());
$permitidos = array_keys(\Drupal::config('field.storage.tec_crm.' . \Drupal\tec_production\Vat::TREATMENT_FIELD)->get('settings.allowed_values') ?? []);
// Los valores permitidos se guardan como lista de parejas, no como mapa, asi que
// hay que sacar el valor de cada pareja antes de comparar.
$permitidos = $permitidos && is_numeric($permitidos[0])
  ? array_column(\Drupal::config('field.storage.tec_crm.' . \Drupal\tec_production\Vat::TREATMENT_FIELD)->get('settings.allowed_values'), 'value')
  : $permitidos;
comprobar($resultados, 'las tres respuestas siguen siendo tres',
  !array_diff($tratamientos, $permitidos) && !array_diff($permitidos, $tratamientos),
  implode(', ', $permitidos));

comprobar($resultados, 'el pedido de compra guarda el porcentaje que le toco',
  (bool) \Drupal\field\Entity\FieldConfig::loadByName('tec_order', 'tec_purchase_order', \Drupal\tec_production\Vat::RATE_FIELD));

// En ventas no, porque esto es sobre comprar. El IVA que se cobra al cliente es
// otra conversacion y no se resuelve copiando esta.
comprobar($resultados, 'y en ventas no, que es otra conversacion',
  !\Drupal\field\Entity\FieldConfig::loadByName('tec_order', 'tec_sales_order', \Drupal\tec_production\Vat::RATE_FIELD),
  'solo en compras');

// La razon de copiar el porcentaje al pedido es que no se mueva nunca mas. Si
// algun pedido se ha quedado sin el, es que se creo antes de que el campo
// existiera, y eso no se arregla inventandole uno.
$sinIva = [];
$conIva = 0;
foreach ($etm->getStorage('tec_order')->loadMultiple() as $pedidoIva) {
  if ($pedidoIva->bundle() !== 'tec_purchase_order' || !$pedidoIva->hasField(\Drupal\tec_production\Vat::RATE_FIELD)) {
    continue;
  }
  if ($pedidoIva->get(\Drupal\tec_production\Vat::RATE_FIELD)->isEmpty()) {
    $sinIva[] = (string) $pedidoIva->label();
  }
  else {
    $conIva++;
  }
}
informar('pedidos de compra con su porcentaje escrito', $conIva);
if ($sinIva) {
  informar('pedidos de compra de antes del IVA, sin porcentaje', count($sinIva),
    implode(', ', array_slice($sinIva, 0, 10)));
}

// Y que el 7 se pueda tocar sin linea de comandos. Nacio en un fichero de
// configuracion sin pantalla, como los cinco nodos de los iconos de la portada,
// y eso vale para quien construye el ERP y no sirve para quien lleva la empresa.
$rutas = \Drupal::service('router.route_provider');
try {
  $pantallaAjustes = $rutas->getRouteByName('tec_production.company_settings');
}
catch (\Throwable $e) {
  $pantallaAjustes = NULL;
}
comprobar($resultados, 'hay una pantalla para tocar el porcentaje',
  $pantallaAjustes !== NULL,
  $pantallaAjustes ? $pantallaAjustes->getPath() : 'NO EXISTE, solo por linea de comandos');

// Con permiso propio: si pidiera el de configurar el sitio entero, cambiar el
// IVA obligaria a ser administrador, que es justo lo que no queremos.
$permisoAjustes = $pantallaAjustes ? ($pantallaAjustes->getRequirement('_permission') ?? '') : '';
comprobar($resultados, 'y no obliga a ser administrador del sitio para entrar',
  $permisoAjustes === 'administer tec company settings', $permisoAjustes ?: 'sin permiso');

$conElPermiso = [];
foreach (\Drupal\user\Entity\Role::loadMultiple() as $rolAjustes) {
  if (!$rolAjustes->isAdmin() && $rolAjustes->hasPermission('administer tec company settings')) {
    $conElPermiso[] = $rolAjustes->id();
  }
}
comprobar($resultados, 'y alguien que no es administrador lo tiene',
  (bool) $conElPermiso, implode(', ', $conElPermiso) ?: 'NADIE, la pantalla es inalcanzable en la practica');

// Un formulario de configuracion sobre ajustes sin esquema suelta avisos, y
// tec_production.settings no tuvo esquema durante todo un ano.
$esquema = \Drupal::service('config.typed');
comprobar($resultados, 'los ajustes del modulo tienen esquema',
  $esquema->hasConfigSchema('tec_production.settings'),
  $esquema->hasConfigSchema('tec_production.settings') ? 'las once claves' : 'SIN ESQUEMA');

// Y la puerta desde la ficha, que es donde surge la pregunta.
$estilosEnlace = file_get_contents(DRUPAL_ROOT . '/modules/custom/admin_form_styles/admin_form_styles.module');
comprobar($resultados, 'la ficha del proveedor enlaza al porcentaje',
  str_contains($estilosEnlace, '_admin_form_styles_attach_vat_rate_link'),
  'debajo de VAT treatment');

// Y que el gancho siga siendo quien lo pone, porque si alguien lo mueve a una
// ECA volvemos a tener dos sitios decidiendo lo mismo.
$moduloIva = file_get_contents(DRUPAL_ROOT . '/modules/custom/tec_production/tec_production.module');
comprobar($resultados, 'lo pone el gancho de guardado y nadie mas',
  str_contains($moduloIva, 'Vat::RATE_FIELD') && str_contains($moduloIva, 'Vat::forContact'),
  'en tec_production_tec_order_presave');

$fichasIva = 0;
$proveedoresIva = 0;
foreach ($etm->getStorage('tec_crm')->loadMultiple() as $contactoIva) {
  if (!$contactoIva->hasField(\Drupal\tec_production\Vat::TREATMENT_FIELD) || !tec_crm_ux_entity_is_supplier($contactoIva)) {
    continue;
  }
  $proveedoresIva++;
  if (!$contactoIva->get(\Drupal\tec_production\Vat::TREATMENT_FIELD)->isEmpty()) {
    $fichasIva++;
  }
}
informar('proveedores que ya dicen como tributan', $fichasIva, 'de ' . $proveedoresIva);

// -----------------------------------------------------------------------------
// 8. El pais de un contacto se elige de una vez.
// -----------------------------------------------------------------------------
titulo('8. El pais de un contacto se elige de una vez');

// El campo de direccion cambia de forma segun el pais: Tailandia pide provincia
// y Singapur no. Eso lo resuelve el ajax del propio modulo Address. Estuvo
// apagado, porque tiraba el proceso de Apache, y en su lugar un boton escondido
// recargaba la pagina entera cada vez que el selector cambiaba. Con doscientos
// cincuenta y siete paises en la lista eso era inservible: cualquier roce del
// teclado enviaba el formulario a medio elegir y el pais parecia deseleccionarse
// solo. Si alguien vuelve a apagarlo, que se sepa aqui y no en una ficha nueva.
foreach (['tec_contact_organization', 'tec_contact_person'] as $bundlePais) {
  $fichaPais = \Drupal::service('entity.form_builder')->getForm(
    $etm->getStorage('tec_crm')->create(['type' => $bundlePais])
  );
  $cajaPais = $fichaPais['field_tec_address']['widget'][0]['address'] ?? [];
  // El envoltorio address_country guarda el selector de verdad bajo su misma
  // clave, asi que hay que bajar un piso antes de mirar.
  $selectorPais = $cajaPais['country_code']['country_code'] ?? $cajaPais['country_code'] ?? [];

  comprobar($resultados, 'el pais de ' . $bundlePais . ' cambia sin recargar',
    !empty($selectorPais['#ajax']['callback'])
      && ($selectorPais['#ajax']['wrapper'] ?? '') === ($cajaPais['#wrapper_id'] ?? ''),
    empty($selectorPais['#ajax'])
      ? 'sin ajax: alguien lo ha vuelto a quitar'
      : (string) ($selectorPais['#ajax']['wrapper'] ?? ''));

  comprobar($resultados, 'y no vuelve el boton que recargaba la pagina',
    !isset($fichaPais['tec_crm_ux_address_rebuild']),
    'la prueba entera esta en scripts/se-elige-el-pais.php');
}

// -----------------------------------------------------------------------------
// 9. El pedido de compra ensena lo que de verdad se paga.
// -----------------------------------------------------------------------------
titulo('9. El pedido de compra ensena lo que de verdad se paga');

// El porcentaje se estampaba en cada pedido desde el punto 7 y no lo leia nadie:
// las tres pantallas de compra sumaban las lineas y llamaban Total al resultado.
// Con un proveedor tailandes eso no es lo que se paga.
$vistaIva = \Drupal\views\Entity\View::load('tec_order_sales_order_line_items');
$pantallasIva = $vistaIva ? $vistaIva->get('display') : [];

// La ficha del pedido y el impreso lo pintan en el servidor, cada una con el
// mismo manejador, escrito una vez, para que no puedan discrepar en un satang.
foreach (['block_2' => 'la ficha del pedido', 'page_3' => 'el impreso'] as $pantallaIva => $comoSeLlama) {
  $pieIva = array_keys($pantallasIva[$pantallaIva]['display_options']['footer'] ?? []);
  comprobar($resultados, $comoSeLlama . ' lleva su pie de IVA',
    in_array('tec_purchase_vat_totals', $pieIva, TRUE),
    implode(', ', $pieIva) ?: 'SIN PIE');
}

// Y ninguna de las dos usa ya el total de antes, o saldrian dos totales que
// dicen cosas distintas, uno encima del otro.
$atiendeA = array_keys($pantallasIva['attachment_1']['display_options']['displays'] ?? []);
comprobar($resultados, 'y ya no arrastran el total sin IVA de antes',
  !array_intersect(['block_2', 'page_3'], $atiendeA),
  implode(', ', $atiendeA));

// El mismo trozo sigue poniendo el total en ventas, que no se ha tocado.
comprobar($resultados, 'ventas conserva el suyo intacto',
  !array_diff(['block_1', 'page_4'], $atiendeA),
  implode(', ', $atiendeA));

comprobar($resultados, 'el manejador esta dado de alta en Views',
  isset(\Drupal::service('views.views_data')->get('views')['tec_purchase_vat_totals']['area']),
  'tec_production.views.inc');

// El borrador no puede pintarlo en el servidor: las cifras se mueven mientras se
// teclea. Lo suma el navegador, y el porcentaje se lo tiene que pasar el gancho,
// porque esta en el pedido y no en ninguna de las filas que tiene delante.
$moduloPie = file_get_contents(DRUPAL_ROOT . '/modules/custom/tec_production/tec_production.module');
comprobar($resultados, 'al borrador de compra le llega el porcentaje',
  str_contains($moduloPie, 'tecPurchaseVat') && str_contains($moduloPie, "str_starts_with((string) \$view->getPath(), 'po/draft')"),
  'en tec_production_views_pre_render');

// Y el borrador de ventas corre el mismo javascript. Sin porcentaje se comporta
// como siempre, asi que lo que hay que vigilar es que siga preguntando por el en
// vez de dar por hecho que hay uno.
$javascriptPie = (string) \Drupal::config('asset_injector.js.draft_for_ace_company_view')->get('code');
comprobar($resultados, 'y el javascript solo pone el pie si lo recibe',
  str_contains($javascriptPie, 'function vatRate()') && str_contains($javascriptPie, "return [['Grand Total', subtotal, true]];"),
  'sin porcentaje, ventas se queda como estaba');

// Y que las tres pantallas sigan llamando igual a lo mismo. La misma columna se
// llamaba "Unit of Purchase (UoP)" en el borrador, "Unit" en la ficha y "Unit"
// en el impreso; el coste era "Cost by UoP", "Cost per UoP" y "Price"; la
// cantidad, "Quantity" y "Qty.". Quien mira dos seguidas tenia que traducir, y
// traducir es donde se equivoca uno.
$rotulosDe = function (string $pantalla) use ($vistaIva): array {
  $rotulos = [];
  foreach ($vistaIva->getDisplay($pantalla)['display_options']['fields'] ?? [] as $campo) {
    if (empty($campo['exclude'])) {
      $rotulos[] = (string) ($campo['label'] ?? '');
    }
  }
  return $rotulos;
};
$rotulosBorrador = $rotulosDe('page_1');
$rotulosFicha = $rotulosDe('block_2');

// La ficha lleva dos columnas de mas, Received y su marca de "closed short".
// Quitadas esas, tiene que decir exactamente lo mismo que el borrador.
$fichaSinRecepcion = array_values(array_diff($rotulosFicha, ['Received', '']));
comprobar($resultados, 'las dos pantallas de compra llaman igual a lo mismo',
  $rotulosBorrador && $fichaSinRecepcion === $rotulosBorrador,
  implode(' | ', $fichaSinRecepcion));

// Y esas dos van pegadas a Quantity: cuanto se pidio y cuanto ha llegado, una al
// lado de la otra.
$dondeLaCantidad = array_search('Quantity', $rotulosFicha, TRUE);
comprobar($resultados, 'y Received va pegado a Quantity, con su marca detras',
  $dondeLaCantidad !== FALSE && array_slice($rotulosFicha, $dondeLaCantidad + 1, 2) === ['Received', ''],
  implode(' | ', array_slice($rotulosFicha, $dondeLaCantidad === FALSE ? 0 : $dondeLaCantidad, 3)));

// Que es lo que deja el Sub total de ultimo. Las cifras del pie se pegan al
// borde derecho de la tabla, asi que caen bajo la ultima columna sea cual sea:
// con Received ahi, la suma de la derecha no era la suma de la columna de
// encima, y el ojo tenia que saltar de una punta a otra para comprobarlo.
comprobar($resultados, 'de modo que el pie cae debajo del Sub total',
  end($rotulosFicha) === 'Sub total', (string) end($rotulosFicha));

// El impreso no es una pantalla mas: es el papel que se manda al proveedor, y
// tenia ademas otro orden, con la cantidad delante del material y el precio
// delante de la unidad. Aqui se compara entero, columna a columna, porque en
// este no sobra ninguna: son las mismas siete del borrador.
$rotulosImpreso = $rotulosDe('page_3');
comprobar($resultados, 'y el papel que se manda al proveedor, tambien',
  $rotulosImpreso === $rotulosBorrador, implode(' | ', $rotulosImpreso));

// La cabecera ya dice Quantity; encima de cada casilla es ruido. La regla existia
// pero con un solo selector, y ese solo cogia la pantalla de ventas.
$cssBorrador = (string) \Drupal::config('asset_injector.css.tec_excel_lover_orders')->get('code');
comprobar($resultados, 'la casilla de cantidad va corta y sin rotulo encima',
  str_contains($cssBorrador, '.views-field-form-field-field-tec-quantity-1 label')
    && str_contains($cssBorrador, '.views-field-form-field-field-tec-quantity-1 input'),
  'en las dos pantallas de borrador');

// -----------------------------------------------------------------------------
// 10. Un pedido emitido vale lo que valia el dia que se hizo.
// -----------------------------------------------------------------------------
titulo('10. Un pedido emitido vale lo que valia el dia que se hizo');

// El importe de una linea de compra se recalculaba en cada guardado con el coste
// que tuviera el material en ese momento, no con el precio con el que la linea
// nacio. Subir un coste reescribia todos los pedidos viejos que llevaran ese
// material, y recibir mercancia guarda la linea, asi que bastaba con que llegara
// el camion. Ventas nunca tuvo ese problema: siempre multiplico por su precio.
$multiplica = (string) (\Drupal::config('eca.eca.process_fpvka81')->get('actions')['Activity_020nm9h']['configuration']['value'] ?? '');
comprobar($resultados, 'el importe de la linea de compra sale de su propio precio',
  $multiplica === '[entity:field_tec_price:value]', $multiplica ?: 'no esta el paso');

$multiplicaVenta = (string) (\Drupal::config('eca.eca.process_lvy385w')->get('actions')['Activity_020nm9h']['configuration']['value'] ?? '');
comprobar($resultados, 'y el de la de venta igual, como siempre',
  $multiplicaVenta === '[entity:field_tec_price:value]', $multiplicaVenta ?: 'no esta el paso');

// Quien crea el pedido desde una ficha de proveedor estampaba en la linea el
// coste por unidad de consumo del material en lugar del de compra. De ahi salian
// precios de 0,02 en lineas de un material que se compra a 80.
$estampa = (string) (\Drupal::config('eca.eca.process_kryibry')->get('actions')['Activity_00es7rw']['configuration']['field_value'] ?? '');
comprobar($resultados, 'la linea nace con el coste de compra del material',
  $estampa === '[inventoryItem:field_tec_cost]', $estampa ?: 'no esta el paso');

// El dibujo tiene que decir lo mismo: si alguien abre el modelador y guarda, el
// ejecutable se vuelve a generar desde el y la trampa se rearma sola.
foreach ([
  'process_fpvka81' => '[entity:field_tec_price:value]',
  'process_kryibry' => '[inventoryItem:field_tec_cost]',
] as $procesoPrecio => $debeDecir) {
  comprobar($resultados, 'y el dibujo de ' . $procesoPrecio . ' tambien lo dice',
    str_contains((string) \Drupal::config('eca.model.' . $procesoPrecio)->get('modeldata'), $debeDecir),
    $debeDecir);
}

// La lista de la compra crea sus lineas por PHP, no por ECA, asi que tiene su
// propio sitio donde equivocarse de campo.
$listaCompra = file_get_contents(DRUPAL_ROOT . '/modules/custom/tec_production/src/Form/PurchaseListForm.php');
comprobar($resultados, 'la lista de la compra estampa ese mismo coste',
  str_contains($listaCompra, "'field_tec_price' => \$this->number(\$material, 'field_tec_cost')"),
  'PurchaseListForm');

// Y ninguna de las dos pantallas de compra puede volver a leer el coste de hoy,
// que es por donde entraba la contradiccion: la fila decia una cosa y el pie,
// que suma los importes guardados, decia otra.
$vistaPrecio = \Drupal\views\Entity\View::load('tec_order_sales_order_line_items');
$leenElMaterial = [];
foreach (['page_1' => 'el borrador', 'block_2' => 'la ficha', 'page_3' => 'el impreso'] as $pantallaPrecio => $comoSeLlamaPrecio) {
  $camposPrecio = $vistaPrecio->getDisplay($pantallaPrecio)['display_options']['fields'] ?? [];
  if (isset($camposPrecio['field_tec_cost']) || str_contains(json_encode($camposPrecio), '@field_tec_cost')) {
    $leenElMaterial[] = $comoSeLlamaPrecio;
  }
}
comprobar($resultados, 'ninguna pantalla de compra lee ya el coste de hoy del material',
  !$leenElMaterial, implode(', ', $leenElMaterial));

// Y tampoco puede leerlo ningun javascript. El borrador tenia dos calculadoras
// encima, la inyectada y otra en admin_form_styles colgada de /po/draft/, las
// dos escribiendo las mismas celdas. Mientras coincidieron no se noto; el dia
// que la columna del coste dejo su sitio al precio de la linea, la segunda
// siguio buscandola, no la encontro, multiplico por cero y dejo un 0,00 encima
// del subtotal bueno. Se borro entera. La regla que queda es: una sola cosa
// hace las cuentas del borrador, y no nombra columnas que no existen.
$javascriptDelSitio = [];
foreach (['modules/custom', 'themes/custom'] as $dondeMirar) {
  if (!is_dir(DRUPAL_ROOT . '/' . $dondeMirar)) {
    continue;
  }
  $paseo = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(DRUPAL_ROOT . '/' . $dondeMirar));
  foreach ($paseo as $fichero) {
    if ($fichero->isFile() && strtolower($fichero->getExtension()) === 'js') {
      $javascriptDelSitio[$dondeMirar . '/' . $fichero->getBasename()] = (string) file_get_contents($fichero->getPathname());
    }
  }
}
foreach (\Drupal::configFactory()->listAll('asset_injector.js.') as $nombreInyector) {
  $javascriptDelSitio[$nombreInyector] = (string) \Drupal::config($nombreInyector)->get('code');
}

$nombranElCoste = array_keys(array_filter($javascriptDelSitio,
  fn(string $codigo): bool => str_contains($codigo, 'views-field-field-tec-cost')));
comprobar($resultados, 'ni ningun javascript, que ya no existe esa columna',
  !$nombranElCoste, implode(', ', $nombranElCoste));

$hacenLasCuentas = array_keys(array_filter($javascriptDelSitio,
  fn(string $codigo): bool => str_contains($codigo, 'views-field-field-tec-line-item-total-number')
    || str_contains($codigo, 'grand-total-value')));
comprobar($resultados, 'y una sola cosa escribe los importes del borrador',
  $hacenLasCuentas === ['asset_injector.js.draft_for_ace_company_view'],
  implode(', ', $hacenLasCuentas) ?: 'ninguna, que tampoco puede ser');

// Lo anterior es como esta montado. Esto es si de verdad cuadra: cada linea
// guardada tiene que valer su precio por su cantidad. Una sola que no cuadre
// significa que algo ha vuelto a escribir importes por su cuenta.
$descuadradas = [];
$almacenLineas = \Drupal::entityTypeManager()->getStorage('tec_line_item');
$idsLineas = $almacenLineas->getQuery()->accessCheck(FALSE)->condition('type', 'tec_po_line_item')->execute();
foreach ($almacenLineas->loadMultiple($idsLineas) as $lineaPrecio) {
  $cantidadPrecio = (float) ($lineaPrecio->get('field_tec_quantity')->value ?? 0);
  $importePrecio = $lineaPrecio->get('field_tec_line_item_total_number')->isEmpty()
    ? NULL : (float) $lineaPrecio->get('field_tec_line_item_total_number')->value;
  if ($importePrecio === NULL || $cantidadPrecio <= 0) {
    continue;
  }
  $suyoPrecio = $lineaPrecio->get('field_tec_price')->isEmpty()
    ? NULL : (float) $lineaPrecio->get('field_tec_price')->value;
  if ($suyoPrecio === NULL || abs($importePrecio - round($suyoPrecio * $cantidadPrecio, 2)) > 0.02) {
    $descuadradas[] = sprintf('linea %s (%s x %s != %s)', $lineaPrecio->id(),
      $suyoPrecio === NULL ? 'sin precio' : number_format($suyoPrecio, 2),
      rtrim(rtrim(number_format($cantidadPrecio, 2, '.', ''), '0'), '.'),
      number_format($importePrecio, 2));
  }
}
comprobar($resultados, 'todas las lineas de compra valen su precio por su cantidad',
  !$descuadradas, $descuadradas ? implode('; ', array_slice($descuadradas, 0, 5)) : count($idsLineas) . ' lineas');

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
